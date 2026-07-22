<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SettingController extends Controller
{
    /**
     * Handle both GET and POST for notification settings
     */
 public function handleNotification(Request $request)
{
    if ($request->isMethod('post')) {
        $request->validate([
            'value' => 'required|in:0,1',
        ]);
        $setting = Setting::firstOrNew(['key' => 'notification_title']);
        $setting->value = $request->value;
        $setting->title = 'Notification Title';
        $setting->description = 'Control notification settings';
        $setting->save();
        return response()->json([
            'success' => true,
            'message' => 'Notification setting updated successfully',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
            ],
        ]);
    }
    $notificationSetting = Setting::where('key', 'notification_title')->first();
    return response()->json([
        'success' => true,
        'data' => $notificationSetting ?: [
            'key' => 'notification_title',
            'value' => '0', // default value
            'title' => 'Notification Title',
            'description' => 'Control notification settings',
        ],
    ]);
}
   public function handleAutoPresent(Request $request)
{
    $user = Auth::guard('api')->user();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not authenticated',
        ], 401);
    }
    
    if ($request->isMethod('post')) {
        $request->validate([
            'value' => 'required|in:0,1',
        ]);
        $user->auto_attendence = $request->value;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Auto-present setting updated successfully',
            'data' => [
                'user_id' => $user->id,
                'auto_attendence' => $user->auto_attendence,
            ],
        ]);
    }
    return response()->json([
        'success' => true,
        'data' => [
            'user_id' => $user->id,
            'auto_attendence' => $user->auto_attendence ?? '0', // default to 0 if null
        ],
    ]);
}
    /**
     * Get all settings (optional)
     */
    public function getAllSettings()
    {
        $settings = Setting::all();
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }


    public function store(Request $request)
    {
        $rules = [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|distinct',
            'settings.*.value' => 'nullable',
        ];

        $numericSettings = [
            'credits_per_job_application' => 'numeric|min:1',
            'points_per_staff_referral' => 'numeric|min:1',
            'staff_referral_points_per_credit' => 'numeric|min:1',
            'credit_purchase_price' => 'numeric|gt:0',
        ];

        foreach ($request->input('settings', []) as $index => $item) {
            $key = $item['key'] ?? null;
            if (isset($numericSettings[$key])) {
                $rules["settings.$index.value"] = 'required|' . $numericSettings[$key];
            }
        }

        $request->validate($rules);

        $data = DB::transaction(function () use ($request) {
            $savedSettings = [];

            foreach ($request->settings as $item) {
                $values = ['value' => $item['value'] ?? null];
                foreach (['title', 'description', 'input_type', 'editable', 'weight'] as $optionalField) {
                    if (array_key_exists($optionalField, $item)) {
                        $values[$optionalField] = $item[$optionalField];
                    }
                }

                $savedSettings[] = Setting::updateOrCreate(
                    ['key' => $item['key']],
                    $values
                );
            }

            return $savedSettings;
        });

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully',
            'data' => $data
        ]);
    }
}
