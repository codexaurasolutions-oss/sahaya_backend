<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\StaffAdvance;
use App\Models\AdvanceTransaction;
use App\Models\User;
use App\Models\Notification;

class AdvanceController extends Controller
{
    /**
     * Employer: list all advances given to staff
     */
    public function index(Request $request)
    {
        $employer = Auth::guard('api')->user();
        $staffId  = $request->get('staff_id');
        $status   = $request->get('status'); // active / closed

        $query = StaffAdvance::with(['staff:id,first_name,last_name,name,image', 'transactions'])
            ->where('employer_id', $employer->id);

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $advances = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $advances,
            'summary' => [
                'total_given'     => $advances->sum('amount'),
                'total_remaining' => $advances->where('status', 'active')->sum('remaining_balance'),
                'active_count'    => $advances->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Employer: give a new advance to staff
     */
    public function store(Request $request)
    {
        $request->validate([
            'staff_id'           => 'required|exists:users,id',
            'amount'             => 'required|numeric|min:1',
            'deduction_type'     => 'required|in:full,installment,manual',
            'installment_amount' => 'nullable|numeric|min:1',
            'remarks'            => 'nullable|string|max:500',
            'given_date'         => 'nullable|date',
        ]);

        $employer = Auth::guard('api')->user();

        if ($request->deduction_type === 'installment' && empty($request->installment_amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Installment amount is required for installment deduction type.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $advance = StaffAdvance::create([
                'employer_id'        => $employer->id,
                'staff_id'           => $request->staff_id,
                'amount'             => $request->amount,
                'remaining_balance'  => $request->amount,
                'deduction_type'     => $request->deduction_type,
                'installment_amount' => $request->installment_amount ?? null,
                'status'             => 'active',
                'remarks'            => $request->remarks,
                'given_date'         => $request->given_date ?? now()->toDateString(),
            ]);

            // Notify staff (in-app + FCM push only)
            \App\Services\NotificationService::send(
                $request->staff_id,
                'Advance Received',
                'You have received an advance of ₹' . number_format($request->amount, 2) .
                 '. Deduction type: ' . ucfirst($request->deduction_type) . '.',
                'advance_received',
                ['skip_whatsapp' => true, 'skip_sms' => true]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advance given successfully.',
                'data'    => $advance->load('staff:id,first_name,last_name,name'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to give advance.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Single advance detail with transaction history
     */
    public function show($id)
    {
        $employer = Auth::guard('api')->user();

        $advance = StaffAdvance::with([
            'staff:id,first_name,last_name,name,image',
            'transactions',
        ])
        ->where('id', $id)
        ->where('employer_id', $employer->id)
        ->first();

        if (!$advance) {
            return response()->json(['success' => false, 'message' => 'Advance not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $advance]);
    }

    /**
     * Manual deduction by employer on an active advance
     */
    public function deduct(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'note'   => 'nullable|string|max:255',
        ]);

        $employer = Auth::guard('api')->user();

        $advance = StaffAdvance::where('id', $id)
            ->where('employer_id', $employer->id)
            ->where('status', 'active')
            ->first();

        if (!$advance) {
            return response()->json(['success' => false, 'message' => 'Active advance not found.'], 404);
        }

        $deductAmount = min((float)$request->amount, (float)$advance->remaining_balance);
        $balanceAfter = $advance->remaining_balance - $deductAmount;

        DB::beginTransaction();
        try {
            AdvanceTransaction::create([
                'advance_id'      => $advance->id,
                'staff_id'        => $advance->staff_id,
                'employer_id'     => $employer->id,
                'deducted_amount' => $deductAmount,
                'balance_after'   => $balanceAfter,
                'note'            => $request->note ?? 'Manual deduction',
            ]);

            $advance->remaining_balance = $balanceAfter;
            if ($balanceAfter <= 0) {
                $advance->status = 'closed';
            }
            $advance->save();

            // Notify staff (in-app + FCM push only)
            \App\Services\NotificationService::send(
                $advance->staff_id,
                'Advance Deduction',
                '₹' . number_format($deductAmount, 2) . ' deducted from your advance. Remaining: ₹' . number_format($balanceAfter, 2),
                'advance_deducted',
                ['skip_whatsapp' => true, 'skip_sms' => true]
            );

            DB::commit();

            return response()->json([
                'success'          => true,
                'message'          => 'Deduction recorded successfully.',
                'deducted_amount'  => $deductAmount,
                'remaining_balance'=> $balanceAfter,
                'advance_status'   => $advance->status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Deduction failed.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Staff: view their own advances & deduction history
     */
    public function staffAdvances()
    {
        $staff = Auth::guard('api')->user();

        $advances = StaffAdvance::with([
            'employer:id,first_name,last_name,name',
            'transactions',
        ])
        ->where('staff_id', $staff->id)
        ->orderBy('created_at', 'desc')
        ->get();

        $totalGiven     = $advances->sum('amount');
        $totalRemaining = $advances->where('status', 'active')->sum('remaining_balance');
        $totalRecovered = $totalGiven - $totalRemaining;

        // Calculate projected per-salary-cycle deduction (matches SalaryController logic)
        $projectedDeduction = $advances->where('status', 'active')->reduce(function ($carry, $advance) {
            if ($advance->deduction_type === 'full') {
                return $carry + (float) $advance->remaining_balance;
            } elseif ($advance->deduction_type === 'installment') {
                return $carry + min((float) $advance->installment_amount, (float) $advance->remaining_balance);
            }
            return $carry;
        }, 0);

        return response()->json([
            'success' => true,
            'data'    => $advances,
            'summary' => [
                'total_given'            => $totalGiven,
                'total_remaining'        => $totalRemaining,
                'total_recovered'        => $totalRecovered,
                'projected_deduction'    => $projectedDeduction,
            ],
        ]);
    }

    /**
     * Used by salary system: get pending advance deduction for a staff
     * Returns how much to deduct in this salary cycle (oldest active advance first)
     */
    public function getPendingDeduction($staff_id)
    {
        $employer = Auth::guard('api')->user();

        // Get oldest active advance first (FIFO)
        $advance = StaffAdvance::where('staff_id', $staff_id)
            ->where('employer_id', $employer->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$advance) {
            return response()->json(['success' => true, 'deduction_amount' => 0, 'advance' => null]);
        }

        $deductionAmount = 0;
        if ($advance->deduction_type === 'full') {
            $deductionAmount = $advance->remaining_balance;
        } elseif ($advance->deduction_type === 'installment') {
            $deductionAmount = min($advance->installment_amount, $advance->remaining_balance);
        } else {
            // manual - employer decides, return 0 here (they do it manually)
            $deductionAmount = 0;
        }

        return response()->json([
            'success'          => true,
            'advance_id'       => $advance->id,
            'deduction_amount' => $deductionAmount,
            'remaining_balance'=> $advance->remaining_balance,
            'deduction_type'   => $advance->deduction_type,
            'advance'          => $advance,
        ]);
    }
}
