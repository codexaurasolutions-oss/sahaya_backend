<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Models\Transaction;
use Carbon\Carbon;
use App\Models\LeaveRequest;
use App\Models\Attendance;
use App\Models\UserWorkInfo;
use App\Models\SubscriptionUser;
use Illuminate\Support\Facades\DB;
use App\Models\Salary;
use App\Models\SalaryPayout;
use App\Models\BankAccount;
use App\Models\Job;
use App\Models\Notification;
use App\Models\KycVerification;
use App\Services\Admin\RazorpayXService;
use Illuminate\Support\Str;

class SalaryController extends Controller
{
    private function getEffectiveSubscriptionAmount($subscriptionUser): float
    {
        $storedAmount = (float) ($subscriptionUser->amount ?? 0);
        if ($storedAmount > 0) {
            return $storedAmount;
        }

        $paymentMode = strtolower((string) ($subscriptionUser->payment_mode ?? ''));
        $paymentStatus = strtolower((string) ($subscriptionUser->payment_status ?? ''));
        $isPaidSubscription = in_array($paymentMode, ['razorpay']) || in_array($paymentStatus, ['paid', 'completed']);

        return $isPaidSubscription
            ? (float) ($subscriptionUser->subscription->price ?? 0)
            : 0.0;
    }

    /**
     * Get staff salary information
     */
    // public function getStaffSalary($user_id): JsonResponse
    // {
    //     try {
    //         $user = User::where('id', $user_id)
    //             ->where('user_role_id', 2)
    //             ->first();

    //         if (!$user) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Staff member not found'
    //             ], 404);
    //         }
    //         $acceptedApplication = JobApplication::where('user_id', $user_id)
    //             ->where('application_status', 'accepted')
    //             ->first();

    //         if (!$acceptedApplication) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'User does not have any accepted job applications'
    //             ], 400);
    //         }
    //         $job = $acceptedApplication->job;
    //          $lastMonth = Carbon::now()->subMonth()->format('F Y');
    //     $lastMonthPayment = Payment::where('staff_id', $user_id)
    //         ->where('salary_period', 'like', '%' . $lastMonth . '%')
    //         ->orderBy('created_at', 'desc')
    //         ->first();
    //         $salaryData = [
    //             'staff_member' => $user,
    //             'salary_details' => [
    //                 'base_salary' => [
    //                     'monthly_salary' => $job->compensation ?? 2500.00,
    //                     'period' => date('F-Y'),
    //                 ],
    //                 'adjustments' => [
    //                     'performance_bonus' => 0.00,
    //                     'overtime_pay' => 0.00,
    //                     'tax_deduction' => 0.00,
    //                     'advance_payment' => 0.00
    //                 ],
    //                   'last_month_salary' => $lastMonthPayment ? [
    //             'payment_id' => $lastMonthPayment->payment_id,
    //             'base_salary' => (float) $lastMonthPayment->base_salary,
    //             'performance_bonus' => (float) $lastMonthPayment->performance_bonus,
    //             'overtime_pay' => (float) $lastMonthPayment->overtime_pay,
    //             'tax_deduction' => (float) $lastMonthPayment->tax_deduction,
    //             'advance_payment' => (float) $lastMonthPayment->advance_payment,
    //             'net_salary' => (float) $lastMonthPayment->net_salary,
    //             'payment_method' => $lastMonthPayment->payment_mode,
    //             'salary_period' => $lastMonthPayment->salary_period,
    //             'payment_status' => $lastMonthPayment->status,
    //             'paid_date' => $lastMonthPayment->updated_at->format('Y-m-d H:i:s')
    //         ] : null,

            
    //                 'net_salary' => $job->compensation ?? 0.00,
    //                 'payment_method' => 'Cash'
    //             ]
    //         ];
    //         $baseSalary = $salaryData['salary_details']['base_salary']['monthly_salary'];
    //         $adjustments = $salaryData['salary_details']['adjustments'];
    //         $netSalary = $baseSalary + $adjustments['performance_bonus'] + $adjustments['overtime_pay'] + 
    //                     $adjustments['tax_deduction'] + $adjustments['advance_payment'];
            
    //         $salaryData['salary_details']['net_salary'] = $netSalary;
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Staff salary data retrieved successfully',
    //             'data' => $salaryData
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to retrieve salary data: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function getStaffSalary($user_id): JsonResponse
{
    try {
        $user = User::where('id', $user_id)
            ->where('user_role_id', 2)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        // Prioritize salary from UserWorkInfo (set by house owner)
        $userWorkInfo = UserWorkInfo::where('user_id', $user_id)->first();
        
        if ($userWorkInfo && $userWorkInfo->salary) {
            // Use salary from UserWorkInfo as primary source of truth
            $baseSalary = (float) $userWorkInfo->salary;
            $jobCompensation = $baseSalary;
            $salarySource = 'staff_record';
        } else {
            // Fallback to accepted job application
            $acceptedApplication = JobApplication::where('user_id', $user_id)
                ->where('application_status', 'accepted')
                ->first();

            if ($acceptedApplication) {
                $job = $acceptedApplication->job;
                $jobCompensation = $job->compensation ?? 0.00;
                $baseSalary = (float) $jobCompensation;
                $salarySource = 'job_application';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Salary information not found. Please set salary in staff profile.'
                ], 400);
            }
        }

        // Get last month payment
        $lastMonth = Carbon::now()->subMonth()->format('F Y');
        $lastMonthPayment = Payment::where('staff_id', $user_id)
            ->where('salary_period', 'like', '%' . $lastMonth . '%')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get pay frequency from UserWorkInfo if available
        $payFrequency = 'Monthly'; // Default
        if (isset($userWorkInfo) && $userWorkInfo->pay_frequency) {
            $payFrequency = $userWorkInfo->pay_frequency;
        } elseif ($acceptedApplication && $acceptedApplication->job) {
            // You might want to add pay_frequency to job or job_application if needed
            $payFrequency = $acceptedApplication->job->pay_frequency ?? 'Monthly';
        }

