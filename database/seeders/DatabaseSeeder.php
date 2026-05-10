<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductLocation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * List of seeders for dummy data, used in app/Console/Commands/DBReset.php.
     */
    public function run(): void
    {
        // Urutan pemanggilan sangat penting! Role dulu baru User
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            RackSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ProductLocationSeeder::class,
            StockTransactionSeeder::class,
        ]);

        $this->command->info('Menyinkronkan stok global...');
        $allLocations = ProductLocation::all();
        foreach ($allLocations as $loc) {
            Product::where('sku', $loc->product_sku)->increment('stock', $loc->qty);
        }
    }

    /**
     * Run the database seeds.
     */
    // public function run(): void
    // {
    //     $this->call(self::$seeder);
    // }
}
