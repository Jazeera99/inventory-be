<?php

namespace Tests\Feature\Product;

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
        $form = [];
        $this->assertJsonReqErrors($form, [
            'product_name' => __('validation.required'),
            'category_id' => __('validation.required'),
            'brand' => __('validation.required'),
            'min_stock' => __('validation.required'),
        ]);
    }

    // public function test_sku_is_required(): void
    // {
    //     $form = [
    //         'sku' => '',
    //     ];

    //     $this->assertJsonReqErrors($form, [
    //         'sku' => __('validation.required'),
    //     ]);
    // }

    // public function test_sku_must_be_unique(): void
    // {
    //     $cat = Category::create(['category_name' => 'Test']); // Pastikan nama kolom sesuai DB kamu (category_name)

    //     Product::create([
    //         'sku' => 'ABC-123',
    //         'product_name' => 'A',
    //         'brand' => 'B',
    //         'category_id' => $cat->id,
    //         'type' => 'abc',
    //         'packaging' => 'Kemasan',
    //         'size' => 'Box',
    //         'min_stock' => 1,
    //     ]);

    //     $form = [
    //         'sku' => 'ABC',
    //     ];

    //     $this->assertJsonReqErrors($form, [
    //         'sku' => __('validation.unique'),
    //     ]);
    // }

    public function test_category_id_must_exist_in_categories_table(): void
    {
        $form = [
            'category_id' => 999,
        ];

        $this->assertJsonReqErrors($form, [
            'category_id' => __('validation.exists'),
        ]);
    }
}
