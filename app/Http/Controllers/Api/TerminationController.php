<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Termination;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\User;

class TerminationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = Termination::with(['user','approver','reporter']);

        if (request()->filled('is_blacklist')) {
            $query->where('is_blacklist', (bool) request()->boolean('is_blacklist'));
        }

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $terminations = $query
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Termination list',
            'data' => $terminations
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
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string',
            'termination_date' => 'required|date',
            'notice_period_days' => 'nullable|integer|min:0',
            'status' => 'nullable|in:pending,approved,rejected',
            'remarks' => 'nullable|string',
            'is_blacklist' => 'nullable|boolean',
            'police_station_name' => 'nullable|string|max:255',
            'police_station_contact' => 'nullable|string|max:50',
            'police_station_address' => 'nullable|string',
            'fir_photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $payload = $request->only([
            'user_id',
            'reason',
            'termination_date',
            'notice_period_days',
            'status',
            'remarks',
            'police_station_name',
            'police_station_contact',
            'police_station_address',
        ]);

        $payload['is_blacklist'] = $request->boolean('is_blacklist');
        $payload['reported_by'] = Auth::guard('api')->id();

        if ($request->hasFile('fir_photo')) {
            $payload['fir_photo'] = $this->uploadCloudary($request, 'fir_photo', 'staff/blacklist');
        }

        $termination = Termination::create($payload);

        // Keep the household link intact so terminated staff still appear under
        // the inactive tab with their past attendance/payment history.
        User::where('id', $request->user_id)->update([
            'is_active' => 0,
            'status' => 'inactive',
        ]);

        \App\Services\NotificationService::staffTerminated(
            $termination->user_id,
            optional(User::find($payload['reported_by']))->name ?? 'Admin'
        );

        return response()->json([
            'success' => true,
            'message' => 'Termination created successfully',
            'data' => $termination
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $termination = Termination::with(['user','approver','reporter'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Termination details',
            'data' => $termination
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
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string',
            'termination_date' => 'required|date',
            'notice_period_days' => 'nullable|integer|min:0',
            'status' => 'nullable|in:pending,approved,rejected',
            'remarks' => 'nullable|string',
            'is_blacklist' => 'nullable|boolean',
            'police_station_name' => 'nullable|string|max:255',
            'police_station_contact' => 'nullable|string|max:50',
            'police_station_address' => 'nullable|string',
            'fir_photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $termination = Termination::findOrFail($id);
        $payload = $request->only([
            'user_id',
            'reason',
            'termination_date',
            'notice_period_days',
            'status',
            'remarks',
            'police_station_name',
            'police_station_contact',
            'police_station_address',
        ]);
        $payload['is_blacklist'] = $request->boolean('is_blacklist');

        if ($request->hasFile('fir_photo')) {
            $payload['fir_photo'] = $this->uploadCloudary($request, 'fir_photo', 'staff/blacklist');
        }

        $termination->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Termination updated successfully',
            'data' => $termination
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $termination = Termination::findOrFail($id);
        $termination->delete();
        return response()->json([
            'success' => true,
            'message' => 'Termination deleted successfully'
        ]);
    }
}
