<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Beras', 'description' => 'Semua jenis beras kualiatas bawah sampai premium'],
            ['category_name' => 'Minyak dan Lemak', 'description' => 'Minyak sawit kemasan dan curah'],
            ['category_name' => 'Gula dan Pemanis', 'description' => 'Gula tebu kristal'],
            ['category_name' => 'Mie dan Instan', 'description' => 'Berbagai merek mie instan'],
            ['category_name' => 'Tepung dan Adonan', 'description' => 'Tepung terigu, tapioka, dan beras'],
            ['category_name' => 'Susu dan Olahan', 'description' => 'Susu kental manis, susu bubuk, dan keju grosir'],
            ['category_name' => 'Bumbu dan Rempah', 'description' => 'Bumbu dapur, rempah, dan penyedap rasa'],
            ['category_name' => 'Minuman Ringan', 'description' => 'Soda, jus, teh botol, dan minuman kemasan lainnya'],
            ['category_name' => 'Snack dan Kue', 'description' => 'Keripik, biskuit, wafer, dan kue kering'],
            ['category_name' => 'Makanan Kaleng', 'description' => 'Sarden, tuna kaleng, dan makanan kaleng lainnya'],
        ];

        foreach ($categories as $cat) {
            // Category::create($cat);
            Category::updateOrCreate(['category_name' => $cat['category_name']], $cat);
        }
    }
}
