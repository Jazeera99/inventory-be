<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminRoleStoreRequest;
use App\Http\Requests\AdminRoleUpdateRequest;
use App\Http\Resources\AdminRoleResource;
use App\Models\Role;

class AdminRoleController extends Controller
{
    public function index()
    {
        $this->authorize('Hak Akses');

        return AdminRoleResource::collection(Role::all());
    }

    public function store(AdminRoleStoreRequest $request)
    {
        $this->authorize('Hak Akses');

        $role = Role::create($request->validated());

        return new AdminRoleResource($role);
    }

    public function update(AdminRoleUpdateRequest $request, Role $role)
    {
        $this->authorize('Hak Akses');

        if (strtolower($role->role_name) === 'superadmin') {
        return response()->json([
            'message' => 'Role Superadmin sistem tidak dapat diubah!',
        ], 403);
    }

        $role->update($request->validated());

        return new AdminRoleResource($role);
    }

    public function destroy(Role $role)
    {
        $this->authorize('Hak Akses');

        if (strtolower($role->role_name) === 'superadmin') {
            return response()->json([
                'message' => 'Role Superadmin bawaan sistem tidak dapat dihapus!',
            ], 403);
        }

        $role->delete($role->id);

        return response()->json(['message' => 'Role dihapus']);
    }
}
