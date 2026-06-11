<?php

namespace Tests\Feature\Request;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockTransaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class StockTransactionStoreRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actingAsSuperadmin();

        // Sesuaikan dengan nama route di api.php
        $this->url(route('admin.stock-transaction.store'), 'POST');
    }

    /**
     * Test validation for required main fields (transaction_no, type, date, user_id, items).
     */
    public function test_required_main_fields(): void
    {
        $form = [
            'type' => '',
            'date' => '',
            'items' => '',
        ];

        $this->assertJsonReqErrors($form, [
            'type' => __('validation.required'),
            'date' => __('validation.required'),
            'items' => __('validation.required'),
        ]);
    }

    /**
     * Test validation for transaction type (must be 'in' or 'out').
     */
    public function test_transaction_type_validation(): void
    {
        $form = ['type' => 'MASUK']; // Di rules kamu minta 'in' atau 'out'

        $this->assertJsonReqErrors($form, [
            'type' => __('validation.in'),
        ]);
    }

    /**
     * Test validation for items array (must be an array and not empty).
     */
    public function test_items_must_be_array(): void
    {
        $form = [
            'items' => 'ini-bukan-array-melainkan-string',
        ];

        $this->assertJsonReqErrors($form, [
            'items' => __('validation.array'),
        ]);
    }

    /**
     * Test validation items array must have at least one item (min:1).
     */
    public function test_items_cannot_be_empty_array(): void
    {
        $form = [
            'items' => [],
        ];

        $this->assertJsonReqErrors($form, [
            'items' => __('validation.required'),
        ]);
    }

    /**
     * Test validation for nested items (sku, qty, rack_id in the array).
     */
    public function test_items_children_validation(): void
    {
        $form = [
            'items' => [
                [
                    'product_sku' => '',
                    'qty' => '',
                    'rack_id' => '',
                    'expired_at' => '',
                ],
            ],
        ];

        $this->assertJsonReqErrors($form, [
            'items.0.product_sku' => __('validation.exists', ['attribute' => 'items.0.product_sku']),
            'items.0.qty' => __('validation.min.numeric', ['attribute' => 'items.0.qty', 'min' => 1]),
            'items.0.rack_id' => __('validation.exists', ['attribute' => 'items.0.rack_id']),
            'items.0.expired_at' => __('validation.date', ['attribute' => 'items.0.expired_at']),
        ]);
    }

    /**
     * Test successful validation with correct data (sku not in DB, qty not int/less than 1, rack_id and expired empty).
     */
    public function test_items_children_validation_constraints(): void
    {
        $form = [
            'items' => [
                [
                    'product_sku' => 'SKU-GAIB-999', // Tidak ada di DB
                    'qty' => 0,                      // Kurang dari min:1
                    'rack_id' => 99999,  // Tidak ada di DB
                    'expired_at' => '',
                ],
            ],
        ];

        $this->assertJsonReqErrors($form, [
            'items.0.product_sku' => __('validation.exists', ['attribute' => 'items.0.product_sku']),
            'items.0.qty' => __('validation.min.numeric', ['attribute' => 'items.0.qty', 'min' => 1]),
            'items.0.rack_id' => __('validation.exists', ['attribute' => 'items.0.rack_id']),
            'items.0.expired_at' => __('validation.date', ['attribute' => 'items.0.expired_at']),
        ]);
    }

    /**
     * Test validation for QTY can't be have negative value on non adjustment transaction.
     */
    public function test_qty_cannot_be_negative_on_non_adjustment_transactions(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $rack = Rack::factory()->create();

        $form = [
            'transaction_no' => 'TRX-NEG-TEST',
            'type' => 'OUT', // Selain ADJUSTMENT, misal OUT tidak boleh negatif
            'date' => now()->format('Y-m-d'),
            'user_id' => auth()->id() ?? 1,
            'items' => [
                [
                    'product_sku' => $product->sku,
                    'qty' => -5, // Mengirim angka minus di transaksi OUT
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addYear()->format('Y-m-d'),
                ],
            ],
        ];

        // Harus memicu error min:1 (atau custom rule pembatasan positif)
        $this->assertJsonReqErrors($form, [
            'items.0.qty' => __('validation.min.numeric', ['attribute' => 'items.0.qty', 'min' => 1]),
        ]);
    }

    /**
     * Test validation for QTY on OUT transaction cannot exceed available stock.
     */
    public function test_qty_cannot_exceed_available_stock_on_out_transaction(): void
    {
        // Setup produk dengan stok terbatas (misal cuma ada 10)
        $product = Product::factory()->create(['stock' => 10]);
        $rack = Rack::factory()->create();

        $form = [
            'transaction_no' => 'TRX-OVERSTOCK-TEST',
            'type' => 'OUT',
            'date' => now()->format('Y-m-d'),
            'user_id' => auth()->id() ?? 1,
            'items' => [
                [
                    'product_sku' => $product->sku,
                    'qty' => 15, // Minta keluar 15, padahal stok cuma 10
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addYear()->format('Y-m-d'),
                ],
            ],
        ];

        // Ganti dengan key pesan error custom kamu di Form Request (misal: 'items.0.qty' => 'Stok tidak mencukupi')
        $this->assertJsonReqErrors($form, [
            'items.0.qty' => __('validation.max.numeric', ['attribute' => 'items.0.qty', 'max' => 10]),
            // Atau jika menggunakan Custom Rule, sesuaikan assertion-nya langsung teks error/lang key-nya.
        ]);
    }

    /**
     * Test validation for unique transaction_no field.
     */
    public function test_transaction_no_must_be_unique(): void
    {
        StockTransaction::factory()->create(['transaction_no' => 'TRX-001']);

        $form = ['transaction_no' => 'TRX-001'];

        $this->assertJsonReqErrors($form, [
            'transaction_no' => __('validation.unique'),
        ]);
    }

    /**
     * Test validation for successful transaction storage with multiple items.
     */
    public function test_success_store_transaction(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $userId = auth()->id() ?: User::first()->id;

        $form = [
            'transaction_no' => 'TRX-'.uniqid(),
            'type' => 'IN',
            'date' => now()->format('Y-m-d'),
            'user_id' => $userId,
            'items' => [
                [
                    'product_sku' => $product->sku,
                    'qty' => 10,
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addMonths(6)->format('Y-m-d'),
                ],
                [
                    'product_sku' => $product->sku, // Bisa produk sama rack beda
                    'qty' => 5,
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addMonths(12)->format('Y-m-d'),
                ],
            ],
        ];

        $response = $this->postJson(route('admin.stock-transaction.store'), $form);

        if ($response->status() !== 201) {
            $response->dump();
        }

        $response->assertStatus(201);
    }

    /**
     * Test validation for incoming QTY cannot exceed remaining rack capacity.
     */
    public function test_qty_cannot_exceed_rack_capacity_on_in_transaction(): void
    {
        $product = Product::factory()->create();
        // Buat rak dengan kapasitas ketat, misal hanya muat 10 item
        $rack = Rack::factory()->create(['capacity' => 10]);

        // Simulasikan rak tersebut sudah terisi 7 item oleh barang lain
        ProductLocation::factory()->create([
            'product_sku' => 'BARANG-LAIN',
            'rack_id' => $rack->id,
            'qty' => 7,
            'batch_code' => 'BATCH-001',
        ]);

        // Sisa kapasitas asli rak sekarang = 10 - 7 = 3 item.
        // Coba input transaksi baru dengan qty = 5 (Harus Error!)
        $form = [
            'transaction_no' => 'TRX-OVER-CAPACITY-'.uniqid(),
            'type' => 'IN',
            'date' => now()->format('Y-m-d'),
            'user_id' => auth()->id() ?? 1,
            'items' => [
                [
                    'product_sku' => $product->sku,
                    'qty' => 5, // 5 > 3 (Sisa kapasitas) -> Memicu Error Validasi
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addYear()->format('Y-m-d'),
                ],
            ],
        ];

        $response = $this->postJson(route('admin.stock-transaction.store'), $form);

        // Memastikan server mengembalikan status 422 unprocessable entity
        $response->assertStatus(422);

        // Memastikan terdapat pesan error spesifik mengenai kelebihan muatan rak
        $response->assertJsonValidationErrors(['items.0.qty']);
    }
}
