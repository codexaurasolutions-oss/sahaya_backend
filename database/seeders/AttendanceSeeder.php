<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Job;
use App\Models\JobApplication;


class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
        |--------------------------------------------------------------------------
        | Leave Types Seeding
        |--------------------------------------------------------------------------
        */

        $leaveTypesData = [
            ['name' => 'Sick Leave', 'description' => 'Leave due to illness'],
            ['name' => 'Casual Leave', 'description' => 'Short-term personal leave'],
            ['name' => 'Annual Leave', 'description' => 'Yearly paid leave'],
            ['name' => 'Maternity Leave', 'description' => 'Leave before/after childbirth'],
            ['name' => 'Paternity Leave', 'description' => 'Leave after childbirth'],
            ['name' => 'Unpaid Leave', 'description' => 'Leave without salary'],
        ];

        foreach ($leaveTypesData as $type) {
            LeaveType::updateOrCreate(
                ['name' => $type['name']],
                [
                    'description' => $type['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Leave Requests Seeding
        |--------------------------------------------------------------------------
        */

        $statuses = ['pending', 'approved', 'rejected'];

        for ($i = 1; $i <= 30; $i++) {

            $job = Job::inRandomOrder()->first();
            if (!$job) continue;

            $application = JobApplication::where('job_id', $job->id)
                            ->inRandomOrder()
                            ->first();
            if (!$application) continue;

            $leaveType = LeaveType::inRandomOrder()->first();
            if (!$leaveType) continue;

            $startDate = Carbon::now()->subDays(rand(0, 60));
            $endDate   = (clone $startDate)->addDays(rand(1, 5));

            LeaveRequest::create([
                'user_id'       => $application->user_id, // ✅ correct user_id
                'job_id'        => $job->id,
                'leave_type_id' => $leaveType->id,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'reason'        => 'Personal reason for leave request.',
                'status'        => $statuses[array_rand($statuses)],
                'supporting_document' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Attendance Seeding
        |--------------------------------------------------------------------------
        */

        $attendanceStatuses = ['present', 'absent', 'late', 'half_day'];

        for ($i = 1; $i <= 300; $i++) {

            $date = Carbon::now()->subDays(rand(0, 30))->format('Y-m-d');

            $staff = User::where('user_role_id', 2)->inRandomOrder()->first();
            $houseowner = User::where('user_role_id', 3)->inRandomOrder()->first();

            if (!$staff || !$houseowner) {
                continue;
            }

            // Check approved leave during that date
            $leave = LeaveRequest::where('user_id', $staff->id)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->where('status', 'approved')
                ->first();

            $status = $leave ? 'absent' : $attendanceStatuses[array_rand($attendanceStatuses)];
            try {
                Attendance::create([
                    'staff_id'      => $staff->id,
                    'date'          => $date,
                    'status'        => $status,
                    'check_in_time' => $status == 'absent'
                                        ? '00:00:00'
                                        : Carbon::createFromTime(9, rand(0, 30)),
                    'late_minutes'  => $status == 'late' ? rand(5, 30) : 0,
                    'leave_id'      => $leave ? $leave->id : null,
                    'description'   => 'Auto generated attendance record',
                    'processed_by'  => $houseowner->id,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            } catch (\Throwable $th) {
                //throw $th;
                \Log::error('Error creating attendance record: ' . $th->getMessage());
            }
            
        }
    }
}
