<?php

namespace Tests;

use App\Models\User;
use App\Models\Role;
use Closure;
use Illuminate\Support\Facades\Auth;

trait TestAuth
{
    protected User $user;

    /**
     * Login User & Set Token (Sesuai gaya senior)
     */
    public function authenticate(User $user): void
    {
        $token = $user->createToken('token')->plainTextToken;
        $this->user = $user;

        Auth::forgetGuards();
        $this->flushHeaders();
        $this->withHeader('Authorization', "Bearer $token");
    }

    // --- Shortcut login sesuai role kamu ---

    public function actingAsSuperadmin(): void
    {
        $this->authenticate($this->createSuperadmin());
    }

    public function actingAsWarehouseAdmin(): void
    {
        $this->authenticate($this->createWarehouseAdmin());
    }

    public function actingAsStaff(): void
    {
        $this->authenticate($this->createStaff());
    }

    // --- Fungsi pembuat User (Factories) ---

    public function createSuperadmin(): User
    {
        $role = Role::firstOrCreate(['role_name' => 'Superadmin']);
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function createWarehouseAdmin(): User
    {
        $role = Role::firstOrCreate(['role_name' => 'Warehouse Admin']);
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function createStaff(): User
    {
        $role = Role::firstOrCreate(['role_name' => 'Staff Gudang']);
        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Fungsi sakti untuk testing Permission
     */
    public function assertUserPermission(Closure $do)
    {
        $authenticate = fn (User $user) => $this->authenticate($user);
        return new AssertAuthPermission($authenticate, $do);
    }
}
