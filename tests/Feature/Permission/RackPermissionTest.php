<?php

namespace Tests\Feature\Permission;

use App\Models\Rack;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class RackPermissionTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Jalankan seeder agar role dasar (Superadmin, Warehouse Admin, dll) ada
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test access for list racks.
     */
    public function test_index(): void
    {
        $url = route('admin.rack.index');
        $this->url($url);

        $this->assertUserPermission(fn () => $this->getJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for bulk generate racks.
     */
    public function test_generate(): void
    {
        $url = route('admin.rack.generate');
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $uniqueChar = str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ')[0];

            $form = [
                'rack_name' => 'Test-'.$uniqueChar.'-'.uniqid(),
                'total_column' => 1,
                'total_level' => 1,
            ];

            return $this->postJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for toggle maintenance status.
     */
    public function test_toggle_maintenance(): void
    {
        $targetRack = Rack::factory()->create([
            'is_maintenance' => false,
            'is_active' => true,
        ]);

        $url = route('admin.rack.toggle-maintenance', $targetRack);
        $this->url($url);

        $this->assertUserPermission(fn () => $this->patchJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for create rack.
     */
    public function test_store(): void
    {
        $url = route('admin.rack.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $form = [
                'location_code' => 'B1-1-'.uniqid(),
                'rack_name' => 'Rak Gudang B',
                'column_number' => 1,
                'level_number' => 1,
            ];

            return $this->postJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    // /**
    //  * Test access for update rack.
    //  */
    // public function test_update(): void
    // {
    //     $targetRack = Rack::factory()->create();

    //     $url = route('admin.rack.update', $targetRack);
    //     $this->url($url);

    //     $this->assertUserPermission(function () use ($url) {
    //         $form = [
    //             'location_code' => 'B1-1-Edit-'.uniqid(),
    //             'rack_name' => 'Rak Gudang B Edit',
    //             'column_number' => 2,
    //             'level_number' => 2,
    //         ];

    //         return $this->putJson($url, $form);
    //     })
    //         ->allow($this->createSuperadmin())
    //         ->allow($this->createWarehouseAdmin())
    //         ->forbid($this->createStaff());
    // }
}
