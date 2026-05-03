<?php

namespace Tests\Feature\Permission;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ProductPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles agar method createSuperadmin() dkk bisa jalan
        $this->seed(RoleSeeder::class);
    }

    public function test_index(): void
    {
        $url = route('admin.product.index');
        $this->url($url);

        $this->assertUserPermission(fn () => $this->getJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    public function test_store(): void
    {
        $category = Category::factory()->create();
        $url = route('admin.product.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url, $category) {
            return $this->postJson($url, [
                'product_name' => 'Indomie Goreng '.uniqid(),
                'category_id' => $category->id,
                'brand' => 'IND',
                'type' => 'GOR',
                'packaging' => 'BKS',
                'size' => '85G',
                'unit' => 'PCS',
                'min_stock' => 20,
            ]);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    public function test_toggle_active(): void
    {
        // Ambil produk yang sudah ada atau buat satu
        $product = Product::factory()->create();

        $url = route('admin.product.toggle-active', $product->sku);
        $this->url($url);

        $this->assertUserPermission(fn () => $this->patchJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }
}
