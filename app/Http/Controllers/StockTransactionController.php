<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockTransactionStoreRequest;
use App\Http\Resources\StockTransactionResource;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockLedger;
use App\Models\StockTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = StockTransaction::query()
            ->with(['user', 'deletedByUser', 'items.product', 'items.rack', 'items.targetRack'])
            ->when($request->status === 'trash', function ($query): void {
                $query->onlyTrashed();
            })
            ->when($request->status === 'all', function ($query): void {
                $query->withTrashed();
            })
            ->when($request->type, function ($query, $type): void {
                $query->where('type', $type);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return StockTransactionResource::collection($transactions);
    }

    public function store(StockTransactionStoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. GENERATE NOMOR TRANSAKSI
            $typeCode = match ($request->type) {
                'IN' => 'IN',
                'OUT' => 'OUT',
                'MOVE' => 'MOVE',
                'ADJUSTMENT' => 'ADJ',
                default => 'GEN'
            };

            $today = Carbon::today()->format('Ymd');
            // Gunakan count langsung dari Model agar lebih aman
            // $lastTrxCount = StockTransaction::whereDate('created_at', Carbon::today())->count();
            $lastTransaction = StockTransaction::withTrashed()
                ->whereDate('created_at', Carbon::today())
                ->where('transaction_no', 'like', "TRX-{$typeCode}-{$today}-%")
                ->orderBy('transaction_no', 'desc')
                ->first();

            if ($lastTransaction) {
                // Mengambil 4 angka terakhir dari string nomor transaksi
                $lastSequence = (int) substr($lastTransaction->transaction_no, -4);
                $nextSequence = $lastSequence + 1;
            } else {
                $nextSequence = 1;
            }
            // $sequence = str_pad((string) ($lastTrxCount + 1), 4, '0', STR_PAD_LEFT);
            $sequence = str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $transactionNo = "TRX-{$typeCode}-{$today}-{$sequence}";

            // 2. SIMPAN HEADER
            $transaction = StockTransaction::create([
                'transaction_no' => $transactionNo,
                'type' => $request->type,
                'date' => $request->date,
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                if ($item['qty'] <= 0) {
                    throw new \Exception('Qty harus lebih dari 0!');
                }
                $product = Product::where('sku', $item['product_sku'])->lockForUpdate()->first();

                if (! $product) {
                    throw new \Exception("Produk SKU {$item['product_sku']} tidak ditemukan!");
                }

                $stokAwalGlobal = $product->stock;

                if ($request->type === 'OUT') {
                    // --- LOGIKA FEFO (KELUAR) ---
                    $qtyNeeded = $item['qty'];

                    if ($product->stock < $qtyNeeded) {
                        throw new \Exception("Stok total {$product->product_name} tidak cukup!");
                    }

                    $availableInSelectedRack = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('rack_id', $item['rack_id'])
                        ->where('qty', '>', 0)
                        ->sum('qty');

                    // JIKA STOK DI RAK TERSEBUT KURANG, DAN USER BELUM KLIK "YAKIN" (force_out tidak bernilai true)
                    if ($availableInSelectedRack < $qtyNeeded && ! ($request->force_out ?? false)) {
                        // Kembalikan response json khusus agar frontend bisa menangkap dan menampilkan modal opsi
                        $fefoSuggestions = ProductLocation::where('product_sku', $item['product_sku'])
                            ->where('qty', '>', 0)
                            ->orderBy('expired_at', 'asc')
                            ->get()
                            ->map(function ($loc) {
                                return [
                                    'rack_code' => $loc->rack->location_code ?? "ID {$loc->rack_id}",
                                    'rack_name' => $loc->rack->rack_name ?? '-',
                                    'qty' => $loc->qty,
                                    'expired_at' => $loc->expired_at ? Carbon::parse($loc->expired_at)->format('d M Y') : '-',
                                ];
                            });

                        return response()->json([
                            'status' => 'warning_insufficient_rack_stock',
                            'message' => 'Stok di rak dipilih tidak cukup.',
                            'product_name' => $product->product_name,
                            'product_sku' => $item['product_sku'],
                            'qty_requested' => $qtyNeeded,
                            'qty_available_here' => $availableInSelectedRack,
                            'fefo_suggestions' => $fefoSuggestions, // <-- Kirim data rincian lokasi alternatif ke FE
                        ], 422);
                    }

                    $product->decrement('stock', $qtyNeeded);

                    $locationsQuery = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('qty', '>', 0);

                    if ($request->force_out ?? false) {
                        // JIKA OPSI 1 DIPILIH: Cari dari rak yang dipilih dulu (biar habis), baru sisanya ambil dari rak manapun sesuai FEFO
                        $locations = $locationsQuery->orderByRaw('CASE WHEN rack_id = ? THEN 0 ELSE 1 END', [$item['rack_id']])
                            ->orderBy('expired_at', 'asc')
                            ->lockForUpdate()
                            ->get();
                    } else {
                        // JIKA NORMAL (stok di rak cukup): Hanya boleh potong dari rak yang dipilih saja
                        $locations = $locationsQuery->where('rack_id', $item['rack_id'])
                            ->orderBy('expired_at', 'asc')
                            ->lockForUpdate()
                            ->get();
                    }

                    foreach ($locations as $loc) {
                        if ($qtyNeeded <= 0) {
                            break;
                        }

                        $take = min($loc->qty, $qtyNeeded);

                        $qtyBeforeRack = $loc->qty;
                        $qtyAfterRack = $qtyBeforeRack - $take;
                        $loc->decrement('qty', $take);

                        // if ($loc->fresh() && $loc->fresh()->qty <= 0) {
                        //     $loc->delete();
                        // }

                        $transaction->items()->create([
                            'product_sku' => $item['product_sku'],
                            'qty' => $take,
                            'qty_before' => $qtyBeforeRack,
                            'qty_after' => $qtyAfterRack,
                            'rack_id' => $loc->rack_id,
                            'expired_at' => $loc->expired_at,
                            'notes' => $item['notes'] ?? null,
                        ]);

                        StockLedger::create([
                            'product_sku' => $item['product_sku'],
                            'transaction_no' => $transactionNo,
                            'type' => 'OUT',
                            'rack_id' => $loc->rack_id,
                            'expired_at' => $loc->expired_at,
                            'qty' => -$take,
                            'balance_before' => $stokAwalGlobal,
                            'balance_after' => $stokAwalGlobal - $take,
                            'user_id' => auth()->id(),
                            'note' => $item['notes'] ?? null,
                        ]);

                        $stokAwalGlobal -= $take;
                        if ($loc->fresh()->qty <= 0) {
                            $loc->delete();
                        }
                        $qtyNeeded -= $take;
                    }

                    if ($qtyNeeded > 0) {
                        throw new \Exception("Kegagalan FEFO: Stok di rak tidak sinkron dengan stok global untuk {$product->product_name}");
                    }
                } elseif ($request->type === 'IN') {
                    // --- LOGIKA PRODUK MASUK (IN) ---
                    $batchCode = $item['product_sku'].'-'.Carbon::parse($item['expired_at'])->format('Ymd');
                    $existingLoc = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('rack_id', $item['rack_id'])
                        ->where('batch_code', $batchCode)
                        ->first();

                    $qtyBeforeRack = $existingLoc ? $existingLoc->qty : 0;
                    $qtyAfterRack = $qtyBeforeRack + $item['qty'];

                    // Update Stok Global
                    $product->increment('stock', $item['qty']);

                    if ($existingLoc) {
                        $existingLoc->increment('qty', $item['qty']);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $item['product_sku'],
                            'rack_id' => $item['rack_id'],
                            'batch_code' => $batchCode,
                            'qty' => $item['qty'],
                            'expired_at' => $item['expired_at'],
                        ]);
                    }

                    // Simpan Detail ke Tabel stock_transaction_items
                    $transaction->items()->create([
                        'product_sku' => $item['product_sku'],
                        'qty' => $item['qty'],
                        'qty_before' => $qtyBeforeRack,
                        'qty_after' => $qtyAfterRack,
                        'rack_id' => $item['rack_id'],
                        'expired_at' => $item['expired_at'],
                        'notes' => $item['notes'] ?? null,
                    ]);

                    // Update/Create di tabel product_locations
                    // $batchCode = $item['product_sku'].'-'.Carbon::parse($item['expired_at'])->format('Ymd');

                    // $productLocation = ProductLocation::where('product_sku', $item['product_sku'])
                    //     ->where('rack_id', $item['rack_id'])
                    //     ->where('batch_code', $batchCode)
                    //     ->first();

                    // if ($productLocation) {
                    //     $productLocation->increment('qty', $item['qty']);
                    // } else {
                    //     ProductLocation::create([
                    //         'product_sku' => $item['product_sku'],
                    //         'rack_id' => $item['rack_id'],
                    //         'batch_code' => $batchCode,
                    //         'qty' => $item['qty'],
                    //         'expired_at' => $item['expired_at'],
                    //     ]);
                    // }

                    StockLedger::create([
                        'product_sku' => $item['product_sku'],
                        'transaction_no' => $transactionNo,
                        'type' => 'IN',
                        'rack_id' => $item['rack_id'],
                        'expired_at' => $item['expired_at'],
                        'qty' => $item['qty'],
                        'balance_before' => $stokAwalGlobal,
                        'balance_after' => $stokAwalGlobal + $item['qty'],
                        'user_id' => auth()->id(),
                        'note' => $item['notes'] ?? null,
                    ]);
                } elseif ($request->type === 'MOVE') {
                    // --- LOGIKA PEMINDAHAN (MOVE) ---
                    // Kurangi dari lokasi asal
                    $qtyToMove = $item['qty'];
                    $fromRack = $item['rack_id'];
                    $toRack = $item['target_rack_id'];

                    if ($fromRack == $toRack) {
                        throw new \Exception('Rak asal dan tujuan tidak boleh sama!');
                    }

                    $formattedExpiredAt = Carbon::parse($item['expired_at'])->format('Y-m-d');

                    // Cari data di lokasi asal (berdasarkan SKU, Rak, dan Expired)
                    $sourceLocation = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('rack_id', $fromRack)
                        ->where('expired_at', $formattedExpiredAt)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceLocation || $sourceLocation->qty < $qtyToMove) {
                        throw new \Exception("Stok SKU {$item['product_sku']} di rak asal tidak cukup!");
                    }

                    $sourceQtyBefore = $sourceLocation->qty;
                    $sourceQtyAfter = $sourceQtyBefore - $qtyToMove;

                    // Kurangi stok di lokasi asal (Gunakan update biasa daripada decrement langsung agar instansinya aman)
                    if ($sourceQtyAfter <= 0) {
                        $sourceLocation->delete();
                    } else {
                        $sourceLocation->update(['qty' => $sourceQtyAfter]);
                    }

                    // Tambahkan ke lokasi tujuan
                    $batchCode = $item['product_sku'].'-'.Carbon::parse($item['expired_at'])->format('Ymd');

                    $targetLoc = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('rack_id', $toRack)
                        ->where('batch_code', $batchCode)
                        ->first();

                    // $sourceQtyBefore = $sourceLocation->qty;
                    // $sourceQtyAfter = $sourceQtyBefore - $qtyToMove;

                    // // Kurangi stok di lokasi asal
                    // // $sourceLocation->decrement('qty', $qtyToMove);

                    // // // Bersihkan jika qty jadi 0
                    // // if ($sourceLocation->fresh()->qty <= 0) {
                    // //     $sourceLocation->delete();
                    // // }

                    // $sourceLocation->update(['qty' => $sourceQtyAfter]);

                    // // Tambahkan ke lokasi tujuan
                    // $batchCode = $item['product_sku'].'-'.Carbon::parse($item['expired_at'])->format('Ymd');

                    // $targetLoc = ProductLocation::where('product_sku', $item['product_sku'])
                    //     ->where('rack_id', $toRack)
                    //     ->where('batch_code', $batchCode)
                    //     ->first();

                    // // 3. Tambahkan ke lokasi tujuan (Pakai updateOrCreate)
                    // $batchCode = $item['product_sku'].'-'.Carbon::parse($item['expired_at'])->format('Ymd');

                    // $targetLoc = ProductLocation::where('product_sku', $item['product_sku'])
                    //     ->where('rack_id', $toRack)
                    //     ->where('batch_code', $batchCode)
                    //     ->first();

                    $targetQtyBefore = $targetLoc ? $targetLoc->qty : 0;
                    $targetQtyAfter = $targetQtyBefore + $qtyToMove;

                    ProductLocation::updateOrCreate(
                        [
                            'product_sku' => $item['product_sku'],
                            'rack_id' => $toRack,
                            'batch_code' => $batchCode, // Pastikan batch code tetap konsisten
                        ],
                        [
                            'qty' => $targetQtyAfter, // Gunakan increment aman
                            'expired_at' => $formattedExpiredAt,
                        ]
                    );

                    $customNote = $item['notes'] ?? 'Pindah stok internal';

                    // Catat di Riwayat (stock_transaction_items)
                    $transaction->items()->create([
                        'product_sku' => $item['product_sku'],
                        'qty' => $qtyToMove,
                        'qty_before' => $sourceQtyBefore,
                        'qty_after' => $sourceQtyAfter,
                        'rack_id' => $fromRack,
                        'target_rack_id' => $toRack,
                        'expired_at' => $formattedExpiredAt,
                        'notes' => $customNote,
                    ]);

                    StockLedger::create([
                        'product_sku' => $item['product_sku'],
                        'transaction_no' => $transactionNo,
                        'type' => 'MOVE',
                        'rack_id' => $fromRack,
                        'expired_at' => $formattedExpiredAt,
                        'qty' => -$qtyToMove,
                        'balance_before' => $stokAwalGlobal,
                        'balance_after' => $stokAwalGlobal,
                        'user_id' => auth()->id(),
                        'note' => $customNote." (Keluar dari Rak {$fromRack})",
                    ]);
                    // 2. Catat Barang Masuk ke Rak Tujuan
                    StockLedger::create([
                        'product_sku' => $item['product_sku'],
                        'transaction_no' => $transactionNo,
                        'type' => 'MOVE',
                        'rack_id' => $toRack,
                        'expired_at' => $formattedExpiredAt,
                        'qty' => $qtyToMove,
                        'balance_before' => $stokAwalGlobal,
                        'balance_after' => $stokAwalGlobal,
                        'user_id' => auth()->id(),
                        'note' => $customNote." (Masuk ke Rak {$toRack})",
                    ]);

                    $remainingQtyInRack = ProductLocation::where('rack_id', $fromRack)
                        ->where('qty', '>', 0)
                        ->sum('qty');

                    if ($remainingQtyInRack <= 0) {
                        $rackModel = Rack::find($fromRack);
                        // Pastikan rak ini bukan Loading Dock (LD) agar tidak ikut ke-disable secara tidak sengaja
                        if ($rackModel && ! str_contains(strtolower($rackModel->location_code), 'ld')) {
                            $rackModel->update([
                                'is_maintenance' => true,
                                'is_active' => false,
                            ]);
                        }
                    }

                    // if ($sourceQtyAfter <= 0) {
                    //     $sourceLocation->delete();
                    // }
                } elseif ($request->type === 'ADJUSTMENT') {
                    $formattedExpiredAt = Carbon::parse($item['expired_at'])->format('Y-m-d');
                    $batchCode = $item['product_sku'].'-'.Carbon::parse($formattedExpiredAt)->format('Ymd');

                    $loc = ProductLocation::where('product_sku', $item['product_sku'])
                        ->where('rack_id', $item['rack_id'])
                        ->where('batch_code', $batchCode)
                        ->first();

                    $qtyBefore = $loc ? (int) $loc->qty : 0;
                    $qtyAfter = abs((int) $item['qty']);
                    $realQty = $qtyAfter - $qtyBefore;

                    if ($realQty === 0) {
                        continue;
                    }

                    if (($product->stock + $realQty) < 0) {
                        throw new \Exception("Gagal Adjustment! Perubahan ini menyebabkan stok global {$product->product_name} menjadi negatif.");
                    }

                    // 4. Update Stok Global menggunakan nilai selisih murni
                    $product->increment('stock', $realQty);

                    if ($loc) {
                        if ($qtyAfter == 0) {
                            // Jika setelah dihitung fisiknya nol, hapus baris lokasinya agar rapi
                            $loc->delete();
                        } else {
                            ProductLocation::where('id', $loc->id)->update(['qty' => $qtyAfter]);
                        }
                    } else {
                        // Jika belum ada record di rak tersebut, buat baru dengan nilai fisik yang diinput
                        if ($qtyAfter > 0) {
                            ProductLocation::create([
                                'product_sku' => $item['product_sku'],
                                'rack_id' => $item['rack_id'],
                                'batch_code' => $batchCode,
                                'qty' => $qtyAfter,
                                'expired_at' => $formattedExpiredAt,
                            ]);
                        }
                    }

                    $transaction->items()->create([
                        'product_sku' => $item['product_sku'],
                        'qty' => $realQty,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $qtyAfter,
                        'rack_id' => $item['rack_id'],
                        'expired_at' => $formattedExpiredAt,
                        'notes' => $item['notes'],
                    ]);

                    StockLedger::create([
                        'product_sku' => $item['product_sku'],
                        'transaction_no' => $transactionNo,
                        'type' => 'ADJUSTMENT',
                        'rack_id' => $item['rack_id'],
                        'expired_at' => $formattedExpiredAt,
                        'qty' => $realQty,
                        'balance_before' => $stokAwalGlobal,
                        'balance_after' => $stokAwalGlobal + $realQty,
                        'user_id' => auth()->id(),
                        'note' => $item['notes'],
                    ]);
                } else {
                    throw new \Exception('Tipe transaksi tidak valid!');
                }
            }

            return new StockTransactionResource($transaction->load(['items.product', 'items.rack', 'items.targetRack', 'user']));
        });
    }

    /**
     * Show Transaction Detail (Mode Read-Only)
     */
    public function show(string $transaction_no)
    {
        // Mengambil transaksi beserta item, detail produk, rak, dan siapa yang input
        $transaction = StockTransaction::with(['items.product', 'items.rack', 'items.targetRack', 'user'])
            ->where('transaction_no', $transaction_no)
            ->firstOrFail();

        return new StockTransactionResource($transaction);
    }

    /**
     * Deleting transaction and rolling back stock changes
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            // 1. Cari transaksi yang aktif (belum di-delete) beserta seluruh itemnya
            $transaction = StockTransaction::with('items')->findOrFail($id);

            if (! $transaction->created_at->isToday()) {
                throw new \Exception("Akses Ditolak! Transaksi {$transaction->transaction_no} sudah dikunci karena melewati hari penginputan.");
            }

            // Jaga-jaga jika tipe transaksi bukan IN untuk pengerjaan kilat ini
            // if ($transaction->type !== 'IN') {
            //     throw new \Exception('Sistem saat ini baru mendukung pembatalan untuk transaksi Produk Masuk (IN)!');
            // }
            if (! in_array($transaction->type, ['IN', 'MOVE', 'OUT', 'ADJUSTMENT'])) {
                throw new \Exception('Sistem saat ini baru mendukung pembatalan untuk jenis transaksi Masuk (IN) dan Pindah (MOVE)!');
            }

            foreach ($transaction->items as $item) {
                $product = Product::where('sku', $item->product_sku)->lockForUpdate()->first();
                if (! $product) {
                    throw new \Exception("Produk SKU {$item->product_sku} tidak ditemukan!");
                }

                // $stokAwalGlobal = $product->stock;
                $batchCode = $item->product_sku.'-'.Carbon::parse($item->expired_at)->format('Ymd');

                if ($transaction->type === 'IN') {
                    $location = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->rack_id)
                        ->where('batch_code', $batchCode)
                        ->lockForUpdate()
                        ->first();

                    if (! $location || $location->qty < $item->qty) {
                        throw new \Exception(
                            "Gagal batal! Barang dari SKU {$item->product_sku} di rak ini sudah terpakai oleh transaksi lain ".
                            '(Sisa di rak: '.($location ? $location->qty : 0).", yang ingin dibatalkan: {$item->qty})."
                        );
                    }

                    // === BALIKKAN TRANSAKSI MASUK (IN) ===
                    if ($product->stock < $item->qty) {
                        throw new \Exception("Gagal batal! Stok global {$product->product_name} sudah terpakai di transaksi lain.");
                    }

                    $product->decrement('stock', $item->qty);

                    // $location = ProductLocation::where('product_sku', $item->product_sku)
                    //     ->where('rack_id', $item->rack_id)
                    //     ->where('batch_code', $batchCode)
                    //     ->lockForUpdate()
                    //     ->first();

                    // if (! $location || $location->qty < $item->qty) {
                    //     throw new \Exception("Gagal batal! Stok fisik di rak untuk SKU {$item->product_sku} sudah berkurang.");
                    // }

                    if ($location->qty - $item->qty <= 0) {
                        $location->delete();
                    } else {
                        $location->decrement('qty', $item->qty);
                    }

                    // StockLedger::create([
                    //     'product_sku' => $item->product_sku,
                    //     'transaction_no' => $transaction->transaction_no,
                    //     'type' => 'OUT',
                    //     'rack_id' => $item->rack_id,
                    //     'expired_at' => $item->expired_at,
                    //     'qty' => -$item->qty,
                    //     'balance_before' => $stokAwalGlobal,
                    //     'balance_after' => $stokAwalGlobal - $item->qty,
                    //     'user_id' => auth()->id(),
                    //     'note' => "PEMBATALAN: Transaksi IN {$transaction->transaction_no} dianulir.",
                    // ]);
                } elseif ($transaction->type === 'OUT') {
                    // === BALIKKAN TRANSAKSI KELUAR (OUT) ===
                    // 1. Kembalikan stok ke level global (karena saat OUT stok global dikurangi)
                    $product->increment('stock', $item->qty);

                    // 2. Kembalikan stok ke rak fisik semula tempat FEFO mengambil barang tersebut
                    $location = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->rack_id)
                        ->where('batch_code', $batchCode)
                        ->lockForUpdate()
                        ->first();

                    if ($location) {
                        $location->increment('qty', $item->qty);
                    } else {
                        // Jika row lokasi sempat terhapus otomatis karena qty nya nol, buat baru row nya
                        ProductLocation::create([
                            'product_sku' => $item->product_sku,
                            'rack_id' => $item->rack_id,
                            'batch_code' => $batchCode,
                            'qty' => $item->qty,
                            'expired_at' => $item->expired_at,
                        ]);
                    }
                } elseif ($transaction->type === 'MOVE') {
                    // === BALIKKAN TRANSAKSI PINDAH (MOVE) ===
                    // 1. Cek ketersediaan stok di rak TUJUAN (target_rack_id) untuk ditarik kembali
                    $targetLocation = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->target_rack_id)
                        ->where('batch_code', $batchCode)
                        ->lockForUpdate()
                        ->first();

                    if (! $targetLocation || $targetLocation->qty < $item->qty) {
                        throw new \Exception("Gagal batal! Barang di rak tujuan ({$item->target_rack_id}) sudah bergeser atau berkurang.");
                    }

                    // Kurangi stok dari rak tujuan (atau delete kalau kosong)
                    if ($targetLocation->qty - $item->qty <= 0) {
                        $targetLocation->delete();
                    } else {
                        $targetLocation->decrement('qty', $item->qty);
                    }

                    // 2. Kembalikan barang ke rak ASAL (rack_id)
                    $sourceLocation = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->rack_id)
                        ->where('batch_code', $batchCode)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceLocation) {
                        $sourceLocation->increment('qty', $item->qty);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $item->product_sku,
                            'rack_id' => $item->rack_id,
                            'batch_code' => $batchCode,
                            'qty' => $item->qty,
                            'expired_at' => $item->expired_at,
                        ]);
                    }

                    // Catat Log Pembatalan Move di Ledger (Stok global tidak berubah)
                    // StockLedger::create([
                    //     'product_sku' => $item->product_sku,
                    //     'transaction_no' => $transaction->transaction_no,
                    //     'type' => 'MOVE',
                    //     'rack_id' => $item->target_rack_id,
                    //     'expired_at' => $item->expired_at,
                    //     'qty' => -$item->qty,
                    //     'balance_before' => $stokAwalGlobal,
                    //     'balance_after' => $stokAwalGlobal,
                    //     'user_id' => auth()->id(),
                    //     'note' => 'PEMBATALAN MOVE: Ditarik keluar dari rak tujuan.',
                    // ]);

                    // StockLedger::create([
                    //     'product_sku' => $item->product_sku,
                    //     'transaction_no' => $transaction->transaction_no,
                    //     'type' => 'MOVE',
                    //     'rack_id' => $item->rack_id,
                    //     'expired_at' => $item->expired_at,
                    //     'qty' => $item->qty,
                    //     'balance_before' => $stokAwalGlobal,
                    //     'balance_after' => $stokAwalGlobal,
                    //     'user_id' => auth()->id(),
                    //     'note' => 'PEMBATALAN MOVE: Dikembalikan ke rak asal.',
                    // ]);
                } elseif ($transaction->type === 'ADJUSTMENT') {
                    if (($product->stock - $item->qty) < 0) {
                        throw new \Exception("Gagal batal! Pembatalan adjustment menyebabkan stok global {$product->product_name} menjadi negatif.");
                    }
                    $product->decrement('stock', $item->qty);

                    // 2. Kembalikan Stok di Rak fisik
                    $location = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->rack_id)
                        ->where('batch_code', $batchCode)
                        ->lockForUpdate()
                        ->first();

                    if ($location) {
                        $newRackQty = $location->qty - $item->qty;
                        if ($newRackQty <= 0) {
                            $location->delete();
                        } else {
                            $location->update(['qty' => $newRackQty]);
                        }
                    } else {
                        // Jika data lokasi sempat terhapus (karena adjustment lama membuat qty jadi 0), buat kembali datanya
                        $originalQtyBeforeAdjustment = $item->qty_before; // mengambil back-up qty sebelum adj jika fieldnya ada

                        if ($originalQtyBeforeAdjustment > 0) {
                            ProductLocation::create([
                                'product_sku' => $item->product_sku,
                                'rack_id' => $item->rack_id,
                                'batch_code' => $batchCode,
                                'qty' => $originalQtyBeforeAdjustment,
                                'expired_at' => $item->expired_at,
                            ]);
                        }
                    }
                }
            }
            StockLedger::where('transaction_no', $transaction->transaction_no)->delete();

            $transaction->update([
                'deleted_by' => auth()->id() ?? User::first()?->id, // Jaga-jaga jika testing via console
            ]);

            // 2. Lakukan Soft Delete pada data header transaksi utama
            $transaction->delete();

            return response()->json([
                'message' => "Transaksi {$transaction->transaction_no} berhasil dibatalkan dan stok telah disesuaikan.",
            ]);
        });
    }
}
