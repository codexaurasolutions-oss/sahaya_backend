<?php

namespace App\Http\Controllers\Api;

use App\Models\Job;
use App\Models\QuitJob;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Traits\ImageUpload;
use App\Models\Notification;

class JobApplicationController extends Controller
{
    use ImageUpload;

    // public function approvedJob(Request $request)
    // {

    //     // find user approved job application
    //     $application = JobApplication::where('user_id',Auth::guard('api')->user()->id)
    //         ->where('application_status', 'accepted')
    //         ->with('job.creator')
    //         ->first();

    //     if (!$application) {
    //         return response()->json([
    //             "message" => "No approved job found",
    //             "data" => null
    //         ], 404);
    //     }

    //     $job = $application->job;
    //     $employer = $job->creator;

    //     // accepted date (use created_at or a specific column if exists)
    //     $acceptedDate = $application->updated_at ?? now();

    //     // next pay date (7 days after acceptance)
    //     $nextPayDate = Carbon::parse($acceptedDate)->addDays(7)->format('F d, Y');

    //     // STATIC KEYS LIKE IMAGE
    //     $response = [

    //         "employer" => $employer->name ?? "Unknown Employer",
    //         "role" => $job->title ?? "Job Role",
    //         "joined_date" => Carbon::parse($acceptedDate)->format('F d, Y'),

    //         "salary_summary" => [
    //             "current_monthly_salary" => $job->compensation ?? 0,
    //             "next_pay_date" => $nextPayDate,
    //         ],

    //         "attendance_summary" => [
    //             "present_days" => 20,
    //             "late_arrivals" => 2,
    //             "absent_days" => 0
    //         ],

    //         "leave_balance" => [
    //             "annual" => 15,
    //             "sick" => 7,
    //             "casual" => 3
    //         ],

    //         "job_details" => [
    //             "job_id" => $job->id,
    //             "application_id" => $application->id,
    //             "application_status" => "accepted",
    //             "city" => $job->city ?? "",
    //             "state" => $job->state ?? "",
    //             "street_address" => $job->street_address ?? "",
    //             "commitment_type" => $job->commitment_type ?? "",
    //             "compensation_type" => $job->compensation_type ?? "",
    //         ]
    //     ];

    //     return response()->json([
    //         "message"   => "Approved job fetched successfully",
    //         "data"      => $response
    //     ]);
    // }

    public function approvedJob(Request $request)
    {
        $applications = JobApplication::where('user_id', Auth::guard('api')->user()->id)
            ->where('application_status', 'accepted')
            ->with('job.creator')
            ->get(); // <-- GET MULTIPLE

        if ($applications->isEmpty()) {
            return response()->json([
                "message" => "No approved job found",
                "data" => []
            ], 404);
        }

        $response = [];

        foreach ($applications as $application) {

            $job = $application->job ? $application->job->toArray() : [];
            $employer = $application->job && $application->job->creator
                ? $application->job->creator->toArray()
                : [];

            $acceptedDate = $application->updated_at ?? now();
            $nextPayDate = \Carbon\Carbon::now()->endOfMonth()->format('F d, Y');

            $response[] = [
                "employer" => $employer['name'] ?? "Unknown Employer",
                "role" => $job['title'] ?? "Job Role",
                "joined_date" => \Carbon\Carbon::parse($acceptedDate)->format('F d, Y'),

                "salary_summary" => [
                    "current_monthly_salary" => $job['compensation'] ?? 0,
                    "next_pay_date" => $nextPayDate,
                ],

                "attendance_summary" => [
                    "present_days" => 20,
                    "late_arrivals" => 2,
                    "absent_days" => 0
                ],

                "leave_balance" => [
                    "annual" => 15,
                    "sick" => 7,
                    "casual" => 3
                ],

                "job_details" => [
                    "job_id" => $job['id'] ?? null,
                    "application_id" => $application->id,
                    "application_status" => "accepted",
                    "city" => $job['city'] ?? "",
                    "state" => $job['state'] ?? "",
                    "street_address" => $job['street_address'] ?? "",
                    "commitment_type" => $job['commitment_type'] ?? "",
                    "compensation_type" => $job['compensation_type'] ?? "",
                ]
            ];
        }

        return response()->json([
            "message" => "Approved jobs fetched successfully",
            "data" => $response
        ]);
    }


