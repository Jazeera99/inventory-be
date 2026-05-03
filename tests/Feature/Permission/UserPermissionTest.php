<?php

namespace Tests\Feature\Permission;

use App\Models\Role as RoleModel;
use App\Utils\Permission\Role;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Penting: Jalankan seeder agar role dasar ada di database
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test access for list user.
     */
    public function test_index(): void
    {
        $url = route('admin.user.index');
        $this->url($url);

        $this->assertUserPermission(fn () => $this->getJson($url))
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for create user.
     */
    public function test_store(): void
    {
        $roleId = Role::STAFF_GUDANG->value;

        // Pakai RoleModel (Model Database), bukan Role (Enum)
        $role = RoleModel::firstOrCreate(
            ['id' => $roleId],
            ['role_name' => 'Lay-Out Worker', 'permissions' => json_encode(['view_dashboard', 'manage_inventory'])]
        );

        $url = route('admin.user.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url, $role) {
            $form = [
                'full_name' => 'Staff Baru',
                'username' => 'staff'.uniqid(),
                'role_id' => $role->id,
                'password' => 'password123',
            ];

            return $this->postJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for update user.
     */
    public function test_update(): void
    {
        $targetUser = $this->createStaff();
        $url = route('admin.user.update', $targetUser);
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $form = [
                'full_name' => 'Update Nama',
                'username' => 'update.user.'.uniqid(),
                'role_id' => Role::STAFF_GUDANG->value,
            ];

            return $this->putJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test toggle status (Patch)
     */
    public function test_toggle_status(): void
    {
        $targetUser = $this->createStaff();

        $url = route('admin.user.toggle-status', $targetUser);
        $this->url($url);

        $this->assertUserPermission(fn () => $this->patchJson($url))
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }
}
