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
        $this->call(CategorySeeder::class);

        // Ambil kategori khusus Minyak untuk test case produk andalan utama
        $minyakCategory = Category::query()->where('category_name', 'Minyak dan Lemak')->first();

        // Insert 1 Produk Andalan Utama (Manual / Fixed)
        Product::updateOrCreate(
            ['sku' => 'MIN-BIM-GOR-POU-2LT-001'],
            [
                'product_name' => 'BIMOLI MINYAK GORENG POUCH 2LT',
                'category_id' => $minyakCategory->id,
                'brand' => 'BIM',
                'type' => 'GOR',
                'packaging' => 'POU',
                'size' => '2 LT',
                'purchase_price' => 32000,
                'selling_price' => 36000,
                'holding_cost_per_day' => 100,
                'stock' => 0,
                'min_stock' => 20,
                'exp_warning_days' => 30,
                'is_active' => true,
            ]
        );

        // Ambil semua kategori sembako yang ada di database sekarang
        $categories = Category::all();

        // Generate 30 produk grosir random yang tersebar merata di setiap kategori sembako
        foreach ($categories as $category) {
            $products = Product::factory(5)->create([
                'category_id' => $category->id,
                'stock' => 0, // Tetap set 0 agar mutasi wajib via StockTransaction (IN/OUT)
            ]);
        }
    }
}
