<?php

namespace Tests\Feature\Permission;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class RolePermissionTest extends TestCase
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
     * Test access for list roles.
     */
    public function test_index(): void
    {
        $url = route('admin.role.index');
        $this->url($url);

        $this->assertUserPermission(fn () => $this->getJson($url))
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for create role.
     */
    public function test_store(): void
    {
        $url = route('admin.role.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $form = [
                'role_name' => 'Role Testing '.uniqid(),
                'permissions' => ['Daftar Produk'],
            ];

            return $this->postJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for update role.
     */
    public function test_update(): void
    {
        // Buat role target yang akan di-update
        $targetRole = Role::factory()->create(['role_name' => 'Target Update']);

        $url = route('admin.role.update', $targetRole);
        $this->url($url);

        $form = [
            'role_name' => 'Nama Role Baru',
            'permissions' => ['*'],
        ];

        $this->assertUserPermission(fn () => $this->putJson($url, $form))
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for delete role.
     */
    public function test_destroy(): void
    {
        $this->assertUserPermission(function () {
            $targetRole = Role::factory()->create(['role_name' => 'Target Delete']);

            return $this->deleteJson(route('admin.role.destroy', $targetRole));
        })
            ->allow($this->createSuperadmin())
            ->forbid($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }
}
