<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $racks = Rack::all();
        $defaultUser = User::first() ?? User::factory()->create();

        if ($products->isEmpty() || $racks->isEmpty()) {
            $this->command->warn('Skip Seeder: Pastikan Product dan Rack sudah ada isinya!');

            return;
        }

        // Bersihkan data lama agar bersih total
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_locations')->truncate();
        DB::table('stock_transactions')->delete();
        DB::table('stock_transaction_items')->truncate();
        DB::table('stock_ledgers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $updateLocation = function ($sku, $rackId, $batch, $expiry, $changeQty): void {
            $location = ProductLocation::where('product_sku', $sku)
                ->where('rack_id', $rackId)
                ->where('batch_code', $batch)
                ->first();

            if ($location) {
                $newQty = $location->qty + $changeQty;
                if ($newQty <= 0) {
                    $location->delete();
                } else {
                    $location->update(['qty' => $newQty]);
                }
            } elseif ($changeQty > 0) {
                ProductLocation::create([
                    'product_sku' => $sku,
                    'rack_id' => $rackId,
                    'batch_code' => $batch,
                    'qty' => $changeQty,
                    'expired_at' => $expiry,
                ]);
            }
        };

        // --- LANGKAH 1: STOK AWAL (DISET 1 BULAN LALU) ---
        // $initDate = now()->subDays(30)->format('Y-m-d H:i:s');

        // foreach ($products as $product) {
        //     if ($product->stock > 0) {
        //         $initialStock = $product->stock;

        //         // Mulai dari 0 sebelum diproses seeder berjalan
        //         $product->stock = 0;
        //         $product->save();

        //         $rack = $racks->random();
        //         $expiredAt = now()->addMonths(12)->format('Y-m-d');
        //         $batchCode = $product->sku.'-'.date('Ymd', strtotime($expiredAt));
        //         $txNo = 'TRX-INIT-'.Str::random(5).'-'.$product->sku;

        //         DB::table('stock_transactions')->insert([
        //             'transaction_no' => $txNo,
        //             'type' => 'IN',
        //             'date' => date('Y-m-d', strtotime($initDate)),
        //             'user_id' => $defaultUser->id,
        //             'created_at' => $initDate,
        //             'updated_at' => $initDate,
        //         ]);

        //         DB::table('stock_transaction_items')->insert([
        //             'transaction_no' => $txNo,
        //             'product_sku' => $product->sku,
        //             'rack_id' => $rack->id,
        //             'qty' => $initialStock,
        //             'qty_before' => 0,
        //             'qty_after' => $initialStock,
        //             'expired_at' => $expiredAt,
        //             'notes' => 'Inisialisasi Stok Bawaan Seeder',
        //             'created_at' => $initDate,
        //             'updated_at' => $initDate,
        //         ]);

        //         DB::table('stock_ledgers')->insert([
        //             'product_sku' => $product->sku,
        //             'transaction_no' => $txNo,
        //             'type' => 'IN',
        //             'rack_id' => $rack->id,
        //             'expired_at' => $expiredAt,
        //             'qty' => $initialStock,
        //             'balance_before' => 0,
        //             'balance_after' => $initialStock,
        //             'user_id' => $defaultUser->id,
        //             'note' => 'Inisialisasi Stok Bawaan Seeder',
        //             'created_at' => $initDate,
        //             'updated_at' => $initDate,
        //         ]);

        //         $updateLocation($product->sku, $rack->id, $batchCode, $expiredAt, $initialStock);
        //         $product->update(['stock' => $initialStock]);
        //     }
        // }

        // --- LANGKAH 2: MUTASI TRANSAKSI BERJALAN (DI-SET BERTAHAP DI BULAN MEI 2026) ---
        $types = ['IN', 'OUT', 'MOVE', 'ADJUSTMENT'];

        for ($step = 1; $step <= 60; $step++) {
            // Kita paksa buat tanggal random buatan yang PASTI masuk range Mei 2026 Anda
            $day = rand(1, 18);
            $hour = rand(1, 23);
            $minute = rand(1, 59);
            $forcedDate = '2026-05-'.sprintf('%02d', $day).' '.sprintf('%02d', $hour).':'.sprintf('%02d', $minute).':00';
            $dateString = date('Ymd', strtotime($forcedDate));
            $chosenType = $types[array_rand($types)];

            $typeCode = match ($chosenType) {
                'IN' => 'IN',
                'OUT' => 'OUT',
                'MOVE' => 'MOVE',
                'ADJUSTMENT' => 'ADJ',
                default => 'GEN'
            };

            $txNo = "TRX-{$typeCode}-{$dateString}-".sprintf('%04d', $step);

            // Buat header tanpa Factory agar terhindar dari manipulasi internal model
            DB::table('stock_transactions')->insert([
                'transaction_no' => $txNo,
                'type' => $chosenType,
                'date' => date('Y-m-d', strtotime($forcedDate)),
                'user_id' => $defaultUser->id,
                'created_at' => $forcedDate,
                'updated_at' => $forcedDate,
            ]);

            $numItems = rand(1, 2);

            for ($i = 0; $i < $numItems; $i++) {
                $products = Product::all();
                $product = Product::where('sku', $products->random()->sku)->first();

                $sourceRack = $racks->random();
                $qty = rand(5, 15);
                $qtyBefore = $product->stock;
                $targetRackId = null;
                $notes = 'Transaksi Seeder '.$chosenType;

                $expiredAt = now()->addMonths(rand(6, 18))->format('Y-m-d');
                $batchCode = $product->sku.'-'.date('Ymd', strtotime($expiredAt));

                $currentType = $chosenType;
                if ($qtyBefore <= 0 && in_array($currentType, ['OUT', 'MOVE', 'ADJUSTMENT'])) {
                    $currentType = 'IN';
                }

                if ($chosenType === 'IN') {
                    $qtyAfter = $qtyBefore + $qty;
                    $product->increment('stock', $qty);
                    $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, $qty);
                } elseif ($chosenType === 'OUT') {
                    $actualQty = min($qty, $qtyBefore);
                    if ($actualQty <= 0) {
                        continue;
                    }

                    $qtyNeeded = $actualQty;
                    $qtyAfter = $qtyBefore - $actualQty;
                    // $product->decrement('stock', $actualQty);

                    $locations = ProductLocation::where('product_sku', $product->sku)->where('qty', '>', 0)->orderBy('expired_at', 'asc')->get();
                    foreach ($locations as $loc) {
                        if ($qtyNeeded <= 0) {
                            break;
                        }

                        $take = min($loc->qty, $qtyNeeded);

                        // Update lokasi secara proporsional sesuai isi rak yang ada
                        $updateLocation($product->sku, $loc->rack_id, $loc->batch_code, $loc->expired_at, -$take);

                        // Catat item transaksi per pecahannya agar ledger akurat
                        DB::table('stock_transaction_items')->insert([
                            'transaction_no' => $txNo,
                            'product_sku' => $product->sku,
                            'rack_id' => $loc->rack_id,
                            'qty' => $take,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyBefore - $take,
                            'expired_at' => $loc->expired_at,
                            'notes' => $notes,
                            'created_at' => $forcedDate,
                            'updated_at' => $forcedDate,
                        ]);

                        DB::table('stock_ledgers')->insert([
                            'product_sku' => $product->sku,
                            'transaction_no' => $txNo,
                            'type' => 'OUT',
                            'rack_id' => $loc->rack_id,
                            'expired_at' => $loc->expired_at,
                            'qty' => -$take,
                            'balance_before' => $qtyBefore,
                            'balance_after' => $qtyBefore - $take,
                            'user_id' => $defaultUser->id,
                            'note' => $notes,
                            'created_at' => $forcedDate,
                            'updated_at' => $forcedDate,
                        ]);

                        $qtyBefore -= $take;
                        $qtyNeeded -= $take;
                    }

                    // Update stok global produk di akhir
                    $product->decrement('stock', $actualQty);

                    continue;
                } elseif ($chosenType === 'MOVE') {
                    $locSource = ProductLocation::where('product_sku', $product->sku)->where('qty', '>', 0)->first();
                    if (! $locSource) {
                        continue;
                    }

                    $targetRackId = $racks->where('id', '!=', $locSource->rack_id)->random()->id;
                    $moveQty = min($qty, $locSource->qty);

                    $updateLocation($product->sku, $locSource->rack_id, $locSource->batch_code, $locSource->expired_at, -$moveQty);
                    $updateLocation($product->sku, $targetRackId, $locSource->batch_code, $locSource->expired_at, $moveQty);

                    $qty = $moveQty;
                    $qtyAfter = $qtyBefore;
                    $sourceRack = Rack::find($locSource->rack_id);
                } elseif ($chosenType === 'ADJUSTMENT') {
                    $isAdding = $qtyBefore > 5 ? fake()->boolean() : true;
                    $qtyAdjustment = $isAdding ? $qty : -min($qty, $qtyBefore);
                    if ($qtyAdjustment == 0) {
                        continue;
                    }

                    $qtyAfter = $qtyBefore + $qtyAdjustment;
                    $product->increment('stock', $qtyAdjustment);
                    $qty = $qtyAdjustment;

                    $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, $qtyAdjustment);
                }

                // Masukkan data item menggunakan DB::table murni agar bypass timestamps otomatis Eloquent
                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $sourceRack->id,
                    'target_rack_id' => $targetRackId,
                    'qty' => $qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $expiredAt,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                DB::table('stock_ledgers')->insert([
                    'product_sku' => $product->sku,
                    'transaction_no' => $txNo,
                    'type' => $currentType,
                    'rack_id' => $sourceRack->id,
                    'expired_at' => $expiredAt,
                    'qty' => $qty,
                    'balance_before' => $qtyBefore,
                    'balance_after' => $qtyAfter,
                    'user_id' => 1,
                    'note' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);
            }
        }

        // DATA TRANSAKSI PEMBATALKAN (SOFT DELETED)
        $testProduct = $products->first();
        $testRack = $racks->first();

        $cancelledTypes = ['IN', 'OUT', 'MOVE'];

        // Membuat 3 Transaksi yang berstatus DIBATALKAN (Soft Deleted)
        // Ini berguna untuk mengisi data tab "Trash" Anda di Vue Frontend
        for ($j = 1; $j <= 3; $j++) {
            $chosenCancelType = $cancelledTypes[$j - 1];
            $cancelledTrx = StockTransaction::factory()->cancelled($chosenCancelType)->create([
                'user_id' => $defaultUser->id,
            ]);

            $cancelNotes = match ($chosenCancelType) {
                'IN' => 'Pembatalan barang masuk dari Supplier',
                'OUT' => 'Pembatalan barang keluar menuju Toko',
                'MOVE' => 'Pembatalan mutasi antar rak dalam gudang',
                default => 'Sampel transaksi dibatalkan'
            };

            StockTransactionItem::factory()->create([
                'transaction_no' => $cancelledTrx->transaction_no,
                'product_sku' => $testProduct->sku,
                'rack_id' => $testRack->id,
                'qty' => rand(5, 15),
                'qty_before' => $testProduct->stock,
                'qty_after' => $chosenCancelType === 'IN' ? $testProduct->stock + 10 : $testProduct->stock - 5,
                'notes' => $cancelNotes,
            ]);
        }
    }
}
