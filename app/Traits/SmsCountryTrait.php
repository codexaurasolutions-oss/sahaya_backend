<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait SmsCountryTrait
{
    public function sendSms($number, $otp, $retryCount = 0)
    {
        return $this->sendViaSmsCountry($number, $otp, $retryCount);
    }

    private function sendViaSmsCountry($number, $otp, $retryCount = 0)
    {
        try {
            $authKey   = config('services.smscountry.auth_key');
            $authToken = config('services.smscountry.auth_token');
            $senderId  = config('services.smscountry.sender_id', 'SAHYYA');

            if (empty($authKey) || empty($authToken)) {
                Log::warning('SMSCountry credentials not configured', ['number' => $number]);
                return [
                    'success' => false,
                    'status'  => 500,
                    'body'    => 'SMSCountry credentials missing',
                ];
            }

            $url = "https://restapi.smscountry.com/v0.1/Accounts/{$authKey}/SMSes/";

            $auth = base64_encode($authKey . ':' . $authToken);

            $payload = json_encode([
                "Text" => "Welcome to Sahayya! Your verification code is {$otp}. Valid for 5 minutes. Please do not share this code with anyone.",
                "Number" => (string) $number,
                "SenderId" => $senderId,
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
                'error'  => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => 500,
                'body'    => $e->getMessage(),
            ];
        }
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
