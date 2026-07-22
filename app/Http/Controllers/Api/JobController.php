<?php

namespace App\Http\Controllers\Api;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;
use App\Models\QuitJob;
use App\Models\Salary;
use App\Models\User;
use App\Models\SubscriptionUser;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;


class JobController extends Controller
{
private function parseCoordinate($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float) $value;
}

private function getUserCoordinates($user): ?array
{
    if (!$user) {
        return null;
    }

    $addresses = $user->relationLoaded('addresses')
        ? $user->addresses
        : $user->addresses()->get();

    $primaryAddress = collect($addresses)->firstWhere('is_primary', true)
        ?? collect($addresses)->first();

    $latitude = $this->parseCoordinate($primaryAddress?->latitude ?? $user->lat ?? null);
    $longitude = $this->parseCoordinate($primaryAddress?->longitude ?? $user->long ?? null);

    if ($latitude === null || $longitude === null) {
        return null;
    }

    return [
        'lat' => $latitude,
        'long' => $longitude,
    ];
}

private function calculateDistanceKm(float $startLat, float $startLong, float $endLat, float $endLong): float
{
    $earthRadius = 6371;

    $latDelta = deg2rad($endLat - $startLat);
    $longDelta = deg2rad($endLong - $startLong);

    $a = sin($latDelta / 2) ** 2
        + cos(deg2rad($startLat)) * cos(deg2rad($endLat))
        * sin($longDelta / 2) ** 2;

    return 2 * $earthRadius * asin(min(1, sqrt($a)));
}

    private function applyJobProximity($collection, ?array $origin, ?float $radiusKm = null)
    {
        if (!$origin || !isset($origin['lat'], $origin['long'])) {
            return $collection;
        }

        $ranked = collect($collection)->map(function ($job) use ($origin, $radiusKm) {
            $latitude = $this->parseCoordinate($job->latitude ?? null);
            $longitude = $this->parseCoordinate($job->longitude ?? null);

            if ($latitude === null || $longitude === null) {
                if ($radiusKm !== null) {
                    return null;
                }

                $job->setAttribute('_distance_km', null);
                return $job;
            }

            $distance = $this->calculateDistanceKm(
                (float) $origin['lat'],
                (float) $origin['long'],
                $latitude,
                $longitude
            );

            if ($radiusKm !== null && $distance > $radiusKm) {
                return null;
            }

            $job->setAttribute('_distance_km', round($distance, 2));
            return $job;
        })->filter()->sortBy(function ($job) {
        return $job->_distance_km ?? PHP_FLOAT_MAX;
    })->values();

    return $ranked;
}

private function normalizeJobLocationInput(Request $request): void
{
    $request->merge([
        'latitude' => $request->input('latitude', $request->input('lat')),
        'longitude' => $request->input('longitude', $request->input('long')),
    ]);
}

