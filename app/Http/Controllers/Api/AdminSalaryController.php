<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Salary;
use App\Models\SalaryPayout;
use App\Models\User;
use App\Models\BankAccount;
use App\Models\Notification;
use App\Models\StaffAdvance;
use App\Models\AdvanceTransaction;
use App\Models\UserWorkInfo;
use App\Services\Admin\RazorpayXService;
use Illuminate\Support\Facades\DB;


class AdminSalaryController extends Controller
{
    private const ACTIVE_PAYOUT_STATUSES = [
        'initiated',
        'queued',
        'pending',
        'processing',
        'processed',
        'sent',
    ];

    private function hasActivePayout(Salary $salary): bool
    {
        return SalaryPayout::where('salary_id', $salary->id)
            ->whereIn('status', self::ACTIVE_PAYOUT_STATUSES)
            ->exists();
    }
    
    public function index(Request $request)
    {
        $query = Salary::with([
            'staff.bankAccounts',
            'houseowner',
            'payouts' => function ($payoutQuery) {
                $payoutQuery->latest();
            },
        ]);

        // Filter by month (format: 2026-02)
        if ($request->month) {
            $query->whereYear('payment_date', substr($request->month, 0, 4))
                  ->whereMonth('payment_date', substr($request->month, 5, 2));
        }
        
        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by staff name
        if ($request->name) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }
        
        $salaries = $query->orderBy('payment_date', 'desc')->paginate(10);
        
