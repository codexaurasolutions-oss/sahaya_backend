<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyVersion;
use App\Models\UserPolicyAcceptance;

class PolicyController extends Controller
{
    /**
     * Get current policy versions and user acceptance status
     * GET /api/policy/status
     */
    public function status(Request $request)
    {
        $user = Auth::guard('api')->user();
        $policies = PolicyVersion::current()->get();

        $result = $policies->map(function ($policy) use ($user) {
            $accepted = false;
            $acceptedAt = null;

            if ($user) {
                $acceptance = UserPolicyAcceptance::where('user_id', $user->id)
                    ->where('policy_type', $policy->type)
                    ->where('policy_version', $policy->version)
                    ->first();

                $accepted = (bool) $acceptance;
                $acceptedAt = $acceptance?->accepted_at?->toISOString();
            }

            return [
                'type' => $policy->type,
                'version' => $policy->version,
                'title' => $policy->title,
                'accepted' => $accepted,
                'accepted_at' => $acceptedAt,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Record user acceptance of a policy version
     * POST /api/policy/accept
     */
    public function accept(Request $request)
    {
        $user = Auth::guard('api')->user();

        $request->validate([
            'policy_type' => 'required|in:terms_and_conditions,privacy_policy,disclaimer',
        ]);

        $policyType = $request->input('policy_type');
        $currentPolicy = PolicyVersion::where('type', $policyType)->where('is_current', true)->first();

        if (!$currentPolicy) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found.',
            ], 404);
        }

        // Upsert acceptance (unique on user_id + policy_type)
        UserPolicyAcceptance::updateOrCreate(
            [
                'user_id' => $user->id,
                'policy_type' => $policyType,
            ],
            [
                'policy_version' => $currentPolicy->version,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Policy accepted successfully.',
            'data' => [
                'policy_type' => $policyType,
                'version' => $currentPolicy->version,
                'accepted_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Admin: Create new policy version (triggers re-acceptance for all users)
     * POST /api/admin/policy-versions
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:terms_and_conditions,privacy_policy,disclaimer',
            'version' => 'required|string|max:50',
            'title' => 'required|string|max:255',
        ]);

        // Mark old current version as not current
        PolicyVersion::where('type', $request->type)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        // Create new current version
        $policy = PolicyVersion::create([
            'type' => $request->type,
            'version' => $request->version,
            'title' => $request->title,
            'content_hash' => md5($request->type . $request->version . now()->timestamp),
            'is_current' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'New policy version created. All users will need to re-accept.',
            'data' => $policy,
        ]);
    }

    /**
     * Admin: List all policy versions
     * GET /api/admin/policy-versions
     */
    public function index()
    {
        $policies = PolicyVersion::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }
}
