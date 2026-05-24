<?php

namespace Tests\Feature\Controller;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductLocationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actingAsSuperadmin();
    }

    /**
     * Test mendapatkan daftar lokasi produk (stok)
     */
    public function test_can_get_list_of_product_locations(): void
    {
        $products = Product::factory()->count(3)->create();
        $racks = Rack::factory()->count(3)->create();

        ProductLocation::factory()->count(5)->create();

        $response = $this->getJson(route('admin.product-location.index'));

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test pencarian stok berdasarkan SKU atau Batch Code
     */
    public function test_can_search_locations_by_sku(): void
    {
        ProductLocation::query()->delete();
        $uniqueSku = 'XYZ-INDOMIE-SPECIFIC-99';
        $otherSku = 'OTHER-PRODUCT-001';

        $p1 = Product::factory()->create(['sku' => $uniqueSku]);
        $p2 = Product::factory()->create(['sku' => $otherSku]);

        ProductLocation::factory()->create(['product_sku' => $p1->sku]);
        ProductLocation::factory()->create(['product_sku' => $p2->sku]);

        $response = $this->getJson(route('admin.product-location.index', ['search' => $uniqueSku]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_sku', $uniqueSku);
    }

    /**
     * Test simpan/penyesuaian stok manual melalui ProductLocationController
     */
    public function test_can_store_product_location_manually(): void
    {
        $product = Product::factory()->create(['sku' => 'TEH-BOTOL']);
        $rack = Rack::factory()->create();
        $expiredDate = '2027-01-01';

        $payload = [
            'product_sku' => $product->sku,
            'rack_id' => $rack->id,
            'qty' => 100,
            'expired_at' => $expiredDate,
        ];

        $response = $this->postJson(route('admin.product-location.store'), $payload);

        $response->assertStatus(201);

        $expectedBatch = 'TEH-BOTOL-20270101';
        $this->assertDatabaseHas('product_locations', [
            'product_sku' => 'TEH-BOTOL',
            'batch_code' => $expectedBatch,
            'qty' => 100,
        ]);
    }

    /**
     * Test menampilkan detail satu lokasi spesifik (misal scan rak)
     */
    public function test_can_show_specific_product_location(): void
    {
        $location = ProductLocation::factory()->create();

        $response = $this->getJson(route('admin.product-location.show', ['product_location' => $location->id]));

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $location->id);
    }
}
