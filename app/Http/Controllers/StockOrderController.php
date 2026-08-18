<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockOrderStoreRequest;
use App\Http\Requests\StockOrderUpdateRequest;
use App\Http\Resources\StockOrderResource;
use App\Models\StockOrder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isStaff = !$user->hasRole('Superadmin') && !in_array('*', $user->role->permissions ?? []);

        $type = $request->input('type');
        $status = $request->input('status');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $hasStartDate = $request->filled('start_date');
        $hasEndDate = $request->filled('end_date');

        $hasExpStartDate = $request->filled('exp_start_date');
        $hasExpEndDate = $request->filled('exp_end_date');
        $expStartDate = $request->input('exp_start_date');
        $expEndDate = $request->input('exp_end_date');

        $orders = StockOrder::query()
            ->with(['supplier', 'customer', 'items.product', 'parent.stockTransactions.items'])
            ->when($isStaff, function ($q) {
                $q->where('status', '!=', 'DRAFT');
            })
            ->when(! empty($type), fn ($q) => $q->where('type', $type))
            ->when(! empty($status), fn ($q) => $q->where('status', $status))

            ->when(! empty($search), function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('order_no', 'like', "%{$search}%")
                        ->orWhere('id', $search);
                });
            })

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
            ->when($hasExpStartDate && $hasExpEndDate, function ($query) use ($expStartDate, $expEndDate): void {
                $start = Carbon::parse($expStartDate)->startOfDay()->toDateTimeString();
                $end = Carbon::parse($expEndDate)->endOfDay()->toDateTimeString();
                $query->whereBetween('expected_date', [$start, $end]);
            })
            ->when($hasExpStartDate && ! $hasExpEndDate, function ($query) use ($expStartDate): void {
                $start = Carbon::parse($expStartDate)->startOfDay()->toDateTimeString();
                $query->where('expected_date', '>=', $start);
            })
            ->when($hasExpEndDate && ! $hasExpStartDate, function ($query) use ($expEndDate): void {
                $end = Carbon::parse($expEndDate)->endOfDay()->toDateTimeString();
                $query->where('expected_date', '<=', $end);
            })
            ->orderByRaw("CASE WHEN status IN ('DRAFT', 'PENDING') THEN 1 WHEN status = 'PARTIAL' THEN 2 ELSE 3 END")
            ->orderBy('expected_date', 'asc')
            ->orderBy('id', 'desc')
            ->latest()
            ->paginate($request->per_page ?? 10);

        return StockOrderResource::collection($orders);
    }

    public function store(StockOrderStoreRequest $request)
    {
        if (! $request->user()->can('Kelola Order') && ! $request->user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membuat dokumen order.'], 403);
        }

        if (in_array($request->type, ['INBOUND', 'OUTBOUND'])) {
            if ($request->supplier_id) {
                $supplier = Supplier::find($request->supplier_id);
                if ($supplier && ! $supplier->is_active) {
                    abort(422, 'Supplier ini sedang tidak aktif dan tidak dapat digunakan untuk PO baru.');
                }
            }
            if ($request->customer_id) {
                $customer = Customer::find($request->customer_id);
                if ($customer && ! $customer->is_active) {
                    abort(422, 'Customer ini sedang tidak aktif dan tidak dapat digunakan untuk SO baru.');
                }
            }
        }

        return DB::transaction(function () use ($request) {
            $parent = null;
            if (in_array($request->type, ['RETURN_IN', 'RETURN_OUT'])) {
                $parent = StockOrder::with(['items', 'returnOrders.items'])->findOrFail($request->parent_id);
                $expectedParentType = $request->type === 'RETURN_OUT' ? 'INBOUND' : 'OUTBOUND';

                if ($parent->type !== $expectedParentType) {
                    abort(422, 'Dokumen asal retur tidak sesuai. Retur ke supplier harus berasal dari PO, retur dari customer harus berasal dari SO.');
                }

                $this->validateReturnItems($request->items, $parent, $request->type);
            }

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

            $initialStatus = $request->input('status', 'DRAFT');
             $orderDate = $request->order_date ? Carbon::parse($request->order_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $expectedDate = $request->expected_date ? Carbon::parse($request->expected_date)->format('Y-m-d') : null;
            // 2. SIMPAN HEADER ORDER
            $order = StockOrder::create([
                'order_no' => $orderNo,
                'type' => $request->type,
                'supplier_id' => $parent?->supplier_id ?? (in_array($request->type, ['INBOUND', 'RETURN_OUT']) ? $request->supplier_id : null),
                'customer_id' => $parent?->customer_id ?? (in_array($request->type, ['OUTBOUND', 'RETURN_IN']) ? $request->customer_id : null),
                'status' => $initialStatus,
                'order_date' => $orderDate,
                'expected_date' => $expectedDate,
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

    public static function updateOrderStatusProgress(StockOrder $order): void
    {
        if (in_array($order->status, ['CANCELLED', 'COMPLETED'])) {
            return;
        }

        $order->load('items');
        $totalOrdered = $order->items->sum('qty_ordered');
        $totalFulfilled = $order->items->sum('qty_fulfilled');

        if ($totalFulfilled >= $totalOrdered && $totalOrdered > 0) {
            $order->update(['status' => 'COMPLETED']);
        } elseif ($totalFulfilled > 0) {
            $order->update(['status' => 'PARTIAL']);
        } else {
            // Jika belum ada barang yang fulfilled sama sekali
            if ($order->status !== 'DRAFT') {
                $order->update(['status' => 'PENDING']);
            }
        }
    }

    /** Daftar PO/SO dan sisa barang yang masih dapat diretur. */
    public function returnable(Request $request)
    {
        $returnType = $request->validate(['type' => 'required|in:RETURN_IN,RETURN_OUT'])['type'];
        $sourceType = $returnType === 'RETURN_OUT' ? 'INBOUND' : 'OUTBOUND';

        $orders = StockOrder::with(['supplier', 'customer', 'items.product', 'returnOrders.items'])
            ->where('type', $sourceType)
            ->whereNotIn('status', ['DRAFT', 'CANCELLED'])
            ->latest()
            ->get()
            ->map(function (StockOrder $order) use ($returnType) {
                $items = $order->items->map(function ($item) use ($order, $returnType) {
                    $alreadyReturned = $order->returnOrders
                        ->where('type', $returnType)
                        ->sum(fn ($return) => $return->items->where('product_sku', $item->product_sku)->sum('qty_ordered'));
                    $available = max(0, $item->qty_fulfilled - $alreadyReturned);

                    return [
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product?->product_name,
                        'qty_available_for_return' => $available,
                        'unit_price' => (float) $item->unit_price,
                    ];
                })->filter(fn ($item) => $item['qty_available_for_return'] > 0)->values();

                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'supplier_id' => $order->supplier_id,
                    'customer_id' => $order->customer_id,
                    'supplier' => $order->supplier,
                    'customer' => $order->customer,
                    'items' => $items,
                ];
            })
            ->filter(fn ($order) => $order['items']->isNotEmpty())
            ->values();

        return response()->json(['data' => $orders]);
    }

    private function validateReturnItems(array $items, StockOrder $parent, string $returnType, ?int $ignoreReturnId = null): void
    {
        foreach ($items as $item) {
            $sourceItem = $parent->items->firstWhere('product_sku', $item['product_sku']);
            $alreadyReturned = $parent->returnOrders
                ->where('type', $returnType)
                ->when($ignoreReturnId, fn ($returns) => $returns->where('id', '!=', $ignoreReturnId))
                ->sum(fn ($return) => $return->items->where('product_sku', $item['product_sku'])->sum('qty_ordered'));
            $available = max(0, (int) ($sourceItem?->qty_fulfilled ?? 0) - $alreadyReturned);

            if (! $sourceItem || $item['qty_ordered'] > $available) {
                abort(422, "Qty retur {$item['product_sku']} melebihi qty yang dapat diretur ({$available}).");
            }
        }
    }

    public function show($id)
    {
        $order = StockOrder::with([
            'supplier',
            'customer',
            'parent.stockTransactions.items',
            'backorders',
            'items.product',
            'stockTransactions.items',
            'stocktransactions.items.product'
            // 'transactions.items.rack',
            // 'transactions.user'
        ])->findOrFail($id);

        return response()->json([
            'data' => $order
        ]);

        return new StockOrderResource($order);
    }

    public function update(StockOrderUpdateRequest $request, $id)
    {
        if (! $request->user()->can('Kelola Order') && ! $request->user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah dokumen order.'], 403);
        }

        $order = StockOrder::with('items')->findOrFail($id);

        // if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
        //     return response()->json([
        //         'message' => 'Order yang sudah Selesai atau Dibatalkan tidak dapat diubah.',
        //     ], 422);
        // }

        if ($order->status !== 'DRAFT'&& ! $request->user()->hasRole('Superadmin')) {
            // Jika dokumen bukan DRAFT, batasi edit! Hanya izinkan ubah status ke CANCELLED
            if (! ($request->has('status') && $request->status === 'CANCELLED')) {
                return response()->json([
                    'message' => 'Dokumen yang sudah diproses (' . $order->status . ') tidak dapat diubah lagi. Hanya dokumen DRAFT yang dapat diedit.',
                ], 422);
            }
        }

        if (in_array($order->type, ['RETURN_IN', 'RETURN_OUT']) && $request->has('items')) {
            // Dokumen retur lama mungkin dibuat sebelum relasi dokumen asal diwajibkan.
            // Gunakan pilihan user untuk melengkapi relasi tersebut sekali ini.
            $parentId = $order->parent_id ?? $request->input('parent_id');
            if (! $parentId) {
                abort(422, 'Dokumen asal wajib dipilih untuk retur.');
            }

            $parent = StockOrder::with(['items', 'returnOrders.items'])->findOrFail($parentId);
            $expectedParentType = $order->type === 'RETURN_OUT' ? 'INBOUND' : 'OUTBOUND';
            if ($parent->type !== $expectedParentType) {
                abort(422, 'Dokumen asal retur tidak sesuai.');
            }
            $this->validateReturnItems($request->items, $parent, $order->type, $order->id);
        }

        DB::transaction(function () use ($request, $order): void {
            $fields = [
                'supplier_id', 'customer_id', 'status', 'order_date', 'expected_date', 'parent_id', 'cancel_reason', 'notes',
            ];
            // Relasi retur ke PO/SO asal bersifat permanen setelah dokumen dibuat.
            if (in_array($order->type, ['RETURN_IN', 'RETURN_OUT']) && $order->parent_id) {
                $fields = array_values(array_diff($fields, ['parent_id', 'supplier_id', 'customer_id']));
            }
            $order->update($request->only($fields));

            if ($request->has('items')) {
                $existingItems = $order->items->keyBy('product_sku');
                $newSkus = collect($request->items)->pluck('product_sku')->toArray();

                // Hapus item yang tidak ada lagi di payload (hanya jika qty_fulfilled == 0)
                foreach ($order->items as $existingItem) {
                    if (!in_array($existingItem->product_sku, $newSkus) && $existingItem->qty_fulfilled == 0) {
                        $existingItem->delete();
                    }
                }

                foreach ($request->items as $item) {
                    $product = Product::query()->where('sku', $item['product_sku'])->first();
                    $defaultPrice = in_array($order->type, ['INBOUND', 'RETURN_OUT'])
                        ? ($product?->purchase_price ?? 0)
                        : ($product?->selling_price ?? 0);

                    $existing = $existingItems->get($item['product_sku']);
                    $fulfilled = $existing ? $existing->qty_fulfilled : 0;
                    $unitPrice = $item['unit_price'] ?? ($existing ? $existing->unit_price : $defaultPrice);

                    if ($existing) {
                        $existing->update([
                            'qty_ordered' => max($item['qty_ordered'], $fulfilled),
                            'unit_price' => $unitPrice,
                        ]);
                    } else {
                        $order->items()->create([
                            'product_sku' => $item['product_sku'],
                            'qty_ordered' => $item['qty_ordered'],
                            'qty_fulfilled' => 0,
                            'unit_price' => $unitPrice,
                        ]);
                    }
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
        if (! $request->user()->can('Kelola Order') && ! $request->user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membatalkan order.'], 403);
        }

        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $order = StockOrder::findOrFail($id);
        $order->update(['status' => 'CANCELLED']);

        return response()->json([
            'message' => "Order {$order->order_no} berhasil dibatalkan.",
        ]);
    }

    public function printPdf($id)
    {
        $order = StockOrder::with([
            'supplier', 'customer', 'items.product'
        ])->findOrFail($id);

        $user = auth()->user();
        $isSuperadmin = $user?->hasRole('Superadmin') || $user?->can('Lihat Harga');
        $pdf = Pdf::loadView('pdf.stock-order', compact('order', 'isSuperadmin'))
                ->setPaper('a4', 'portrait');

        return $pdf->download("Stock-Order-{$order->order_no}.pdf");
    }
}
