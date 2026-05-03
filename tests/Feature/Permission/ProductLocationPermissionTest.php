<?php

namespace Tests\Feature\Permission;

use App\Models\Product;
use App\Models\Rack;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductLocationPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles agar helper createSuperadmin() dkk bisa jalan
        $this->seed(RoleSeeder::class);
    }

    public function test_store_permission(): void
    {
        // Siapkan data dummy untuk testing store
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        // PASTIKAN NAMA ROUTE INI SUDAH ADA DI routes/api.php
        // Kalau belum ada, sesuaikan dengan nama route kamu
        $url = route('admin.product-location.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url, $product, $rack) {
            return $this->postJson($url, [
                'product_sku' => $product->sku,
                'rack_id' => $rack->id,
                'qty' => 10,
                'expired_at' => now()->addYear()->format('Y-m-d'),
            ]);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->allow($this->createStaff());
    }

    public function test_unauthorized_user_cannot_create_location(): void
    {
        // Tes untuk user yang belum login (Guest)
        // Gunakan route() supaya tidak salah ketik URL
        $url = route('admin.product-location.store');

        $response = $this->postJson($url, []);

        // Pastikan response 401 (Unauthorized)
        $response->assertStatus(401);
    }
}
