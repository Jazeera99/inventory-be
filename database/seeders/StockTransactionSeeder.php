<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockOrder;
use App\Models\StockOrderItem;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        $suppliers = Supplier::all();
        $customers = Customer::all();
        $faker = Faker::create();

        if ($products->isEmpty() || $racks->isEmpty()) {
            $this->command->warn('Skip Seeder: Pastikan Product dan Rack sudah ada isinya!');

            return;
        }

        // Bersihkan data lama agar bersih total
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_locations')->truncate();
        DB::table('stock_orders')->truncate();
        DB::table('stock_order_items')->truncate();
        DB::table('stock_transactions')->delete();
        DB::table('stock_transaction_items')->truncate();
        DB::table('stock_ledgers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $updateLocation = function ($sku, $rackId, $batch, $expiry, $changeQty) use (&$racks) {
            if ($changeQty < 0) {
                $qtyNeeded = abs($changeQty);
                $locations = ProductLocation::query()->where('product_sku', $sku)
                    ->where('rack_id', $rackId)
                    ->whereDate('expired_at', $expiry)
                    ->where('qty', '>', 0)
                    ->get();

                foreach ($locations as $loc) {
                    if ($qtyNeeded <= 0) {
                        break;
                    }
                    $take = min($loc->qty, $qtyNeeded);

                    if ($loc->qty - $take <= 0) {
                        $loc->delete();
                    } else {
                        $loc->decrement('qty', $take);
                    }
                    $qtyNeeded -= $take;
                }

                return $rackId; // Kembalikan rak asal
            }

            // Jika penambahan barang (IN / ADJUSTMENT POSITIF)
            $remainingToPlace = $changeQty;
            $lastAssignedRackId = $rackId;

            while ($remainingToPlace > 0) {
                // Cari rak aktif yang kapasitasnya belum penuh (< 15)
                $targetRack = Rack::query()->where('is_active', true)
                    ->where('is_maintenance', false)
                    ->where('column_number', '>', 0)
                    ->get()
                    ->first(function ($r) {
                        return ProductLocation::query()->where('rack_id', $r->id)->sum('qty') < 15;
                    });

                if (! $targetRack) {
                    $lastRack = Rack::query()->where('column_number', '>', 0)->orderBy('id', 'desc')->first();

                    $letter = 'C';
                    $nextColumn = 1;
                    $nextLevel = 1;

                    if ($lastRack) {
                        $lastLetter = str_replace('Rak ', '', $lastRack->rack_name);
                        $nextColumn = $lastRack->column_number;
                        $nextLevel = $lastRack->level_number + 1;

                        if ($nextLevel > 5) {
                            $nextLevel = 1;
                            $nextColumn++;
                        }

                        if ($nextColumn > 10) {
                            $nextColumn = 1;
                            $lastLetter++;
                        }
                        $letter = $lastLetter;
                    }

                    $targetRack = Rack::create([
                        'location_code' => "{$letter}{$nextColumn}-{$nextLevel}",
                        'rack_name' => "Rak {$letter}",
                        'column_number' => $nextColumn,
                        'level_number' => $nextLevel,
                        'capacity' => 15,
                        'is_active' => true,
                        'is_maintenance' => false,
                    ]);

                    $racks = Rack::all();
                }

                $totalUsedInRack = ProductLocation::query()->where('rack_id', $targetRack->id)->sum('qty');
                $spaceLeftInRack = 15 - $totalUsedInRack;
                $canInsert = min($remainingToPlace, $spaceLeftInRack);

                if ($canInsert > 0) {
                    $location = ProductLocation::query()->where('product_sku', $sku)
                        ->where('rack_id', $targetRack->id)
                        ->where('batch_code', $batch)
                        ->first();

                    if ($location) {
                        $location->increment('qty', $canInsert);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $sku,
                            'rack_id' => $targetRack->id,
                            'batch_code' => $batch,
                            'qty' => $canInsert,
                            'expired_at' => $expiry,
                        ]);
                    }

                    $remainingToPlace -= $canInsert;
                    $lastAssignedRackId = $targetRack->id;
                } else {
                    break;
                }
            }

            return $lastAssignedRackId;
        };

        $poPartialDate = Carbon::parse('2026-07-01');
        $poPartial = StockOrder::create([
            'order_no' => 'PO-' . $poPartialDate->format('Ymd') . '-0101', // Sesuai Order Date
            'type' => 'INBOUND',
            'supplier_id' => $suppliers->first()?->id,
            'status' => 'PARTIAL',
            'order_date' => $poPartialDate->format('Y-m-d'),
            'expected_date' => $poPartialDate->copy()->addDays(5)->format('Y-m-d'), // Estimasi: 6 Juli
            'created_at' => $poPartialDate,
        ]);

        StockOrderItem::create([
            'stock_order_id' => $poPartial->id,
            'product_sku' => $products->first()->sku,
            'qty_ordered' => 1000,
            'qty_fulfilled' => 400, // Baru masuk 400 pcs
            'unit_price' => 50000,
        ]);

        // Transaksi Penerimaan Kloter 1 (3 Juli)
        StockTransaction::create([
            'transaction_no' => 'TRX-IN-20260703-0001',
            'type' => 'IN',
            'date' => '2026-07-03',
            'user_id' => $defaultUser->id,
            'stock_order_id' => $poPartial->id,
            'created_at' => '2026-07-03 10:00:00',
        ]);


        // --- KASUS 2: COMPLETED ORDER VIA 2 KLOTER (Pengiriman Bertahap) ---
        $poCompleteDate = Carbon::parse('2026-07-05');
        $poCompleted = StockOrder::create([
            'order_no' => 'PO-' . $poCompleteDate->format('Ymd') . '-0102', // Sesuai Order Date
            'type' => 'INBOUND',
            'supplier_id' => $suppliers->first()?->id,
            'status' => 'COMPLETED',
            'order_date' => $poCompleteDate->format('Y-m-d'), // 5 Juli
            'expected_date' => $poCompleteDate->copy()->addDays(5)->format('Y-m-d'), // Estimasi: 10 Juli
            'created_at' => $poCompleteDate,
        ]);

        StockOrderItem::create([
            'stock_order_id' => $poCompleted->id,
            'product_sku' => $products->last()->sku,
            'qty_ordered' => 500,
            'qty_fulfilled' => 500, // 200 + 300 = 500 pcs (Selesai/Lunas)
            'unit_price' => 75000,
        ]);

        // Transaksi Kloter 1 (7 Juli)
        StockTransaction::create([
            'transaction_no' => 'TRX-IN-20260707-0005',
            'type' => 'IN',
            'date' => '2026-07-07',
            'user_id' => $defaultUser->id,
            'stock_order_id' => $poCompleted->id,
            'created_at' => '2026-07-07 09:00:00',
        ]);

        // Transaksi Kloter 2 / TERAKHIR (12 Juli) -> Ini Tanggal Selesai Aktualnya!
        StockTransaction::create([
            'transaction_no' => 'TRX-IN-20260712-0012',
            'type' => 'IN',
            'date' => '2026-07-12',
            'user_id' => $defaultUser->id,
            'stock_order_id' => $poCompleted->id,
            'created_at' => '2026-07-12 14:00:00',
        ]);

        // --- MUTASI TRANSAKSI BERJALAN ---
        $types = ['IN', 'IN', 'IN', 'IN', 'IN', 'OUT', 'OUT', 'MOVE', 'ADJUSTMENT'];

        for ($step = 1; $step <= 200; $step++) {
            $randomTimestamp = $faker->dateTimeBetween('2026-05-01 00:00:00', '2026-07-11 23:59:59');
            $forcedDate = $randomTimestamp->format('Y-m-d H:i:s');
            $dateString = $randomTimestamp->format('Ymd');
            $chosenType = $types[array_rand($types)];

            if (in_array($chosenType, ['OUT', 'ADJUSTMENT', 'MOVE'])) {
                $product = Product::query()->where('stock', '>', 0)->inRandomOrder()->first();
                if (! $product) {
                    $chosenType = 'IN';
                    $product = Product::query()->inRandomOrder()->first();
                }
            } else {
                $product = Product::query()->inRandomOrder()->first();
            }

            $qtyBefore = $product->stock;

            $typeCode = match ($chosenType) {
                'IN' => 'IN',
                'OUT' => 'OUT',
                'MOVE' => 'MOVE',
                'ADJUSTMENT' => 'ADJ',
                default => 'GEN'
            };

            $txNo = "TRX-{$typeCode}-{$dateString}-".sprintf('%04d', $step);
            $qty = rand(1, 15);
            $notes = 'Transaksi Seeder '.$chosenType;
            $expiredAt = now()->addMonths(rand(6, 18))->format('Y-m-d');
            $batchCode = $product->sku.'-'.date('Ymd', strtotime($expiredAt));
            $sourceRack = $racks->random();

            if ($chosenType === 'IN') {
                // 1. Buat Header PO di StockOrder
                $supplier = $suppliers->isEmpty() ? null : $suppliers->random();
                $po = StockOrder::create([
                    'order_no' => "PO-{$dateString}-".sprintf('%04d', $step),
                    'type' => 'INBOUND',
                    'supplier_id' => $supplier?->id,
                    'status' => 'COMPLETED',
                    'order_date' => date('Y-m-d', strtotime($forcedDate)),
                    'expected_date' => date('Y-m-d', strtotime($forcedDate.' +3 days')),
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 2. Buat Item PO
                StockOrderItem::create([
                    'stock_order_id' => $po->id,
                    'product_sku' => $product->sku,
                    'qty_ordered' => $qty,
                    'qty_fulfilled' => $qty,
                    'unit_price' => $product->purchase_price ?? 50000,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 3. Simpan Header Transaksi IN (dengan FK ke PO)
                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'IN',
                    'date' => date('Y-m-d', strtotime($forcedDate)),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => $po->id,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 4. Update Stok & Ledger
                $qtyAfter = $qtyBefore + $qty;
                $product->increment('stock', $qty);
                $actualRackId = $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, $qty);

                $this->insertLedger($product->sku, $txNo, 'IN', $sourceRack->id, $expiredAt, $qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $actualRackId,
                    'target_rack_id' => null,
                    'qty' => $qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $expiredAt,
                    'notes' => "Penerimaan barang dari PO {$po->order_no}",
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

            } elseif ($chosenType === 'OUT') {
                $maxOut = max(1, (int) floor($qtyBefore * 0.6));
                $qty = rand(1, min($qty, $maxOut));

                // 1. Buat Header SO di StockOrder
                $customer = $customers->isEmpty() ? null : $customers->random();
                $so = StockOrder::create([
                    'order_no' => "SO-{$dateString}-".sprintf('%04d', $step),
                    'type' => 'OUTBOUND',
                    'customer_id' => $customer?->id,
                    'status' => 'COMPLETED',
                    'order_date' => date('Y-m-d', strtotime($forcedDate)),
                    'expected_date' => date('Y-m-d', strtotime($forcedDate.' +2 days')),
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 2. Buat Item SO
                StockOrderItem::create([
                    'stock_order_id' => $so->id,
                    'product_sku' => $product->sku,
                    'qty_ordered' => $qty,
                    'qty_fulfilled' => $qty,
                    'unit_price' => $product->selling_price ?? 75000,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 3. Simpan Header Transaksi OUT (dengan FK ke SO)
                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'OUT',
                    'date' => date('Y-m-d', strtotime($forcedDate)),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => $so->id,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                // 4. Update Stok & Ledger
                $qtyAfter = $qtyBefore - $qty;
                $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, -$qty);

                $activeLoc = ProductLocation::query()->where('product_sku', $product->sku)->first();
                $actualRackId = $activeLoc ? $activeLoc->rack_id : $sourceRack->id;
                $product->decrement('stock', $qty);

                $this->insertLedger($product->sku, $txNo, 'OUT', $sourceRack->id, $expiredAt, -$qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $actualRackId,
                    'target_rack_id' => null,
                    'qty' => -$qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $expiredAt,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

            } elseif ($chosenType === 'MOVE') {
                // SIMPAN HEADER TRANSAKSI MOVE (Tanpa PO/SO)
                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'MOVE',
                    'date' => date('Y-m-d', strtotime($forcedDate)),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => null,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $locSource = ProductLocation::query()->where('product_sku', $product->sku)->where('qty', '>', 0)->first();
                if (! $locSource) {
                    $actualRackId = $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, $qty);
                    $locSource = ProductLocation::query()->where('product_sku', $product->sku)->first();
                }

                $sourceRackId = $locSource->rack_id;

                // PERBAIKAN BUG: Gunakan ID rak integer ($sourceRackId), bukan Object Model ($sourceRack)
                $availableTargetRack = $racks->where('id', '!=', $sourceRackId)->random();
                $qty = min($qty, $locSource->qty);
                if ($qty <= 0) {
                    $qty = 1;
                }

                $updateLocation($product->sku, $sourceRackId, $locSource->batch_code, $locSource->expired_at, -$qty);
                $targetRackId = $updateLocation($product->sku, $availableTargetRack->id, $locSource->batch_code, $locSource->expired_at, $qty);

                $this->insertLedger($product->sku, $txNo, 'MOVE', $sourceRackId, $locSource->expired_at, -$qty, $qtyBefore, $qtyBefore, $defaultUser->id, $notes.' (Keluar dari Rak)', $forcedDate);
                $this->insertLedger($product->sku, $txNo, 'MOVE', $targetRackId, $locSource->expired_at, $qty, $qtyBefore, $qtyBefore, $defaultUser->id, $notes.' (Masuk ke Rak)', $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $sourceRackId,
                    'target_rack_id' => $targetRackId,
                    'qty' => $qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyBefore,
                    'expired_at' => $locSource->expired_at,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

            } elseif ($chosenType === 'ADJUSTMENT') {
                // SIMPAN HEADER TRANSAKSI ADJUSTMENT (Tanpa PO/SO)
                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'ADJUSTMENT',
                    'date' => date('Y-m-d', strtotime($forcedDate)),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => null,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $maxAdj = max(1, (int) floor($qtyBefore * 0.4));
                $qty = rand(1, min($qty, $maxAdj));
                $qtyAfter = $qtyBefore - $qty;

                $updateLocation($product->sku, $sourceRack->id, $batchCode, $expiredAt, -$qty);

                $activeLoc = ProductLocation::query()->where('product_sku', $product->sku)->first();
                $actualRackId = $activeLoc ? $activeLoc->rack_id : $sourceRack->id;

                $product->decrement('stock', $qty);

                $this->insertLedger($product->sku, $txNo, 'ADJUSTMENT', $sourceRack->id, $expiredAt, -$qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $actualRackId,
                    'target_rack_id' => null,
                    'qty' => -$qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $expiredAt,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);
            }
        }

        // DATA TRANSAKSI PEMBATALAN (SOFT DELETED TRASH)
        $testProduct = $products->first();
        $testRack = $racks->first();
        $cancelledTypes = ['IN', 'OUT', 'MOVE'];

        for ($j = 1; $j <= 3; $j++) {
            $chosenCancelType = $cancelledTypes[$j - 1];
            $cancelledTrx = StockTransaction::factory()->cancelled($chosenCancelType)->create([
                'user_id' => $defaultUser->id,
            ]);

            $cancelNotes = match ($chosenCancelType) {
                'IN' => 'Pembatalan barang masuk dari Supplier',
                'OUT' => 'Pembatalan barang keluar ke Customer',
                'MOVE' => 'Pembatalan mutasi antar rak dalam gudang',
                default => 'Transaksi dibatalkan'
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

    /**
     * Helper method to insert stock ledger entries.
     */
    private function insertLedger($sku, $txNo, $type, $rackId, $expiredAt, $qty, $balanceBefore, $balanceAfter, $userId, $note, $forcedDate): void
    {
        DB::table('stock_ledgers')->insert([
            'product_sku' => $sku,
            'transaction_no' => $txNo,
            'type' => $type,
            'rack_id' => $rackId,
            'expired_at' => $expiredAt,
            'qty' => $qty,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => $forcedDate,
            'updated_at' => $forcedDate,
        ]);
    }
}
