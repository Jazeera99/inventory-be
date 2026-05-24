<?php

namespace Database\Seeders;

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
            StockTransactionSeeder::class,
        ]);

        $this->command->info('Database berhasil di-seed dengan riwayat transaksi lengkap!');
    }

    /**
     * Run the database seeds.
     */
    // public function run(): void
    // {
    //     $this->call(self::$seeder);
    // }
}