        return response()->json([
            'status' => true,
            'message' => 'Salaries retrieved successfully',
            'data' => $salaries
        ], 200);
    }

    public function payoutHistory($id)
    {
        $salary = Salary::with([
            'staff.bankAccounts',
            'houseowner',
            'payouts' => function ($payoutQuery) {
                $payoutQuery->with('bankAccount')->latest();
            },
        ])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Salary payout history retrieved successfully',
            'data' => $salary,
        ]);
    }

    public function initiateRazorpayXPayout(Request $request, $id, RazorpayXService $razorpayXService)
    {
        $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'mode' => 'nullable|string|in:bank_transfer,neft,imps,rtgs,upi',
            'purpose' => 'nullable|string|max:50',
            'narration' => 'nullable|string|max:255',
            'queue_if_low_balance' => 'nullable|boolean',
        ]);

        if (!$razorpayXService->isConfigured()) {
            return response()->json([
                'status' => false,
                'message' => 'RazorpayX credentials are not configured yet.',
            ], 422);
        }

        $salary = Salary::with(['staff.bankAccounts', 'houseowner'])->findOrFail($id);

        if (strtolower((string) $salary->status) !== 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Salary must be marked as paid before initiating payout.',
            ], 422);
        }

        if ((float) $salary->net_salary <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Salary amount must be greater than zero.',
            ], 422);
        }

        $bankAccount = null;
        $payout = null;

        try {
            $payout = DB::transaction(function () use ($request, $salary) {
                $lockedSalary = Salary::whereKey($salary->id)->lockForUpdate()->firstOrFail();

                if ($this->hasActivePayout($lockedSalary)) {
                    throw new \RuntimeException('A payout is already in progress or already completed for this salary.');
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
                    throw new \RuntimeException('No bank account found for this staff member. Please add or set a bank account first.');
                }

                return SalaryPayout::create([
                    'salary_id' => $lockedSalary->id,
                    'staff_id' => $lockedSalary->staff_id,
                    'houseowner_id' => $lockedSalary->houseowner_id,
                    'bank_account_id' => $selectedBankAccount->id,
                    'requested_by' => Auth::guard('api')->id(),
                    'amount' => $lockedSalary->net_salary,
                    'currency' => 'INR',
                    'mode' => strtolower((string) $request->input('mode', 'bank_transfer')),
                    'purpose' => $request->input('purpose', 'salary'),
                    'status' => 'initiated',
                    'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                    'narration' => $request->input('narration', 'Salary payout'),
                    'queue_if_low_balance' => (bool) $request->boolean('queue_if_low_balance', true),
                    'request_payload' => null,
                    'response_payload' => null,
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

        $contactResult = null;
        $fundAccountResult = null;
        $payoutResult = null;
        $contactId = null;
        $fundAccountId = null;

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
                    'purpose' => $request->input('purpose', 'salary'),
                    'narration' => $request->input('narration', 'Salary payout'),
                    'queue_if_low_balance' => (bool) $request->boolean('queue_if_low_balance', true),
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
                'message' => 'RazorpayX payout initiated successfully.',
                'data' => [
                    'salary' => $salary->fresh(['staff.bankAccounts', 'houseowner']),
                    'payout' => $payout->fresh(['bankAccount']),
                    'razorpayx' => [
                        'contact' => $contactResult['data'] ?? ['id' => $contactId],
                        'fund_account' => $fundAccountResult['data'] ?? ['id' => $fundAccountId],
                        'payout' => $payload,
                    ],
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
                'data' => [
                    'salary' => $salary->fresh(['staff.bankAccounts', 'houseowner']),
                    'payout' => $payout?->fresh(['bankAccount']),
                ],
            ], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'payment_receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120'
        ]);

        if(!in_array($request->status, ['paid', 'unpaid', 'pending'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid status'
            ], 400);
        }
        
        $salary = Salary::findOrFail($id);
        $oldStatus = $salary->status;
        $salary->status = $request->status;
        
        if ($request->hasFile('payment_receipt')) {
            $file = $request->file('payment_receipt');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/receipts'), $filename);
            $salary->payment_receipt = 'uploads/receipts/' . $filename;
        }

        $salary->save();
        
        // Send notification to staff when salary is marked as paid
        if ($request->status === 'paid' && $oldStatus !== 'paid') {
            $staff = User::find($salary->staff_id);
            $owner = User::find($salary->houseowner_id);
            if ($staff) {
                \App\Services\NotificationService::salaryPaid(
                    $staff->id,
                    number_format($salary->net_salary, 2),
                    $owner ? $owner->name : 'Admin'
                );
            }
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Salary updated successfully',
            'data' => $salary
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'houseowner_id' => 'required|exists:users,id',
            'basic_salary' => 'required|numeric',
            'performative_allowance' => 'nullable|numeric',
            'over_time_allowance' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'advance_payment' => 'nullable|numeric',
            'status' => 'required|in:pending,paid',
            'payment_mode' => 'nullable|string'
        ]);

        // ✅ Calculate Net Salary (Capped at 0)
        $totalEarnings = 
            $request->basic_salary
            + ($request->performative_allowance ?? 0)
            + ($request->over_time_allowance ?? 0);
            
        $totalDeductions = 
            ($request->tax ?? 0)
            + ($request->advance_payment ?? 0);
            
        $netSalary = $totalEarnings - $totalDeductions;
        
        if ($netSalary <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Net salary must be greater than zero. Please adjust earnings or deductions.'
            ], 422);
        }
        
        $netSalary = max(0, $netSalary); // Safeguard
        // ✅ Create Salary
        $salary = Salary::create([
            'staff_id' => $request->staff_id,
            'houseowner_id' => $request->houseowner_id,
            'basic_salary' => $request->basic_salary,
            'performative_allowance' => $request->performative_allowance ?? 0,
            'over_time_allowance' => $request->over_time_allowance ?? 0,
            'tax' => $request->tax ?? 0,
            'advance_payment' => $request->advance_payment ?? 0,
            'net_salary' => $netSalary,
            'payment_mode' => $request->payment_mode,
            'status' => $request->status,
            'payment_date' => now()->toDateString(),
        ]);

        // ✅ CRITICAL FIX: Update UserWorkInfo with new base salary to ensure consistency
        UserWorkInfo::updateOrCreate(
            ['user_id' => $request->staff_id],
            ['salary' => $request->basic_salary]
        );
        
        // Send notification to staff if salary is marked as paid
        if ($request->status === 'paid') {
            $staff = User::find($request->staff_id);
            $owner = User::find($request->houseowner_id);
            if ($staff) {
                \App\Services\NotificationService::salaryPaid(
                    $staff->id,
                    number_format($netSalary, 2),
                    $owner ? $owner->name : 'Admin'
                );
            }
        }
        
        // ✅ Auto-deduct from StaffAdvance table (installment / full logic)
        // Process oldest active advance first (FIFO)
        $employerId = Auth::guard('api')->id();
        $remainingToDeduct = (float)($request->advance_payment ?? 0);

        if ($remainingToDeduct > 0) {
            $activeAdvances = StaffAdvance::where('staff_id', $request->staff_id)
                ->where('employer_id', $employerId)
                ->where('status', 'active')
                ->orderBy('created_at', 'asc') // oldest first
                ->get();

            foreach ($activeAdvances as $advance) {
                if ($remainingToDeduct <= 0) break;

                // How much to deduct from this advance
                if ($advance->deduction_type === 'installment' && $advance->installment_amount > 0) {
                    $deductFromThis = min($remainingToDeduct, (float)$advance->installment_amount, (float)$advance->remaining_balance);
                } else {
                    $deductFromThis = min($remainingToDeduct, (float)$advance->remaining_balance);
                }
                $balanceAfter   = $advance->remaining_balance - $deductFromThis;

                // Record transaction
                AdvanceTransaction::create([
                    'advance_id'      => $advance->id,
                    'staff_id'        => $advance->staff_id,
                    'employer_id'     => $employerId,
                    'deducted_amount' => $deductFromThis,
                    'balance_after'   => $balanceAfter,
                    'salary_id'       => $salary->id,
                    'note'            => 'Salary deduction (' . ucfirst($advance->deduction_type) . ')',
                ]);

                // Update advance balance
                $advance->remaining_balance = $balanceAfter;
                if ($balanceAfter <= 0) {
                    $advance->status = 'closed';
                }
                $advance->save();

                $remainingToDeduct -= $deductFromThis;
            }

            // Also update legacy advance_withdraw_amount on users table
            $user = User::find($request->staff_id);
            if ($user) {
                $user->advance_withdraw_amount = max(0, $user->advance_withdraw_amount - ($request->advance_payment ?? 0));
                $user->advance_withdraw_added_by = $employerId;
                $user->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary created successfully',
            'data' => $salary
        ]);
    }

}
