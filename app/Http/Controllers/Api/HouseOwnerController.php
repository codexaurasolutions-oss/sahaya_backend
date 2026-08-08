<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SubscriptionUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;
use App\Models\UserAddress;

class HouseOwnerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        // Admin
        if (Auth::user()->user_role_id == 1) {
            $role = Role::where('slug', 'householder')->firstOrFail();
            $query = User::where('user_role_id', $role->id);
            // 🔍 Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                });
            }
            // 🟢 Status filter
            if (!empty($status)) {
                $query->where('status', $status);
            }
            $users = $query->latest()->paginate(10);
        } else {
            $users = User::where('id', Auth::id())->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Householders retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $role = Role::where('slug', 'householder')->first();
        $house = User::with(['addresses', 'petDetails', 'householdInformation', 'kycInformation'])
            ->where('id', $id)
            ->where('user_role_id', $role->id)
            ->first();

        if (empty($house)) {
            return response()->json([
                'success' => false,
                'message' => 'House owner not found'
            ], 404);
        }

        $house->setAttribute('current_subscription', SubscriptionUser::with('subscription')
            ->where('user_id', $house->id)
            ->where('status', 'active')
            ->latest()
            ->first());

        return response()->json([
            'success' => true,
            'data' => $house
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::where('slug', 'householder')->first();
        $house = User::where('id', $id)
            ->where('user_role_id', $role?->id)
            ->first();

        if (!$house) {
            return response()->json([
                'success' => false,
                'message' => 'House owner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|max:100',
            'last_name' => 'nullable|max:100',
            'email' => 'nullable|email|max:150|unique:users,email,' . $id,
            'phone_number' => 'nullable|max:20|unique:users,phone_number,' . $id,
            'dob' => 'nullable|max:50',
            'gender' => 'nullable|max:20',
            'status' => 'nullable|in:active,block,inactive',
            'area_locality' => 'nullable|max:255',
            'google_location' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'current_city' => 'nullable|max:100',
            'current_state' => 'nullable|max:100',
            'current_pincode' => 'nullable|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $firstName = $data['first_name'] ?? $house->first_name;
            $lastName = $data['last_name'] ?? $house->last_name;

            $filtered = collect($data)->filter(function ($value) {
                return $value !== null && $value !== '';
            })->toArray();

            $filtered['name'] = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

            $house->update($filtered);

            $primaryAddress = $house->addresses()->where('is_primary', true)->first()
                ?: $house->addresses()->first();

            $addressData = array_filter([
                'street' => $primaryAddress?->street,
                'city' => $filtered['current_city'] ?? $primaryAddress?->city,
                'state' => $filtered['current_state'] ?? $primaryAddress?->state,
                'pincode' => $filtered['current_pincode'] ?? $primaryAddress?->pincode,
                'area_locality' => $filtered['area_locality'] ?? $primaryAddress?->area_locality,
                'google_location' => $filtered['google_location'] ?? $primaryAddress?->google_location,
                'latitude' => $filtered['lat'] ?? $primaryAddress?->latitude,
                'longitude' => $filtered['long'] ?? $primaryAddress?->longitude,
                'is_primary' => true,
            ], function ($value) {
                return $value !== null && $value !== '';
            });

            if ($primaryAddress) {
                $primaryAddress->update($addressData);
            } elseif (!empty($addressData)) {
                $house->addresses()->create($addressData);
            }

            return response()->json([
                'success' => true,
                'message' => 'House owner updated successfully',
                'data' => $house->fresh(['addresses', 'petDetails', 'householdInformation', 'kycInformation'])
            ]);
        } catch (\Throwable $e) {
            \Log::error('House owner update failed: ' . $e->getMessage(), ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $house = User::where('id', $id)->first();
        if (!$house) {
            return response()->json([
                'success' => false,
                'message' => 'House owner not found'
            ], 404);
        }
        $house->update([
            'is_deleted' => 1,
            'deleted_at' => now(),
        ]);
        return response()->json([
            'success' => true,
            'message' => 'House owner deleted successfully'
        ]);
    }
}
