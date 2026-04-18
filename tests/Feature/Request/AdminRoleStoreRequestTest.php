<?php

namespace Tests\Feature\Request;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class AdminRoleStoreRequestTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->url(route('admin.role.store'), 'POST');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [];
        $this->assertJsonReqErrors($form, [
            'role_name' => __('validation.required'),
            'permissions' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when role_name is already exist.
     */
    public function test_role_name_must_be_unique(): void
    {
        Role::factory()->create(['role_name' => 'Manager Gudang']);

        $form = [
            'role_name' => 'Manager Gudang',
            'permissions' => ['Daftar Produk'],
        ];

        $this->assertJsonReqErrors($form, [
            'role_name' => __('validation.unique'),
        ]);
    }

    /**
     * Test error message when permissions is not an array.
     */
    public function test_permissions_must_be_array(): void
    {
        $form = [
            'role_name' => 'Admin Baru',
            'permissions' => 'bukan-array',
        ];

        $this->assertJsonReqErrors($form, [
            'permissions' => __('validation.array'),
        ]);
    }

    /**
     * Test security: Only superadmin can access this endpoint.
     */
    public function test_only_superadmin_can_access(): void
    {
        $User = User::factory()->create(['role_id' => 2]);

        $this->actingAs($User);

        $form = [
            'role_name' => 'User',
            'permissions' => ['*'],
        ];

        $response = $this->json('POST', route('admin.role.store'), $form);

        $response->assertStatus(403);
    }
}
