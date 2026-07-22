<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Attendance; 
use App\Models\SubscriptionUser; 
use App\Models\KycVerification;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Validator;
use App\Models\Salary;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    private function getPaidSubscriptionEntries($startDate, $endDate)
    {
        return SubscriptionUser::with('subscription')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['paid', 'completed'])
            ->get();
    }

    private function resolveSubscriptionRevenue($subscriptionUser)
    {
        $storedAmount = (float) ($subscriptionUser->amount ?? 0);
        if ($storedAmount > 0) {
            return $storedAmount;
        }

        return (float) ($subscriptionUser->subscription?->price ?? 0);
    }

    private function resolveRoleIds()
    {
        $staffRole = Role::where('slug', 'staff')->first();
        $houseOwnerRole = Role::where('slug', 'householder')->first();

        return [
            'staff_role_id' => $staffRole?->id,
            'house_owner_role_id' => $houseOwnerRole?->id,
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roleIds = $this->resolveRoleIds();
        $staffDataCount = $roleIds['staff_role_id']
            ? User::where('user_role_id', $roleIds['staff_role_id'])->count()
            : 0;
        $houseOwnerDataCount = $roleIds['house_owner_role_id']
            ? User::where('user_role_id', $roleIds['house_owner_role_id'])->count()
            : 0;
        $openJobCount = Job::where('status', 'open')->count();
        $presentAttendanceCount = Attendance::where('date', Carbon::today())->where('status', 'present')->count();
        $absentAttendanceCount = Attendance::where('date', Carbon::today())->where('status', 'absent')->count();
        $openJobCount = Job::where('status', 'open')->count();
        $presentAttendanceCount = Attendance::where('date', Carbon::today())->where('status', 'present')->count();
        $absentAttendanceCount = Attendance::where('date', Carbon::today())->where('status', 'absent')->count();
        
        $leaveCount = Attendance::where('date', Carbon::today())->where('status', 'leave')->count();
        $totalAttendance = $presentAttendanceCount + $absentAttendanceCount + $leaveCount;
        $attendanceRate = $totalAttendance > 0  ? round(($presentAttendanceCount / $totalAttendance) * 100, 2) : 0;
        
        $data = [
            'staff_count' => $staffDataCount,
            'job_count' => $openJobCount,
            'house_owner_count' => $houseOwnerDataCount,
            'present_attendance_count' => $presentAttendanceCount,
            'absent_attendance_count' => $absentAttendanceCount,
            'leave' => $leaveCount,
            'overall_attendance_rate' => $attendanceRate
        ];
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'message' => 'Dashboard data retrieved successfully'
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $type = $request->type;
        
        // Date filter
        if ($type == 'monthly') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
        } else {
            $startDate = Carbon::now()->startOfYear();
            $endDate   = Carbon::now()->endOfYear();
        }
        
        $roleIds = $this->resolveRoleIds();

        $staffDataCount = $roleIds['staff_role_id']
            ? User::where('user_role_id', $roleIds['staff_role_id'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count()
            : 0;
        
        $houseOwnerDataCount = $roleIds['house_owner_role_id']
            ? User::where('user_role_id', $roleIds['house_owner_role_id'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count()
            : 0;

        $openJobCount = Job::where('status', 'open')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $paidSubscriptions = $this->getPaidSubscriptionEntries($startDate, $endDate);
        $memberSubscriptionRevenue = $paidSubscriptions->sum(function ($subscriptionUser) {
            return $this->resolveSubscriptionRevenue($subscriptionUser);
        });
        $memberSalarySum = Salary::whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('net_salary');
        
        $presentAttendanceCount = Attendance::whereBetween('date', [$startDate, $endDate])
            ->where('status', 'present')
            ->count();

        $absentAttendanceCount = Attendance::whereBetween('date', [$startDate, $endDate])
            ->where('status', 'absent')
            ->count();
        
        $leaveCount = Attendance::whereBetween('date', [$startDate, $endDate])
            ->where('status', 'leave')
            ->count();
        $totalAttendance = $presentAttendanceCount + $absentAttendanceCount + $leaveCount;
        $attendanceRate = $totalAttendance > 0  ? round(($presentAttendanceCount / $totalAttendance) * 100, 2) : 0;
        
        $jobStatusOverview = Job::select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->pluck('total', 'status');

        $applicationStatusOverview = JobApplication::select('application_status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('application_status')
            ->pluck('total', 'application_status');

        $topJobPostings = Job::withCount([
                'applications as applications_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('applications_count')
            ->limit(5)
            ->get(['id', 'title', 'city', 'status'])
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'city' => $job->city,
                    'status' => $job->status,
                    'applications_count' => (int) $job->applications_count,
                ];
            })
            ->values();

        $acceptedApplications = (int) ($applicationStatusOverview['accepted'] ?? $applicationStatusOverview['hired'] ?? 0);
        $reviewedApplications = (int) ($applicationStatusOverview['reviewed'] ?? 0);
        $pendingApplications = (int) ($applicationStatusOverview['pending'] ?? 0);
        $rejectedApplications = (int) ($applicationStatusOverview['rejected'] ?? 0);
        $totalApplications = $acceptedApplications + $reviewedApplications + $pendingApplications + $rejectedApplications;

        
        $revenueOverview = [];
        if ($type == 'monthly') {
            $revenues = $paidSubscriptions
                ->groupBy(function ($subscriptionUser) {
                    return Carbon::parse($subscriptionUser->created_at)->format('Y-m-d');
                })
                ->map(function ($subscriptionEntries, $date) {
                    return [
                        'date' => $date,
                        'total' => $subscriptionEntries->sum(function ($subscriptionUser) {
                            return $this->resolveSubscriptionRevenue($subscriptionUser);
                        }),
                    ];
                })
                ->sortBy('date')
                ->values();
            foreach ($revenues as $revenue) {
                $revenueOverview[] = [
                    'label' => Carbon::parse($revenue['date'])->format('d M'),
                    'revenue' => (float) $revenue['total'],
                    'amount' => (float) $revenue['total'],
                ];
            }
        } else {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
            $revenues = $this->getPaidSubscriptionEntries($startDate, $endDate)
                ->groupBy(function ($subscriptionUser) {
                    return Carbon::parse($subscriptionUser->created_at)->format('n');
                })
                ->map(function ($subscriptionEntries, $month) {
                    return [
                        'month' => (int) $month,
                        'total' => $subscriptionEntries->sum(function ($subscriptionUser) {
                            return $this->resolveSubscriptionRevenue($subscriptionUser);
                        }),
                    ];
                })
                ->sortBy('month')
                ->values();
            foreach ($revenues as $revenue) {
                $revenueOverview[] = [
                    'label' => Carbon::create()->month($revenue['month'])->format('M'),
                    'revenue' => (float) $revenue['total'],
                    'amount' => (float) $revenue['total'],
                ];
            }
        }
            
        $data = [
            'staff_count' => $staffDataCount,
            'job_count' => $openJobCount,
            'house_owner_count' => $houseOwnerDataCount,
            'present_attendance_count' => $presentAttendanceCount,
            'absent_attendance_count' => $absentAttendanceCount,
            'leave' => $leaveCount,
            'overall_attendance_rate' => $attendanceRate,
            'member_subscription_revenue' => $memberSubscriptionRevenue,
            'member_salary_paid' => $memberSalarySum,
            'job_status_overview' => [
                'open' => (int) ($jobStatusOverview['open'] ?? 0),
                'pending' => (int) ($jobStatusOverview['pending'] ?? 0),
                'closed' => (int) ($jobStatusOverview['closed'] ?? 0),
                'paused' => (int) ($jobStatusOverview['paused'] ?? 0),
            ],
            'hiring_report' => [
                'total_applications' => $totalApplications,
                'pending' => $pendingApplications,
                'reviewed' => $reviewedApplications,
                'accepted' => $acceptedApplications,
                'rejected' => $rejectedApplications,
                'conversion_rate' => $totalApplications > 0
                    ? round(($acceptedApplications / $totalApplications) * 100, 2)
                    : 0,
            ],
            'top_job_postings' => $topJobPostings,
            'chartdata' =>  [
                'revenue_overview' => $revenueOverview
            ]
        ];
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'message' => 'Report data retrieved successfully'
        ]);
    }
}