public function index(Request $request): JsonResponse
{
    $user = Auth::guard('api')->user();
    
    $jobs = Job::select('jobs.*')->withCount('applications')
              ->when($user, function ($query) use ($user, $request) {
                  // Add select for is_applied
                  $query->addSelect([
                      'is_applied' => JobApplication::selectRaw('COUNT(*)')
                          ->whereColumn('job_id', 'jobs.id')
                          ->where('user_id', $user->id)
                  ]);

                  // Automatically filter by role and location if user is staff (role 2)
                  // and no specific filters are provided in request
                  if ($user->user_role_id == 2 && !$request->filled('role') && !$request->filled('city')) {
                      // Get staff role and city
                      $workInfo = $user->userWorkInfo;
                      $primaryAddress = $user->addresses()->first();
                      
                      $staffRole = $workInfo ? $workInfo->primary_role : null;
                      $staffCity = $primaryAddress ? $primaryAddress->city : null;

                      if ($staffRole) {
                          $roleString = is_array($staffRole) ? implode(' ', $staffRole) : $staffRole;
                          $query->where(function($q) use ($roleString) {
                              $q->where('title', 'LIKE', '%' . $roleString . '%')
                                ->orWhere('description', 'LIKE', '%' . $roleString . '%');
                          });
                      }

                      if ($staffCity) {
                          $query->where('city', 'LIKE', '%' . $staffCity . '%');
                      }
                  }
              })
              ->when($request->filled('role'), function ($query) use ($request) {
                  $query->where(function($q) use ($request) {
                      $q->where('title', 'LIKE', '%' . $request->role . '%')
                        ->orWhere('description', 'LIKE', '%' . $request->role . '%');
                  });
              })
              ->when($request->filled('city'), function ($query) use ($request) {
                  $query->where('city', 'LIKE', '%' . $request->city . '%');
              })
              ->when($request->filled('state'), function ($query) use ($request) {
                  $query->where('state', 'LIKE', '%' . $request->state . '%');
              })
              ->orderBy('created_at', 'desc')
              ->get();

    $userOrigin = $this->getUserCoordinates($user);
    $jobs = $this->applyJobProximity($jobs, $userOrigin);
    
    if (!$user) {
        $jobs->each(function ($job) {
            $job->is_applied = 0;
        });
    } else {
        $jobs->each(function ($job) {
            $job->is_applied = $job->is_applied > 0 ? 1 : 0;
        });
    }

    return response()->json([
        'status' => 'success',
        'data' => $jobs,
        'message' => 'Jobs retrieved successfully'
    ]);
}

    /**
     * Retrieve a list of jobs that the authenticated user has created.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authBaseList(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 10), 50);

        $query = Job::withCount('applications')
            ->where('created_by', Auth::user()->id);

        // 🔍 Filter by Job Title
        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }

        // 📍 Filter by city
        if ($request->filled('city')) {
            $query->where('city', 'LIKE', '%' . $request->city . '%');
        }

        $jobs = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jobs retrieved successfully',
            'data'    => $jobs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {   
        DB::beginTransaction();
        try {
            $userId = Auth::guard('api')->id();
            $this->normalizeJobLocationInput($request);

            $subscription = SubscriptionUser::where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->where('end_date', '>', now())
                ->latest()
                ->lockForUpdate()
                ->first();
            if (!$subscription) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error_code' => 'UPGRADE_REQUIRED',
                    'message' => 'Please upgrade to Premium Plan to post jobs.'
                ]);
            }

            $plan = Subscription::find($subscription->subscription_id);
            if (!$plan) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error_code' => 'UPGRADE_REQUIRED',
                    'message' => 'Please upgrade to Premium Plan to post jobs.'
                ]);
            }

            $actualPostedJobs = Job::where('created_by', $userId)
                ->when($subscription->start_date, function ($query) use ($subscription) {
                    $query->where('created_at', '>=', $subscription->start_date);
                })
                ->when($subscription->end_date, function ($query) use ($subscription) {
                    $query->where('created_at', '<=', $subscription->end_date);
                })
                ->count();

            $allowed_limit = ($plan->job_limit ?? 3) + ($subscription->extra_jobs ?? 0);
            if ($actualPostedJobs >= $allowed_limit) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error_code' => 'LIMIT_EXCEEDED',
                    'extra_job_price' => $plan->extra_job_price ?? 500,
                    'message' => 'Monthly Job limit exceeded.'
                ]);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'compensation' => 'nullable|numeric',
                'expected_compensation' => 'nullable|numeric',
                'compensation_type' => 'required|in:monthly,hourly,yearly',
                'street_address' => 'required|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'zip_code' => 'required|string',
                'area_locality' => 'required|string|max:255',
                'google_location' => 'required|string',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'commitment_type' => 'required|in:full-time,part-time,flexible',
                'stay_type' => 'nullable|string|max:255',
                'preferred_hours' => 'nullable|string',
                'preferred_days' => 'nullable|string',
                'childcare_experience' => 'boolean',
                'cooking_required' => 'boolean',
                'driving_license_required' => 'boolean',
                'first_aid_certified' => 'boolean',
                'pet_care_required' => 'boolean',
                'additional_requirements' => 'nullable|string',
                'required_skills' => 'nullable|string',
                'status' => 'nullable|in:pending,open,closed'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $job = Job::create(array_merge($validator->validated(), [
                'created_by' => $userId,
                'status' => $request->input('status', 'pending')
            ]));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $job,
                'message' => 'Job created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Job store failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create job. Please try again.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $job = Job::with([
            'creator.addresses',
            'creator.householdInformation',
            'applications.user'
        ])->find($id);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);
        }

        $primaryAddress = optional($job->creator)->addresses
            ?->firstWhere('is_primary', 1) ?? optional($job->creator)->addresses?->first();
        $household = optional($job->creator)->householdInformation;

        $job->setAttribute('owner_summary', [
            'name' => optional($job->creator)->name ?: trim((optional($job->creator)->first_name ?? '') . ' ' . (optional($job->creator)->last_name ?? '')),
            'residence_type' => $household?->residence_type,
            'number_of_rooms' => $household?->number_of_rooms,
            'city' => $primaryAddress?->city,
            'state' => $primaryAddress?->state,
            'locality' => $primaryAddress?->locality,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $job,
            'message' => 'Job retrieved successfully'
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {

        $job = Job::where('id', $id)->where('created_by', Auth::guard('api')->id())->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);

        }

        $this->normalizeJobLocationInput($request);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'compensation' => 'nullable|numeric',
            'expected_compensation' => 'nullable|numeric',
            'compensation_type' => 'sometimes|required|in:monthly,hourly,yearly',
            'street_address' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'zip_code' => 'sometimes|required|string',
            'area_locality' => 'sometimes|required|string|max:255',
            'google_location' => 'sometimes|required|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'commitment_type' => 'sometimes|required|in:full-time,part-time,flexible',
            'stay_type' => 'nullable|string|max:255',
            'preferred_hours' => 'nullable|string',
            'preferred_days' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,open,closed',
            'childcare_experience' => 'boolean',
            'cooking_required' => 'boolean',
            'driving_license_required' => 'boolean',
            'first_aid_certified' => 'boolean',
            'pet_care_required' => 'boolean',
            'additional_requirements' => 'nullable|string',
            'required_skills' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $job->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'data' => $job,
            'message' => 'Job updated successfully'
        ]);
    }

    /**
     * Delete a job
     *
     * @param int $id The ID of the job to delete
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        // Check if the job exists and belongs to the authenticated user
        $job = Job::where('id', $id)->where('created_by', Auth::guard('api')->id())->first();
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);
        }

        // Delete the job
        $job->delete();

        // Return a success response
        return response()->json([
            'status' => 'success',
            'message' => 'Job deleted successfully'
        ]);
    }

    /**
     * Update the status of a job
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        // Validate the request body
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,open,closed,paused'
        ]);

        // If validation fails, return a 422 response with errors
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the job by ID and verify ownership
        $job = Job::where('id', $id)->where('created_by', Auth::guard('api')->id())->first();

        // If the job is not found, return a 404 response
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);
        }

        // Update the job status
        $job->update(['status' => $request->status]);

        // Return a successful response with the updated job
        return response()->json([
            'status' => 'success',
            'data' => $job,
            'message' => 'Job status updated successfully'
        ]);
    }

    public function joblist(Request $request)
    {
        $user = Auth::user();
        $jobs = Job::orderBy('created_at', 'desc')->paginate(10);
        
        if (!$user) {
            $jobs->getCollection()->transform(function ($job) {
                $job->is_applied = 0;
                return $job;
            });
        } else {
            $jobs->getCollection()->transform(function ($job) {
                $job->is_applied = isset($job->is_applied) && $job->is_applied > 0 ? 1 : 0;
                return $job;
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jobs retrieved successfully',
            'data' => $jobs
        ]);
    }
    

}
