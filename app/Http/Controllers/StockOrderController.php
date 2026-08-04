<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockOrderStoreRequest;
use App\Http\Requests\StockOrderUpdateRequest;
use App\Http\Resources\StockOrderResource;
use App\Models\StockOrder;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOrderController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $status = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $hasStartDate = $request->filled('start_date');
        $hasEndDate = $request->filled('end_date');

        $orders = StockOrder::query()
            ->with(['supplier', 'customer', 'items.product', 'parent'])
            ->when(! empty($type), fn ($q) => $q->where('type', $type))
            ->when(! empty($status), fn ($q) => $q->where('status', $status))
            ->when($hasStartDate && $hasEndDate, function ($query) use ($startDate, $endDate): void {
                $start = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                $end = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
                $query->whereBetween('order_date', [$start, $end]);
            })
            ->when($hasStartDate && ! $hasEndDate, function ($query) use ($startDate): void {
                $start = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                $query->where('order_date', '>=', $start);
            })
            ->when($hasEndDate && ! $hasStartDate, function ($query) use ($endDate): void {
                $end = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
                $query->where('order_date', '<=', $end);
            })
                ->latest()
                ->paginate($request->per_page ?? 10);

        return StockOrderResource::collection($orders);
    }

    public function store(StockOrderStoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. GENERATE NOMOR ORDER AUTOMATIS (PO-YYYYMMDD-001 / SO-YYYYMMDD-001)
            $prefix = match ($request->type) {
                'INBOUND'     => 'PO',
                'OUTBOUND'    => 'SO',
                'RETURN_IN'   => 'RE-IN',
                'RETURN_OUT'  => 'RE-OUT',
                default       => 'ORD'
            };

            $today = Carbon::today()->format('Ymd');

            $lastOrder = StockOrder::query()->where('order_no', 'like', "{$prefix}-{$today}-%")
                ->orderBy('order_no', 'desc')
                ->first();

            $nextSeq = $lastOrder ? ((int) substr($lastOrder->order_no, -3)) + 1 : 1;
            $orderNo = sprintf('%s-%s-%03d', $prefix, $today, $nextSeq);

            // 2. SIMPAN HEADER ORDER
            $order = StockOrder::create([
                'order_no' => $orderNo,
                'type' => $request->type,
                'supplier_id' => in_array($request->type, ['INBOUND', 'RETURN_OUT']) ? $request->supplier_id : null,
                'customer_id' => in_array($request->type, ['OUTBOUND', 'RETURN_IN']) ? $request->customer_id : null,
                'status' => 'PENDING',
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'parent_id' => $request->parent_id ?? null,
                'notes' => $request->notes ?? null,
            ]);

            // 3. SIMPAN DETAIL ITEMS
            foreach ($request->items as $item) {
                $product = Product::query()->where('sku', $item['product_sku'])->first();
                $defaultPrice = in_array($request->type, ['INBOUND', 'RETURN_OUT'])
                    ? ($product?->purchase_price ?? 0)
                    : ($product?->selling_price ?? 0);

                $order->items()->create([
                    'product_sku' => $item['product_sku'],
                    'qty_ordered' => $item['qty_ordered'],
                    'qty_fulfilled' => 0,
                    'unit_price' => $item['unit_price'] ?? $defaultPrice,
                ]);
            }

            return new StockOrderResource($order->load(['supplier', 'customer', 'items.product']));
        });
    }

    public function show($id)
    {
        $order = StockOrder::with([
            'supplier',
            'customer',
            'parent',
            'backorders',
            'items.product',
            'stockTransactions.items'
            // 'transactions.items.product',
            // 'transactions.items.rack',
            // 'transactions.user'
        ])->findOrFail($id);

        return new StockOrderResource($order);
    }

    public function update(StockOrderUpdateRequest $request, $id)
    {
       $order = StockOrder::with('items')->findOrFail($id);

        if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'message' => 'Order yang sudah Selesai atau Dibatalkan tidak dapat diubah.',
            ], 422);
        }

        $hasFulfilledItems = $order->items->pluck('qty_fulfilled')->sum() > 0;
        if ($hasFulfilledItems && $request->has('items')) {
            return response()->json([
                'message' => 'Item pada order ini sudah diproses sebagian di gudang dan tidak dapat diubah lagi! Gunakan tombol "Batal Sisa" jika ada perubahan.',
            ], 422);
        }

        DB::transaction(function () use ($request, $order, $hasFulfilledItems): void {
            $order->update($request->only(['supplier_id', 'customer_id', 'status', 'order_date', 'expected_date', 'parent_id', 'cancel_reason', 'notes',
            ]));

            if ($request->has('items') && ! $hasFulfilledItems) {
                $order->items()->delete();
                foreach ($request->items as $item) {
                    $product = Product::query()->where('sku', $item['product_sku'])->first();
                    $defaultPrice = $order->type === 'INBOUND'
                        ? ($product?->purchase_price ?? 0)
                        : ($product?->selling_price ?? 0);

                    $order->items()->create([
                        'product_sku' => $item['product_sku'],
                        'qty_ordered' => $item['qty_ordered'],
                        'qty_fulfilled' => 0,
                        'unit_price' => $item['unit_price'] ?? $defaultPrice,
                    ]);
                }
            }
        });

        return new StockOrderResource($order->fresh(['supplier', 'customer', 'items.product']));
    }

    public function closeRemaining(Request $request, $id)
    {
        $order = StockOrder::findOrFail($id);

        if ($order->status === 'COMPLETED') {
            return response()->json(['message' => 'Order ini sudah selesai.'], 422);
        }

        // Tandai status sebagai COMPLETED / SHORT_CLOSED
        $order->update([
            'status' => 'COMPLETED',
            'notes' => trim(($order->notes ?? '') . " | Sisa pesanan dibatalkan/Short-Closed: " . ($request->reason ?? 'Tanpa alasan')),
        ]);

        return response()->json([
            'message' => "Sisa barang pada order {$order->order_no} berhasil dibatalkan dan status ditutup.",
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $order = StockOrder::findOrFail($id);
        $order->update(['status' => 'CANCELLED']);

        return response()->json([
            'message' => "Order {$order->order_no} berhasil dibatalkan.",
        ]);
    }
}
