<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Http\Controllers\Controller;

class NotificationService extends Controller
{
    protected static $whatsapp;
    protected static $sms;

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
     * Get SMS service instance
     */
    protected static function getSMS()
    {
        if (!self::$sms) {
            self::$sms = new SMSService();
        }
        return self::$sms;
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
     * Send SMS to a user
     */
    protected static function sendSMSMessage($userId, $message)
    {
        try {
            $phone = User::where('id', $userId)->value('phone_number');
            if (!empty($phone)) {
                self::getSMS()->sendTextMessage($phone, $message);
            }
        } catch (\Exception $e) {
            \Log::warning("SMS failed for user $userId: " . $e->getMessage());
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
        self::send($staffId, 'Welcome to Sahayya!', $message, 'staff_added');
        self::sendWhatsAppTemplate($staffId, 'staffAdded', [$ownerName]);
    }

    /**
     * Salary Paid - WhatsApp + Push to staff, WhatsApp only to staff
     */
    public static function salaryPaid($staffId, $amount, $ownerName)
    {
        $message = "Your salary of ₹{$amount} has been paid by {$ownerName}.";
        self::send($staffId, 'Salary Paid', $message, 'salary_paid');
        self::sendWhatsAppTemplate($staffId, 'salaryPaid', [$amount, $ownerName]);
    }

    /**
     * Staff Terminated - WhatsApp + Push to staff, WhatsApp only to staff
     */
    public static function staffTerminated($staffId, $ownerName)
    {
        $message = "You have been terminated from your position by {$ownerName}.";
        self::send($staffId, 'Termination Notice', $message, 'termination');
        self::sendWhatsAppTemplate($staffId, 'staffTerminated', [$ownerName]);
    }

    /**
     * Leave Approved - WhatsApp + Push to staff
     */
    public static function leaveApproved($staffId, $ownerName)
    {
        $message = "Your leave request has been approved by {$ownerName}.";
        self::send($staffId, 'Leave Approved', $message, 'leave_approved');
        self::sendWhatsAppTemplate($staffId, 'leaveApproved', [$ownerName]);
    }

    /**
     * Leave Rejected - WhatsApp + Push to staff
     */
    public static function leaveRejected($staffId, $ownerName)
    {
        $message = "Your leave request has been rejected by {$ownerName}.";
        self::send($staffId, 'Leave Rejected', $message, 'leave_rejected');
        self::sendWhatsAppTemplate($staffId, 'leaveRejected', [$ownerName]);
    }

    /**
     * Job Applied - WhatsApp + Push to owner
     */
    public static function jobApplied($ownerId, $staffName, $jobTitle, $extra = [])
    {
        $message = "A new job application has been received from {$staffName} for \"{$jobTitle}\".";
        self::send($ownerId, 'New Job Application', $message, 'job_application', $extra);
        self::sendWhatsAppTemplate($ownerId, 'jobApplied', [$staffName, $jobTitle]);
    }

    /**
     * Leave Applied - WhatsApp + Push to owner
     */
    public static function leaveApplied($ownerId, $staffName, $dates, $extra = [])
    {
        $message = "{$staffName} has applied for leave on {$dates}.";
        self::send($ownerId, 'Leave Application', $message, 'leave_application', $extra);
        self::sendWhatsAppTemplate($ownerId, 'leaveApplied', [$staffName, $dates, $dates]);
    }
}
