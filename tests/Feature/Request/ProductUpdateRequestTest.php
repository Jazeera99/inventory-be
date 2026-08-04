<?php

namespace Tests\Feature\Request;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductUpdateRequestTest extends TestCase
{
    protected Product $product;

    protected Category $activeCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actingAsSuperadmin();

        $this->activeCategory = Category::factory()->create(['category_name' => 'Semen', 'is_active' => true]);

        $this->product = Product::factory()->create([
            'sku' => 'SEM-BIM-GEL-PLT-500GR-001',
            'product_name' => 'Semen Tiga Roda',
            'category_id' => $this->activeCategory->id,
            'brand' => 'Bima',
            'type' => 'Gel',
            'packaging' => 'Plastik',
            'size' => '500 GR',
            'min_stock' => 5,
        ]);

        $this->url(route('admin.product.update', ['product' => $this->product->id]), 'PUT');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required_fields_on_update(): void
    {
        $form = [
            'product_name' => '',
            'category_id' => '',
            'brand' => '',
            'packaging' => '',
            'size' => '',
            'min_stock' => '',
        ];
        $this->assertJsonReqErrors($form, [
            'product_name' => __('validation.required'),
            'category_id' => __('validation.required'),
            'brand' => __('validation.required'),
            'packaging' => __('validation.required'),
            'size' => __('validation.required'),
            'min_stock' => __('validation.required'),
        ]);
    }

    /**
     * Validation tests for constraints character and numeric on update
     */
    public function test_validation_constraints_on_update(): void
    {
        $form = [
            'product_name' => str_repeat('X', 256),
            'category_id' => 'string-salah',
            'brand' => 'Ki',
            'type' => 999,
            'packaging' => true,
            'size' => ['array-salah'],
            'min_stock' => -5,
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
     * Test validation to ensure product cannot be updated to an inactive category.
     */
    public function test_cannot_update_product_to_inactive_category(): void
    {
        $inactiveCategory = Category::factory()->create(['is_active' => false]);

        $form = [
            'product_name' => 'Semen Tiga Roda',
            'category_id' => $inactiveCategory->id,
            'brand' => 'Bima',
            'min_stock' => 5,
        ];

        $this->assertJsonReqErrors($form, [
            'category_id' => __('validation.exists'),
        ]);
    }

    /**
     * Test validation to ensure SKU regenerates automatically when identity fields change on update.
     */
    public function test_sku_automatically_regenerates_when_identity_changes(): void
    {
        $form = [
            'product_name' => 'Semen Tiga Roda',
            'category_id' => $this->activeCategory->id,
            'brand' => 'Gresik', // Brand diubah dari Bima -> Gresik
            'type' => 'Gel',
            'packaging' => 'Plastik',
            'size' => '500 GR',
            'min_stock' => 5,
        ];

        $response = $this->putJson($this->url, $form);
        $response->assertStatus(200);

        // SKU di database wajib berubah otomatis dari SEM-BIM-001 menjadi SEM-GRE-001
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'brand' => 'Gresik',
            'sku' => 'SEM-GRE-001',
        ]);

        $form['type'] = 'Bubuk';
        $form['size'] = '1 KG';

        $response = $this->putJson($this->url, $form);
        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sku' => 'SEM-GRE-BUB-PLT-1KG-001',
        ]);
    }
}
