<?php

namespace Tests\Feature\Controller;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockTransaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class StockTransactionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actingAsSuperadmin();
    }

    /**
     * Test sukses simpan transaksi MASUK dan update lokasi stok
     */
    public function test_can_store_stock_in_transaction_and_update_location(): void
    {
        $user = User::first() ?? User::factory()->create();

        $product = Product::factory()->create(['sku' => 'KECAP01']);
        $rack = Rack::factory()->create();
        $expiredDate = now()->addYear()->format('Y-m-d');

        $payload = [
            'transaction_no' => 'TRX-'.uniqid(),
            'type' => 'IN',
            'date' => now()->format('Y-m-d'),
            'user_id' => $user->id,
            'notes' => 'Barang masuk dari Supplier A',
            'items' => [
                [
                    'product_sku' => 'KECAP01',
                    'qty' => 50,
                    'rack_id' => $rack->id,
                    'expired_at' => $expiredDate,
                ],
            ],
        ];

        $response = $this->postJson(route('admin.stock-transaction.store'), $payload);

        // if ($response->status() === 422) {
        //     dump($response->json());
        // }

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'IN',
        ]);

        // Cek Stok di Lokasi (ProductLocation)
        $expectedBatch = 'KECAP01-'.now()->addYear()->format('Ymd');
        $this->assertDatabaseHas('product_locations', [
            'product_sku' => $product->sku,
            'batch_code' => $expectedBatch,
            'qty' => 50,
            'rack_id' => $rack->id,
        ]);

        $this->assertDatabaseHas('stock_transaction_items', [
            'product_sku' => 'KECAP01',
            'qty' => 50,
        ]);
    }

    /**
     * Test increment qty jika batch & rak yang sama diinput lagi
     */
    public function test_increments_qty_if_product_location_already_exists(): void
    {
        $user = User::first() ?? User::factory()->create();
        $product = Product::factory()->create(['sku' => 'SAOS01']);
        $rack = Rack::factory()->create();
        $expiredDate = now()->addYear()->format('Y-m-d');
        $batchCode = 'SAOS01-'.now()->addYear()->format('Ymd');

        // 1. Buat stok awal 10
        ProductLocation::create([
            'product_sku' => $product->sku,
            'rack_id' => $rack->id,
            'batch_code' => $batchCode,
            'qty' => 10,
            'expired_at' => $expiredDate,
        ]);

        $payload = [
            'transaction_no' => 'TRX-INC-'.uniqid(),
            'type' => 'IN',
            'date' => now()->format('Y-m-d'),
            'user_id' => $user->id,
            'items' => [
                [
                    'product_sku' => 'SAOS01',
                    'qty' => 25, // Tambah 25
                    'rack_id' => $rack->id,
                    'expired_at' => $expiredDate,
                ],
            ],
        ];

        $response = $this->postJson(route('admin.stock-transaction.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('product_locations', [
            'product_sku' => 'SAOS01',
            'qty' => 35,
        ]);
    }

    /**
     * Test gagal simpan jika field wajib kosong (422)
     */
    public function test_fails_if_required_fields_missing(): void
    {
        $response = $this->postJson(route('admin.stock-transaction.store'), []);

        $response->assertStatus(422);
    }

    /**
     * Test menampilkan detail transaksi berdasarkan nomor transaksi
     */
    public function test_can_show_transaction_detail(): void
    {
        $trx = StockTransaction::factory()->create(['transaction_no' => 'TRX-TEST-123']);

        $response = $this->getJson(route('admin.stock-transaction.show', ['transaction_no' => $trx->transaction_no]));

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction_no', 'TRX-TEST-123');
    }
}
