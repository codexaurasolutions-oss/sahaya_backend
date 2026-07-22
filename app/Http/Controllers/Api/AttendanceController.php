<?php
// app/Http/Controllers/Api/AttendanceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attendance = Attendance::query();

        if ($request->has('start_date') && $request->has('end_date')) {
            $attendance->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('staff_id')) {
            $attendance->where('staff_id', $request->staff_id);
        }

        if ($request->has('status')) {
            $attendance->where('status', $request->status);
        }

        $data = $attendance->orderBy('date', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,holiday',
            'check_in_time' => 'nullable',
            'late_minutes' => 'nullable|integer|min:1',
            'leave_id' => 'nullable',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $staffId = $request->input('staff_id');
            $date = $request->input('date');
            $status = $request->input('status') ?? 'present';

            $attendanceData = [
                'status' => $status,
                'description' => $request->input('description') ?? 'Manual update',
                'processed_by' => Auth::id() ?? 1,
                'check_in_time' => '09:00:00',
                'late_minutes' => (int)($request->input('late_minutes') ?? 0),
                'leave_id' => ($status == 'absent') ? $request->input('leave_id') : null,
                'updated_at' => now(),
            ];

            // 🚀 BYPASS ELOQUENT - Use Direct DB Query to ensure data goes through
            $exists = DB::table('attendance')
                        ->where('staff_id', $staffId)
                        ->where('date', $date)
                        ->exists();

            if ($exists) {
                DB::table('attendance')
                    ->where('staff_id', $staffId)
                    ->where('date', $date)
                    ->update($attendanceData);
                $message = 'Attendance updated successfully';
            } else {
                $attendanceData['staff_id'] = $staffId;
                $attendanceData['date'] = $date;
                $attendanceData['created_at'] = now();
                DB::table('attendance')->insert($attendanceData);
                $message = 'Attendance marked successfully';
            }

            DB::commit();

            // Notify staff (in-app + FCM push only, no WhatsApp/SMS)
            \App\Services\NotificationService::send(
                $staffId,
                'Attendance Update',
                'Your attendance for ' . $date . ' has been marked as ' . ucfirst($status),
                'attendance',
                ['skip_whatsapp' => true, 'skip_sms' => true]
            );

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $attendanceData
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'DB Direct Error: ' . $e->getMessage(),
                'payload' => $request->all()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'Attendance not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $attendance
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,holiday',
            'check_in_time' => 'nullable',
            'late_minutes' => 'nullable|integer|min:1',
            'leave_id' => 'nullable',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $attendance = Attendance::find($id);

            if (!$attendance) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance not found'
                ], 404);
            }

            $existing = Attendance::where('staff_id', $request->staff_id)
                ->where('date', $request->date)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance already exists for this staff on the selected date'
                ], 400);
            }

            $attendanceData = [
                'staff_id' => $request->staff_id,
                'date' => $request->date,
                'status' => $request->status,
                'description' => $request->description,
                'processed_by' => Auth::guard('api')->user()->id
            ];

            if ($request->status == 'present') {
                $attendanceData['check_in_time'] = $request->check_in_time ?? '09:00:00';
            } else {
                $attendanceData['check_in_time'] = null;
            }

            if ($request->status == 'late') {
                $attendanceData['late_minutes'] = $request->late_minutes;
            }

            if ($request->status == 'absent') {
                $attendanceData['leave_id'] = $request->leave_id;
            } else {
                $attendanceData['leave_id'] = null;
            }

            $attendance->update($attendanceData);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Attendance updated successfully',
                'data' => $attendance
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update attendance'
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $attendance = Attendance::find($id);

            if (!$attendance) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance not found'
                ], 404);
            }

            $attendance->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Attendance deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete attendance'
            ], 500);
        }
    }
}