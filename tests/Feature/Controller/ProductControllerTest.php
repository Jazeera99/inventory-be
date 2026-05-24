<?php

namespace Tests\Feature\Controller;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jalankan seeder role agar user admin bisa dibuat (mengikuti setup kamu)
        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();
    }

    /**
     * Test Menampilkan daftar produk (Index)
     */
    public function test_index(): void
    {
        $response = $this->getJson(route('admin.product.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['sku', 'product_name', 'brand', 'category'],
                ],
            ]);
    }

    /**
     * Test Membuat produk baru dengan SKU Otomatis (Store)
     */
    public function test_store_creates_product_with_automatic_sku(): void
    {
        // Kita buat kategori dengan nama unik biar gak bentrok di DB manual
        $uniqueName = 'Kategori '.time();
        $category = Category::create(['category_name' => $uniqueName]);

        $payload = [
            'product_name' => 'Produk Test '.time(),
            'category_id' => $category->id,
            'brand' => 'Apple',
            'type' => 'Pro',
            'packaging' => 'Box',
            'size' => '13 Inch',
            'min_stock' => 5,
        ];

        $response = $this->postJson(route('admin.product.store'), $payload);

        $response->assertStatus(200);

        // Logic SKU: KAT-APP-PRO-BOX-13I-001 (KAT diambil dari 3 huruf pertama Kategori)
        $prefix = strtoupper(substr(str_replace(' ', '', $uniqueName), 0, 3)).'-APP-PRO-BOX-13I';

        // Kita cek apakah SKU mengandung prefix yang benar (nomor urutnya fleksibel)
        $this->assertDatabaseHas('products', [
            'product_name' => $payload['product_name'],
            'category_id' => $category->id,
        ]);

        $responseData = $response->json('data');
        $this->assertStringContainsString($prefix, $responseData['sku']);
    }

    /**
     * Test Update produk
     */
    // public function test_update_product(): void
    // {
    //     // Ambil produk terakhir yang ada di DB atau buat baru
    //     $product = Product::latest()->first() ?? Product::factory()->create();

    //     $payload = [
    //         'product_name' => 'Nama Update '.time(),
    //         'category_id' => $product->category_id,
    //         'brand' => 'Updated Brand',
    //         'min_stock' => 99,
    //     ];

    //     $response = $this->putJson(route('admin.product.update', $product->sku), $payload);

    //     $response->assertStatus(200);
    //     $this->assertDatabaseHas('products', [
    //         'sku' => $product->sku,
    //         'product_name' => $payload['product_name'],
    //     ]);
    // }

    /**
     * Test Toggle Status Aktif (ToggleActive)
     */
    public function test_toggle_active_status(): void
    {
        $product = Product::latest()->first() ?? Product::factory()->create();
        $initialStatus = (bool) $product->is_active;

        $response = $this->patchJson(route('admin.product.toggle-active', $product->sku));

        $response->assertStatus(200);
        // Status harus berubah dari kondisi awal
        $this->assertEquals(! $initialStatus, $response->json('is_active'));
    }
}
