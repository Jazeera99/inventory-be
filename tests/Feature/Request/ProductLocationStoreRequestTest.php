<?php

namespace Tests\Feature\Request;

use App\Models\Product;
use App\Models\Rack;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductLocationStoreRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles agar helper actingAsSuperadmin bisa jalan
        $this->seed(RoleSeeder::class);
        $this->actingAsSuperadmin();

        // Gunakan route() agar URL-nya dinamis mengikuti routes/api.php
        $this->url(route('admin.product-location.store'), 'POST');
    }

    /**
     * Test validation for required fields (product_sku, rack_id, qty, expired_at)
     */
    public function test_required_fields(): void
    {
        $form = [];

        $this->assertJsonReqErrors($form, [
            'product_sku' => __('validation.required'),
            'rack_id' => __('validation.required'),
            'qty' => __('validation.required'),
            'expired_at' => __('validation.required'),
        ]);
    }

    /**
     * Test validation for fields that must exist in the database (product_sku in products table, rack_id in racks table)
     */
    public function test_must_exist_in_database(): void
    {
        $form = [
            'product_sku' => 'SKU-PALSU-123',
            'rack_id' => 999999,
        ];

        $this->assertJsonReqErrors($form, [
            'product_sku' => __('validation.exists'),
            'rack_id' => __('validation.exists'),
        ]);
    }

    /**
     * Test validation for qty field (must be integer and minimum 1)
     */
    public function test_qty_minimum_value(): void
    {
        $form = [
            'qty' => 0, // Minimal harus 1
        ];

        $this->assertJsonReqErrors($form, [
            'qty' => __('validation.min.numeric', ['min' => 1]),
        ]);
    }

    /**
     * Test validation for expired_at field (must be a date and after today)
     */
    public function test_expired_at_validation(): void
    {
        // 1. Tes kalau formatnya bukan tanggal
        $this->assertJsonReqErrors(['expired_at' => 'bukan-tanggal'], [
            'expired_at' => __('validation.date'),
        ]);

        // 2. Tes kalau tanggalnya kemarin (harus after today)
        $this->assertJsonReqErrors(['expired_at' => now()->subDay()->format('Y-m-d')], [
            'expired_at' => __('validation.after', ['date' => 'today']),
        ]);
    }

    /**
     * Test validation if all fields are valid (should pass without errors)
     */
    public function test_success_request(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $form = [
            'product_sku' => $product->sku,
            'rack_id' => $rack->id,
            'qty' => 50,
            'expired_at' => now()->addYear()->format('Y-m-d'),
        ];

        $response = $this->postJson(route('admin.product-location.store'), $form);

        // Karena ini tes request, kita pastikan tidak ada error validasi (status bukan 422)
        $response->assertStatus(201);
    }
}
