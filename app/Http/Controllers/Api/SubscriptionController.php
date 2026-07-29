<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\Transaction;
use Carbon\Carbon;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;
use Illuminate\Http\JsonResponse;


class SubscriptionController extends Controller
{
    private function normalizeRoleId($roleId): ?int
    {
        $role = strtolower((string) $roleId);

        if (in_array($role, ['2', 'staff'], true)) {
            return 2;
        }

        if (in_array($role, ['3', 'house', 'householder', 'house_owner'], true)) {
            return 3;
        }

        return null;
    }

        public function index(Request $request)
    {
        $query = Subscription::query();
        if ($request->has('type') && !is_null($request->type)) {
            $query->where('type', $request->type);
        }

        if ($request->has('validity') && !is_null($request->validity)) {
            $query->where('validity', $request->validity);
        }
        $subscriptions = $query
            ->orderBy('role_id')
            ->orderBy('price')
            ->get();

        $user = Auth::guard('api')->user();
        $walletBalance = $user ? (float) ($user->wallet_balance ?? 0) : 0;

        foreach ($subscriptions as $sub) {
            $sub->original_price = $sub->price;
            if ($walletBalance > 0) {
                $sub->price = max(0, $sub->price - $walletBalance);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Subscriptions fetched successfully',
            'data' => $subscriptions
        ]);
    }

