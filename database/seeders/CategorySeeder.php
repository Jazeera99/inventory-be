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
            ['category_name' => 'Minyak Goreng', 'description' => 'Minyak sawit kemasan dan curah'],
            ['category_name' => 'Gula Pasir', 'description' => 'Gula tebu kristal'],
            ['category_name' => 'Mie Instan', 'description' => 'Berbagai merek mie instan'],
            ['category_name' => 'Tepung', 'description' => 'Tepung terigu, tapioka, dan beras'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }
}
