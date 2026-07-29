<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiKey;
    protected $customerId;
    protected $botKey;
    protected $apiUrl = 'https://waba-v2.360dialog.io';

    public function __construct()
    {
        $this->apiKey = env('TELEBU_API_KEY', '');
        $this->customerId = env('TELEBU_CUSTOMER_ID', '');
        $this->botKey = env('TELEBU_BOT_KEY', '');
    }

    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Send a WhatsApp template message via 360dialog (Telebu backend)
     */
    public function sendTemplate($to, $templateName, $parameters = [], $languageCode = 'en')
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp (Telebu/360dialog) credentials not configured');
            return false;
        }

        $phone = $this->formatPhone($to);
        if (!$phone) {
            Log::warning("WhatsApp: Invalid phone number: $to");
            return false;
        }

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function ($param) {
                    return ['type' => 'text', 'text' => (string) $param];
                }, $parameters),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'policy' => 'deterministic',
                    'code' => $languageCode,
                ],
                'components' => $components,
            ],
        ];

        return $this->sendRequest($payload);
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

    // ═══════════════════════════════════════════════════════════════
    // CORE API REQUEST - 360dialog
    // ═══════════════════════════════════════════════════════════════

    protected function sendRequest($payload)
    {
        $url = $this->apiUrl . '/v1/messages';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'D360-API-KEY: ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("WhatsApp (360dialog) cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            Log::info("WhatsApp sent to {$payload['to']} via 360dialog");
            return true;
        }

        Log::error("WhatsApp (360dialog) HTTP $httpCode: " . json_encode($result));
        return false;
    }

    protected function formatPhone($phone)
    {
        if (empty($phone)) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) < 10) return null;

        if (strlen($phone) === 11 && $phone[0] === '0') {
            $phone = substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }
}
