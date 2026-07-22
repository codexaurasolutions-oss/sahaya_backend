<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SMSService
{
    protected $apiUrl;
    protected $apiKey;
    protected $senderId;

    public function __construct()
    {
        $this->apiUrl = env('SMS_API_URL', '');
        $this->apiKey = env('SMS_API_KEY', '');
        $this->senderId = env('SMS_SENDER_ID', 'SAHAYYA');
    }

    /**
     * Send a plain text SMS
     */
    public function sendTextMessage($to, $message)
    {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            Log::warning('SMS credentials not configured');
            return false;
        }

        $to = $this->formatPhone($to);
        if (!$to) {
            Log::warning('SMS: Invalid phone number');
            return false;
        }

        $payload = [
            'to' => $to,
            'message' => $message,
            'sender' => $this->senderId,
        ];

        return $this->sendRequest($payload, $to);
    }

    /**
     * Send SMS via API
     */
    protected function sendRequest($payload, $to)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => !env('APP_DEBUG', true),
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("SMS API cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            Log::info("SMS sent successfully to $to");
            return true;
        }

        Log::error("SMS API error (HTTP $httpCode): " . json_encode($result));
        return false;
    }

    /**
     * Format phone number to E.164
     */
    protected function formatPhone($phone)
    {
        if (empty($phone)) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) < 10) return null;

        // Remove leading zero (e.g., 01234567890 -> 1234567890)
        if (strlen($phone) === 11 && $phone[0] === '0') {
            $phone = substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }
}
