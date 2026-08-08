<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $d360ApiKey;
    protected $apiUrl = 'https://waba-v2.360dialog.io/messages';

    public function __construct()
    {
        $this->d360ApiKey = config('whatsapp.d360_api_key', env('D360_API_KEY', ''));
    }

    public function isConfigured()
    {
        return !empty($this->d360ApiKey);
    }

    /**
     * Send a WhatsApp template message via360dialog API
     */
    public function sendTemplate($to, $templateName, $parameters = [], $languageCode = 'en_US')
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp360dialog credentials not configured (D360_API_KEY missing)');
            return false;
        }

        $phone = $this->formatPhone($to);
        if (!$phone) {
            Log::warning("WhatsApp: Invalid phone number: $to");
            return false;
        }

        $components = [];
        if (!empty($parameters)) {
            $bodyParams = array_map(function ($param) {
                return ['type' => 'text', 'text' => (string) $param];
            }, $parameters);

            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                    'policy' => 'deterministic',
                ],
                'components' => $components,
            ],
        ];

        return $this->sendRequest($payload, $phone);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEMPLATE HELPER METHODS
    // ═══════════════════════════════════════════════════════════════

    public function staffAdded($phone, $ownerName)
    {
        return $this->sendTemplate($phone, 'staff_added', [$ownerName]);
    }

    public function jobApplied($phone, $staffName, $jobTitle)
    {
        return $this->sendTemplate($phone, 'job_applied', [$staffName, $jobTitle]);
    }

    public function jobAccepted($phone, $jobTitle)
    {
        return $this->sendTemplate($phone, 'job_accepted', [$jobTitle]);
    }

    public function jobRejected($phone, $jobTitle)
    {
        return $this->sendTemplate($phone, 'job_rejected', [$jobTitle]);
    }

    public function leaveApplied($phone, $staffName, $fromDate, $toDate)
    {
        return $this->sendTemplate($phone, 'leave_applied', [$staffName, $fromDate, $toDate]);
    }

    public function leaveApproved($phone, $approvedBy)
    {
        return $this->sendTemplate($phone, 'leave_approved', [$approvedBy]);
    }

    public function leaveRejected($phone, $rejectedBy)
    {
        return $this->sendTemplate($phone, 'leave_rejected', [$rejectedBy]);
    }

    public function salaryPaid($phone, $amount, $paidBy)
    {
        return $this->sendTemplate($phone, 'salary_paid', [$amount, $paidBy]);
    }

    public function staffTerminated($phone, $terminatedBy)
    {
        return $this->sendTemplate($phone, 'staff_terminated', [$terminatedBy]);
    }

    public function quitJobRequest($phone, $staffName, $jobTitle)
    {
        return $this->sendTemplate($phone, 'quit_job_request', [$staffName, $jobTitle]);
    }

    public function kycApproved($phone)
    {
        return $this->sendTemplate($phone, 'kyc_approved', []);
    }

    public function kycRejected($phone)
    {
        return $this->sendTemplate($phone, 'kyc_rejected', []);
    }

    public function salarySlip($phone, $month, $amount)
    {
        return $this->sendTemplate($phone, 'salary_slip', [$month, $amount]);
    }

    public function leaveMarked($phone, $date, $status)
    {
        return $this->sendTemplate($phone, 'leave_marked', [$date, $status]);
    }

    public function paymentInvoice($phone, $name, $invoiceNo, $date, $plan, $baseAmount, $gstAmount, $totalAmount, $paymentId)
    {
        return $this->sendTemplate($phone, 'payment_invoice', [
            $name,
            $invoiceNo,
            $date,
            $plan,
            number_format($baseAmount, 2),
            number_format($gstAmount, 2),
            number_format($totalAmount, 2),
            $paymentId,
        ]);
    }

    public function sendTextMessage($phone, $message)
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp360dialog credentials not configured');
            return false;
        }

        $formattedPhone = $this->formatPhone($phone);
        if (!$formattedPhone) {
            Log::warning("WhatsApp text: Invalid phone: $phone");
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedPhone,
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        $response = Http::withHeaders([
            'D360-API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($this->apiUrl, $payload);

        if ($response->successful()) {
            Log::info('WhatsApp text sent', ['phone' => $formattedPhone]);
            return true;
        }

        Log::warning('WhatsApp text failed', [
            'phone' => $formattedPhone,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return false;
    }

    public function sendOtp($phone, $otp)
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp360dialog credentials not configured');
            return false;
        }

        $formattedPhone = $this->formatPhone($phone);
        if (!$formattedPhone) {
            Log::warning("WhatsApp OTP: Invalid phone: $phone");
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedPhone,
            'type' => 'template',
            'template' => [
                'name' => 'otp_code',
                'language' => [
                    'code' => 'en_US',
                    'policy' => 'deterministic',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => (string) $otp],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'COPY_CODE',
                        'index' => 0,
                        'parameters' => [
                            ['type' => 'text', 'text' => (string) $otp],
                        ],
                    ],
                ],
            ],
        ];

        return $this->sendRequest($payload, $formattedPhone);
    }

    // ═══════════════════════════════════════════════════════════════
    // CORE API REQUEST -360dialog
    // ═══════════════════════════════════════════════════════════════

    protected function sendRequest($payload, $phone)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'D360-API-KEY: ' . $this->d360ApiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("WhatsApp360dialog cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($result['messages'][0]['id'])) {
                Log::info("WhatsApp sent to $phone via360dialog. Message ID: {$result['messages'][0]['id']}");
                return true;
            }
            Log::warning("WhatsApp360dialog unexpected response: " . json_encode($result));
            return false;
        }

        Log::error("WhatsApp360dialog HTTP $httpCode: " . json_encode($result));
        return false;
    }

    protected function formatPhone($phone)
    {
        if (empty($phone)) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) < 10) return null;

        // Remove leading zero (e.g., 03101234567 -> 3101234567)
        if (strlen($phone) === 11 && $phone[0] === '0') {
            $phone = substr($phone, 1);
        }

        // 10-digit number without country code
        if (strlen($phone) === 10) {
            // Pakistani numbers start with 3 (e.g., 301xxxxxxx, 310xxxxxxx)
            if ($phone[0] === '3') {
                $phone = '92' . $phone;
            } else {
                $phone = '91' . $phone;
            }
        }

        return $phone;
    }
}
