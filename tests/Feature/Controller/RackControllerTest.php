<?php

namespace Tests\Feature\Controller;

use App\Models\Rack;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RackSeeder;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class RackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RackSeeder::class);

        $role = Role::where('role_name', 'SuperAdmin')->first();

        if (! $role) {
            $role = Role::factory()->create([
                'role_name' => 'SuperAdmin',
                'permissions' => ['Manajemen Rak'],
            ]);
        }

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);
    }

    /**
     * This test displays a list of shelves (Index).
     */
    public function test_can_list_racks(): void
    {
        $response = $this->getJson(route('admin.rack.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'location_code', 'rack_name', 'is_active', 'is_maintenance'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    /**
     * Test Bulk Generate.
     */
    public function test_can_bulk_generate_racks(): void
    {
        $initialCount = Rack::count();

        $form = [
            'rack_name' => 'GudangBaru',
            'total_column' => 2,
            'total_level' => 2,
        ];

        $response = $this->postJson(route('admin.rack.generate'), $form);

        $response->assertStatus(200);
        $this->assertEquals($initialCount + 4, Rack::count());
    }

    /**
     * Test Store use Factory.
     */
    public function test_can_store_rack(): void
    {
        // Gunakan make() untuk mendapatkan data dari factory tanpa menyimpannya ke DB dulu
        // Tambahkan suffix unik agar tidak bentrok dengan data Seeder
        $rackData = Rack::factory()->make([
            'location_code' => 'UNIQUE-'.uniqid(),
        ])->toArray();

        $response = $this->postJson(route('admin.rack.store'), $rackData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('racks', ['location_code' => $rackData['location_code']]);
    }

    /**
     * Test Toggle Maintenance use data from Seeder.
     */
    public function test_can_toggle_maintenance_status(): void
    {
        $rack = Rack::first();

        $response = $this->patchJson(route('admin.rack.toggle-maintenance', $rack));

        $response->assertStatus(200);

        $rack->refresh();

        $this->assertNotEquals($rack->is_active, true);
    }

    /**
     * Test error message when permission user is not this fitur access.
     */
    public function test_user_without_permission_cannot_access(): void
    {
        $roleTanpaIzin = Role::factory()->create(['permissions' => ['Hanya Lihat']]);
        $userTanpaIzin = User::factory()->create(['role_id' => $roleTanpaIzin->id]);

        $this->actingAs($userTanpaIzin);

        $response = $this->getJson(route('admin.rack.index'));
        $response->assertStatus(403);
    }

    // /**
    //  * Test Update.
    //  */
    // public function test_can_update_rack(): void
    // {
    //     $rack = Rack::first();
    //     $updatedName = 'Rak Diperbarui';

    //     $response = $this->putJson(route('admin.rack.update', $rack), [
    //         'location_code' => $rack->location_code . '-UPD',
    //         'rack_name' => $updatedName,
    //         'column_number' => $rack->column_number,
    //         'level_number' => $rack->level_number,
    //     ]);

    //     $response->assertStatus(200);
    //     $this->assertDatabaseHas('racks', [
    //         'id' => $rack->id,
    //         'rack_name' => $updatedName
    //     ]);
    // }
}
