<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Http;

class RazorpayService
{
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->apiKey = config('services.razorpay.key');
        $this->apiSecret = config('services.razorpay.secret');
    }

    /**
     * Create a Razorpay order
     *
     * @param float $amount Amount in INR
     * @param string $currency Currency code, default 'INR'
     * @param string|null $receipt Receipt identifier
     * @param int $payment_capture 1 = automatic capture, 0 = manual
     * @return array
     */
    public function createOrder(float $amount, string $currency = 'INR', ?string $receipt = null, int $payment_capture = 1): array
    {
        try {
            $data = [
                'amount' => (int) ($amount * 100), // convert to paise
                'currency' => $currency,
                'receipt' => $receipt ?? 'order_' . uniqid(),
                'payment_capture' => $payment_capture
            ];

            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->post('https://api.razorpay.com/v1/orders', $data);

            if ($response->failed()) {
                return [
                    'status' => false,
                    'message' => $response->body()
                ];
            }

            return [
                'status' => true,
                'data' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch a Razorpay order by ID
     */
    public function fetchOrder(string $orderId): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->get("https://api.razorpay.com/v1/orders/{$orderId}");

            if ($response->failed()) {
                return [
                    'status' => false,
                    'message' => $response->body()
                ];
            }

            return [
                'status' => true,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify payment signature
     */
    public function verifyPayment(array $attributes): bool
    {
        $expectedSignature = hash_hmac(
            'sha256',
            $attributes['razorpay_order_id'] . '|' . $attributes['razorpay_payment_id'],
            $this->apiSecret
        );

        return $expectedSignature === $attributes['razorpay_signature'];
    }
}