    // Store new subscription
    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_name' => 'required|string|max:150',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric',
            'validity'          => 'required',
            'type'              => 'required|in:monthly,yearly,quarterly',
            'razorpay_order_id' => 'nullable',
            'role_id'           => 'required|exists:roles,id',
            'extra'             => 'nullable',
            'subscription_limit' => 'required|numeric',
            'job_limit' => 'nullable|numeric',
            'staff_limit' => 'nullable|numeric',
            'extra_job_price' => 'nullable|numeric',
            'extra_staff_price' => 'nullable|numeric'
        ]);

        // Job posting limits/pricing are only applicable for House Owner plans (role_id = 3)
        if ((string)($data['role_id'] ?? '') !== '3') {
            $data['job_limit'] = 0;
            $data['staff_limit'] = 0;
            $data['extra_job_price'] = 0;
            $data['extra_staff_price'] = 0;
        } else {
            $data['job_limit'] = (float)($data['job_limit'] ?? 0);
            $data['staff_limit'] = (float)($data['staff_limit'] ?? 0);
            $data['extra_job_price'] = (float)($data['extra_job_price'] ?? 0);
            $data['extra_staff_price'] = (float)($data['extra_staff_price'] ?? 0);
        }

        $subscription = Subscription::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Subscription created successfully',
            'data' => $subscription
        ], 201);
    }

    // Update subscription
    public function update(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        $data = $request->validate([
            'subscription_name' => 'sometimes|string|max:150',
            'description'       => 'nullable|string',
            'price'             => 'sometimes|numeric',
            'validity'          => 'sometimes',
            'type'              => 'sometimes|in:monthly,yearly,quarterly',
            'razorpay_order_id' => 'nullable|string',
            'role_id'           => 'required|exists:roles,id',
            'extra'             => 'nullable|array',
            'subscription_limit' => 'required|numeric',
            'job_limit' => 'nullable|numeric',
            'staff_limit' => 'nullable|numeric',
            'extra_job_price' => 'nullable|numeric',
            'extra_staff_price' => 'nullable|numeric'
        ]);

        // Job posting limits/pricing are only applicable for House Owner plans (role_id = 3)
        if ((string)($data['role_id'] ?? $subscription->role_id ?? '') !== '3') {
            $data['job_limit'] = 0;
            $data['staff_limit'] = 0;
            $data['extra_job_price'] = 0;
            $data['extra_staff_price'] = 0;
        } else {
            if (array_key_exists('job_limit', $data)) {
                $data['job_limit'] = (float)($data['job_limit'] ?? 0);
            }
            if (array_key_exists('staff_limit', $data)) {
                $data['staff_limit'] = (float)($data['staff_limit'] ?? 0);
            }
            if (array_key_exists('extra_job_price', $data)) {
                $data['extra_job_price'] = (float)($data['extra_job_price'] ?? 0);
            }
            if (array_key_exists('extra_staff_price', $data)) {
                $data['extra_staff_price'] = (float)($data['extra_staff_price'] ?? 0);
            }
        }

        $subscription->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Subscription updated successfully',
            'data' => $subscription
        ]);
    }


    public function destroy($id)
    {
        $subscription = Subscription::find($id);
        if (!$subscription) {
            return response()->json([
                'status' => false,
                'message' => 'Subscription not found'
            ], 404);
        }
        $subscription->delete();
        return response()->json([
            'status' => true,
            'message' => 'Subscription deleted successfully'
        ]);
    }

     public function show($id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'status' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Subscription details fetched successfully',
            'data' => $subscription
        ]);
    }


        public function createSubscriptionOrder(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);
        $user = Auth::user();
        $subscription = Subscription::find($request->subscription_id);
        if(!$subscription){
            return response()->json([
                'status' => false,
                'message' => 'Subscription not found'
            ], 404);
        }
        // Check if user already has an active subscription
        $activeSubscription = SubscriptionUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();
        if ($activeSubscription) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an active subscription'
            ], 400);
        }

        try {
            $walletBalance = (float) ($user->wallet_balance ?? 0);
            $subscriptionPrice = (float) $subscription->price;
            $gst = \App\Services\GstService::calculate($subscriptionPrice);
            $baseAmount = $gst['base_amount'];
            $gstAmount = $gst['gst_amount'];
            $totalAmount = $gst['total_amount'];
            $payable_amount = max(0, $totalAmount - $walletBalance);
            $wallet_used = min($walletBalance, $totalAmount);

            if($payable_amount == 0){
                $subscriptionUser = SubscriptionUser::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'order_id' => 'SUB' . time() . $user->id,
                    'order_number' => 'SUB' . time() . $user->id,
                    'amount' => 0,
                    'base_amount' => $baseAmount,
                    'gst_amount' => $wallet_used >= $totalAmount ? $gstAmount : round($wallet_used * 18 / 100, 2),
                    'total_amount' => $totalAmount,
                    'wallet_used' => $wallet_used,
                    'currency' => 'INR',
                    'payment_status' => 'completed',
                    'role' => $user->user_role_id,
                    'type' => 'credit',
                    'start_date' => now(),
                    'end_date' => now()->addDays($subscription->validity),
                    'job_user_limit' => 0,
                    'staff_user_limit' => $subscription->staff_limit ?? 2,
                ]);
                
                if ($wallet_used > 0) {
                    $user->wallet_balance -= $wallet_used;
                    $user->save();
                }

                $data = $this->zeroPaymentData($subscriptionUser);
                return $data;
            } else {
                $api_key = config('services.razorpay.key');
                $api_secret = config('services.razorpay.secret');
                
                $razorpayData = [
                    "amount" => (int) ($payable_amount * 100),
                    "currency" => "INR",
                    "receipt" => "sub_" . uniqid(),
                    "payment_capture" => 1
                ];
                $api = new \Razorpay\Api\Api($api_key, $api_secret);
                $order = $api->order->create($razorpayData);
                $subscriptionUser = SubscriptionUser::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'order_id' => $order['id'],
                    'order_number' => 'SUB' . time() . $user->id,
                    'amount' => $payable_amount,
                    'base_amount' => $baseAmount,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                    'wallet_used' => $wallet_used,
                    'currency' => 'INR',
                    'payment_status' => 'pending',
                    'role' => $user->user_role_id,
                    'type' => 'credit',
                    'start_date' => now(),
                    'end_date' => now()->addDays($subscription->validity),
                    'job_user_limit' => 0,
                    'staff_user_limit' => $subscription->staff_limit ?? 2,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Order created successfully',
                    'order_id' => $order['id'],
                    'amount' => $payable_amount,
                    'base_amount' => $baseAmount,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                    'currency' => 'INR',
                    'subscription_user_id' => $subscriptionUser->id,
                    'razorpay_key' => $api_key,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify subscription payment
     */
    public function verifySubscriptionPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
            'subscription_user_id' => 'required',
        ]);
        $user = Auth::guard('api')->user();
        $subscriptionUser = SubscriptionUser::find($request->subscription_user_id);
        
                try {
            DB::beginTransaction();

            if ($subscriptionUser->payment_status === 'completed') {
                DB::rollBack();
                return response()->json([
                    'status' => true,
                    'message' => 'Payment already verified',
                    'data' => $subscriptionUser
                ]);
            }

            // Verify signature
            $generated_signature = hash_hmac(
                'sha256',
                $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
                config('services.razorpay.secret')
            );

            if ($generated_signature !== $request->razorpay_signature) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid payment signature'
                ], 400);
            }

            // Get subscription details
            $subscription = Subscription::find($subscriptionUser->subscription_id);

            // Calculate start and end dates
            $startDate = now();
            $endDate = now()->addDays($subscription->validity);

                        // Update subscription user record
            $subscriptionUser->update([
                'transaction_id' => $request->razorpay_payment_id,
                'payment_id' => $request->razorpay_payment_id,
                'payment_status' => 'completed',
                'payment_mode' => 'razorpay',
                'payment_response' => $request->all(),
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            // Deduct wallet balance if used
            if ($subscriptionUser->wallet_used > 0) {
                $user->wallet_balance = max(0, $user->wallet_balance - $subscriptionUser->wallet_used);
                $user->save();
            }

            // Update user role if needed
            if ($subscriptionUser->role !== 'user') {
                $user->update(['user_role_id' => $subscriptionUser->role]);
            }

            // Send notifications
            $this->sendSubscriptionNotifications($user, $subscriptionUser);

            // Send WhatsApp invoice
            $this->sendWhatsAppInvoice($user, $subscriptionUser, $subscription);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Payment verified successfully and subscription activated.',
                'subscription' => $subscriptionUser->load('subscription'),
                'valid_until' => $endDate->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function zeroPaymentData($request)
    {
        
        $user = Auth::guard('api')->user();
        $subscriptionUser = SubscriptionUser::find($request->id);
        
        try {
            DB::beginTransaction();
            // Get subscription details
            $subscription = Subscription::find($subscriptionUser->subscription_id);

            // Calculate start and end dates
            $startDate = now();
            $endDate = now()->addDays($subscription->validity);

                        // Update subscription user record
            $subscriptionUser->update([
                'transaction_id' => $request->razorpay_payment_id,
                'payment_id' => $request->razorpay_payment_id,
                'payment_status' => 'completed',
                'payment_mode' => 'razorpay',
                'payment_response' => $request->all(),
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            // Deduct wallet balance if used
            if ($subscriptionUser->wallet_used > 0) {
                $user->wallet_balance = max(0, $user->wallet_balance - $subscriptionUser->wallet_used);
                $user->save();
            }

            // Update user role if needed
            if ($subscriptionUser->role !== 'user') {
                $user->update(['user_role_id' => $subscriptionUser->role]);
            }
            
            // Send notifications
            $this->sendSubscriptionNotifications($user, $subscriptionUser);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Payment verified successfully and subscription activated.',
                'subscription' => $subscriptionUser->load('subscription'),
                'valid_until' => $endDate->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's current subscription
     */
    public function getCurrentSubscription()
    {
        $user = Auth::guard('api')->user();

        $activeSubscriptions = SubscriptionUser::with('subscription')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->latest()
            ->get();

        // Older accounts can contain both a free and a paid active row. Prefer
        // the paid entitlement instead of allowing a newer free row to hide it.
        $subscription = $activeSubscriptions->first(
            fn (SubscriptionUser $item) => $item->hasActivePaidAccess()
        ) ?? $activeSubscriptions->first();

        return response()->json([
            'status' => true,
            'subscription' => $subscription,
            'is_active' => $subscription ? true : false,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get user's subscription history
     */
    public function getSubscriptionHistory()
    {
        $user = Auth::user();

        if($user->user_role_id == 1) {
            $subscriptions = SubscriptionUser::with([
                'subscription',
                'user:id,first_name,last_name,name,email,user_role_id'
            ])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            $subscriptions = SubscriptionUser::with([
                'subscription',
                'user:id,first_name,last_name,name,email,user_role_id'
            ])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }
    
        return response()->json([
            'status' => true,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Get subscription user details
     */
    public function getSubscriptionUser($id)
    {
        $subscriptionUser = SubscriptionUser::with('subscription')
            ->where('id', $id)
            ->first();

        return response()->json([
            'status' => true,
            'data' => $subscriptionUser,
            'message' => 'Subscription user details fetched successfully'
        ]);
    }

    /**
     * Send subscription notifications
     */
    private function sendSubscriptionNotifications($user, $subscriptionUser)
    {
        // Send notification to user (in-app + FCM push only, no WhatsApp/SMS)
        \App\Services\NotificationService::send(
            $user->id,
            'Subscription Activated',
            'Your subscription #' . $subscriptionUser->order_number . ' has been activated successfully. Valid until ' . $subscriptionUser->end_date->format('d M, Y'),
            'subscription_activated',
            ['skip_whatsapp' => true, 'skip_sms' => true]
        );

        // Send notification to admin (in-app only, skip push/WhatsApp/SMS)
        \App\Services\NotificationService::send(
            1,
            'New Subscription',
            'User ' . $user->name . ' has purchased subscription #' . $subscriptionUser->order_number,
            'subscription_new',
            ['skip_push' => true, 'skip_whatsapp' => true, 'skip_sms' => true]
        );
    }

    private function sendWhatsAppInvoice($user, $subscriptionUser, $subscription)
    {
        try {
            $baseAmount = (float) ($subscriptionUser->base_amount ?? $subscription->price);
            $gstAmount = (float) ($subscriptionUser->gst_amount ?? 0);
            $totalAmount = (float) ($subscriptionUser->total_amount ?? $subscriptionUser->amount);

            $invoiceText = \App\Services\GstService::formatInvoiceText(
                $subscription->subscription_name ?? 'Subscription Plan',
                $baseAmount,
                $gstAmount,
                $totalAmount,
                $subscriptionUser->order_number,
                now()->format('d M Y, h:i A')
            );

            \App\Services\NotificationService::send($user->id, 'Payment Receipt', $invoiceText, 'payment_receipt');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("WhatsApp invoice failed", ['error' => $e->getMessage()]);
        }
    }

    public function subscriptionByRole(Request $request)
    {
        $roleId = $this->normalizeRoleId($request->input('role_id'));

        if (!$roleId || !Role::where('id', $roleId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => [
                    'role_id' => ['A valid role_id is required.']
                ]
            ], 400);
        }

        $subscriptions = Subscription::where('role_id', $roleId)
            ->orderBy('price')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'status' => true,
            'data' => $subscriptions,
            'message' => 'Subscriptions fetched successfully'
        ]);
    }

    /**
     * Subscribe user to a plan — handles both free and paid plans.
     * If paymentId is provided, plan is activated as a paid subscription.
     * If no paymentId, plan is activated as free.
     */
    public function subscribeFree(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated. Please log in again.',
            ], 401);
        }

        $subscriptionId = $request->input('subscriptionId') ?? $request->input('subscription_id');
        $paymentId      = $request->input('paymentId')      ?? $request->input('payment_id');
        $requestedRoleId = $this->normalizeRoleId($request->input('role_id'));

        if (!$subscriptionId) {
            return response()->json(['status' => false, 'message' => 'subscription_id is required'], 422);
        }

        $subscription = Subscription::find($subscriptionId);
        if (!$subscription) {
            return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($requestedRoleId && (int) $user->user_role_id !== $requestedRoleId) {
            $user->update(['user_role_id' => $requestedRoleId]);
            $user->refresh();
        }

        if ($subscription->role_id && (string) $subscription->role_id !== (string) $user->user_role_id) {
            return response()->json([
                'status' => false,
                'message' => 'Selected plan is not available for your role.',
            ], 422);
        }

        $isPaid    = !empty($paymentId);
        $startDate = now();
        $endDate   = now()->addDays($subscription->validity ?? 30);
        $amount    = $isPaid
            ? (float) ($request->input('amount') ?? $subscription->price ?? 0)
            : (float) ($subscription->price ?? 0);

        $result = DB::transaction(function () use ($user, $subscription, $paymentId, $isPaid, $startDate, $endDate, $amount) {
            $activeSubscriptions = SubscriptionUser::where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            $existingSamePlan = $activeSubscriptions->first(function ($activeSubscription) use ($subscription) {
                return (int) $activeSubscription->subscription_id === (int) $subscription->id
                    && $activeSubscription->end_date
                    && Carbon::parse($activeSubscription->end_date)->isFuture();
            });

            if ($existingSamePlan) {
                return [
                    'subscriptionUser' => $existingSamePlan->load('subscription'),
                    'message' => 'Subscription already active.',
                ];
            }

            $carriedOverExtraJobs = 0;
            $carriedOverExtraStaff = 0;
            foreach ($activeSubscriptions as $oldSubscription) {
                $carriedOverExtraJobs = max($carriedOverExtraJobs, (int) ($oldSubscription->extra_jobs ?? 0));
                $carriedOverExtraStaff = max($carriedOverExtraStaff, (int) ($oldSubscription->extra_staff ?? 0));
                $oldSubscription->update(['status' => 'cancelled']);
            }

            $subscriptionUser = SubscriptionUser::create([
                'user_id'          => $user->id,
                'subscription_id'  => $subscription->id,
                'role'             => $user->user_role_id,
                'order_id'         => $paymentId ? ('SUBPAY_' . $paymentId) : ('SUBFREE_' . now()->timestamp . $user->id),
                'order_number'     => 'SUB' . now()->timestamp . $user->id,
                'amount'           => $amount,
                'currency'         => 'INR',
                'payment_status'   => $isPaid ? 'paid'     : 'free',
                'payment_mode'     => $isPaid ? 'razorpay' : 'free',
                'payment_id'       => $paymentId ?? null,
                'type'             => 'credit',
                'status'           => 'active',
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'user_limit'       => 0,
                'job_user_limit'   => 0,
                'extra_jobs'       => $carriedOverExtraJobs,
                'staff_user_limit' => $subscription->staff_limit ?? 2,
                'extra_staff'      => $carriedOverExtraStaff,
            ]);

            return [
                'subscriptionUser' => $subscriptionUser->load('subscription'),
                'message' => $isPaid
                    ? "Subscribed to {$subscription->subscription_name} successfully!"
                    : 'Subscribed to free plan successfully.',
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => $result['message'],
            'data'    => $result['subscriptionUser'],
        ]);
    }

    /**
     * Create Razorpay order to purchase 1 extra job posting
     */
    public function createExtraJobOrder(Request $request)
    {
        $user = Auth::user();
        
        $activeSubscription = SubscriptionUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->latest()
            ->first();

        if (!$activeSubscription) {
            return response()->json([
                'status' => false,
                'message' => 'No active subscription found. Upgrade to Premium to purchase extra jobs.'
            ], 404);
        }

        $plan = Subscription::find($activeSubscription->subscription_id);
        if (!$plan) {
            return response()->json([
                'status' => false,
                'message' => 'Subscription plan not found.'
            ], 404);
        }

        $price = (float) ($plan->extra_job_price ?? 0);
        if ($price <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Extra job pricing is not configured. Please contact support.'
            ], 400);
        }

        try {
            $gst = \App\Services\GstService::calculate($price);
            $api_key = config('services.razorpay.key');
            $api_secret = config('services.razorpay.secret');
            
            $razorpayData = [
                "amount" => (int) ($gst['total_amount'] * 100),
                "currency" => "INR",
                "receipt" => "extra_job_" . uniqid(),
                "payment_capture" => 1
            ];
            $api = new Api($api_key, $api_secret);
            $order = $api->order->create($razorpayData);

            Transaction::create([
                'user_id'         => $user->id,
                'role'            => $user->user_role_id,
                'transaction_id'  => $order['id'],
                'type'            => 'debit',
                'order_id'        => $order['id'],
                'order_number'    => 'EXTJOB' . time() . $user->id,
                'reference_id'    => $activeSubscription->id,
                'amount'          => $price,
                'base_amount'     => $gst['base_amount'],
                'gst_amount'      => $gst['gst_amount'],
                'total_amount'    => $gst['total_amount'],
                'currency'        => 'INR',
                'payment_mode'    => 'razorpay',
                'payment_status'  => 'pending',
                'for_entry'       => 'extra_job_limit',
                'created_by'      => $user->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Extra job order created successfully',
                'order_id' => $order['id'],
                'amount' => $gst['total_amount'],
                'base_amount' => $gst['base_amount'],
                'gst_amount' => $gst['gst_amount'],
                'currency' => 'INR',
                'razorpay_key' => $api_key,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify Razorpay payment and increment extra_jobs limit
     */
    public function verifyExtraJobPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            // Verify signature
            $generated_signature = hash_hmac(
                'sha256',
                $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
                config('services.razorpay.secret')
            );

            if ($generated_signature !== $request->razorpay_signature) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid payment signature'
                ], 400);
            }

            $activeSubscription = SubscriptionUser::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('end_date', '>', now())
                ->latest()
                ->lockForUpdate()
                ->first();

            if (!$activeSubscription) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Active subscription not found'
                ], 404);
            }

            $existingTransaction = Transaction::where('transaction_id', $request->razorpay_payment_id)
                ->where('for_entry', 'extra_job_limit')
                ->first();

            if ($existingTransaction) {
                DB::rollBack();
                return response()->json([
                    'status' => true,
                    'message' => 'Payment already verified. Extra job post limit already added.'
                ]);
            }

            $activeSubscription->increment('extra_jobs');

            $plan = Subscription::find($activeSubscription->subscription_id);

            Transaction::create([
                'user_id' => $user->id,
                'role' => $user->user_role_id,
                'transaction_id' => $request->razorpay_payment_id,
                'type' => 'credit',
                'order_id' => $request->razorpay_order_id,
                'order_number' => 'EXTJOB' . time() . $user->id,
                'reference_id' => $activeSubscription->id,
                'amount' => $plan->extra_job_price ?? 0,
                'currency' => 'INR',
                'payment_mode' => 'razorpay',
                'payment_status' => 'completed',
                'payment_response' => json_encode($request->all()),
                'for_entry' => 'extra_job_limit',
                'created_by' => $user->id,
            ]);

            DB::commit();

            // Send notification to user (in-app + FCM push, no WhatsApp/SMS)
            \App\Services\NotificationService::send(
                $user->id,
                'Extra Job Posting Purchased',
                'You have successfully purchased 1 extra job posting limit.',
                'extra_job_purchased',
                ['skip_whatsapp' => true, 'skip_sms' => true]
            );

            return response()->json([
                'status' => true,
                'message' => 'Payment verified successfully. Extra job post added.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createExtraStaffOrder(Request $request)
    {
        $user = Auth::user();
        
        $activeSubscription = SubscriptionUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->latest()
            ->first();

        if (!$activeSubscription) {
            return response()->json([
                'status' => false,
                'message' => 'No active subscription found. Upgrade to Premium to purchase extra staff limit.'
            ], 404);
        }

        $plan = Subscription::find($activeSubscription->subscription_id);
        if (!$plan) {
            return response()->json([
                'status' => false,
                'message' => 'Subscription plan not found.'
            ], 404);
        }

        $price = $plan->extra_staff_price ?? 500;
        if ($price <= 0) {
            $activeSubscription->increment('extra_staff');
            return response()->json([
                'status' => true,
                'free' => true,
                'message' => 'Extra staff limit added for free.',
            ]);
        }

        try {
            $gst = \App\Services\GstService::calculate((float) $price);
            $api_key = config('services.razorpay.key');
            $api_secret = config('services.razorpay.secret');
            
            $razorpayData = [
                "amount" => (int) ($gst['total_amount'] * 100),
                "currency" => "INR",
                "receipt" => "extra_staff_" . uniqid(),
                "payment_capture" => 1
            ];
            $api = new Api($api_key, $api_secret);
            $order = $api->order->create($razorpayData);

            return response()->json([
                'status' => true,
                'message' => 'Extra staff order created successfully',
                'order_id' => $order['id'],
                'amount' => $gst['total_amount'],
                'base_amount' => $gst['base_amount'],
                'gst_amount' => $gst['gst_amount'],
                'currency' => 'INR',
                'razorpay_key' => $api_key,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyExtraStaffPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
        ]);

        $user = Auth::user();

        try {
            $generated_signature = hash_hmac(
                'sha256',
                $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
                config('services.razorpay.secret')
            );

            if ($generated_signature !== $request->razorpay_signature) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid payment signature'
                ], 400);
            }

            $activeSubscription = SubscriptionUser::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('end_date', '>', now())
                ->latest()
                ->first();

            if (!$activeSubscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active subscription not found'
                ], 404);
            }

            $existingTransaction = Transaction::where('transaction_id', $request->razorpay_payment_id)
                ->where('for_entry', 'extra_staff_limit')
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'status' => true,
                    'message' => 'Payment already verified. Extra staff limit already added.'
                ]);
            }

            $activeSubscription->increment('extra_staff');

            $plan = Subscription::find($activeSubscription->subscription_id);

            Transaction::create([
                'user_id' => $user->id,
                'role' => $user->user_role_id,
                'transaction_id' => $request->razorpay_payment_id,
                'type' => 'credit',
                'order_id' => $request->razorpay_order_id,
                'order_number' => 'EXTSTAFF' . time() . $user->id,
                'reference_id' => $activeSubscription->id,
                'amount' => $plan->extra_staff_price ?? 0,
                'currency' => 'INR',
                'payment_mode' => 'razorpay',
                'payment_status' => 'completed',
                'payment_response' => json_encode($request->all()),
                'for_entry' => 'extra_staff_limit',
                'created_by' => $user->id,
            ]);

            \App\Services\NotificationService::send(
                $user->id,
                'Extra Staff Limit Purchased',
                'You have successfully purchased 1 extra staff limit.',
                'extra_staff_purchased',
                ['skip_whatsapp' => true, 'skip_sms' => true]
            );

            return response()->json([
                'status' => true,
                'message' => 'Payment verified successfully. Extra staff limit added.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function refundSubscription($id)
    {
        try {
            $subscriptionUser = \App\Models\SubscriptionUser::find($id);

            if (!$subscriptionUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription record not found.'
                ], 404);
            }

            if ($subscriptionUser->payment_status === 'refunded') {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription is already refunded.'
                ], 400);
            }

                        // Refund wallet balance if it was used
            if ($subscriptionUser->wallet_used > 0) {
                $user = \App\Models\User::find($subscriptionUser->user_id);
                if ($user) {
                    $user->wallet_balance += $subscriptionUser->wallet_used;
                    $user->save();
                }
            }

            // Mark as refunded
            $subscriptionUser->payment_status = 'refunded';
            $subscriptionUser->status = 'inactive';
            $subscriptionUser->save();

            return response()->json([
                'status' => true,
                'message' => 'Subscription refunded successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ], 500);
        }
    }
}





