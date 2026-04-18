<?php

namespace Tests\Feature\Request;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class AdminRoleUpdateRequestTest extends TestCase
{
    protected Role $role;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->role = Role::factory()->create([
            'role_name' => 'Manager Lama',
            'permissions' => ['Produk Masuk'],
        ]);

        $this->url(route('admin.role.update', $this->role), 'PUT');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [
            'role_name' => '',
            'permissions' => [],
        ];

        $this->assertJsonReqErrors($form, [
            'role_name' => __('validation.required'),
            'permissions' => __('validation.required'),
        ]);
    }

    /**
     * That message should not display an error message if the role name remains the same.
     */
    public function test_unique_ignore_current_id(): void
    {
        $form = [
            'role_name' => 'Manager Lama',
            'permissions' => ['Produk Masuk', 'Produk Keluar'],
        ];

        $response = $this->json('PUT', $this->url, $form);

        $response->assertStatus(200);
    }

    /**
     * An error message will appear if you use an existing role name.
     */
    public function test_role_name_must_be_unique(): void
    {
        // Buat role lain
        Role::factory()->create(['role_name' => 'Staff Gudang']);

        $form = [
            'role_name' => 'Staff Gudang',
            'permissions' => ['*'],
        ];

        $this->assertJsonReqErrors($form, [
            'role_name' => __('validation.unique'),
        ]);
    }

    /**
     * Only superadmins are allowed to access it.
     */
    public function test_only_superadmin_can_access(): void
    {
        $user = User::factory()->create(['role_id' => 2]);

        $this->actingAs($user);

        $response = $this->json('PUT', $this->url, []);

        $response->assertStatus(403);
    }
}
