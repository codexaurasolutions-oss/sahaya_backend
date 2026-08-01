<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Http\Controllers\Controller;

class NotificationService extends Controller
{
    protected static $whatsapp;

    /**
     * Get WhatsApp service instance
     */
    protected static function getWhatsApp()
    {
        if (!self::$whatsapp) {
            self::$whatsapp = new WhatsAppService();
        }
        return self::$whatsapp;
    }

    /**
     * Send notification (in-app + FCM push + optional WhatsApp + SMS)
     *
     * @param int    $userId   Recipient user ID
     * @param string $title    Notification title
     * @param string $message  Notification body
     * @param string $type     Notification type
     * @param array  $extra    Extra data:
     *   - job_id, application_id: stored in DB
     *   - skip_push: skip FCM push
     *   - skip_whatsapp: skip WhatsApp message
     *   - skip_sms: skip SMS
     *   - whatsapp_message: custom WhatsApp message (if different from title+message)
     *   - sms_message: custom SMS message
     * @return Notification|null
     */
    public static function send($userId, $title, $message, $type = 'general', $extra = [])
    {
        // 1. Create in-app notification record
        $notification = null;
        try {
            $notification = Notification::create([
                'user_id'         => $userId,
                'title'           => $title,
                'message'         => $message,
                'type'            => $type,
                'job_id'          => $extra['job_id'] ?? null,
                'application_id'  => $extra['application_id'] ?? null,
                'status'          => 'unread',
            ]);
        } catch (\Exception $e) {
            \Log::error('NotificationService DB create failed: ' . $e->getMessage());
        }

        // 2. Send FCM push notification
        if (empty($extra['skip_push'])) {
            self::sendFCM($userId, $message, $title, $type, $extra);
        }

        // 3. Send WhatsApp message
        if (empty($extra['skip_whatsapp'])) {
            self::sendWhatsApp($userId, $extra['whatsapp_message'] ?? $message);
        }

        // 4. Send SMS
        if (empty($extra['skip_sms'])) {
            self::sendSMSMessage($userId, $extra['sms_message'] ?? $message);
        }

        return $notification;
    }

    /**
     * Send notification to multiple users
     */
    public static function sendToMany($userIds, $title, $message, $type = 'general', $extra = [])
    {
        foreach ($userIds as $userId) {
            self::send($userId, $title, $message, $type, $extra);
        }
    }

    /**
     * Send broadcast notification to all users (or filtered by role)
     */
    public static function broadcast($title, $message, $type = 'broadcast', $roleId = null)
    {
        $query = User::select('id');
        if ($roleId) {
            $query->where('user_role_id', $roleId);
        }
        $userIds = $query->pluck('id')->toArray();
        self::sendToMany($userIds, $title, $message, $type);
    }

