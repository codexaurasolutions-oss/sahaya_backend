<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplyLimitPurchase;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;

class JobApplyLimitController extends Controller
{
    /**
     * Get current staff's credit balance and apply eligibility
     */
    public function status(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::guard('api')->user();

        $creditsPerApplication = (int) (Setting::where('key', 'credits_per_job_application')->value('value') ?? 5);
        $walletBalance = (float) ($user->wallet_balance ?? 0);
        $applicationsPossible = $creditsPerApplication > 0 ? floor($walletBalance / $creditsPerApplication) : 0;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'wallet_balance'           => $walletBalance,
                'credits_per_application'  => $creditsPerApplication,
                'credit_purchase_price'    => (float) (Setting::where('key', 'credit_purchase_price')->value('value') ?? 10),
                'can_apply'                => $walletBalance >= $creditsPerApplication,
                'applications_possible'    => (int) $applicationsPossible,
            ],
        ]);
    }

    /**
     * Create Razorpay order for purchasing credits
     */
    public function createOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'credits_to_purchase' => 'required|integer|min:1|max:10000',
        ]);

        $user = Auth::guard('api')->user();
        $pricePerCredit = (float) (Setting::where('key', 'credit_purchase_price')->value('value') ?? 10);
        $creditsToPurchase = (int) $request->credits_to_purchase;
        $totalAmount = $pricePerCredit * $creditsToPurchase;

        try {
            $razorpayKey    = config('services.razorpay.key');
            $razorpaySecret = config('services.razorpay.secret');

            $api = new RazorpayApi($razorpayKey, $razorpaySecret);

            $orderData = [
                'receipt'  => 'credit_' . $user->id . '_' . time(),
                'amount'   => (int) round($totalAmount * 100),
                'currency' => 'INR',
                'notes'    => [
                    'user_id'  => $user->id,
                    'purpose'  => 'credit_purchase',
                    'credits'  => $creditsToPurchase,
                ],
            ];

            $razorpayOrder = $api->order->create($orderData);

            $purchase = JobApplyLimitPurchase::create([
                'user_id'             => $user->id,
                'razorpay_order_id'   => $razorpayOrder->id,
                'amount'              => $totalAmount,
                'extra_limit_granted' => $creditsToPurchase,
                'status'              => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'        => $razorpayOrder->id,
                    'amount'          => (int) round($totalAmount * 100),
                    'currency'        => 'INR',
                    'razorpay_key'    => $razorpayKey,
                    'purchase_id'     => $purchase->id,
                    'name'            => 'Sahayya',
                    'description'     => $creditsToPurchase . ' Credits',
                    'prefill_name'    => $user->first_name . ' ' . $user->last_name,
                    'prefill_email'   => $user->email ?? '',
                    'prefill_contact' => $user->phone_number ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Credit order creation failed: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create payment order. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify Razorpay payment and credit wallet
     */
    public function verifyPayment(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'credits_to_purchase' => 'required|integer|min:1',
        ]);

        $user = Auth::guard('api')->user();

        try {
            $razorpayKey    = config('services.razorpay.key');
            $razorpaySecret = config('services.razorpay.secret');

            $api = new RazorpayApi($razorpayKey, $razorpaySecret);

            $attributes = [
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // Atomic: lock purchase record + credit wallet in a transaction
            $creditsGranted = \DB::transaction(function () use ($request, $user) {
                $purchase = JobApplyLimitPurchase::where('razorpay_order_id', $request->razorpay_order_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$purchase) {
                    throw new \Exception('Purchase record not found or already processed.');
                }

                $purchase->update([
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature'  => $request->razorpay_signature,
                    'status'              => 'success',
                ]);

                $credits = (int) $purchase->extra_limit_granted;
                \DB::table('users')->where('id', $user->id)->increment('wallet_balance', $credits);

                return $credits;
            });

            $freshUser = $user->fresh();

            return response()->json([
                'status'  => 'success',
                'message' => "Payment verified! {$creditsGranted} credits added to your wallet.",
                'data'    => [
                    'wallet_balance'          => (float) $freshUser->wallet_balance,
                    'credits_added'           => $creditsGranted,
                    'credits_per_application' => (int) (Setting::where('key', 'credits_per_job_application')->value('value') ?? 5),
                ],
            ]);
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            JobApplyLimitPurchase::where('razorpay_order_id', $request->razorpay_order_id)
                ->where('user_id', $user->id)
                ->update(['status' => 'failed']);

            Log::error('Razorpay signature verification failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Payment verification failed. Please contact support.'], 400);
        } catch (\Exception $e) {
            Log::error('Credit verify payment error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage() === 'Purchase record not found or already processed.' ? $e->getMessage() : 'An error occurred during payment verification.'], 500);
        }
    }

    // =====================================================
    // ADMIN METHODS
    // =====================================================

    /**
     * Get admin settings for credit system
     */
    public function adminGetSettings(): \Illuminate\Http\JsonResponse
    {
        $keys = [
            'credits_per_job_application',
            'credits_per_staff_referral',
            'points_per_staff_referral',
            'staff_referral_points_per_credit',
            'credit_purchase_price',
        ];
        $settings = Setting::whereIn('key', $keys)->get()->keyBy('key');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'credits_per_job_application' => (float) ($settings->get('credits_per_job_application')?->value ?? 5),
                'credits_per_staff_referral'  => (float) ($settings->get('credits_per_staff_referral')?->value ?? 10),
                'points_per_staff_referral' => (float) ($settings->get('points_per_staff_referral')?->value ?? $settings->get('credits_per_staff_referral')?->value ?? 10),
                'staff_referral_points_per_credit' => (float) ($settings->get('staff_referral_points_per_credit')?->value ?? 10),
                'credit_purchase_price'       => (float) ($settings->get('credit_purchase_price')?->value ?? 10),
            ],
        ]);
    }

    /**
     * Update admin settings for credit system
     */
    public function adminUpdateSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'credits_per_job_application' => 'required|numeric|min:1',
            'credits_per_staff_referral'  => 'required|numeric|min:0',
            'points_per_staff_referral' => 'required|numeric|min:1',
            'staff_referral_points_per_credit' => 'required|numeric|min:1',
            'credit_purchase_price'       => 'required|numeric|min:1',
        ]);

        $fields = [
            'credits_per_job_application' => ['title' => 'Credits Per Job Application', 'desc' => 'Credits deducted per job application'],
            'credits_per_staff_referral'  => ['title' => 'Credits Per Staff Referral', 'desc' => 'Legacy direct-credit setting retained for compatibility'],
            'points_per_staff_referral' => ['title' => 'Points Per Staff Referral', 'desc' => 'Points awarded per successful staff referral'],
            'staff_referral_points_per_credit' => ['title' => 'Staff Referral Points Per Credit', 'desc' => 'Referral points required to redeem one job credit'],
            'credit_purchase_price'       => ['title' => 'Credit Purchase Price (INR)', 'desc' => 'Price in INR per 1 credit'],
        ];

        foreach ($fields as $key => $meta) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value'       => $request->input($key),
                    'title'       => $meta['title'],
                    'description' => $meta['desc'],
                    'updated_at'  => now(),
                ]
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Credit settings updated successfully.',
        ]);
    }

    /**
     * Get credit purchase statistics for admin
     */
    public function adminStats(): \Illuminate\Http\JsonResponse
    {
        $totalPurchases = JobApplyLimitPurchase::where('status', 'success')->count();
        $totalRevenue   = JobApplyLimitPurchase::where('status', 'success')->sum('amount');
        $totalCredits   = JobApplyLimitPurchase::where('status', 'success')->sum('extra_limit_granted');

        $recentPurchases = JobApplyLimitPurchase::with('user')
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($p) {
                return [
                    'id'                  => $p->id,
                    'user_name'           => $p->user ? ($p->user->first_name . ' ' . $p->user->last_name) : 'Unknown',
                    'user_phone'          => $p->user->phone_number ?? '',
                    'razorpay_payment_id' => $p->razorpay_payment_id,
                    'amount'              => $p->amount,
                    'credits_granted'     => $p->extra_limit_granted,
                    'created_at'          => $p->created_at,
                ];
            });

        $monthlyStats = JobApplyLimitPurchase::where('status', 'success')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as purchases, SUM(amount) as revenue, SUM(extra_limit_granted) as credits')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_purchases'   => $totalPurchases,
                'total_revenue'     => $totalRevenue,
                'total_credits'     => $totalCredits,
                'recent_purchases'  => $recentPurchases,
                'monthly_stats'     => $monthlyStats,
            ],
        ]);
    }

    /**
     * Get all staff with their credit info (for admin)
     */
    public function adminStaffLimits(Request $request): \Illuminate\Http\JsonResponse
    {
        $creditsPerApplication = (int) (Setting::where('key', 'credits_per_job_application')->value('value') ?? 5);

        $query = User::where('user_role_id', 2)
            ->select('id', 'first_name', 'last_name', 'phone_number', 'wallet_balance')
            ->orderBy('id', 'desc');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone_number', 'like', '%' . $request->search . '%');
            });
        }

        $staff = $query->paginate(20)->through(function ($s) use ($creditsPerApplication) {
            $walletBalance = (float) ($s->wallet_balance ?? 0);
            $applyCount    = \App\Models\JobApplication::where('user_id', $s->id)->count();
            return [
                'id'                       => $s->id,
                'name'                     => $s->first_name . ' ' . $s->last_name,
                'phone'                    => $s->phone_number,
                'wallet_balance'           => $walletBalance,
                'credits_per_application'  => $creditsPerApplication,
                'applications_possible'    => $creditsPerApplication > 0 ? floor($walletBalance / $creditsPerApplication) : 0,
                'apply_count'              => $applyCount,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $staff,
        ]);
    }
}
