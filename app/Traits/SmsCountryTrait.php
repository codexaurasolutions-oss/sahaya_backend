<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait SmsCountryTrait
{
    public function sendSms($number, $otp, $retryCount = 0)
    {
        $twilioSid    = config('services.twilio.account_sid');
        $twilioToken  = config('services.twilio.auth_token');
        $twilioFrom   = config('services.twilio.from_number');

        if ($twilioSid && $twilioToken && $twilioFrom) {
            return $this->sendViaTwilio($number, $otp, $retryCount);
        }

        Log::warning('Twilio not configured, falling back to SMSCountry', ['number' => $number]);
        return $this->sendViaSmsCountry($number, $otp, $retryCount);
    }

    private function sendViaTwilio($number, $otp, $retryCount = 0)
    {
        $sid   = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from  = config('services.twilio.from_number');
        $to    = $this->formatPhoneNumber($number);
        $url   = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->timeout(8)
                ->asForm()
                ->post($url, [
                    'From' => $from,
                    'To'   => $to,
                    'Body' => "Your Sahayya verification code is {$otp}. Valid for 30 minutes. Do not share this code.",
                ]);

            $data = $response->json();
            $success = $response->successful();

            Log::info('Twilio SMS Response', [
                'number'   => $to,
                'status'   => $response->status(),
                'success'  => $success,
                'sid'      => $data['sid'] ?? null,
                'error'    => $data['message'] ?? null,
                'retry'    => $retryCount,
            ]);

            if (!$success && $retryCount < 1) {
                sleep(1);
                return $this->sendViaTwilio($number, $otp, $retryCount + 1);
            }

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Twilio Exception', [
                'number' => $number,
                'error'  => $e->getMessage(),
                'retry'  => $retryCount,
            ]);

            if ($retryCount < 1) {
                sleep(1);
                return $this->sendViaTwilio($number, $otp, $retryCount + 1);
            }

            return [
                'success' => false,
                'status'  => 500,
                'body'    => $e->getMessage(),
            ];
        }
    }

    private function sendViaSmsCountry($number, $otp, $retryCount = 0)
    {
        try {
            $url = "https://restapi.smscountry.com/v0.1/Accounts/"
                . config('services.smscountry.auth_key')
                . "/SMSes/";

            $auth = base64_encode(
                config('services.smscountry.auth_key') . ':' . config('services.smscountry.auth_token')
            );

            $payload = json_encode([
                "Text" => "Welcome to Sahayya! Your verification code is {$otp}. Valid for 30 minutes. Please do not share this code with anyone.",
                "Number" => (string) $number,
                "SenderId" => "SAHYYA",
                "DRNotifyUrl" => "https://www.domainname.com/notifyurl",
                "DRNotifyHttpMethod" => "POST",
                "Tool" => "API"
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $auth
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl)) {
                $error = curl_error($curl);
                curl_close($curl);

                Log::warning('SMSCountry cURL Error', [
                    'number' => $number,
                    'error' => $error,
                    'retry' => $retryCount,
                ]);

                if ($retryCount < 1) {
                    sleep(1);
                    return $this->sendViaSmsCountry($number, $otp, $retryCount + 1);
                }

                return [
                    'success' => false,
                    'status'  => 500,
                    'body'    => $error,
                ];
            }
            curl_close($curl);

            $success = ($httpCode >= 200 && $httpCode < 300);

            Log::info('SMSCountry Response', [
                'number' => $number,
                'http_code' => $httpCode,
                'success' => $success,
                'response' => json_decode($response, true) ?? $response,
                'retry' => $retryCount,
            ]);

            if (!$success && $retryCount < 1) {
                sleep(1);
                return $this->sendViaSmsCountry($number, $otp, $retryCount + 1);
            }

            return [
                'success' => $success,
                'status'  => $httpCode,
                'body'    => json_decode($response, true) ?? $response,
            ];

        } catch (\Exception $e) {
            Log::error('SMSCountry Exception', [
                'number' => $number,
                'error' => $e->getMessage(),
                'retry' => $retryCount,
            ]);

            return [
                'success' => false,
                'status'  => 500,
                'body'    => $e->getMessage(),
            ];
        }
    }

    private function formatPhoneNumber($number)
    {
        $number = ltrim($number, '0');
        if (strpos($number, '+') === 0) {
            return $number;
        }
        if (strlen($number) === 10) {
            return '+91' . $number;
        }
        return '+' . $number;
    }

    public function sendOtp($number, $otp)
    {
        $response = $this->sendSms($number, $otp);

        Log::info('sendOtp Result', [
            'number' => $number,
            'otp_sent' => $response['success'],
            'api_status' => $response['status'],
        ]);

        return [
            'success' => $response['success'],
            'otp'     => $otp,
            'api'     => $response
        ];
    }
}
