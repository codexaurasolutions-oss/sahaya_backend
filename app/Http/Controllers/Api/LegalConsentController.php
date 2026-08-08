<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalConsent;
use App\Models\User;
use Illuminate\Http\Request;

class LegalConsentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:privacy_policy,disclaimer,terms_and_conditions',
            'consent_data' => 'nullable|array',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $consent = LegalConsent::create([
            'user_id' => $request->user()?->id,
            'phone_number' => $request->phone_number,
            'type' => $request->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'consent_data' => $request->consent_data,
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consent recorded successfully.',
            'data' => $consent,
        ]);
    }

    public function storeBulk(Request $request)
    {
        $request->validate([
            'consents' => 'required|array',
            'consents.*.type' => 'required|in:privacy_policy,disclaimer,terms_and_conditions',
            'consents.*.consent_data' => 'nullable|array',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $records = [];
        foreach ($request->consents as $item) {
            $records[] = LegalConsent::create([
                'user_id' => $request->user()?->id,
                'phone_number' => $request->phone_number,
                'type' => $item['type'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'consent_data' => $item['consent_data'] ?? null,
                'accepted_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'All consents recorded successfully.',
            'data' => $records,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = LegalConsent::with('user:id,name,first_name,last_name,phone_number,email');

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->date_from) {
            $query->whereDate('accepted_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('accepted_at', '<=', $request->date_to);
        }

        $consents = $query->latest('accepted_at')->paginate($request->per_page ?? 50);

        // For records where user_id is null, try to find user by phone_number
        $phoneNumbers = $consents->getCollection()
            ->filter(fn($c) => is_null($c->user) && !empty($c->phone_number))
            ->pluck('phone_number')
            ->unique()
            ->values();

        $usersByPhone = [];
        if ($phoneNumbers->isNotEmpty()) {
            $users = User::whereIn('phone_number', $phoneNumbers)
                ->get(['id', 'name', 'first_name', 'last_name', 'phone_number', 'phone', 'email']);

            foreach ($users as $user) {
                $phone = $user->phone_number ?? $user->phone;
                if ($phone) {
                    $usersByPhone[$phone] = $user;
                }
            }
        }

        // Attach resolved user to consent records where user was null
        $consents->getCollection()->transform(function ($consent) use ($usersByPhone) {
            if (is_null($consent->user) && !empty($consent->phone_number)) {
                $resolved = $usersByPhone[$consent->phone_number] ?? null;
                if ($resolved) {
                    $consent->user = $resolved;
                }
            }
            return $consent;
        });

        return response()->json([
            'success' => true,
            'data' => $consents,
        ]);
    }
}
