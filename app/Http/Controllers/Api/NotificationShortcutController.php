<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotificationShortcutController extends Controller
{
    public function getNotifications(Request $request)
    {
        $perPage = $request->input('per_page', 20);

        $notifications = Notification::with('user:id,name,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function sendNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:push,whatsapp,promotional',
            'audience' => 'required|string|in:all,home_owners,staff,paid_members'
        ]);

        // Map audience to user_role_id
        $roleIdMap = [
            'all' => null,
            'home_owners' => 3,
            'staff' => 2,
            'paid_members' => null,
        ];

        $roleId = $roleIdMap[$request->audience] ?? null;

        // Build user query
        $query = User::select('id');

        if ($roleId) {
            $query->where('user_role_id', $roleId);
        }

        if ($request->audience === 'paid_members') {
            $query->whereHas('subscriptionUsers', function ($q) {
                $q->where('status', 'active')
                  ->where('end_date', '>', now());
            });
        }

        $userIds = $query->pluck('id')->toArray();

        // Send notifications
        $sentCount = 0;
        foreach ($userIds as $userId) {
            try {
                NotificationService::send(
                    $userId,
                    $request->title,
                    $request->message,
                    'admin_broadcast',
                    ['skip_whatsapp' => true, 'skip_sms' => true]
                );
                $sentCount++;
            } catch (\Exception $e) {
                Log::warning("Admin broadcast notification failed for user $userId: " . $e->getMessage());
            }
        }

        Log::info("Admin sent {$request->type} notification to $sentCount users: " . $request->title);

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->type) . ' notification sent successfully to ' . $sentCount . ' users',
            'sent_count' => $sentCount
        ]);
    }
}