    /**
     * Send WhatsApp message to a user
     */
    protected static function sendWhatsApp($userId, $message)
    {
        try {
            $phone = User::where('id', $userId)->value('phone_number');
            if (!empty($phone)) {
                self::getWhatsApp()->sendTextMessage($phone, $message);
            }
        } catch (\Exception $e) {
            \Log::warning("WhatsApp failed for user $userId: " . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp template message to a user
     */
    protected static function sendWhatsAppTemplate($userId, $methodName, $params = [])
    {
        try {
            $phone = User::where('id', $userId)->value('phone_number');
            if (empty($phone)) return;

            $whatsapp = self::getWhatsApp();
            if (!$whatsapp->isConfigured()) return;

            $whatsapp->$methodName($phone, ...$params);
        } catch (\Exception $e) {
            \Log::warning("WhatsApp template failed for user $userId: " . $e->getMessage());
        }
    }

    /**
     * Send SMS to a user via SMSCountry API
     */
    protected static function sendSMSMessage($userId, $message)
    {
        try {
            $phone = User::where('id', $userId)->value('phone_number');
            if (empty($phone)) return;

            $authKey = config('services.smscountry.auth_key');
            $authToken = config('services.smscountry.auth_token');

            if (empty($authKey) || empty($authToken)) {
                Log::warning("SMS skipped — SMSCountry credentials not configured");
                return;
            }

            // Format phone: strip +, leading 0; ensure 91 prefix for 10-digit numbers
            $phone = ltrim(trim($phone), '0');
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) === 10) {
                $phone = '91' . $phone;
            }

            $url = "https://restapi.smscountry.com/v0.1/Accounts/{$authKey}/SMSes/";
            $auth = base64_encode($authKey . ':' . $authToken);

            $payload = json_encode([
                "Text" => $message,
                "Number" => $phone,
                "SenderId" => "SAHAYYA",
                "DRNotifyUrl" => "",
                "DRNotifyHttpMethod" => "POST",
                "Tool" => "API",
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $auth,
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_errno($curl) ? curl_error($curl) : null;
            curl_close($curl);

            Log::info('SMSCountry Transactional SMS', [
                'user_id' => $userId,
                'phone' => $phone,
                'http_code' => $httpCode,
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'error' => $error,
                'response' => json_decode($response, true) ?? $response,
            ]);
        } catch (\Exception $e) {
            Log::warning("SMS failed for user $userId: " . $e->getMessage());
        }
    }

    /**
     * Send FCM push notification to a user
     */
    protected static function sendFCM($userId, $message, $title, $type, $extra = [])
    {
        try {
            $tokenRecord = UserDeviceToken::where('user_id', $userId)->first();
            if ($tokenRecord) {
                $controller = new static();
                $controller->send_push_notification(
                    $tokenRecord->device_token,
                    $tokenRecord->device_type ?? 'android',
                    $message,
                    $title,
                    $type,
                    array_merge(
                        ['user_id' => (string) $userId],
                        isset($extra['job_id']) ? ['job_id' => (string) $extra['job_id']] : [],
                        isset($extra['application_id']) ? ['application_id' => (string) $extra['application_id']] : []
                    )
                );
            }
        } catch (\Exception $e) {
            \Log::warning("FCM push failed for user $userId: " . $e->getMessage());
        }
    }

    // =========================================================================
    // Convenience methods for specific events
    // =========================================================================

    /**
     * Staff Added - WhatsApp + Push to staff
     */
    public static function staffAdded($staffId, $ownerName)
    {
        $message = "Welcome to Sahayya! You have been added as staff by {$ownerName}.";
        try {
            self::send($staffId, 'Welcome to Sahayya!', $message, 'staff_added');
        } catch (\Throwable $e) {
            \Log::warning('staffAdded send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'staffAdded', [$ownerName]);
        } catch (\Throwable $e) {
            \Log::warning('staffAdded WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Salary Paid - WhatsApp + Push to staff, WhatsApp only to staff
     */
    public static function salaryPaid($staffId, $amount, $ownerName)
    {
        $message = "Your salary of ₹{$amount} has been paid by {$ownerName}.";
        try {
            self::send($staffId, 'Salary Paid', $message, 'salary_paid');
        } catch (\Throwable $e) {
            \Log::warning('salaryPaid send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'salaryPaid', [$amount, $ownerName]);
        } catch (\Throwable $e) {
            \Log::warning('salaryPaid WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Staff Terminated - WhatsApp + Push to staff, WhatsApp only to staff
     */
    public static function staffTerminated($staffId, $ownerName)
    {
        $message = "You have been terminated from your position by {$ownerName}.";
        try {
            self::send($staffId, 'Termination Notice', $message, 'termination');
        } catch (\Throwable $e) {
            \Log::warning('staffTerminated send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'staffTerminated', [$ownerName]);
        } catch (\Throwable $e) {
            \Log::warning('staffTerminated WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Leave Approved - WhatsApp + Push to staff
     */
    public static function leaveApproved($staffId, $ownerName)
    {
        $message = "Your leave request has been approved by {$ownerName}.";
        try {
            self::send($staffId, 'Leave Approved', $message, 'leave_approved');
        } catch (\Throwable $e) {
            \Log::warning('leaveApproved send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'leaveApproved', [$ownerName]);
        } catch (\Throwable $e) {
            \Log::warning('leaveApproved WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Leave Rejected - WhatsApp + Push to staff
     */
    public static function leaveRejected($staffId, $ownerName)
    {
        $message = "Your leave request has been rejected by {$ownerName}.";
        try {
            self::send($staffId, 'Leave Rejected', $message, 'leave_rejected');
        } catch (\Throwable $e) {
            \Log::warning('leaveRejected send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'leaveRejected', [$ownerName]);
        } catch (\Throwable $e) {
            \Log::warning('leaveRejected WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Job Applied - WhatsApp + Push to owner
     */
    public static function jobApplied($ownerId, $staffName, $jobTitle, $extra = [])
    {
        $message = "A new job application has been received from {$staffName} for \"{$jobTitle}\".";
        try {
            self::send($ownerId, 'New Job Application', $message, 'job_application', $extra);
        } catch (\Throwable $e) {
            \Log::warning('jobApplied send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($ownerId, 'jobApplied', [$staffName, $jobTitle]);
        } catch (\Throwable $e) {
            \Log::warning('jobApplied WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Leave Applied - WhatsApp + Push to owner
     */
    public static function leaveApplied($ownerId, $staffName, $dates, $extra = [])
    {
        $message = "{$staffName} has applied for leave on {$dates}.";
        try {
            self::send($ownerId, 'Leave Application', $message, 'leave_application', $extra);
        } catch (\Throwable $e) {
            \Log::warning('leaveApplied send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($ownerId, 'leaveApplied', [$staffName, $dates, $dates]);
        } catch (\Throwable $e) {
            \Log::warning('leaveApplied WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Job Application Accepted - WhatsApp + SMS + Push to staff
     */
    public static function jobAccepted($staffId, $jobTitle)
    {
        $message = "Congratulations! Your application for \"{$jobTitle}\" has been accepted.";
        try {
            self::send($staffId, 'Application Accepted', $message, 'job_application_accepted');
        } catch (\Throwable $e) {
            \Log::warning('jobAccepted send failed: ' . $e->getMessage());
        }
        try {
            self::sendWhatsAppTemplate($staffId, 'jobAccepted', [$jobTitle]);
        } catch (\Throwable $e) {
            \Log::warning('jobAccepted WhatsApp failed: ' . $e->getMessage());
        }
    }
}
