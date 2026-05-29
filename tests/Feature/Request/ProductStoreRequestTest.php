<?php

namespace Tests\Feature\Request;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductStoreRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Login sebagai Superadmin
        $this->actingAsSuperadmin();

        // Set URL target POST
        $this->url(route('admin.product.store'), 'POST');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [
            'sku' => '',
            'product_name' => '',
            'category_id' => '',
            'brand' => '',
            'min_stock' => '',
        ];
        $this->assertJsonReqErrors($form, [
            'sku' => __('validation.required'),
            'product_name' => __('validation.required'),
            'category_id' => __('validation.required'),
            'brand' => __('validation.required'),
            'min_stock' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when sku is already exist.
     */
    public function test_sku_must_be_unique(): void
    {
        $cat = Category::create(['category_name' => 'Test']); // Pastikan nama kolom sesuai DB kamu (category_name)

        Product::create([
            'sku' => 'ABC-123',
            'product_name' => 'A',
            'brand' => 'B',
            'category_id' => $cat->id,
            'type' => 'abc',
            'packaging' => 'Kemasan',
            'size' => 'Box',
            'min_stock' => 1,
        ]);

        $form = [
            'sku' => 'ABC-A-B-abc-box-123',
        ];

        $this->assertJsonReqErrors($form, [
            'sku' => __('validation.unique'),
        ]);
    }

    /**
     * Test validation for character limits (min/max) and data types.
     */
    public function test_validation_constraints(): void
    {
        $form = [
            'product_name' => str_repeat('A', 256),
            'category_id' => 'bukan-angka',
            'brand' => 'Ab',
            'type' => 12345,
            'packaging' => ['bukan-string'],
            'size' => true,
            'min_stock' => -1,
        ];

        $this->assertJsonReqErrors($form, [
            'product_name' => __('validation.max.string', ['max' => 255]),
            'brand' => __('validation.max.string', ['max' => 50]),
            'type' => __('validation.max.string', ['max' => 50]),
            'packaging' => __('validation.max.string', ['max' => 50]),
            'size' => __('validation.max.string', ['max' => 50]),
            'min_stock' => __('validation.min.numeric', ['min' => 1]),
        ]);
    }

    /**
     * Test validation for category_id field (must exist in categories table).
     */
    public function test_category_id_must_exist_in_categories_table(): void
    {
        $form = [
            'category_id' => 999,
        ];

        $this->assertJsonReqErrors($form, [
            'category_id' => __('validation.exists'),
        ]);
    }

    /**
     * Test validation for category_id field (must be active category).
     */
    public function test_cannot_store_product_with_inactive_category(): void
    {
        // Buat kategori nonaktif
        $inactiveCategory = Category::factory()->create(['is_active' => false]);

        $form = [
            'product_name' => 'Kecap Asin',
            'category_id' => $inactiveCategory->id,
            'brand' => 'Bango',
            'min_stock' => 10,
        ];

        $this->assertJsonReqErrors($form, [
            'category_id' => __('validation.exists'),
        ]);
    }
}
