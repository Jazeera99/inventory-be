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

        if ($products->isEmpty() || $racks->isEmpty()) {
            $this->command->warn('Skip Seeder: Pastikan Product dan Rack sudah ada isinya!');
            return;
        }

        // 1. Bersihkan database agar bersih total
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_locations')->truncate();
        DB::table('stock_orders')->truncate();
        DB::table('stock_order_items')->truncate();
        DB::table('stock_transactions')->delete();
        DB::table('stock_transaction_items')->truncate();
        DB::table('stock_ledgers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Reset stok global product menjadi 0 di awal
        foreach ($products as $p) {
            $p->update(['stock' => 0]);
        }

        // Helper untuk penambahan / pengurangan lokasi stok rak
        $updateLocation = function ($sku, $batch, $expiry, $changeQty) use (&$racks) {
            if ($changeQty < 0) {
                $qtyNeeded = abs($changeQty);
                $locations = ProductLocation::query()
                    ->where('product_sku', $sku)
                    ->where('qty', '>', 0)
                    ->get();

                $lastUsedRackId = null;
                foreach ($locations as $loc) {
                    if ($qtyNeeded <= 0) break;
                    $take = min($loc->qty, $qtyNeeded);

                    if ($loc->qty - $take <= 0) {
                        $loc->delete();
                    } else {
                        $loc->decrement('qty', $take);
                    }
                    $qtyNeeded -= $take;
                    $lastUsedRackId = $loc->rack_id;
                }
                return $lastUsedRackId;
            }

            // Penambahan barang (IN)
            $remainingToPlace = $changeQty;
            $lastAssignedRackId = null;

            while ($remainingToPlace > 0) {
                // Cari rak aktif yang total isinya < 15
                $targetRack = Rack::query()
                    ->where('is_active', true)
                    ->where('is_maintenance', false)
                    ->get()
                    ->first(function ($r) {
                        return ProductLocation::query()->where('rack_id', $r->id)->sum('qty') < 15;
                    });

                if (!$targetRack) break;

                $totalUsedInRack = ProductLocation::query()->where('rack_id', $targetRack->id)->sum('qty');
                $spaceLeftInRack = 15 - $totalUsedInRack;

                if ($spaceLeftInRack <= 0) continue;

                $canInsert = min($remainingToPlace, $spaceLeftInRack);

                $location = ProductLocation::query()
                    ->where('product_sku', $sku)
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
            }

            return $lastAssignedRackId;
        };

        // Helper untuk menghitung total kapasitas tersisa di seluruh rak gudang
        $getAvailableWarehouseSpace = function() {
            $racks = Rack::where('is_active', true)->where('is_maintenance', false)->get();
            $totalSpace = 0;
            foreach ($racks as $r) {
                $used = ProductLocation::where('rack_id', $r->id)->sum('qty');
                $totalSpace += max(0, 15 - $used);
            }
            return $totalSpace;
        };

        // =========================================================================
        // STEP 1: SOLUSI SALDO MINUS -> BARANG WAJIB DIAWALI TRANSAKSI IN (STOK AWAL)
        // =========================================================================
        $startDate = Carbon::parse('2026-05-01 08:00:00');
        $stepCounter = 1;

        foreach ($products as $product) {
            $availSpace = $getAvailableWarehouseSpace();
            if ($availSpace <= 0) break; // Jika seluruh gudang sudah penuh, stop

            $initQty = rand(5, min(15, $availSpace));
            if ($initQty <= 0) continue;

            $dateStr = $startDate->format('Ymd');
            $forcedDate = $startDate->format('Y-m-d H:i:s');
            $txNo = "TRX-IN-{$dateStr}-" . sprintf('%04d', $stepCounter++);
            $expiredAt = Carbon::parse('2027-05-10')->format('Y-m-d');
            $batchCode = $product->sku . '-20270510';

            $po = StockOrder::create([
                'order_no' => "PO-{$dateStr}-" . sprintf('%04d', $stepCounter),
                'type' => 'INBOUND',
                'supplier_id' => $suppliers->first()?->id,
                'status' => 'COMPLETED',
                'order_date' => $startDate->format('Y-m-d'),
                'expected_date' => $startDate->copy()->addDays(2)->format('Y-m-d'),
                'created_at' => $forcedDate,
                'updated_at' => $forcedDate,
            ]);

            StockOrderItem::create([
                'stock_order_id' => $po->id,
                'product_sku' => $product->sku,
                'qty_ordered' => $initQty,
                'qty_fulfilled' => $initQty,
                'unit_price' => $product->purchase_price ?? 50000,
                'created_at' => $forcedDate,
                'updated_at' => $forcedDate,
            ]);

            DB::table('stock_transactions')->insert([
                'transaction_no' => $txNo,
                'type' => 'IN',
                'date' => $startDate->format('Y-m-d'),
                'user_id' => $defaultUser->id,
                'stock_order_id' => $po->id,
                'created_at' => $forcedDate,
                'updated_at' => $forcedDate,
            ]);

            $actualRackId = $updateLocation($product->sku, $batchCode, $expiredAt, $initQty);

            $realStockNow = ProductLocation::query()->where('product_sku', $product->sku)->sum('qty');
            $product->update(['stock' => $realStockNow]);

            $this->insertLedger($product->sku, $txNo, 'IN', $actualRackId, $expiredAt, $initQty, 0, $initQty, $defaultUser->id, 'Penerimaan Stok Awal', $forcedDate);

            DB::table('stock_transaction_items')->insert([
                'transaction_no' => $txNo,
                'product_sku' => $product->sku,
                'rack_id' => $actualRackId,
                'target_rack_id' => null,
                'qty' => $initQty,
                'qty_before' => 0,
                'qty_after' => $initQty,
                'expired_at' => $expiredAt,
                'notes' => 'Penerimaan Stok Awal Seeder',
                'created_at' => $forcedDate,
                'updated_at' => $forcedDate,
            ]);
        }

        // =========================================================================
        // STEP 2: SIMULASI TRANSAKSI KRONOLOGIS
        // =========================================================================
        $types = ['IN', 'IN', 'OUT', 'OUT', 'MOVE', 'ADJUSTMENT'];
        $currentDate = $startDate->copy()->addHours(6);
        $endDate = Carbon::now();

        while ($currentDate->lessThan($endDate)) {
            $currentDate->addHours(rand(4, 12));
            if ($currentDate->greaterThan($endDate)) break;

            $forcedDate = $currentDate->format('Y-m-d H:i:s');
            $dateString = $currentDate->format('Ymd');
            $chosenType = $types[array_rand($types)];

            // Jika Gudang Penuh, alihkan IN menjadi OUT/MOVE
            if ($chosenType === 'IN' && $getAvailableWarehouseSpace() <= 0) {
                $chosenType = 'OUT';
            }

            if (in_array($chosenType, ['OUT', 'ADJUSTMENT', 'MOVE'])) {
                $product = Product::query()->where('stock', '>', 2)->inRandomOrder()->first();
                if (!$product) {
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

            $txNo = "TRX-{$typeCode}-{$dateString}-" . sprintf('%04d', $stepCounter++);
            $notes = 'Transaksi Seeder ' . $chosenType;

            if ($chosenType === 'IN') {
                $availSpace = $getAvailableWarehouseSpace();
                if ($availSpace <= 0) continue;

                $qty = rand(1, min(10, $availSpace));
                $expiredAt = $currentDate->copy()->addMonths(rand(6, 18))->format('Y-m-d');
                $batchCode = $product->sku . '-' . date('Ymd', strtotime($expiredAt));

                $po = StockOrder::create([
                    'order_no' => "PO-{$dateString}-" . sprintf('%04d', $stepCounter),
                    'type' => 'INBOUND',
                    'supplier_id' => $suppliers->random()?->id,
                    'status' => 'COMPLETED',
                    'order_date' => $currentDate->format('Y-m-d'),
                    'expected_date' => $currentDate->copy()->addDays(2)->format('Y-m-d'),
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                StockOrderItem::create([
                    'stock_order_id' => $po->id,
                    'product_sku' => $product->sku,
                    'qty_ordered' => $qty,
                    'qty_fulfilled' => $qty,
                    'unit_price' => $product->purchase_price ?? 50000,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'IN',
                    'date' => $currentDate->format('Y-m-d'),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => $po->id,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $actualRackId = $updateLocation($product->sku, $batchCode, $expiredAt, $qty);

                $qtyAfter = $qtyBefore + $qty;
                $product->update(['stock' => $qtyAfter]);

                $this->insertLedger($product->sku, $txNo, 'IN', $actualRackId, $expiredAt, $qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $actualRackId,
                    'target_rack_id' => null,
                    'qty' => $qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $expiredAt,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

            } elseif ($chosenType === 'OUT') {
                $existingLoc = ProductLocation::query()->where('product_sku', $product->sku)->where('qty', '>', 0)->first();
                if (!$existingLoc) continue;

                $qty = rand(1, min(5, $existingLoc->qty));
                $qtyAfter = $qtyBefore - $qty;

                $so = StockOrder::create([
                    'order_no' => "SO-{$dateString}-" . sprintf('%04d', $stepCounter),
                    'type' => 'OUTBOUND',
                    'customer_id' => $customers->random()?->id,
                    'status' => 'COMPLETED',
                    'order_date' => $currentDate->format('Y-m-d'),
                    'expected_date' => $currentDate->copy()->addDays(2)->format('Y-m-d'),
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                StockOrderItem::create([
                    'stock_order_id' => $so->id,
                    'product_sku' => $product->sku,
                    'qty_ordered' => $qty,
                    'qty_fulfilled' => $qty,
                    'unit_price' => $product->selling_price ?? 75000,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'OUT',
                    'date' => $currentDate->format('Y-m-d'),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => $so->id,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $updateLocation($product->sku, $existingLoc->batch_code, $existingLoc->expired_at, -$qty);
                $product->decrement('stock', $qty);

                $this->insertLedger($product->sku, $txNo, 'OUT', $existingLoc->rack_id, $existingLoc->expired_at, -$qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $existingLoc->rack_id,
                    'target_rack_id' => null,
                    'qty' => -$qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $existingLoc->expired_at,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

            } elseif ($chosenType === 'MOVE') {
                $locSource = ProductLocation::query()->where('product_sku', $product->sku)->where('qty', '>', 0)->first();
                if (!$locSource) continue;

                $targetRack = $racks->where('id', '!=', $locSource->rack_id)->first(function ($r) {
                    return ProductLocation::query()->where('rack_id', $r->id)->sum('qty') < 15;
                });

                if (!$targetRack) continue;

                $spaceInTarget = 15 - ProductLocation::query()->where('rack_id', $targetRack->id)->sum('qty');
                $qty = rand(1, min(5, $locSource->qty, $spaceInTarget));

                if ($qty <= 0) continue;

                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'MOVE',
                    'date' => $currentDate->format('Y-m-d'),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => null,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $updateLocation($product->sku, $locSource->batch_code, $locSource->expired_at, -$qty);
                $targetRackId = $updateLocation($product->sku, $locSource->batch_code, $locSource->expired_at, $qty);

                $this->insertLedger($product->sku, $txNo, 'MOVE', $locSource->rack_id, $locSource->expired_at, -$qty, $qtyBefore, $qtyBefore, $defaultUser->id, $notes . ' (Keluar Rak)', $forcedDate);
                $this->insertLedger($product->sku, $txNo, 'MOVE', $targetRackId, $locSource->expired_at, $qty, $qtyBefore, $qtyBefore, $defaultUser->id, $notes . ' (Masuk Rak)', $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $locSource->rack_id,
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
                $existingLoc = ProductLocation::query()->where('product_sku', $product->sku)->where('qty', '>', 0)->first();
                if (!$existingLoc) continue;

                $qty = rand(1, min(3, $existingLoc->qty));
                $qtyAfter = $qtyBefore - $qty;

                DB::table('stock_transactions')->insert([
                    'transaction_no' => $txNo,
                    'type' => 'ADJUSTMENT',
                    'date' => $currentDate->format('Y-m-d'),
                    'user_id' => $defaultUser->id,
                    'stock_order_id' => null,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);

                $updateLocation($product->sku, $existingLoc->batch_code, $existingLoc->expired_at, -$qty);
                $product->decrement('stock', $qty);

                $this->insertLedger($product->sku, $txNo, 'ADJUSTMENT', $existingLoc->rack_id, $existingLoc->expired_at, -$qty, $qtyBefore, $qtyAfter, $defaultUser->id, $notes, $forcedDate);

                DB::table('stock_transaction_items')->insert([
                    'transaction_no' => $txNo,
                    'product_sku' => $product->sku,
                    'rack_id' => $existingLoc->rack_id,
                    'target_rack_id' => null,
                    'qty' => -$qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'expired_at' => $existingLoc->expired_at,
                    'notes' => $notes,
                    'created_at' => $forcedDate,
                    'updated_at' => $forcedDate,
                ]);
            }
        }

        // =========================================================================
        // STEP 3: RE-SYNC TOTAL STOK GLOBAL
        // =========================================================================
        foreach (Product::all() as $prod) {
            $realTotalStock = ProductLocation::query()->where('product_sku', $prod->sku)->sum('qty');
            $prod->update(['stock' => $realTotalStock]);
        }
    }

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
