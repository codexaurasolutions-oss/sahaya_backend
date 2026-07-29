<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\Analytics;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Booking;
class WalletController extends Controller
{
    /**
     * Display a listing of wallet transactions for the authenticated user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated. Please login.'
                ], 401);
            }

            $transactions = Wallet::where('user_id', $user->id)
                ->with('user:id,name,email')
                ->get();
$totalAmount = Wallet::where('user_id', $user->id)
    ->sum('amount');

            return response()->json([
                'status' => true,
                'message' => 'Wallet transactions retrieved successfully',
                'data' => [
                    'transactions' => $transactions,
                    'total_amount' => (float) $totalAmount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve wallet transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new wallet transaction for the authenticated user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:1'
    ]);

    $user = Auth::guard('api')->user();
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated. Please login.'
        ], 401);
    }

    $amount = $request->amount;
    $gst = \App\Services\GstService::calculate((float) $amount);
    $totalAmount = $gst['total_amount'];
    $data = [
        "amount" => $totalAmount * 100,
        "currency" => "INR",
        "receipt" => "wallet_recharge_" . uniqid(),
        "payment_capture" => 1
    ];
    $api_key = config('services.razorpay.key');
    $api_secret = config('services.razorpay.secret');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.razorpay.com/v1/orders",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$api_key:$api_secret",
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return response()->json(['status' => false, 'message' => $err], 500);
    }

    $order = json_decode($response, true);
    $wallet = Wallet::create([
        'user_id'        => $user->id,
        'amount'         => $amount,
        'base_amount'    => $gst['base_amount'],
        'gst_amount'     => $gst['gst_amount'],
        'total_amount'   => $totalAmount,
        'type'           => $request->type,
        'transaction_id' => $order['id'],   
        'payment_id'     => null,        
        'status'         => '0'
    ]);

    return response()->json([
        'status'       => true,
        'message'      => 'Order created successfully',
        'order_id'     => $order['id'],
        'razorpay_key' => $api_key,
        'amount'       => $amount,
        'base_amount'  => $gst['base_amount'],
        'gst_amount'   => $gst['gst_amount'],
        'total_amount' => $totalAmount,
        'transaction'  => $wallet,
        'user' => [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone_number,
        ]
    ], 201);
}




    public function addMoney(Request $request)
	{
		$request->validate([
			'amount' => 'required|numeric|min:1'
		]);

		$amount = $request->amount;
		$user = Auth::guard('api')->user();

		$data = [
			"amount" => $amount * 100, // in paise
			"currency" => "INR",
			"receipt" => "wallet_recharge_" . uniqid(),
			"payment_capture" => 1
		];

		$api_key = config('services.razorpay.key');
		$api_secret = config('services.razorpay.secret');

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => "https://api.razorpay.com/v1/orders",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => "$api_key:$api_secret",
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode($data),
		]);
		$response = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);

		if ($err) {
			return response()->json(['status' => 'error', 'msg' => $err], 500);
		}

		$order = json_decode($response, true);

		return response()->json([
			'status' => 'success',
			'order_id' => $order['id'],
			'razorpay_key' => $api_key,
			'amount' => $amount,
			'user_name' => $user->name,
			'email' => $user->email,
			'phone' => $user->phone_number,
		]);
		
	}

	public function verifyAndCreditWallet(Request $request)
	{
		$request->validate([
			'razorpay_payment_id' => 'required',
			'razorpay_order_id' => 'required',
			'razorpay_signature' => 'required',
			'amount' => 'required|numeric|min:1'
		]);

		$generated_signature = hash_hmac(
			'sha256',
			$request->razorpay_order_id . "|" . $request->razorpay_payment_id,
			config('services.razorpay.secret')
		);

		if ($generated_signature !== $request->razorpay_signature) {
			return response()->json(['status' => 'error', 'msg' => 'Invalid payment signature']);
		}
		$user = Auth::guard('api')->user();
		$user->wallet += $request->amount;
		$user->save();
		DB::table('notifications')->insert([
			'user_id' => $user->id,
			'title' =>'Wallet Recharge',
			'message' => 'Your wallet was recharged successfully. Amount: ₹' . number_format($request->amount, 2),
			'created_at' => now(),
			'updated_at' => now(),
		]);

		try {
			$gst = \App\Services\GstService::calculate((float) $request->amount);
			$invoiceText = \App\Services\GstService::formatInvoiceText(
				'Wallet Top-Up',
				$gst['base_amount'],
				$gst['gst_amount'],
				$gst['total_amount'],
				'WALLET-' . $request->razorpay_order_id,
				now()->format('d M Y, h:i A')
			);
			\App\Services\NotificationService::send($user->id, 'Wallet Top-Up', $invoiceText, 'wallet_topup');
		} catch (\Throwable $e) {
			\Illuminate\Support\Facades\Log::error("WhatsApp wallet invoice failed", ['error' => $e->getMessage()]);
		}

		return response()->json([
			'status' => 'success',
			'msg' => 'Wallet recharged successfully',
			'wallet_balance' => $user->wallet
		]);
	}
    /**
     * Get user's current balance.
     *
     * @param  int  $userId
     * @return float
     */
    private function getBalance($userId)
    {
        $result = Wallet::where('user_id', $userId)
            ->selectRaw('SUM(CASE WHEN type = "credit" THEN amount ELSE -amount END) as balance')
            ->first();
            
        return (float) ($result->balance ?? 0);
    }
    
    /**
     * Get wallet balance for the authenticated user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated. Please login.'
                ], 401);
            }

            $balance = $this->getBalance($user->id);

            return response()->json([
                'status' => true,
                'message' => 'Wallet balance retrieved successfully',
                'data' => [
                    'balance' => $balance
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve wallet balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAnalytics(Request $request)
{
    $userId = Auth::guard('api')->user()->id;
    $now = Carbon::now();
    $startOfMonth = $now->copy()->startOfMonth();
    $endOfMonth = $now->copy()->endOfMonth();
    $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
    $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
    $revenueThisMonth = Booking::where('vendor_id', $userId)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->whereIn('status', ['confirmed', 'completed'])
        ->sum('amount');
    $totalCustomers = Booking::where('vendor_id', $userId)
        ->distinct('customer_id')
        ->count('customer_id');
    $customersThisMonth = Booking::where('vendor_id', $userId)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->distinct('customer_id')
        ->count('customer_id');
    $customersLastMonth = Booking::where('vendor_id', $userId)
        ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
        ->distinct('customer_id')
        ->count('customer_id');
    if ($customersLastMonth > 0) {
        $reach = (($customersThisMonth - $customersLastMonth) / $customersLastMonth) * 100;
    } else {
        $reach = $customersThisMonth > 0 ? 100 : 0;
    }
    $totalBookingsThisMonth = Booking::where('vendor_id', $userId)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->count();
    $daysPassed = $now->day; 
    $footfallPerDay = $daysPassed > 0 ? round($totalBookingsThisMonth / $daysPassed) : 0;

    return response()->json([
        'status' => true,
        'data' => [
            'revenue_this_month' => $revenueThisMonth,
            'total_customers'    => $totalCustomers,
            'reach_vs_last_month'=> round($reach, 2) . '%',
            'estimated_footfall' => $footfallPerDay . ' / Day',
            'total_bookings'     => $totalBookingsThisMonth,
        ]
    ]);
}

}