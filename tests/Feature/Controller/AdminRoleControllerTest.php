<?php

namespace Tests\Feature\Controller;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AdminRoleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();
    }

    /**
     * A basic feature test example.
     */
    public function test_index_attributes(): void
    {
        $role = Role::factory()->create([
            'role_name' => 'Manager Gudang',
            'permissions' => ['Produk Masuk', 'Laporan Stok'],
        ]);

        $this->url(route('admin.role.index'));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data')
            ->has('data.3', fn (AssertableJson $json) => $json
                ->where('role_name', 'Manager Gudang')
                ->where('permissions', ['Produk Masuk', 'Laporan Stok'])
                ->etc()
            )
        );
    }

    /**
     * Add a new role with customizable access settings.
     */
    public function test_store(): void
    {
        $form = [
            'role_name' => 'Staff Admin Baru',
            'permissions' => ['Daftar Produk', 'Produk Keluar'],
        ];

        $this->url(route('admin.role.store'));
        $this->assertJsonPost($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->has('id')
                ->where('role_name', $form['role_name'])
                ->where('permissions', $form['permissions'])
                ->etc()
            )
        );

        $this->assertDatabaseHas('roles', [
            'role_name' => $form['role_name'],
        ]);
    }

    /**
     * Rename that role ang update access permissions
     */
    public function test_update(): void
    {
        $role = Role::factory()->create([
            'role_name' => 'Role Lama',
            'permissions' => ['Fitur A'],
        ]);

        $form = [
            'role_name' => 'Role Terupdate',
            'permissions' => ['Fitur B', 'Fitur C'],
        ];

        $this->url(route('admin.role.update', $role));
        $this->assertJsonPut($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->where('id', $role->id)
                ->where('role_name', $form['role_name'])
                ->where('permissions', $form['permissions'])
                ->etc()
            )
        );

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'role_name' => $form['role_name'],
        ]);
    }

    /**
     * Use Soft Delete.
     */
    public function test_destroy_soft_delete(): void
    {
        $role = Role::factory()->create();

        $this->url(route('admin.role.destroy', $role));

        // Response sukses hapus
        $this->json('DELETE', $this->url)->assertStatus(200);

        // Cek di DB: Masih ada datanya tapi kolom deleted_at tidak null
        $this->assertSoftDeleted('roles', [
            'id' => $role->id,
        ]);
    }
}
