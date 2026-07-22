<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\JobApplication;
use App\Models\Salary;

class GenerateMonthlySalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly salary for all staff';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // daily present
        $users = User::where('user_role_id', '2')->get();
        foreach($users as $user){
            Attendance::updateOrCreate([
                'staff_id' => $user->id,
                'date' => Carbon::now()->format('Y-m-d'),
            ],[
                'staff_id' => $user->id,
                'date' => Carbon::now()->format('Y-m-d'),
                'status' => 'present',
                'check_in_time' => Carbon::today()->setTime(10, 0, 0),
                'late_minutes' => 0,
                'description' => "auto generated",
                'processed_by' => $user->added_by ?? $user->parent_user_id
            ]);
        }
        $this->info('Generating monthly salary...');

        // Generate monthly salary
        


        // $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
        // $endOfMonth = Carbon::now()->endOfMonth()->format('Y-m-d');

        // $staffMembers = User::where('user_role_id', 2) // staff role
        //                     ->where('status', 'active') // optional
        //                     ->get();
        // foreach ($staffMembers as $staff) {
        //     $attendanceRecords = Attendance::where('staff_id', $staff->id)->whereBetween('date', [$startOfMonth, $endOfMonth])->get();
        //     if ($attendanceRecords->isNotEmpty()) {
                
        //         $jobApplication = JobApplication::where('user_id', $staff->id)->where('application_status', 'accepted')->first();
        //         if ($jobApplication) {
        //             $compensation = $jobApplication->expected_salary / 22; // or get from Job model
        //             $dailyCompensation = $compensation * $attendanceRecords->count(); // Calculate based on attendance
        //         } else {
        //             $dailyCompensation = 0; // Default if no job application found
        //         }
        //         if(isset($staff->added_by)){
        //             Salary::updateOrCreate(
        //                 [
        //                     'staff_id' => $staff->id,
        //                     'payment_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
        //                 ],
        //                 [
        //                     'houseowner_id' => $staff->added_by,
        //                     'amount' => $dailyCompensation,
        //                     'status' => 'pending',
        //                 ]
        //             );
        //         }
        //         // Process attendance records to calculate salary
        //     }
        // }
        return Command::SUCCESS;
    }
}
