<?php

namespace Tests;

use App\Models\Role as RoleModel;
use App\Models\User;
use App\Utils\Permission\Role;
use Closure;
use Illuminate\Support\Facades\Auth;

trait TestAuth
{
    protected User $user;

    public function authenticate(User $user): void
    {
        $token = $user->createToken('token')->plainTextToken;
        $this->user = $user;

        Auth::forgetGuards();
        $this->flushHeaders();
        $this->withHeader('Authorization', "Bearer $token");
    }

    public function actingAsSuperadmin(): void
    {
        $this->authenticate($this->createSuperadmin());
    }

    public function createSuperadmin(): User
    {
        $roleId = Role::SUPERADMIN->value;

        RoleModel::firstOrCreate(
            ['id' => $roleId],
            ['role_name' => 'Superadmin']
        );

        return User::factory()->create(['role_id' => $roleId]);
    }

    public function createWarehouseAdmin(): User
    {
        $roleId = Role::WAREHOUSE_MANAGER->value;

        RoleModel::firstOrCreate(
            ['id' => $roleId],
            ['role_name' => 'Warehouse Manager']
        );

        return User::factory()->create(['role_id' => $roleId]);
    }

    public function createStaff(): User
    {
        $roleId = Role::STAFF_GUDANG->value;

        RoleModel::firstOrCreate(
            ['id' => $roleId],
            ['role_name' => 'Staff Gudang']
        );

        return User::factory()->create(['role_id' => $roleId]);
    }

    public function assertUserPermission(Closure $do)
    {
        $authenticate = fn (User $user) => $this->authenticate($user);

        return new AssertAuthPermission($authenticate, $do);
    }
}
