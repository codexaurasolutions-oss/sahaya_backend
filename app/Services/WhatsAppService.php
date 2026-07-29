<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiKey;
    protected $customerId;
    protected $botKey;
    protected $apiUrl = 'https://api.engati.ai';

    public function __construct()
    {
        $this->apiKey = env('TELEBU_API_KEY', '');
        $this->customerId = env('TELEBU_CUSTOMER_ID', '');
        $this->botKey = env('TELEBU_BOT_KEY', '');
    }

    public function isConfigured()
    {
        return !empty($this->apiKey) && !empty($this->customerId) && !empty($this->botKey);
    }

    /**
     * Send a WhatsApp template message via Telebu/Engati API
     */
    public function sendTemplate($to, $templateName, $parameters = [], $languageCode = 'en')
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp (Telebu) credentials not configured');
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
            'language' => [
                'policy' => 'deterministic',
                'code' => $languageCode,
            ],
            'name' => $templateName,
            'components' => $components,
        ];

        $body = [
            'phonenumber' => '+' . $phone,
            'payload' => $payload,
        ];

        return $this->sendRequest($body);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEMPLATE HELPER METHODS - One per template
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
    // CORE API REQUEST
    // ═══════════════════════════════════════════════════════════════

    protected function sendRequest($body)
    {
        $url = "{$this->apiUrl}/whatsapp-api/v1.0/customer/{$this->customerId}/bot/{$this->botKey}/template";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("WhatsApp (Telebu) cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $status = $result['status'] ?? [];
            if (($status['code'] ?? 0) === 1000) {
                Log::info("WhatsApp sent to {$body['phonenumber']} via Telebu");
                return true;
            }
            Log::warning("WhatsApp (Telebu) API error: " . json_encode($status));
            return false;
        }

        Log::error("WhatsApp (Telebu) HTTP $httpCode: " . json_encode($result));
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