    public function index(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        
       // if ($user->isAdmin()) {
            $applications = JobApplication::with(['job', 'user'])
                         ->orderBy('created_at', 'desc')
                         ->get();
        // } else {
        //     $applications = $user->jobApplications()
        //                  ->with(['job'])
        //                  ->orderBy('created_at', 'desc')
        //                  ->get();
        // }

        return response()->json([
            'status' => 'success',
            'data' => $applications,
            'message' => 'Applications retrieved successfully'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:jobs,id',
            // 'cover_letter' => 'required|string|min:10|max:5000',
            'expected_salary' => 'nullable|numeric|min:0|max:9999999.99',
            'available_from' => 'required|date|after_or_equal:today',
            'is_advance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get authenticated user
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // -----------------------------------------------
            // CREDIT CHECK: Staff must have enough credits to apply
            // -----------------------------------------------
            $creditsPerApplication = (int) (\App\Models\Setting::where('key', 'credits_per_job_application')->value('value') ?? 5);
            $walletBalance = (float) ($user->wallet_balance ?? 0);

            if ($walletBalance < $creditsPerApplication) {
                return response()->json([
                    'status'  => 'insufficient_credits',
                    'message' => "You need {$creditsPerApplication} credits to apply. Your wallet has " . number_format($walletBalance, 2) . " credits.",
                    'data'    => [
                        'wallet_balance'          => $walletBalance,
                        'credits_per_application' => $creditsPerApplication,
                    ],
                ], 403);
            }

            // Atomic deduction with balance check to prevent race conditions
            $deducted = \DB::table('users')
                ->where('id', $user->id)
                ->where('wallet_balance', '>=', $creditsPerApplication)
                ->decrement('wallet_balance', $creditsPerApplication);

            if (!$deducted) {
                return response()->json([
                    'status'  => 'insufficient_credits',
                    'message' => 'Insufficient credits. Please check your wallet balance.',
                ], 403);
            }
            // -----------------------------------------------

            $jobId = $request->job_id;

            // Check if job exists and is open
            $job = Job::find($jobId);
            if (!$job) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Job not found'
                ], 404);
            }

            if ($job->status !== 'open') {
                // Refund credits
                \DB::table('users')->where('id', $user->id)->increment('wallet_balance', $creditsPerApplication);
                return response()->json([
                    'status' => 'error',
                    'message' => 'This job is not currently accepting applications'
                ], 400);
            }

            // Check for existing application
            $existingApplication = JobApplication::where('job_id', $jobId)
                                            ->where('user_id', $user->id)
                                            ->first();

