<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Rack;
use App\Models\StockOrder;
use App\Models\StockOrderItem;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $supplier = Supplier::query()->first();
        $customer = Customer::query()->first();
        $user = User::query()->first();
        $rack = Rack::query()->first();

        if ($products->isEmpty() || ! $rack || ! $user) {
            return;
        }

        DB::transaction(function () use ($products, $supplier, $customer, $user, $rack) {
            $poNo = 'PO-'.now()->format('Ymd').'-001';

        // 1. Sampel Purchase Order (INBOUND)
        $po = StockOrder::create([
            'order_no' => $poNo,
                'type' => 'INBOUND',
                'supplier_id' => $supplier?->id,
                'status' => 'COMPLETED',
                'order_date' => now(),
                'expected_date' => now()->addDays(2),
        ]);

        foreach ($products->take(2) as $product) {
            StockOrderItem::create([
                'stock_order_id' => $po->id,
                'product_sku' => $product->sku,
                'qty_ordered' => 100,
                'qty_fulfilled' => 100,
                'unit_price' => $product->purchase_price ?? 60000,
            ]);
        }

        $trxIn = StockTransaction::create([
                'transaction_no' => 'TRX-IN-'.now()->format('Ymd').'-0001',
                'type' => 'IN',
                'date' => now(),
                'user_id' => $user->id,
                'stock_order_id' => $po->id, // Terhubung ke PO!
            ]);

            foreach ($products->take(2) as $product) {
                $trxIn->items()->create([
                    'product_sku' => $product->sku,
                    'qty' => 100,
                    'qty_before' => 0,
                    'qty_after' => 100,
                    'rack_id' => $rack->id,
                    'expired_at' => now()->addYear()->format('Y-m-d'),
                    'notes' => "Penerimaan barang dari order {$po->order_no}",
                ]);
            }

            $soNo = 'SO-'.now()->format('Ymd').'-001';

        // 2. Sampel Sales Order (OUTBOUND)
        $so = StockOrder::create([
            'order_no' => $soNo,
            'type' => 'OUTBOUND',
            'customer_id' => $customer?->id,
            'status' => 'PARTIAL',
            'order_date' => now(),
            'expected_date' => now()->addDays(1),
        ]);

        foreach ($products->take(2) as $product) {
            StockOrderItem::create([
                'stock_order_id' => $so->id,
                'product_sku' => $product->sku,
                'qty_ordered' => 500,
                'qty_fulfilled' => 0,
                'unit_price' => $product->selling_price ?? 100000,
            ]);
            }
        });
    }
}
