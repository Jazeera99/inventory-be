<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::first() ?? Category::factory()->create(['category_name' => 'SEMBAKO']);

        Product::create([
            'sku' => 'SEM-BIM-GOR-POU-1LT-PCS-001',
            'product_name' => 'BIMOLI GORENG POUCH 1LT',
            'category_id' => $category->id,
            'brand' => 'BIM',
            'type' => 'GOR',
            'packaging' => 'POU',
            'size' => '1 LT',
            'stock' => 0,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        // Tambah 10 produk random pake factory
        Product::factory(10)->create([
            'category_id' => $category->id,
            'stock' => 0,
        ]);
    }
}
