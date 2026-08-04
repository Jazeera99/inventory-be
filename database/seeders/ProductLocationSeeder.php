<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil semua rak aktif yang SIAP DIGUNAKAN (Bukan Loading Dock, Bukan Maintenance)
        $availableRacks = Rack::query()->where('is_active', true)
            ->where('is_maintenance', false)
            ->where('column_number', '>', 0)
            ->where('level_number', '>', 0)
            ->get();

        if ($availableRacks->isEmpty()) {
            $this->command->warn('Tidak ada rak aktif yang tersedia untuk seeding produk.');

            return;
        }

        // Ambil semua produk yang telah dibuat oleh ProductSeeder
        $products = Product::query()->where('is_active', true)->get();

        if ($products->isEmpty()) {
            $this->command->warn('Tidak ada produk aktif yang tersedia untuk dialokasikan.');

            return;
        }

        $faker = Factory::create();

        // Looping setiap rak, kita isi produk sampai hampir penuh / sesuai batas kapasitasnya
        foreach ($availableRacks as $rack) {
            $currentCapacityUsed = 0;
            $maxCapacity = $rack->capacity; // Misalnya 15 atau 50

            // Isi rak ini dengan 1-3 variasi produk acak selama kapasitasnya muat
            $maxVarieties = rand(1, 3);

            for ($i = 0; $i < $maxVarieties; $i++) {
                // Sisa ruang kosong di rak saat ini
                $remainingSpace = $maxCapacity - $currentCapacityUsed;

                if ($remainingSpace <= 0) {
                    break; // Rak sudah penuh total, hentikan pengisian di rak ini
                }

                // Ambil 1 produk secara acak
                $product = $products->random();

                // Tentukan jumlah qty yang mau dimasukkan (Jangan sampai melampaui sisa space rak)
                $qtyToStore = rand(3, min(15, $remainingSpace));

                if ($qtyToStore <= 0) {
                    continue;
                }

                // Generate expired date menggunakan Carbon untuk konsistensi timezone
                $expiredAt = Carbon::now('Asia/Jakarta')->addMonths(rand(12, 24))->startOfDay();

                // Simpan ke table product_locations
                ProductLocation::create([
                    'product_sku' => $product->sku,
                    'rack_id' => $rack->id,
                    'qty' => $qtyToStore,
                    'batch_code' => $product->sku.'-'.$expiredAt->format('Ymd').'-'.$faker->numerify('###'),
                    'unit_cost' => $product->purchase_price ?? 0,
                    'expired_at' => $expiredAt,
                    'status' => 'AVAILABLE',
                ]);

                // Update tracker kapasitas internal looping
                $currentCapacityUsed += $qtyToStore;
            }
        }

        $this->command->info('Seeding ProductLocation berhasil disesuaikan dengan kapasitas rak aktif!');
    }
}