        $salaryData = [
            'staff_member' => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name) ?: ($user->name ?: 'Staff Member'),
                'email' => $user->email,
                'phone' => $user->phone_number,
                'image' => $user->image,
                'upi_id' => $user->upi_id,
            ],
            'salary_details' => [
                'base_salary' => [
                    'monthly_salary' => $baseSalary,
                    'period' => date('F Y'),
                    'pay_frequency' => $payFrequency,
                    'source' => $salarySource ?? 'unknown'
                ],
                'adjustments' => [
                    'performance_bonus' => 0.00,
                    'overtime_pay' => 0.00,
                    'tax_deduction' => 0.00,
                    'advance_payment' => (float) \DB::table('staff_advances')
                        ->where('staff_id', $user_id)
                        ->where('employer_id', Auth::guard('api')->id())
                        ->where('status', 'active')
                        ->get()
                        ->reduce(function ($carry, $advance) {
                            if ($advance->deduction_type === 'full') {
                                return $carry + (float) $advance->remaining_balance;
                            } elseif ($advance->deduction_type === 'installment') {
                                return $carry + min((float) $advance->installment_amount, (float) $advance->remaining_balance);
                            }
                            return $carry;
                        }, 0)
                ],
                'last_month_salary' => $lastMonthPayment ? [
                    'payment_id' => $lastMonthPayment->payment_id,
                    'base_salary' => (float) $lastMonthPayment->base_salary,
                    'performance_bonus' => (float) $lastMonthPayment->performance_bonus,
                    'overtime_pay' => (float) $lastMonthPayment->overtime_pay,
                    'tax_deduction' => (float) $lastMonthPayment->tax_deduction,
                    'advance_payment' => (float) $lastMonthPayment->advance_payment,
                    'net_salary' => (float) $lastMonthPayment->net_salary,
                    'payment_method' => $lastMonthPayment->payment_mode,
                    'salary_period' => $lastMonthPayment->salary_period,
                    'payment_status' => $lastMonthPayment->status,
                    'paid_date' => $lastMonthPayment->updated_at->format('Y-m-d H:i:s')
                ] : null,
                'net_salary' => $baseSalary,
                'payment_method' => 'Cash'
            ]
        ];

        // Calculate net salary including adjustments
        // ✅ CRITICAL FIX: Tax and advance should be SUBTRACTED, not added!
        $adjustments = $salaryData['salary_details']['adjustments'];
        $netSalary = max(
            0,
            $baseSalary
            + $adjustments['performance_bonus']
            + $adjustments['overtime_pay']
            - $adjustments['tax_deduction']
            - $adjustments['advance_payment']
        );
        
        $salaryData['salary_details']['net_salary'] = $netSalary;

        return response()->json([
            'status' => true,
            'message' => 'Staff salary data retrieved successfully',
            'data' => $salaryData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve salary data: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Update staff salary information
     */
   public function updateStaffSalary(Request $request, $user_id): JsonResponse
{
    try {
        $user = User::where('id', $user_id)
            ->where('user_role_id', 2)
            ->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        // Double-click protection (Idempotency)
        $currentPeriod = date('F Y');
        if (Payment::where('staff_id', $user_id)
            ->where('user_id', Auth::guard('api')->id())
            ->where('salary_period', $currentPeriod)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Processing payment, please wait...'
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'base_salary' => 'nullable|numeric|min:0',
            'basic_salary' => 'nullable|numeric|min:0',
            'performance_bonus' => 'nullable|numeric|min:0',
            'performative_allowance' => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
            'over_time_allowance' => 'nullable|numeric|min:0',
            'advance_payment' => 'nullable|numeric',
            'payment_method' => 'nullable|in:Cash,UPI,Bank Transfer',
            'payment_mode' => 'nullable|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        $baseSalary = $request->base_salary ?? $request->basic_salary ?? 0;
        $performanceBonus = $request->performance_bonus ?? $request->performative_allowance ?? 0;
        $overtimePay = $request->overtime_pay ?? $request->over_time_allowance ?? 0;
        $taxDeduction = abs($request->tax_deduction ?? $request->tax ?? 0);
        $advancePayment = abs($request->advance_payment ?? 0);
        $paymentMode = $request->payment_method ?? $request->payment_mode ?? 'Cash';

        // Correct formula: Base + Bonus + Overtime - Tax - Advance
        $netSalary = max(0, $baseSalary + $performanceBonus + $overtimePay - $taxDeduction - $advancePayment);

        $paymentId = 'PAY_' . strtoupper(uniqid());
        $orderId = 'SAL_' . strtoupper(uniqid());
        $transactionId = 'TXN_' . strtoupper(uniqid());

        // Determine status: default to 'paid' for cash, 'pending' for others unless specified
        $status = $request->status;
        if (!$status) {
            $status = (strtolower($paymentMode) === 'cash') ? 'paid' : 'pending';
        }

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => Auth::guard('api')->user()->id,
                'staff_id' => $user_id,
                'amount' => $netSalary,
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'status' => $status,
                'payment_mode' => $paymentMode,
                'base_salary' => $baseSalary,
                'performance_bonus' => $performanceBonus,
                'overtime_pay' => $overtimePay,
                'tax_deduction' => $taxDeduction,
                'advance_payment' => $advancePayment,
                'net_salary' => $netSalary,
                'salary_period' => $currentPeriod,
            ]);

            // Handle Advance Deduction Logic
            if ($advancePayment > 0 && ($status === 'paid' || $status === 'completed')) {
                $remainingToDeduct = $advancePayment;
                $activeAdvances = DB::table('staff_advances')
                    ->where('staff_id', $user_id)
                    ->where('employer_id', Auth::guard('api')->id())
                    ->where('status', 'active')
                    ->orderBy('given_date', 'asc')
                    ->get();

                foreach ($activeAdvances as $advance) {
                    if ($remainingToDeduct <= 0) break;

                    // For installment advances, only deduct the per-month installment_amount
                    if ($advance->deduction_type === 'installment' && $advance->installment_amount > 0) {
                        $deductFromThis = min($remainingToDeduct, (float) $advance->installment_amount, (float) $advance->remaining_balance);
                    } else {
                        $deductFromThis = min($remainingToDeduct, $advance->remaining_balance);
                    }
                    $newBalance = $advance->remaining_balance - $deductFromThis;

                    DB::table('staff_advances')->where('id', $advance->id)->update([
                        'remaining_balance' => $newBalance,
                        'status' => $newBalance <= 0 ? 'closed' : 'active'
                    ]);

                    // Record transaction
                    DB::table('advance_transactions')->insert([
                        'advance_id' => $advance->id,
                        'staff_id' => $user_id,
                        'employer_id' => Auth::guard('api')->id(),
                        'deducted_amount' => $deductFromThis,
                        'balance_after' => $newBalance,
                        'salary_id' => $payment->id,
                        'note' => 'Auto-deducted from salary for ' . date('F Y'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    $remainingToDeduct -= $deductFromThis;
                }
                
                // Update user aggregate field
                if (isset($user)) {
                    $user->advance_withdraw_amount = max(0, $user->advance_withdraw_amount - $advancePayment);
                    $user->advance_withdraw_added_by = Auth::guard('api')->id();
                    $user->save();
                }
            }
            DB::commit();

            // 🚀 Notify staff member about salary payment only if successful
            if ($status === 'paid' || $status === 'completed') {
                try {
                    \App\Services\NotificationService::salaryPaid(
                        $user_id,
                        number_format($netSalary, 2),
                        Auth::guard('api')->user()->name
                    );
                } catch (\Exception $e) {
                    \Log::error('Salary notification failed: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        $transaction = Transaction::create([
            'user_id' => $user_id,
            'transaction_id' => $transactionId,
            'type' => 'salary',
            'order_id' => $orderId,
            'order_number' => $orderId,
            'reference_id' => $paymentId,
            'amount' => $netSalary,
            'currency' => 'INR',
            'payment_mode' => $paymentMode,
            'payment_status' => $status,
            'created_by' => Auth::guard('api')->user()->id,
            'payment_response' => json_encode([
                'base_salary' => $baseSalary,
                'performance_bonus' => $performanceBonus,
                'overtime_pay' => $overtimePay,
                'tax_deduction' => $taxDeduction,
                'advance_payment' => $advancePayment,
                'net_salary' => $netSalary,
                'period' => date('F Y')
            ]),
            'for_entry' => 'salary_payment'
        ]);

        // ✅ Also Create record in 'salaries' table for unified history/admin visibility
        \App\Models\Salary::create([
            'staff_id' => $user_id,
            'houseowner_id' => Auth::guard('api')->user()->id,
            'basic_salary' => $baseSalary,
            'performative_allowance' => $performanceBonus,
            'over_time_allowance' => $overtimePay,
            'tax' => $taxDeduction,
            'advance_payment' => $advancePayment,
            'net_salary' => $netSalary,
            'payment_mode' => $paymentMode,
            'status' => $status,
            'payment_date' => now()->toDateString(),
        ]);

        $salaryData = [
            'staff_member' => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name) ?: ($user->name ?: 'Staff Member'),
                'email' => $user->email,
                'phone' => $user->phone_number,
                'image' => $user->image,
            ],
            'salary_details' => [
                'base_salary' => [
                    'monthly_salary' => (float) $baseSalary,
                    'period' => date('F Y')
                ],
                'adjustments' => [
                    'performance_bonus' => (float) $performanceBonus,
                    'overtime_pay' => (float) $overtimePay,
                    'tax_deduction' => (float) $taxDeduction,
                    'advance_payment' => (float) $advancePayment
                ],
                'net_salary' => (float) $netSalary,
                'payment_method' => $request->payment_method
            ],
            'payment_info' => [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'status' => $request->status ?? 'paid'
            ],
            // Backward compatibility for Salary.js
            'basic_salary' => (float) $baseSalary,
            'performative_allowance' => (float) $performanceBonus,
            'over_time_allowance' => (float) $overtimePay,
            'tax' => (float) $taxDeduction,
            'advance_payment' => (float) $advancePayment,
            'net_salary' => (float) $netSalary,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Salary updated and payment processed successfully',
            'data' => $salaryData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to update salary: ' . $e->getMessage()
        ], 500);
    }
}

public function getEarningsSummary(Request $request, $job_id = null)
{
    try {
        $input = $request->all();

        // Populate job_id from route parameter if not in query parameters
        if ($job_id !== null && !isset($input['job_id'])) {
            $input['job_id'] = $job_id;
        }

        // Sanitize job_id to handle React Native state uninitialized values (like 'null', 'undefined', empty, or 0)
        if (isset($input['job_id']) && ($input['job_id'] === 'null' || $input['job_id'] === 'undefined' || $input['job_id'] === '' || $input['job_id'] == 0)) {
            unset($input['job_id']);
        }

        // Sanitize month to handle empty/uninitialized strings
        if (isset($input['month']) && ($input['month'] === 'null' || $input['month'] === 'undefined' || $input['month'] === '')) {
            unset($input['month']);
        }

        $validator = Validator::make($input, [
            'job_id' => 'sometimes|exists:jobs,id',
            'month' => 'sometimes|date_format:Y-m'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $jobId = $input['job_id'] ?? null;
        $month = $input['month'] ?? date('Y-m');
        $monthName = date('F Y', strtotime($month));
        // Get approved job applications
        $applications = JobApplication::where('user_id', $user->id)
            ->where('application_status', 'accepted')
            ->with(['job.creator', 'user.addedByUser', 'user.userWorkInfo'])
            ->when($jobId, function($query) use ($jobId) {
                return $query->where('job_id', $jobId);
            })
            ->get();

        if ($applications->isEmpty()) {
            // Reload user with relations if needed
            $user->load(['addedByUser', 'userWorkInfo']);
            
            // Check if user was directly added by someone
            if ($user->addedByUser || $user->added_by) {
                $employer = $user->addedByUser ?: User::find($user->added_by);
                
                // Create a "virtual" application object for consistent processing
                $virtualApplication = new \stdClass();
                $virtualApplication->id = 0;
                $virtualApplication->updated_at = $user->created_at;
                
                // Create a virtual job object
                $virtualJob = new \stdClass();
                $virtualJob->id = null;
                $virtualJob->title = ($user->userWorkInfo && $user->userWorkInfo->primary_role) ? $user->userWorkInfo->primary_role : "Staff Member";
                $virtualJob->compensation = ($user->userWorkInfo && $user->userWorkInfo->salary) ? (float) $user->userWorkInfo->salary : 0;
                $virtualJob->city = $employer->location ?? "";
                $virtualJob->state = "";
                $virtualJob->street_address = $employer->location ?? "";
                $virtualJob->commitment_type = "";
                $virtualJob->compensation_type = "";
                $virtualJob->creator = $employer;
                
                // Create a virtual employer object for consistent processing
                $virtualEmployer = new \stdClass();
                $virtualEmployer->id = $employer->id ?? 0;
                $virtualEmployer->first_name = $employer->first_name ?? '';
                $virtualEmployer->last_name = $employer->last_name ?? '';
                $virtualEmployer->name = $employer->name ?? '';
                $virtualEmployer->location = $employer->location ?? '';
                
                $virtualApplication->job = $virtualJob;
                $virtualApplication->job_id = null;
                $applications = collect([$virtualApplication]);
            } else {
                return response()->json([
                    "status" => false,
                    "message" => "No approved jobs found",
                    "data" => []
                ], 404);
            }
        }
        
        $response = [];
        
        foreach ($applications as $application) {
            $job = $application->job ? (array)$application->job : [];
            $employer = $application->job && isset($application->job->creator) 
                ? (array)$application->job->creator 
                : [];

            // Get salary payments for this user and job
            $paymentsQuery = Payment::where('staff_id', $user->id);

            // Get current month payments
            $currentMonthPayments = (clone $paymentsQuery)
                ->where('status', 'paid')
                ->where(function($q) use ($monthName) {
                    $q->where('salary_period', 'like', '%' . $monthName . '%')
                      ->orWhere('salary_period', 'like', '%' . str_replace(' ', '-', $monthName) . '%');
                })
                ->get();
                
            $currentMonthSalaries = Salary::where('staff_id', $user->id)
                ->whereMonth('payment_date', date('m'))
                ->whereYear('payment_date', date('Y'))
                ->where('status', 'paid')
                ->get();

            // Filter out duplicate payments that exist in the salaries table
            $filteredCurrentMonthPayments = $currentMonthPayments->filter(function($payment) use ($currentMonthSalaries) {
                foreach ($currentMonthSalaries as $salary) {
                    $timeDiff = abs(strtotime($payment->created_at) - strtotime($salary->created_at));
                    if ($timeDiff < 60 && abs($payment->net_salary - $salary->net_salary) < 0.01) {
                        return false;
                    }
                }
                return true;
            });

            // Calculate totals for current month using the deduplicated list
            $totalBaseSalary = $filteredCurrentMonthPayments->sum('base_salary') + $currentMonthSalaries->sum('basic_salary');
            $totalPerformanceBonus = $filteredCurrentMonthPayments->sum('performance_bonus') + $currentMonthSalaries->sum('performative_allowance');
            $totalOvertimePay = $filteredCurrentMonthPayments->sum('overtime_pay') + $currentMonthSalaries->sum('over_time_allowance');
            $totalTaxDeduction = $filteredCurrentMonthPayments->sum('tax_deduction') + $currentMonthSalaries->sum('tax');
            $totalAdvancePayment = $filteredCurrentMonthPayments->sum('advance_payment') + $currentMonthSalaries->sum('advance_payment');
            $totalNetSalary =
                $filteredCurrentMonthPayments->sum(fn($payment) => max(0, (float) $payment->net_salary)) +
                $currentMonthSalaries->sum(fn($salary) => max(0, (float) $salary->net_salary));

            // If no records for current month, use prioritized base salary
            if ($filteredCurrentMonthPayments->isEmpty() && $currentMonthSalaries->isEmpty()) {
                $userWorkInfo = UserWorkInfo::where('user_id', $user->id)->first();
                if ($userWorkInfo && $userWorkInfo->salary) {
                    $totalBaseSalary = (float) $userWorkInfo->salary;
                } elseif (isset($job['compensation'])) {
                    $totalBaseSalary = (float) $job['compensation'];
                } elseif (is_object($application->job) && isset($application->job->compensation)) {
                     $totalBaseSalary = (float) $application->job->compensation;
                } else {
                    $totalBaseSalary = 0;
                }
                $totalNetSalary = $totalBaseSalary;
            }

            // Get payment history (last 3 months)
            $salaryHistory = Salary::where('staff_id', $user->id)
                ->orderBy('payment_date', 'desc')
                ->limit(3)
                ->get()
                ->map(function($s) {
                    return [
                        'id' => $s->id,
                        'month' => Carbon::parse($s->payment_date)->format('F Y'),
                        'date' => $s->created_at->toDateTimeString(),
                        'paid_on' => Carbon::parse($s->payment_date)->format('d/m/Y'),
                        'amount' => max(0, (float) $s->net_salary),
                        'status' => $s->status,
                        'type' => (float)($s->advance_payment ?? 0) > 0 ? 'advance' : 'salary',
                        'payment_mode' => $s->payment_mode,
                        'base_salary' => $s->basic_salary,
                        'performance_bonus' => $s->performative_allowance,
                        'overtime_pay' => $s->over_time_allowance,
                        'advance_payment' => $s->advance_payment,
                        'payment_id' => 'SAL-' . $s->id,
                    ];
                });

            $paymentHistory = (clone $paymentsQuery)
                // ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'month' => $payment->salary_period,
                        'date' => $payment->created_at->toDateTimeString(),
                        'paid_on' => $payment->updated_at->format('d/m/Y'),
                        'amount' => max(0, (float) $payment->net_salary),
                        'status' => $payment->status,
                        'type' => (float)($payment->advance_payment ?? 0) > 0 ? 'advance' : 'salary',
                        'payment_mode' => $payment->payment_mode,
                        'base_salary' => $payment->base_salary,
                        'performance_bonus' => $payment->performance_bonus,
                        'overtime_pay' => $payment->overtime_pay,
                        'advance_payment' => $payment->advance_payment,
                        'payment_id' => $payment->payment_id,
                        'order_id' => $payment->order_id
                    ];
                });

            // Deduplicate paymentHistory against salaryHistory
            $filteredPaymentHistory = $paymentHistory->filter(function($payment) use ($salaryHistory) {
                foreach ($salaryHistory as $salary) {
                    $timeDiff = abs(strtotime($payment['date']) - strtotime($salary['date']));
                    if ($timeDiff < 60 && abs($payment['amount'] - $salary['amount']) < 0.01) {
                        return false;
                    }
                }
                return true;
            });

            // Merge and sort combined history
            $combinedHistory = $salaryHistory->concat($filteredPaymentHistory)
                ->sortByDesc('date')
                ->values()
                ->take(3);
            $startDate = date('Y-m-01', strtotime($month));
            $endDate = date('Y-m-t', strtotime($month));
            
            $attendanceRecords = Attendance::where('staff_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $presentDays = $attendanceRecords->where('status', 'present')->count();
            $lateArrivals = $attendanceRecords->where('status', 'late')->count();
            $absentDays = $attendanceRecords->where('status', 'absent')->count();
            
            // Calculate total working days in the month (excluding weekends)
            $totalWorkingDays = $this->getWorkingDays($startDate, $endDate);
            
            // Calculate absent days from total working days
            $actualAbsentDays = $totalWorkingDays - ($presentDays + $lateArrivals);

            $acceptedDate = $application->updated_at ?? now();
            $nextPayDate = \Carbon\Carbon::now()->endOfMonth()->format('d/m/Y');

            $earningsSummary = [
                "employer" => $application->job && isset($application->job->creator) 
                    ? (trim(($application->job->creator->first_name ?? '') . ' ' . ($application->job->creator->last_name ?? '')) ?: ($application->job->creator->name ?? "Your Employer"))
                    : (trim(($user->addedByUser->first_name ?? '') . ' ' . ($user->addedByUser->last_name ?? '')) ?: ($user->addedByUser->name ?? ($employer['name'] ?? "Your Employer"))),
                "job_id" => $job['id'] ?? null,
                "role" => $job['title'] ?? "Job Role",

                "total_payable_amount" => $totalNetSalary,
                "payment_date" => $nextPayDate,

                "earnings_breakdown" => [
                    "base_salary" => [
                        "amount" => $totalBaseSalary,
                        "included" => $totalBaseSalary > 0
                    ],
                    "performance_bonus" => [
                        "amount" => $totalPerformanceBonus,
                        "included" => $totalPerformanceBonus > 0
                    ],
                    "overtime_pay" => [
                        "amount" => $totalOvertimePay,
                        "included" => $totalOvertimePay > 0
                    ]
                ],

                "deductions" => [
                    "provident_fund" => [
                        "amount" => 0, 
                        "included" => false
                    ],
                    "advance_repayment" => [
                        "amount" => abs($totalAdvancePayment),
                        "included" => $totalAdvancePayment != 0
                    ]
                ],

                "payment_status" => $filteredCurrentMonthPayments->isEmpty() && $currentMonthSalaries->isEmpty() ? 'pending' : 'paid',

                "payment_history" => $combinedHistory,

                "salary_summary" => [
                    "current_monthly_salary" => (float)(($user->userWorkInfo && $user->userWorkInfo->salary) ? $user->userWorkInfo->salary : ($job['compensation'] ?? (is_object($application->job) ? ($application->job->compensation ?? 0) : 0))),
                    "next_pay_date" => $nextPayDate,
                ],

                "attendance_summary" => [
                    "present_days" => $presentDays,
                    "late_arrivals" => $lateArrivals,
                    "absent_days" => $actualAbsentDays > 0 ? $actualAbsentDays : $absentDays,
                    "total_working_days" => $totalWorkingDays,
                    "attendance_percentage" => $totalWorkingDays > 0 ? 
                        round((($presentDays + $lateArrivals) / $totalWorkingDays) * 100, 2) : 0
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

            $response[] = $earningsSummary;
        }


        return response()->json([
            "status" => true,
            "message" => "Earnings summary fetched successfully",
            "data" => $response
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch earnings summary: ' . $e->getMessage()
        ], 500);
    }
}

private function getWorkingDays($startDate, $endDate)
{
    $start = Carbon::parse($startDate);
    $end = Carbon::parse($endDate);
    
    $workingDays = 0;
    
    while ($start->lte($end)) {
        if ($start->isWeekday()) {
            $workingDays++;
        }
        $start->addDay();
    }
    
    return $workingDays;
}

    /**
     * Get all staff members (for dropdown selection)
     */
    public function getStaffMembers(): JsonResponse
    {
        try {
            $staffMembers = User::where('user_role_id', 2)
                ->whereHas('jobApplications', function($query) {
                    $query->where('application_status', 'accepted');
                })
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number')
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone_number
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Staff members retrieved successfully',
                'data' => $staffMembers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve staff members: ' . $e->getMessage()
            ], 500);
        }
    }


        public function getRecentPayments(Request $request): JsonResponse
    {         $user = Auth::guard('api')->user();
        try {

            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'status' => 'nullable|in:success,failed,pending',
                'payment_mode' => 'nullable|string',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'staff_id' => 'nullable|exists:users,id',
                'user_id' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $limit = $request->limit ?? 20;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $limit;

            // 1. Get salary payments
            $paymentsQuery = Payment::with(['user', 'staff'])->where('user_id', $user->id);
            
            if ($request->filled('staff_id')) {
                $paymentsQuery->where('staff_id', $request->staff_id);
            }
            if ($request->filled('date_from')) {
                $paymentsQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $paymentsQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $payments = $paymentsQuery->orderBy('created_at', 'desc')->get();

            // 2. Get staff advances
            $advancesQuery = \App\Models\StaffAdvance::with(['staff'])->where('employer_id', $user->id);
            
            if ($request->filled('staff_id')) {
                $advancesQuery->where('staff_id', $request->staff_id);
            }
            if ($request->filled('date_from')) {
                $advancesQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $advancesQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $advances = $advancesQuery->orderBy('created_at', 'desc')->get();

            // 3. Unify results
            $unifiedData = collect();

            foreach ($payments as $payment) {
                $unifiedData->push([
                    'id' => $payment->id,
                    'type' => 'salary',
                    'amount' => (float) $payment->net_salary,
                    'payment_mode' => $payment->payment_mode,
                    'status' => $payment->status,
                    'date' => $payment->created_at->toISOString(),
                    'salary_period' => $payment->salary_period,
                    'staff_name' => $payment->staff 
                        ? (trim($payment->staff->first_name . ' ' . $payment->staff->last_name) ?: ($payment->staff->name ?: 'Staff Member'))
                        : 'Unknown',
                ]);
            }

            foreach ($advances as $advance) {
                // Determine payment mode from remarks if not explicitly stored
                $mode = 'cash';
                if (stripos($advance->remarks, 'UPI') !== false) $mode = 'upi';
                
                $unifiedData->push([
                    'id' => $advance->id,
                    'type' => 'advance',
                    'is_advance' => true,
                    'amount' => (float) $advance->amount,
                    'payment_mode' => $mode,
                    'status' => $advance->status === 'active' ? 'paid' : $advance->status,
                    'date' => $advance->created_at->toISOString(),
                    'deduction_method' => $advance->deduction_type === 'full' ? 'one_time' : 'monthly',
                    'staff_name' => $advance->staff 
                        ? (trim($advance->staff->first_name . ' ' . $advance->staff->last_name) ?: ($advance->staff->name ?: 'Staff Member'))
                        : 'Unknown',
                ]);
            }

            // Sort by date desc
            $sortedData = $unifiedData->sortByDesc('date')->values();

            return response()->json([
                'status' => true,
                'message' => 'Unified history retrieved successfully',
                'data' => $sortedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve payments: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve payments. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }


    public function getTodayActiveStaff(Request $request): JsonResponse
{
    try {
        // Validation for optional parameters
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->limit ?? 20;
        $page = $request->page ?? 1;
        $offset = ($page - 1) * $limit;
        $today = Carbon::today();

        // Get current authenticated user (the one who added the staff)
        $authUser = Auth::guard('api')->user();
        if (!$authUser) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Build query for staff members
        $staffQuery = User::where('user_role_id', 2)
            ->where('added_by', $authUser->id)
            ->where('is_staff_added', 1)
            ->where('is_active', 1)
            ->where('is_deleted', 0)
            ->with(['lastExp', 'userWorkInfo']);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $staffQuery->where(function($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Get total count for pagination
        $total = $staffQuery->count();

        // Get paginated results
        $staffMembers = $staffQuery->orderBy('first_name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Get all staff IDs for batch queries
        $staffIds = $staffMembers->pluck('id')->toArray();

        // Get approved leave requests for today in one query
        $todayLeaves = LeaveRequest::whereIn('user_id', $staffIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()
            ->keyBy('user_id');

        // Get today's attendance records for all staff in one query
        $todayAttendance = Attendance::whereIn('staff_id', $staffIds)
            ->whereDate('date', $today)
            ->get()
            ->keyBy('staff_id');
        $activeStaffData = $staffMembers->map(function($staff) use ($todayLeaves, $todayAttendance, $today) {
            $hasApprovedLeave = $todayLeaves->has($staff->id);
            $hasAttendance = $todayAttendance->has($staff->id);
            
            // Get leave details if exists
            $leaveDetails = null;
            if ($hasApprovedLeave) {
                $leave = $todayLeaves->get($staff->id);
                $leaveDetails = [
                    'leave_id' => $leave->id,
                    'leave_type' => $leave->leaveType ? $leave->leaveType->name : null,
                    'start_date' => $leave->start_date,
                    'end_date' => $leave->end_date,
                    'reason' => $leave->reason,
                    'supporting_document_url' => $leave->supporting_document_url
                ];
            }

            // Get attendance details if exists
            $attendanceDetails = null;
            if ($hasAttendance) {
                $attendance = $todayAttendance->get($staff->id);
                $attendanceDetails = [
                    'attendance_id' => $attendance->id,
                    'staff_id' => $attendance->staff_id,
                    'status' => $attendance->status,
                    'check_in_time' => $attendance->check_in_time,
                    'late_minutes' => $attendance->late_minutes,
                    'description' => $attendance->description,
                    'date' => $attendance->date,
                    'processed_by' => $attendance->processed_by
                ];
            }

            return [
                'staff' => $staff,
                'name' => $staff->first_name . ' ' . $staff->last_name,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'email' => $staff->email,
                'phone_number' => $staff->phone_number,
                'image' => $staff->image,
                'is_active_today' => !$hasApprovedLeave,
                'status' => !$hasApprovedLeave ? 'active' : 'on_leave',
                'is_attendance' => $hasAttendance,
                'attendance_status' => $hasAttendance ? $todayAttendance->get($staff->id)->status : null,
                'last_work_experience' => $staff->lastExp,
                'work_info' => $staff->userWorkInfo,
                'leave_details' => $leaveDetails,
                'attendance_details' => $attendanceDetails,
                'created_at' => $staff->created_at->format('Y-m-d H:i:s')
            ];
        });

        // Separate active and on-leave staff
        $activeStaff = $activeStaffData->where('is_active_today', true)->values();
        $onLeaveStaff = $activeStaffData->where('is_active_today', false)->values();

        // Calculate attendance stats for active staff
        $attendanceStats = [
            'present' => $activeStaff->where('attendance_status', 'present')->count(),
            'absent' => $activeStaff->where('attendance_status', 'absent')->count(),
            'late' => $activeStaff->where('attendance_status', 'late')->count(),
            'not_marked' => $activeStaff->where('is_attendance', false)->count()
        ];

        $pagination = [
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total' => $total,
            'last_page' => ceil($total / $limit),
            'from' => $offset + 1,
            'to' => $offset + $staffMembers->count()
        ];

        $stats = [
            'total_staff' => $total,
            'active_today' => $activeStaff->count(),
            'on_leave_today' => $onLeaveStaff->count(),
            'date' => $today->format('Y-m-d'),
            'attendance_summary' => $attendanceStats
        ];

        return response()->json([
            'status' => true,
            'message' => 'Today\'s active staff list retrieved successfully',
            'data' => [
                'stats' => $stats,
                'active_staff' => $activeStaff,
                'on_leave_staff' => $onLeaveStaff
            ],
            'pagination' => $pagination
        ]);

    } catch (\Exception $e) {
        \Log::error('Failed to retrieve today\'s active staff: ' . $e->getMessage());
        
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve staff list. Please try again later.',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}


/**
 * Get staff dashboard summary
 */
    public function getStaffDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get current date information
            $currentDate = Carbon::now();
            $today = $currentDate->format('Y-m-d');
            $currentMonth = $currentDate->format('Y-m');
            $lastMonth = $currentDate->copy()->subMonth()->format('Y-m');
            
            // Get staff information
            $staffInfo = [
                'name' => $user->first_name . ' ' . $user->last_name,
                'greeting' => 'Ready for a productive day!',
                'date' => Carbon::now()->format('l, F j, Y')
            ];

            // Attendance Summary (Last 30 Days)
            $thirtyDaysAgo = Carbon::now()->subDays(30)->format('Y-m-d');
            
            $attendanceRecords = Attendance::where('staff_id', $user->id)
                ->whereBetween('date', [$thirtyDaysAgo, $today])
                ->get();

            $presentDays = $attendanceRecords->where('status', 'present')->count();
            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $absentDays = $attendanceRecords->where('status', 'absent')->count();
            
            $totalWorkingDays = $presentDays + $lateDays + $absentDays;
            $leaveDays = $absentDays; // Assuming absent days are leave days

            $attendanceSummary = [
                'last_30_days' => [
                    'days_present' => $presentDays + $lateDays, // Both present and late count as present
                    'total_days' => 30,
                    'leaves_taken' => $leaveDays,
                    'attendance_percentage' => $totalWorkingDays > 0 ? 
                        round((($presentDays + $lateDays) / 30) * 100, 2) : 0
                ]
            ];

            // Earnings Summary (Current Month)
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            
            $currentMonthPayments = Payment::where('staff_id', $user->id)
                ->where('status', 'paid')
                ->where('salary_period', 'like', '%' . date('F Y') . '%')
                ->get();
                
            $currentMonthSalaries = Salary::where('staff_id', $user->id)
                ->whereMonth('payment_date', date('m'))
                ->whereYear('payment_date', date('Y'))
                ->where('status', 'paid')
                ->get();

            // Filter out duplicate payments that exist in the salaries table
            $filteredCurrentMonthPayments = $currentMonthPayments->filter(function($payment) use ($currentMonthSalaries) {
                foreach ($currentMonthSalaries as $salary) {
                    $timeDiff = abs(strtotime($payment->created_at) - strtotime($salary->created_at));
                    if ($timeDiff < 60 && abs($payment->net_salary - $salary->net_salary) < 0.01) {
                        return false;
                    }
                }
                return true;
            });

            $totalEarnings =
                $filteredCurrentMonthPayments->sum(fn($payment) => max(0, (float) $payment->net_salary)) +
                $currentMonthSalaries->sum(fn($salary) => max(0, (float) $salary->net_salary));
            
            // If no payments/salaries found, get base salary from prioritized sources
            if ($filteredCurrentMonthPayments->isEmpty() && $currentMonthSalaries->isEmpty()) {
                $userWorkInfo = UserWorkInfo::where('user_id', $user->id)->first();
                if ($userWorkInfo && $userWorkInfo->salary) {
                    $totalEarnings = (float) $userWorkInfo->salary;
                } else {
                    $acceptedJob = JobApplication::where('user_id', $user->id)
                        ->where('application_status', 'accepted')
                        ->with('job')
                        ->first();
                    
                    if ($acceptedJob && $acceptedJob->job) {
                        $totalEarnings = $acceptedJob->job->compensation ?? 0;
                    }
                }
            }

            $earningsSummary = [
                'total_earnings' => (float) $totalEarnings,
                'currency' => 'INR',
                'period' => 'this month',
                'trend' => 'up' // You can calculate this by comparing with previous month
            ];

            // Leave Requests (Last Month)
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
            
            $leaveRequestsLastMonth = LeaveRequest::where('user_id', $user->id)
                ->whereBetween('start_date', [$lastMonthStart, $lastMonthEnd])
                ->get();

            $leaveSummary = [
                'last_month' => [
                    'total_requests' => $leaveRequestsLastMonth->count(),
                    'approved_requests' => $leaveRequestsLastMonth->where('status', 'approved')->count(),
                    'pending_requests' => $leaveRequestsLastMonth->where('status', 'pending')->count(),
                    'rejected_requests' => $leaveRequestsLastMonth->where('status', 'rejected')->count()
                ]
            ];

            // New Job Matches
            $newJobMatches = JobApplication::where('user_id', $user->id)
                ->where('application_status', 'pending')
                ->with('job')
                ->limit(3)
                ->get()
                ->map(function($application) {
                    return [
                        'job_id' => $application->job_id,
                        'title' => $application->job->title ?? 'Job Title',
                        'employer' => $application->job->creator->name ?? 'Employer',
                        'compensation' => $application->job->compensation ?? 0,
                        'location' => ($application->job->city ?? '') . ', ' . ($application->job->state ?? ''),
                        'applied_date' => $application->created_at->format('M j, Y')
                    ];
                });

            // Today's Attendance Status
            $todayAttendance = Attendance::where('staff_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            $todayStatus = [
                'has_attendance' => !is_null($todayAttendance),
                'status' => $todayAttendance ? $todayAttendance->status : 'not_marked',
                'check_in_time' => $todayAttendance ? $todayAttendance->check_in_time : null,
                'late_minutes' => $todayAttendance ? $todayAttendance->late_minutes : 0
            ];

            // Upcoming Leaves
            $upcomingLeaves = LeaveRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '>=', $today)
                ->orderBy('start_date', 'asc')
                ->limit(2)
                ->get()
                ->map(function($leave) {
                    return [
                        'leave_id' => $leave->id,
                        'leave_type' => $leave->leaveType->name ?? 'Leave',
                        'start_date' => $leave->start_date,
                        'end_date' => $leave->end_date,
                        'reason' => $leave->reason,
                        'duration_days' => Carbon::parse($leave->start_date)->diffInDays($leave->end_date) + 1
                    ];
                });

            // Recent Payments
            $recentPayments = Payment::where('staff_id', $user->id)
                // ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function($payment) {
                    return [
                        'payment_id' => $payment->id,
                        'amount' => max(0, (float) $payment->net_salary),
                        'period' => $payment->salary_period,
                        'payment_date' => $payment->updated_at->format('M j, Y'),
                        'payment_method' => $payment->payment_mode,
                        'status' => $payment->status
                    ];
                });

            // Compile dashboard data
            $dashboardData = [
                'staff_info' => $staffInfo,
                'attendance_summary' => $attendanceSummary,
                'earnings_summary' => $earningsSummary,
                'leave_summary' => $leaveSummary,
                'job_matches' => [
                    'count' => $newJobMatches->count(),
                    'jobs' => $newJobMatches
                ],
                'today_status' => $todayStatus,
                'upcoming_leaves' => $upcomingLeaves,
                'recent_payments' => $recentPayments,
                'quick_actions' => [
                    'apply_leave' => true,
                    'view_jobs' => true,
                    'view_attendance' => true,
                    'view_earnings' => true
                ]
            ];

            return response()->json([
                'status' => true,
                'message' => 'Staff dashboard data retrieved successfully',
                'data' => $dashboardData
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve staff dashboard: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve dashboard data. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    
    }


    public function advanceWithdraw(Request $request)
    {
        try {
            // ✅ Validation
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0',
                'should_deduct' => 'nullable|boolean',
                'deduction_method' => 'nullable|string|in:monthly,one_time,installments',
                'num_installments' => 'nullable|integer|min:1|max:24',
                'monthly_deduction' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|in:cash,upi,bank_transfer'
            ]);

            // ✅ Find user
            $user = User::find($request->user_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $shouldDeduct = $request->input('should_deduct', true);
            $deductionMethod = $request->input('deduction_method', 'monthly');
            $paymentMode = $request->input('payment_mode', 'cash');
            $status = $request->input('status');
            
            if (!$status) {
                $status = (strtolower($paymentMode) === 'cash') ? 'paid' : 'pending';
            }
            
            $employerId = Auth::guard('api')->id();

            // ✅ Only update advance_withdraw_amount if deduction is enabled
            if ($shouldDeduct) {
                $user->advance_withdraw_amount += $request->amount;
            }

            // mark added by user
            $user->advance_withdraw_added_by = $employerId;
            $user->save();

            // ✅ Map frontend deduction types to backend enum values
            $mappedDeductionType = 'manual';
            if ($deductionMethod === 'one_time') {
                $mappedDeductionType = 'full';
            } elseif ($deductionMethod === 'installments' || $deductionMethod === 'monthly') {
                $mappedDeductionType = 'installment';
            }

            // ✅ Also create StaffAdvance record so staff can see it in My Advances
            try {
                $numInstallments = $request->input('num_installments');
                $monthlyDeduction = $request->input('monthly_deduction');

                // Calculate installment_amount: monthly deduction amount if installments, else full amount
                if ($deductionMethod === 'installments' && $numInstallments && $numInstallments > 0) {
                    $installmentAmount = $monthlyDeduction ?: ceil($request->amount / $numInstallments);
                } else {
                    $installmentAmount = $request->amount;
                }

                \App\Models\StaffAdvance::create([
                    'staff_id'           => $user->id,
                    'employer_id'        => $employerId,
                    'amount'             => $request->amount,
                    'remaining_balance'  => $shouldDeduct ? $request->amount : 0,
                    'deduction_type'     => $mappedDeductionType,
                    'installment_amount' => $installmentAmount,
                    'num_installments'   => ($deductionMethod === 'installments' && $numInstallments) ? $numInstallments : null,
                    'given_date'         => now()->toDateString(),
                    'status'             => $status === 'paid' ? ($shouldDeduct ? 'active' : 'closed') : 'pending',
                    'remarks'            => 'Paid via ' . strtoupper($paymentMode),
                ]);
            } catch (\Exception $e) {
                \Log::warning('StaffAdvance record creation failed: ' . $e->getMessage());
                // non-fatal — advance_withdraw_amount already updated
            }

            // ✅ Create notification for staff (in-app + FCM push only)
            try {
                \App\Services\NotificationService::send(
                    $user->id,
                    'Advance Payment Received',
                    "You have received an advance of ₹" . number_format($request->amount, 2) . ($shouldDeduct ? ". This will be deducted from your salary ($deductionMethod)." : "."),
                    'advance_payment',
                    ['skip_whatsapp' => true, 'skip_sms' => true]
                );
            } catch (\Exception $e) {
                \Log::warning('Advance notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $shouldDeduct 
                    ? "Advance payment processed via " . strtoupper($paymentMode) . ". Amount will be deducted from salary ($deductionMethod)."
                    : "Advance payment processed via " . strtoupper($paymentMode) . " without salary deduction.",
                'data' => [
                    'user_id' => $user->id,
                    'advance_withdraw_amount' => $user->advance_withdraw_amount,
                    'should_deduct' => $shouldDeduct,
                    'deduction_method' => $deductionMethod,
                    'num_installments' => ($deductionMethod === 'installments' && $numInstallments) ? $numInstallments : null,
                    'installment_amount' => ($deductionMethod === 'installments' && $numInstallments) ? ($monthlyDeduction ?: ceil($request->amount / $numInstallments)) : null,
                    'payment_mode' => $paymentMode
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getAdminDashboard(Request $request)
    {
        // try {
            $user = Auth::guard('api')->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
 
            $thirtyDaysAgo = Carbon::now()->subDays(30)->format('Y-m-d');
            $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d');
            $currentDate = Carbon::now();
            $today = $currentDate->format('Y-m-d');
            
            $staffCount = User::where('user_role_id', 2)->count();
            $employerCount = User::where('user_role_id', 3)->count();
            
            $staffMonthCount = User::where('user_role_id', 2)->whereBetween('created_at', [$thirtyDaysAgo, $today])->count();
            $employerMonthCount = User::where('user_role_id', 3)->whereBetween('created_at', [$thirtyDaysAgo, $today])->count();
            // Compile dashboard data

            $recentSubscriptions = SubscriptionUser::with('subscription')
                ->whereBetween('created_at', [$thirtyDaysAgo, $today])
                ->get();
            $allSubscriptions = SubscriptionUser::with('subscription')->get();
            $activeMemberships = SubscriptionUser::where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                })
                ->count();
            $pendingVerifications = KycVerification::where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', 'pending');
            })->count();

            $subscriptionUsers = $recentSubscriptions->count();
            $subscriptionRevenue = $recentSubscriptions->sum(fn($sub) => $this->getEffectiveSubscriptionAmount($sub));
            $totalSubscriptionRevenue = $allSubscriptions->sum(fn($sub) => $this->getEffectiveSubscriptionAmount($sub));
            
            $newUserWeekCount = User::whereBetween('created_at', [$sevenDaysAgo, $today])->count();
            $newUserMonthCount = User::whereBetween('created_at', [$thirtyDaysAgo, $today])->count();

            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $userMonthGrowth = User::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as total')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('month')
                ->orderBy('month', 'ASC')
                ->get()
                ->keyBy('month');

            $startDate = Carbon::now()->subDays(29)->startOfDay();
            $endDate = Carbon::now()->endOfDay();

            $dailySignups = User::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->keyBy('date'); 
                
            // reveue growth
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $revenueMonthGrowth = SubscriptionUser::with('subscription')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()
                ->groupBy(function ($subscriptionUser) {
                    return Carbon::parse($subscriptionUser->created_at)->format('Y-m');
                })
                ->map(function ($monthSubscriptions) {
                    return [
                        'total_revenue' => $monthSubscriptions->sum(fn($sub) => $this->getEffectiveSubscriptionAmount($sub)),
                    ];
                });
            $freeUser = $allSubscriptions->filter(fn($sub) => $this->getEffectiveSubscriptionAmount($sub) <= 0)->count();
            $paidUser = $allSubscriptions->filter(fn($sub) => $this->getEffectiveSubscriptionAmount($sub) > 0)->count();
                
            $jobToday = Job::whereDate('created_at', Carbon::today())->count();
           
            $jobTotal = Job::count();
             
            $jobApplicationsToday = JobApplication::whereDate('created_at', Carbon::today())->count();
            $jobApplicationsTotal = JobApplication::count();

            // Compile dashboard data

            $totalSalaryProcessed = Salary::where('status', 'paid')->sum('net_salary');
            $salaryPaymentsDone = Salary::where('status', 'paid')->count();
            $pendingPayments = DB::table('salaries')->where('status', 'pending')->count();
            
            $dashboardData = [
                'overall_stats' => [
                    'total_staff' => $staffCount,
                    'total_employers' => $employerCount,
                    'staff_this_month' => $staffMonthCount,
                    'employers_this_month' => $employerMonthCount,
                    'new_subscriptions_this_month' => $subscriptionUsers,
                    'active_memberships' => $activeMemberships,
                    'pending_verifications' => $pendingVerifications,
                    'subscription_revenue_this_month' => (float) $subscriptionRevenue,
                    'total_subscription_revenue' => (float) $totalSubscriptionRevenue,
                    'new_users_last_week' => $newUserWeekCount,
                    'new_users_last_month' => $newUserMonthCount,
                    // You can add more overall stats here
                ],
                'user_month_growth' => $userMonthGrowth,
                'daily_signups' => $dailySignups,
                'revenue_month_growth' => $revenueMonthGrowth,
                'subscription_breakdown' => [
                    'free_users' => $freeUser,
                    'paid_users' => $paidUser
                ],
                'job_stats' => [
                    'jobs_posted_today' => $jobToday,
                    'total_jobs' => $jobTotal,
                    'job_applications_today' => $jobApplicationsToday,
                    'total_job_applications' => $jobApplicationsTotal
                ],
                'salary_stats' => [
                    'total_salary_processed' => (float) $totalSalaryProcessed,
                    'salary_payments_done' => $salaryPaymentsDone,
                    'pending_payments' => $pendingPayments,
                ]
            ];

            return response()->json([
                'status' => true,
                'message' => 'Staff dashboard data retrieved successfully',
                'data' => $dashboardData
            ]);

        // } catch (\Exception $e) {
        //     \Log::error('Failed to retrieve staff dashboard: ' . $e->getMessage());
            
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Failed to retrieve dashboard data. Please try again later.',
        //         'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        //     ], 500);
        // }
    
    }

    /**
     * Owner-side: Initiate RazorpayX payout for a paid salary
     */
    public function sendToBank(Request $request, $id, RazorpayXService $razorpayXService)
    {
        $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'mode' => 'nullable|string|in:bank_transfer,neft,imps,rtgs,upi',
            'narration' => 'nullable|string|max:255',
        ]);

        if (!$razorpayXService->isConfigured()) {
            return response()->json([
                'status' => false,
                'message' => 'RazorpayX credentials are not configured yet.',
            ], 422);
        }

        $userId = Auth::guard('api')->user()->id;
        $salary = Salary::with(['staff.bankAccounts', 'houseowner'])->findOrFail($id);

        if ($salary->houseowner_id != $userId) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if (strtolower((string) $salary->status) !== 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Salary must be marked as paid before sending to bank.',
            ], 422);
        }

        if ((float) $salary->net_salary <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Salary amount must be greater than zero.',
            ], 422);
        }

        $ACTIVE_STATUSES = ['initiated', 'queued', 'pending', 'processing', 'processed', 'sent'];
        $payout = null;

        try {
            $payout = DB::transaction(function () use ($request, $salary, $userId, $ACTIVE_STATUSES) {
                $lockedSalary = Salary::whereKey($salary->id)->lockForUpdate()->firstOrFail();

                $hasActive = SalaryPayout::where('salary_id', $lockedSalary->id)
                    ->whereIn('status', $ACTIVE_STATUSES)
                    ->exists();

                if ($hasActive) {
                    throw new \RuntimeException('A payout is already in progress or completed for this salary.');
                }

                $selectedBankAccount = null;
                if ($request->filled('bank_account_id')) {
                    $selectedBankAccount = BankAccount::where('id', $request->bank_account_id)
                        ->where('user_id', $lockedSalary->staff_id)
                        ->first();
                }

                if (!$selectedBankAccount) {
                    $selectedBankAccount = $lockedSalary->staff?->bankAccounts?->firstWhere('is_set', 1)
                        ?? $lockedSalary->staff?->bankAccounts?->sortByDesc('id')->first();
                }

                if (!$selectedBankAccount) {
                    throw new \RuntimeException('No bank account found for this staff. Please ask staff to add a bank account first.');
                }

                return SalaryPayout::create([
                    'salary_id' => $lockedSalary->id,
                    'staff_id' => $lockedSalary->staff_id,
                    'houseowner_id' => $lockedSalary->houseowner_id,
                    'bank_account_id' => $selectedBankAccount->id,
                    'requested_by' => $userId,
                    'amount' => $lockedSalary->net_salary,
                    'currency' => 'INR',
                    'mode' => strtolower((string) $request->input('mode', 'bank_transfer')),
                    'purpose' => 'salary',
                    'status' => 'initiated',
                    'idempotency_key' => (string) Str::uuid(),
                    'narration' => $request->input('narration', 'Salary payout'),
                    'queue_if_low_balance' => true,
                    'requested_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $payout->load(['bankAccount.user', 'salary.staff.bankAccounts', 'salary.houseowner']);
        $salary = $payout->salary;
        $bankAccount = $payout->bankAccount;

        if ($bankAccount && !$bankAccount->relationLoaded('user')) {
            $bankAccount->setRelation('user', $salary->staff);
        }

        $previousPayout = SalaryPayout::where('salary_id', $salary->id)
            ->where('id', '!=', $payout->id)
            ->latest()
            ->first();

        $contactId = null;
        $fundAccountId = null;
        $payoutResult = null;

        try {
            if ($previousPayout?->contact_id && $previousPayout->staff_id === $salary->staff_id) {
                $contactId = $previousPayout->contact_id;
            } else {
                $contactResult = $razorpayXService->createContact($salary->staff);
                if (!$contactResult['status']) {
                    throw new \RuntimeException($contactResult['message'] ?? 'Failed to create contact.');
                }
                $contactId = $contactResult['data']['id'] ?? null;
            }

            if (!$contactId) {
                throw new \RuntimeException('RazorpayX contact id was not returned.');
            }

            if ($previousPayout?->fund_account_id && $previousPayout->bank_account_id === $bankAccount->id) {
                $fundAccountId = $previousPayout->fund_account_id;
            } else {
                $fundAccountResult = $razorpayXService->createFundAccount($bankAccount, $contactId);
                if (!$fundAccountResult['status']) {
                    throw new \RuntimeException($fundAccountResult['message'] ?? 'Failed to create fund account.');
                }
                $fundAccountId = $fundAccountResult['data']['id'] ?? null;
            }

            if (!$fundAccountId) {
                throw new \RuntimeException('RazorpayX fund account id was not returned.');
            }

            $idempotencyKey = 'salary-' . $salary->id . '-payout-' . $payout->id;
            $referenceId = 'salary_' . $salary->id . '_payout_' . $payout->id;

            $payoutResult = $razorpayXService->createPayout(
                $salary,
                $bankAccount,
                $fundAccountId,
                $idempotencyKey,
                [
                    'reference_id' => $referenceId,
                    'mode' => strtolower((string) $request->input('mode', 'bank_transfer')),
                    'purpose' => 'salary',
                    'narration' => $request->input('narration', 'Salary payout'),
                    'queue_if_low_balance' => true,
                ]
            );

            if (!$payoutResult['status']) {
                throw new \RuntimeException($payoutResult['message'] ?? 'Failed to create payout.');
            }

            $payload = $payoutResult['data'] ?? [];
            $payout->update([
                'contact_id' => $contactId,
                'fund_account_id' => $fundAccountId,
                'payout_id' => $payload['id'] ?? null,
                'reference_id' => $payload['reference_id'] ?? $referenceId,
                'status' => $payload['status'] ?? 'queued',
                'request_payload' => $payoutResult['request'] ?? null,
                'response_payload' => $payload,
                'processed_at' => in_array(($payload['status'] ?? ''), ['processed', 'queued', 'pending', 'processing'], true) ? now() : null,
                'utr' => $payload['utr'] ?? null,
                'error_message' => null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payout initiated successfully. Funds will be transferred shortly.',
                'data' => [
                    'payout' => $payout->fresh(['bankAccount']),
                    'status' => $payload['status'] ?? 'queued',
                ],
            ], 200);
        } catch (\Throwable $e) {
            $failedPayload = is_array($payoutResult) ? $payoutResult : [];
            $payout->update([
                'contact_id' => $contactId ?? null,
                'fund_account_id' => $fundAccountId ?? null,
                'payout_id' => $failedPayload['data']['id'] ?? null,
                'reference_id' => $failedPayload['data']['reference_id'] ?? $payout->reference_id,
                'status' => 'failed',
                'request_payload' => $failedPayload['request'] ?? $payout->request_payload,
                'response_payload' => $failedPayload['response'] ?? $payout->response_payload,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Owner-side: Get payout history for a salary
     */
    public function payoutHistory($id)
    {
        $userId = Auth::guard('api')->user()->id;
        $salary = Salary::with([
            'staff.bankAccounts',
            'houseowner',
            'payouts' => function ($payoutQuery) {
                $payoutQuery->with('bankAccount')->latest();
            },
        ])->findOrFail($id);

        if ($salary->houseowner_id != $userId) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payout history retrieved successfully',
            'data' => $salary,
        ]);
    }

    
}