            if ($existingApplication) {
                // Refund credits
                \DB::table('users')->where('id', $user->id)->increment('wallet_balance', $creditsPerApplication);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already applied for this job'
                ], 400);
            }

            // Create application
            $application = JobApplication::create([
                'job_id' => $jobId,
                'user_id' => $user->id,
                'cover_letter' => $request->cover_letter ?? '',
                'expected_salary' => $request->expected_salary,
                'available_from' => $request->available_from,
                'is_advance' => $request->boolean('is_advance'),
                'application_status' => 'pending',
            ]);
            $applicationCreated = true;
            
            // Get job details
            $job = Job::find($jobId);
            
            // Send notification to house owner (WhatsApp + Push)
            if ($job && $job->created_by) {
                $staffName = $user->first_name ? $user->first_name . ' ' . ($user->last_name ?? '') : ($user->name ?? 'A staff member');
                \App\Services\NotificationService::jobApplied(
                    $job->created_by,
                    $staffName,
                    $job->title,
                    ['job_id' => $job->id, 'application_id' => $application->id]
                );
            }
            
            // Send notification to staff (self, skip WhatsApp/SMS)
            \App\Services\NotificationService::send(
                $user->id,
                'Application Submitted',
                'Your application for ' . ($job ? $job->title : 'the job') . ' has been submitted successfully',
                'job_application',
                ['job_id' => $job?->id, 'application_id' => $application->id, 'skip_whatsapp' => true, 'skip_sms' => true]
            );

            return response()->json([
                'status' => 'success',
                'data' => $application->load('job'),
                'message' => 'Application submitted successfully'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Job application error: ' . $e->getMessage());

            // Refund credits ONLY if application was NOT created
            if (isset($creditsPerApplication) && !isset($applicationCreated)) {
                \DB::table('users')->where('id', $user->id)->increment('wallet_balance', $creditsPerApplication);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit application. Please try again.'
            ], 500);
        }
    }

    public function updateApplicationStatus(Request $request, $id): JsonResponse
    {
       
        $validator = Validator::make($request->all(), [
            'application_status' => 'required|in:pending,reviewed,accepted,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = JobApplication::find($id);

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }

        $application->update(['application_status' => $request->application_status]);
        
        // Get job and user details
        $job = Job::find($application->job_id);
        $staff = User::find($application->user_id);
        
        if ($request->application_status == "accepted") {
            // Do NOT automatically add as staff here. The owner must go through
            // the NewStaffFrom screen and verify via Aadhar OTP to add them.
            
            // Send notification to staff (in-app + FCM push)
            if ($staff) {
                \App\Services\NotificationService::send(
                    $staff->id,
                    'Application Accepted',
                    'Congratulations! Your application for ' . ($job ? $job->title : 'the job') . ' has been accepted',
                    'job_application_accepted'
                );
            }
        }

        if ($request->application_status == "rejected") {
            if ($staff) {
                // Mark staff as no longer added and clear owner reference
                $staff->update([
                    'is_staff_added' => 0,
                    'added_by' => null,
                    'auto_attendence' => 0,
                ]);
                
                // Deactivate Hire Me if active
                $staff->update(['is_hire_me' => 0, 'available_from' => null, 'available_to' => null]);
                
                // Log the rejection for audit trail
                \Log::info('Staff rejected by owner', [
                    'staff_id' => $staff->id,
                    'owner_id' => $application->job ? $application->job->user_id : null,
                    'job_id' => $application->job_id,
                    'was_active' => $staff->is_staff_added,
                ]);
            }
            
            // Send notification to staff (in-app + FCM push)
            if ($staff) {
                \App\Services\NotificationService::send(
                    $staff->id,
                    'Application Rejected',
                    'Your application for ' . ($job ? $job->title : 'the job') . ' has been rejected',
                    'job_application_rejected'
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $application,
            'message' => 'Application status updated successfully'
        ]);
    }

    public function getJobApplications($jobId): JsonResponse
    {
        try {
            \Log::info('getJobApplications called for Job ID: ' . $jobId);

            $applications = JobApplication::with([
                                'user',
                                'user.userWorkInfo',
                                'user.addresses'
                             ])
                             ->where('job_id', $jobId)
                             ->orderBy('created_at', 'desc')
                             ->get();

            \Log::info('Applications count: ' . $applications->count());

            // Safely load reviews separately to avoid polymorphic type errors
            foreach ($applications as $app) {
                \Log::info('App ID: ' . $app->id . ', User ID: ' . $app->user_id);
                if ($app->user) {
                    try {
                        $app->user->setRelation(
                            'reviews_received',
                            \App\Models\Review::where('received_by_id', $app->user->id)
                                ->where('received_by_type', 'user')
                                ->get()
                        );
                    } catch (\Exception $reviewEx) {
                        \Log::warning('Could not load reviews for user ' . $app->user->id . ': ' . $reviewEx->getMessage());
                        $app->user->setRelation('reviews_received', collect([]));
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $applications,
                'message' => 'Job applications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getJobApplications error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => 'Failed to retrieve applications: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }
      
        $application->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Application deleted successfully'
        ]);
    }

    public function requestQuitJob(Request $request)
    {
        $request->validate([
            "job_id" => "required|exists:jobs,id",
            "end_date" => "required|date",
            "reason" => "required|string"
        ]);
        $userId =  Auth::guard('api')->user()->id;
        $quit = QuitJob::create([
            "job_id" => $request->job_id,
            "user_id" => $userId,
            "end_date" => $request->end_date,
            "reason" => $request->reason,
            "status" => "pending"
        ]);
        
        // Get job and house owner details
        $job = Job::find($request->job_id);
        $staff = Auth::guard('api')->user();
        
        // Send notification to house owner
        if ($job && $job->created_by) {
            \App\Services\NotificationService::send(
                $job->created_by,
                'Job Quit Request',
                $staff->name . ' has requested to quit the job: ' . $job->title,
                'job_quit',
                ['job_id' => $job->id]
            );
        }
        
        // Send notification to staff (self, skip WhatsApp/SMS)
        \App\Services\NotificationService::send(
            $userId,
            'Quit Request Submitted',
            'Your quit request for ' . ($job ? $job->title : 'the job') . ' has been submitted',
            'job_quit',
            ['job_id' => $job?->id, 'skip_whatsapp' => true, 'skip_sms' => true]
        );
        
        return response()->json([
            "message" => "Quit request submitted successfully",
            "data" => $quit
        ], 201);
    }


    public function applyLeave(Request $request)
    {
        $request->validate([
            "houseowner_id" => "required|exists:users,id",
            "leave_type_id" => "required|exists:leave_types,id",
            "start_date" => "required|date",
            "end_date" => "required|date",
            "reason" => "required|string",
            "supporting_document" => "nullable|file|mimes:jpg,jpeg,png,pdf|max:2048"
        ]);

        $user = Auth::guard('api')->user();

        $filePath = null;

        if ($request->hasFile('supporting_document')) {
                $directory = "uploads/leave_documents";
                // if (!file_exists(public_path($directory))) mkdir(public_path($directory), 0755, true);
                // $image = $request->file('supporting_document');
                // $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                // $image->move(public_path($directory), $fileName);
                // $path = $directory . '/' . $fileName;
                // if ($user->image && file_exists(public_path($user->image))) unlink(public_path($user->image));
                $path = $this->uploadCloudary($request,"supporting_document",$directory);
                $filePath = $path;
        }
        $leave = LeaveRequest::create([
            "user_id" => $user->id,
            'houseowner_id' => $request->houseowner_id,
            "leave_type_id" => $request->leave_type_id,
            "start_date" => $request->start_date,
            "end_date" => $request->end_date,
            "reason" => $request->reason,
            "status" => "pending",
            "supporting_document" => $filePath,
            "created_by" => $user->id
        ]);

        // Notify staff (self, skip WhatsApp/SMS)
        \App\Services\NotificationService::send(
            $user->id,
            'Leave Applied',
            'Your leave request has been submitted successfully.',
            'leave_application',
            ['skip_whatsapp' => true, 'skip_sms' => true]
        );

        // Notify house owner (WhatsApp + Push)
        if ($request->houseowner_id) {
            $staffName = $user->first_name ? $user->first_name . ' ' . ($user->last_name ?? '') : ($user->name ?? 'A staff member');
            $dates = $request->start_date . ' to ' . $request->end_date;
            \App\Services\NotificationService::leaveApplied(
                $request->houseowner_id,
                $staffName,
                $dates,
                ['application_id' => $leave->id]
            );
        }

        return response()->json([
            "status" => true,
            "message" => "Leave request submitted successfully",
            "data" => $leave
        ], 201);
    }



    public function leaveList(Request $request)
    { 
        // 1. Logged in API user ID
        $user = Auth::guard('api')->user();
        // 2. Get all job IDs created by this user
        if($user->user_role_id == 2){
            $leaveRequests = LeaveRequest::with(['user', 'leaveType'])
            ->where('created_by', $user->id)
            ->orderBy('id', 'desc')
            ->get();
        } elseif($user->user_role_id == 1){
            $leaveRequests = LeaveRequest::with(['user', 'leaveType'])->orderBy('id', 'desc')
            ->get();
        } else {
            $leaveRequests = LeaveRequest::with(['user', 'leaveType'])
            ->where('houseowner_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();
        }
        return response()->json([
            'status' => true,
            'message' => 'Leave requests fetched successfully',
            'data' => $leaveRequests
        ], 200);
    }
    /**
     * Approve Leave Request
     */
    public function approve($id)
    {
        $user = Auth::guard('api')->user();
        $leave = LeaveRequest::find($id);
        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave request not found'
            ], 404);
        }

        $canManageLeave = (int) $user->user_role_id === 1
            || ((int) $user->user_role_id === 3 && (int) $leave->houseowner_id === (int) $user->id);
        if (!$canManageLeave) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to manage this leave request.'
            ], 403);
        }

        if (strtolower((string) $leave->status) !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'This leave request has already been processed.'
            ], 422);
        }

        $leave->status = 'approved';
        $leave->save();

        $owner = User::find($leave->houseowner_id);
        \App\Services\NotificationService::leaveApproved(
            $leave->user_id,
            $owner ? $owner->name : 'Admin'
        );

        return response()->json([
            'status' => true,
            'message' => 'Leave request approved successfully',
            'data' => $leave
        ], 200);
    }

    /**
     * Reject Leave Request
     */
    public function reject($id)
    {
        $user = Auth::guard('api')->user();
        $leave = LeaveRequest::find($id);
        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave request not found'
            ], 404);
        }

        $canManageLeave = (int) $user->user_role_id === 1
            || ((int) $user->user_role_id === 3 && (int) $leave->houseowner_id === (int) $user->id);
        if (!$canManageLeave) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to manage this leave request.'
            ], 403);
        }

        if (strtolower((string) $leave->status) !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'This leave request has already been processed.'
            ], 422);
        }

        $leave->status = 'rejected';
        $leave->save();

        $owner = User::find($leave->houseowner_id);
        \App\Services\NotificationService::leaveRejected(
            $leave->user_id,
            $owner ? $owner->name : 'Admin'
        );

        return response()->json([
            'status' => true,
            'message' => 'Leave request rejected successfully',
            'data' => $leave
        ], 200);
    }



    public function leaveTypeList()
    {
        $types = LeaveType::all();

        return response()->json([
            "status" => true,
            "message" => "Leave type list fetched successfully",
            "data" => $types
        ], 200);
    }



}
