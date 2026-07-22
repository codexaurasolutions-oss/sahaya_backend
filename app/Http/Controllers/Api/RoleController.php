<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    // GET /api/roles
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data'    => RoleResource::collection(Role::all())
        ], 200);
    }

    // POST /api/roles
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name, '_')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data'    => new RoleResource($role)
        ], 201);
    }

    // GET /api/roles/{role}
    public function show(Role $role)
    {
        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data'    => new RoleResource($role)
        ], 200);
    }

    // PUT /api/roles/{role}
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id
        ]);

        $role->update([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data'    => new RoleResource($role)
        ], 200);
    }

    // DELETE /api/roles/{role}
    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ], 200);
    }
}
