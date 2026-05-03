<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminRoleStoreRequest;
use App\Http\Requests\AdminRoleUpdateRequest;
use App\Http\Resources\AdminRoleResource;
use App\Models\Role;

class AdminRoleController extends Controller
{
    private function validateAdmin(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->role) {
            abort(403, 'Akses ditolak.');
        }

        $permissions = $user->role->permissions;

        // LOGIKA KUNCI: Jika ada '*', dia adalah dewa, izinkan semua!
        if (is_array($permissions) && in_array('*', $permissions)) {
            return;
        }

        // Jika bukan superadmin, cek apakah dia punya akses spesifik ke hak akses
        if (is_array($permissions) && in_array('Hak Akses', $permissions)) {
            return;
        }

        abort(403, 'Akses ditolak.');
    }

    public function index()
    {
        $this->validateAdmin();

        return AdminRoleResource::collection(Role::all());
    }

    public function store(AdminRoleStoreRequest $request)
    {
        $this->validateAdmin();

        $role = Role::create($request->validated());

        return new AdminRoleResource($role);
    }

    public function update(AdminRoleUpdateRequest $request, Role $role)
    {
        $this->validateAdmin();

        $role->update($request->validated());

        return new AdminRoleResource($role);
    }

    public function destroy(Role $role)
    {
        $this->validateAdmin();

        $role->delete();

        return response()->json(['message' => 'Role dihapus']);
    }
}
