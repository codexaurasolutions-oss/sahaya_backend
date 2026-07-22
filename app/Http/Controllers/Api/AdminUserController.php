<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private function resolveAdminRoleId($admin)
    {
        $adminRole = Role::query()
            ->where('slug', 'admin')
            ->orWhere('name', 'Admin')
            ->first();

        if ($adminRole?->id) {
            return (int) $adminRole->id;
        }

        if (!empty($admin->user_role_id)) {
            return (int) $admin->user_role_id;
        }

        return 3;
    }

    private function ensureAdmin()
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401));
        }

        if ($user->is_admin_panel_user && !in_array('sub_admins', is_array($user->admin_permissions) ? $user->admin_permissions : [], true)) {
            abort(response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage sub-admin users.',
            ], 403));
        }

        return $user;
    }

    private function normalizePermissions($permissions)
    {
        if (is_array($permissions)) {
            return array_values(array_unique(array_filter($permissions)));
        }

        if (is_string($permissions) && $permissions !== '') {
            $decoded = json_decode($permissions, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter($decoded)));
            }
        }

        return [];
    }

    public function index()
    {
        $admin = $this->ensureAdmin();

        $subAdmins = User::query()
            ->where('is_admin_panel_user', 1)
            ->where('admin_parent_id', $admin->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'is_active' => (bool) $user->is_active,
                    'permissions' => $this->normalizePermissions($user->admin_permissions),
                    'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin users retrieved successfully.',
            'data' => $subAdmins,
        ]);
    }

    public function store(Request $request)
    {
        $admin = $this->ensureAdmin();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subAdmin = User::create([
            'user_role_id' => $this->resolveAdminRoleId($admin),
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_active' => true,
            'is_deleted' => false,
            'step' => 1,
            'is_admin_panel_user' => true,
            'admin_parent_id' => $admin->id,
            'admin_permissions' => $this->normalizePermissions($request->permissions),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin created successfully.',
            'data' => [
                'id' => $subAdmin->id,
                'name' => $subAdmin->name,
                'email' => $subAdmin->email,
                'phone_number' => $subAdmin->phone_number,
                'is_active' => (bool) $subAdmin->is_active,
                'permissions' => $this->normalizePermissions($subAdmin->admin_permissions),
            ],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = $this->ensureAdmin();

        $subAdmin = User::query()
            ->where('is_admin_panel_user', 1)
            ->where('admin_parent_id', $admin->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($subAdmin->id)],
            'phone_number' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone_number' => $request->phone_number,
            'admin_permissions' => $this->normalizePermissions($request->permissions),
        ];

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->password);
        }

        if ($request->has('is_active')) {
            $payload['is_active'] = (bool) $request->is_active;
        }

        $subAdmin->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin updated successfully.',
            'data' => [
                'id' => $subAdmin->id,
                'name' => $subAdmin->name,
                'email' => $subAdmin->email,
                'phone_number' => $subAdmin->phone_number,
                'is_active' => (bool) $subAdmin->is_active,
                'permissions' => $this->normalizePermissions($subAdmin->admin_permissions),
            ],
        ]);
    }

    public function destroy($id)
    {
        $admin = $this->ensureAdmin();

        $subAdmin = User::query()
            ->where('is_admin_panel_user', 1)
            ->where('admin_parent_id', $admin->id)
            ->findOrFail($id);

        $subAdmin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin deleted successfully.',
        ]);
    }

    public function toggleStatus($id)
    {
        $admin = $this->ensureAdmin();

        $subAdmin = User::query()
            ->where('is_admin_panel_user', 1)
            ->where('admin_parent_id', $admin->id)
            ->findOrFail($id);

        $subAdmin->update([
            'is_active' => !$subAdmin->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => $subAdmin->is_active ? 'Sub-admin activated successfully.' : 'Sub-admin blocked successfully.',
            'data' => [
                'id' => $subAdmin->id,
                'is_active' => (bool) $subAdmin->is_active,
                'permissions' => $this->normalizePermissions($subAdmin->admin_permissions),
            ],
        ]);
    }
}
