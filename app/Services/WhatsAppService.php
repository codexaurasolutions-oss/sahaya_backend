<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $phoneNumberId;
    protected $accessToken;
    protected $apiUrl = 'https://graph.facebook.com/v18.0';

    public function __construct()
    {
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN', '');
    }

    /**
     * Send a free-form text message via WhatsApp
     */
    public function sendTextMessage($to, $message)
    {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::warning('WhatsApp credentials not configured');
            return false;
        }

        $to = $this->formatPhone($to);
        if (!$to) {
            Log::warning('WhatsApp: Invalid phone number');
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Send a template message via WhatsApp (for pre-approved templates)
     */
    public function sendTemplateMessage($to, $templateName, $languageCode = 'en', $parameters = [])
    {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::warning('WhatsApp credentials not configured');
            return false;
        }

        $to = $this->formatPhone($to);
        if (!$to) {
            Log::warning('WhatsApp: Invalid phone number');
            return false;
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        if (!empty($parameters)) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(function ($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters),
            ]];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Send request to WhatsApp API
     */
    protected function sendRequest($payload)
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
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
            Log::error("WhatsApp API cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            Log::info("WhatsApp message sent successfully to {$payload['to']}");
            return true;
        }

        Log::error("WhatsApp API error (HTTP $httpCode): " . json_encode($result));
        return false;
    }

    /**
     * Format phone number to E.164 (remove spaces, dashes, plus sign)
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
