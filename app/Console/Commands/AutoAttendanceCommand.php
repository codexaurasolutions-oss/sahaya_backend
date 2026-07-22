<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;


class AutoAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-mark';
    protected $description = 'Automatically mark attendance for users with is_attendance = 1';

    public function handle()
    {
        // Use IST timezone so date/day matches India time
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $todayDayName = strtolower(Carbon::now('Asia/Kolkata')->format('l')); // e.g. 'monday'

        // 1. Get all employers who have auto_attendence enabled
        $employers = User::whereIn('auto_attendence', [1, '1', true])
            ->where('is_active', 1)
            ->where('is_deleted', 0)
            ->get();

        $markedCount = 0;
        $errors = [];

        foreach ($employers as $employer) {
            try {
                // Get all staff members hired by this employer (via Job Applications)
                $hiredStatuses = ['accepted', 'approved', 'active', 'hired'];
                
                $hiredStaffIds = \App\Models\JobApplication::whereIn('application_status', $hiredStatuses)
                    ->whereHas('job', function($query) use ($employer) {
                        $query->where('created_by', $employer->id);
                    })
                    ->pluck('user_id')
                    ->toArray();

                // Get staff directly added by this employer
                $directlyAddedStaffIds = User::where('user_role_id', 2)
                    ->where('added_by', $employer->id)
                    ->pluck('id')
                    ->toArray();

                $allStaffIds = array_unique(array_merge($hiredStaffIds, $directlyAddedStaffIds));

                if (empty($allStaffIds)) {
                    continue;
                }

                $staffMembers = User::with(['userWorkInfo'])
                    ->whereIn('id', $allStaffIds)
                    ->where('is_active', 1)
                    ->where('is_deleted', 0)
                    ->get();

                foreach ($staffMembers as $user) {
                    // Check if today is a working day for this staff.
                    $rawDays = $user->userWorkInfo?->working_days;

                    if (empty($rawDays)) {
                        $rawDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    }

                    $workingDays3 = array_map(fn($d) => substr(strtolower($d), 0, 3), $rawDays);
                    $today3       = substr($todayDayName, 0, 3); // e.g. "sun","mon"

                    if (!in_array($today3, $workingDays3)) {
                        $this->info("Today ({$todayDayName}) is not a working day for user {$user->id}, skipping.");
                        continue;
                    }

                    // ✅ CRITICAL FIX: Only create attendance if it doesn't exist
                    $existingAttendance = Attendance::where('staff_id', $user->id)
                        ->where('date', $today)
                        ->first();

                    if ($existingAttendance) {
                        $this->info("Attendance already exists for user {$user->id} on {$today} (status: {$existingAttendance->status}), skipping auto-mark.");
                        continue;
                    }

                    // Create new attendance record only if none exists
                    Attendance::create([
                        'staff_id'      => $user->id,
                        'date'          => $today,
                        'check_in_time' => '07:00:00',
                        'status'        => 'present',
                        'description'   => 'Auto-marked by system at 7 AM',
                        'processed_by'  => 1,
                    ]);

                    $markedCount++;
                    $this->info("Auto-marked attendance for user {$user->id} - {$user->name}");
                }

            } catch (\Exception $e) {
                $errors[] = "Failed to mark attendance for employer {$employer->id}: " . $e->getMessage();
                $this->error("Error for employer {$employer->id}: " . $e->getMessage());
                \Log::error("Auto-attendance error for employer {$employer->id}: " . $e->getMessage());
            }
        }

        $this->info("Auto-attendance completed. Marked: {$markedCount}, Errors: " . count($errors));

        if (!empty($errors)) {
            \Log::error('Auto-attendance errors: ', $errors);
        }

        return 0;
    }
}
