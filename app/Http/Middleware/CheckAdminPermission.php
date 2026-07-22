<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (!$user->is_admin_panel_user) {
            return $next($request);
        }

        $permissions = is_array($user->admin_permissions) ? $user->admin_permissions : [];

        if (in_array($module, $permissions, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this module.',
        ], 403);
    }
}
