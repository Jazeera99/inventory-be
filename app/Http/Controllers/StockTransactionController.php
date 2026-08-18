<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockTransactionStoreRequest;
use App\Http\Resources\StockTransactionResource;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockLedger;
use App\Models\StockOrder;
use App\Models\StockTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransactionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('Transaksi');

        $type = $request->input('type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');

        $hasStartDate = $request->filled('start_date');
        $hasEndDate = $request->filled('end_date');

        $transactions = StockTransaction::query()
            ->with(['user', 'deletedByUser', 'items.product', 'items.rack', 'items.targetRack'])
            ->when($status === 'trash', function ($query): void {
                $query->onlyTrashed();
            })
            ->when($status === 'all', function ($query): void {
                $query->withTrashed();
            })
            ->when(! empty($type), function ($query) use ($type): void {
                $query->where('type', $type);
            })
            ->when($hasStartDate && $hasEndDate, function ($query) use ($startDate, $endDate): void {
                $start = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                $end = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
                $query->whereBetween('created_at', [$start, $end]);
            })
            ->when($hasStartDate && ! $hasEndDate, function ($query) use ($startDate): void {
                $start = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                $query->where('created_at', '>=', $start);
            })
            ->when($hasEndDate && ! $hasStartDate, function ($query) use ($endDate): void {
                $end = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
                $query->where('created_at', '<=', $end);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return StockTransactionResource::collection($transactions);
    }

    public function store(StockTransactionStoreRequest $request)
    {
        $this->authorize('Transaksi');

        if ($request->type === 'OUT' && ! ($request->force_out ?? false)) {
            $allFefoSuggestions = [];
            $hasViolation = false;
            $triggerReason = 'FEFO_VIOLATION';
            $hasInsufficientStock = false;
            $hasFefoViolation = false;
            $errorDetails = null;

            foreach ($request->items as $item) {
                if ($item['qty'] <= 0) {
                    continue;
                }

                $product = Product::where('sku', $item['product_sku'])->first();
                if (! $product || ! $product->is_active) {
                    continue;
                }

                $sku = $item['product_sku'];
                $qtyNeeded = (int) $item['qty'];
                $selectedRackId = $item['rack_id'];

                // Ambil urutan FEFO ketat untuk produk ini
                // $fefoStrictOrder = ProductLocation::with(['product', 'rack'])
                //     ->where('product_sku', $item['product_sku'])
                //     ->where('qty', '>', 0)
                //     ->orderBy('expired_at', 'asc')
                //     ->get();

                // $urgentLocations = collect();
                // $accumulatedQty = 0;

                // foreach ($fefoStrictOrder as $loc) {
                //     if ($accumulatedQty >= $qtyNeeded) {
                //         break;
                //     }
                //     $urgentLocations->push($loc);
                //     $accumulatedQty += $loc->qty;
                // }

                // $mostUrgentLocation = $fefoStrictOrder->first();
                // $availableInSelectedRack = $fefoStrictOrder->where('rack_id', $item['rack_id'])->sum('qty');
                // $isAnotherRackMoreUrgent = $mostUrgentLocation && $mostUrgentLocation->rack_id != $item['rack_id'];

                // if ($availableInSelectedRack < $qtyNeeded) {
                //     $hasViolation = true;
                //     // $hasInsufficientStock = true;
                // }

                // // Cek kondisi 2: Ada rak lain yang expired-nya lebih mendesak (Pelanggaran FEFO)
                // if ($isAnotherRackMoreUrgent) {
                //     $hasViolation = true;
                //     // $hasFefoViolation = true;
                // }

                // Total Stok Global Cukup?
                if ($product->stock < $qtyNeeded) {
                    return response()->json([
                        'status' => 'error_stock_insufficient',
                        'message' => "Stok total produk {$product->product_name} (SKU: {$sku}) tidak mencukupi!",
                    ], 422);
                }

                // A. Stok Fisik di Rak Terpilih (Loading Dock)
                $availableInSelectedRack = ProductLocation::where('product_sku', $sku)
                    ->where('rack_id', $selectedRackId)
                    ->where('qty', '>', 0)
                    ->sum('qty');

                // B. Tanggal Expired Paling Mendedak di Rak Terpilih
                $earliestInSelectedRack = ProductLocation::where('product_sku', $sku)
                    ->where('rack_id', $selectedRackId)
                    ->where('qty', '>', 0)
                    ->min('expired_at');

                // C. Tanggal Expired Paling Mendesak di RAK LAIN
                $earliestInOtherRacks = ProductLocation::where('product_sku', $sku)
                    ->where('rack_id', '!=', $selectedRackId)
                    ->where('qty', '>', 0)
                    ->min('expired_at');

                // Cek 1: Stok di rak terpilih kurang
                $isRackStockInsufficient = $availableInSelectedRack < $qtyNeeded;

                // Cek 2: Ada rak lain dengan Expired LEBIH CEPAT
                $isAnotherRackMoreUrgent = false;
                if ($earliestInOtherRacks) {
                    if (! $earliestInSelectedRack || Carbon::parse($earliestInOtherRacks)->lt(Carbon::parse($earliestInSelectedRack))) {
                        $isAnotherRackMoreUrgent = true;
                    }
                }

                // Jika terdeteksi ada pelanggaran stok rak atau ada yang lebih urgent expired-nya
                if ($isRackStockInsufficient || $isAnotherRackMoreUrgent) {
                    $hasViolation = true;

                    $triggerReason = $isRackStockInsufficient ? 'INSUFFICIENT_RACK_STOCK' : 'FEFO_VIOLATION';

                    // $targetMoveQty = $isAnotherRackMoreUrgent ? $qtyNeeded : max(0, $qtyNeeded - $availableInSelectedRack);

                    // Ambil daftar lokasi rak lain urut FEFO
                    $allLocations = ProductLocation::with(['product', 'rack'])
                        ->where('product_sku', $sku)
                        ->where('qty', '>', 0)
                        ->orderBy('expired_at', 'asc')
                        ->get();

                    $accumulatedQty = 0;

                    // Kumpulkan semua saran evakuasi/pindah rak untuk produk ini
                    // foreach ($urgentLocations as $loc) {
                    //     $allFefoSuggestions[] = [
                    //         'product_sku' => $loc->product_sku,
                    //         'product_name' => $loc->product->product_name ?? 'Produk',
                    //         'current_rack_id' => $loc->rack_id,
                    //         'current_rack_code' => $loc->rack->location_code ?? "ID {$loc->rack_id}",
                    //         'rack_name' => $loc->rack->rack_name ?? '-',
                    //         'qty' => $loc->qty,
                    //         'recommended_move_qty' => $loc->qty,
                    //         'expired_at' => $loc->expired_at ? Carbon::parse($loc->expired_at)->format('d M Y') : 'Tanpa EXP',
                    //     ];
                    // }

                    foreach ($allLocations as $loc) {
                        // STOP perulangan jika akumulasi rekomendasi sudah CUKUP memenuhi target
                        if ($accumulatedQty >= $qtyNeeded) {
                            break;
                        }

                        // Filter FEFO: Hentikan jika tanggal expired sudah lebih lama dari LD (jika LD cukup kuantitas)
                        // if ($isAnotherRackMoreUrgent && $earliestInSelectedRack && $availableInSelectedRack >= $qtyNeeded) {
                        //     if (Carbon::parse($loc->expired_at)->gte(Carbon::parse($earliestInSelectedRack))) {
                        //         break;
                        //     }
                        // }

                        $remainingNeeded = $qtyNeeded - $accumulatedQty;
                        $qtyToTake = min($loc->qty, $remainingNeeded);

                        $isAtLoadingDock = ($loc->rack_id == $selectedRackId);

                        if ($isAtLoadingDock) {
                            // ✅ STOK DI LOADING DOCK:
                            // Kurangi kebutuhan user ($accumulatedQty bertambah),
                            // tapi JANGAN dimasukkan ke $allFefoSuggestions (karena tak perlu di-MOVE)
                            $accumulatedQty += $qtyToTake;
                        } else {
                            // ✅ STOK DI RAK INTERNAL:
                            // Kurangi kebutuhan user DAN masukkan ke saran pemindahan (evakuasi)
                            $allFefoSuggestions[] = [
                                'product_sku' => $loc->product_sku,
                                'product_name' => $loc->product->product_name ?? 'Produk',
                                'current_rack_id' => $loc->rack_id,
                                'current_rack_code' => $loc->rack->location_code ?? "ID {$loc->rack_id}",
                                'rack_name' => $loc->rack->rack_name ?? '-',
                                'available_qty' => $loc->qty,
                                'recommended_move_qty' => $qtyToTake, // Presisi sisa Qty yang perlu di-MOVE
                                'qty' => $qtyToTake,
                                'expired_at' => $loc->expired_at ? Carbon::parse($loc->expired_at)->format('d M Y') : 'Tanpa EXP',
                                'expired_at_raw' => $loc->expired_at ? Carbon::parse($loc->expired_at)->format('Y-m-d') : '',
                            ];

                            $accumulatedQty += $qtyToTake;
                        }
                    }

                    // Simpan detail untuk produk pertama yang memicu error sebagai fallback UI lama
                    if (empty($errorDetails)) {
                        $errorDetails = [
                            'product_name' => $product->product_name,
                            'product_sku' => $sku,
                            'qty_requested' => $qtyNeeded,
                            'qty_available_here' => $availableInSelectedRack,
                        ];
                    }
                }
            }

            // Jika dari sekian banyak produk ada yang melanggar, langsung lempar response error massal ke Vue
            if ($hasViolation) {
                return response()->json([
                    'status' => 'warning_insufficient_rack_stock',
                    'trigger_reason' => $triggerReason,
                    'message' => $triggerReason === 'INSUFFICIENT_RACK_STOCK'
                        ? 'Stok di rak terpilih tidak mencukupi kuantiti yang diminta.'
                        : 'Terdeteksi produk di rak internal memiliki tanggal expired lebih mendesak!',
                    'product_name' => $errorDetails['product_name'],
                    'product_sku' => $errorDetails['product_sku'],
                    'qty_requested' => $errorDetails['qty_requested'],
                    'qty_available_here' => $errorDetails['qty_available_here'],
                    'fefo_suggestions' => $allFefoSuggestions,
                ], 422);
            }
        }

        if ($request->type === 'IN' && ! ($request->force ?? false)) {
            foreach ($request->items as $item) {
                if ($item['qty'] <= 0) {
                    continue;
                }

                $recentTransactionExists = StockLedger::where('product_sku', $item['product_sku'])
                    ->where('type', 'IN')
                    ->where('rack_id', $item['rack_id'])
                    ->where('expired_at', $item['expired_at'])
                    ->where('qty', $item['qty'])
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->exists();

                if ($recentTransactionExists) {
                    return response()->json([
                        'status' => 'error_duplicate_input',
                        'message' => "Gagal! SKU {$item['product_sku']} dengan Qty {$item['qty']} baru saja diinput ke rak tersebut oleh user lain dalam 10 menit terakhir.",
                        'info' => 'Silakan bersihkan form atau cek riwayat berjalan untuk menghindari input ganda.',
                    ], 422);
                }
            }
        }

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
                'stock_order_id' => $request->stock_order_id ?? null,
            ]);

            if ($request->type === 'MOVE') {
                $groupedItems = [];
                $evacuateRackIds = [];
                $totalQtyMoved = 0;

                // Step 1: Grouping & Validasi Payload
                foreach ($request->items as $item) {
                    if ($item['qty'] <= 0) {
                        throw new \Exception('Qty harus lebih dari 0!');
                    }

                    $sku = $item['product_sku'];
                    $qtyToMove = (int) $item['qty'];
                    $fromRack = $item['rack_id'];
                    $toRack = $item['target_rack_id'];

                    if ($fromRack == $toRack) {
                        throw new \Exception("Rak asal dan tujuan untuk SKU {$sku} tidak boleh sama!");
                    }

                    if (! empty($item['isEvacuation'])) {
                        $evacuateRackIds[$fromRack] = true;
                    }

                    $formattedExpiredAt = Carbon::parse($item['expired_at'])->format('Y-m-d');
                    $groupKey = "{$sku}_{$fromRack}_{$toRack}_{$formattedExpiredAt}";

                    if (! isset($groupedItems[$groupKey])) {
                        $groupedItems[$groupKey] = [
                            'product_sku' => $sku,
                            'rack_id' => $fromRack,
                            'target_rack_id' => $toRack,
                            'qty' => 0,
                            'expired_at' => $formattedExpiredAt,
                            'notes' => $item['notes'] ?? 'Pindah stok internal',
                        ];
                    }

                    $groupedItems[$groupKey]['qty'] += $qtyToMove;
                    $totalQtyMoved += $qtyToMove;
                }

                // Step 2: Eksekusi Pindah Stok Massal
                $virtualRackQty = [];

                foreach ($groupedItems as $item) {
                    $sku = $item['product_sku'];
                    $qtyToMove = $item['qty'];
                    $fromRack = $item['rack_id'];
                    $toRack = $item['target_rack_id'];
                    $formattedExpiredAt = $item['expired_at'];
                    $carbonDate = Carbon::parse($formattedExpiredAt);

                    $product = Product::where('sku', $sku)->lockForUpdate()->first();
                    if (! $product) {
                        throw new \Exception("Produk SKU {$sku} tidak ditemukan!");
                    }

                    if (! $product->is_active) {
                        throw new \Exception("Gagal Transaksi! Produk '{$product->product_name}' (SKU: {$sku}) berstatus TIDAK AKTIF.");
                    }

                    $stokAwalGlobal = $product->stock;

                    $sourceLocation = ProductLocation::where('product_sku', $sku)
                        ->where('rack_id', $fromRack)
                        ->whereDate('expired_at', $formattedExpiredAt)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceLocation) {
                        throw new \Exception("Stok untuk SKU {$sku} dengan expired {$formattedExpiredAt} tidak ditemukan di rak asal (Rak ID: {$fromRack})!");
                    }

                    if ($sourceLocation->qty < $qtyToMove) {
                        throw new \Exception("Gagal! Stok SKU {$sku} di rak asal tidak mencukupi. Sisa di DB: {$sourceLocation->qty}, Diminta: {$qtyToMove}.");
                    }

                    // Cek Kapasitas Rak Tujuan
                    $rackModel = Rack::find($toRack);
                    if ($rackModel) {
                        $isLoadingDock = str_contains(strtolower($rackModel->rack_name), 'loading') ||
                                         str_contains(strtolower($rackModel->location_code), 'ld');

                        if (! $isLoadingDock) {
                            if (! isset($virtualRackQty[$toRack])) {
                                $virtualRackQty[$toRack] = ProductLocation::where('rack_id', $toRack)->sum('qty');
                            }
                            if (! isset($virtualRackQty[$fromRack])) {
                                $virtualRackQty[$fromRack] = ProductLocation::where('rack_id', $fromRack)->sum('qty');
                            }

                            $currentAvailableSpace = $rackModel->capacity - $virtualRackQty[$toRack];

                            if ($qtyToMove > $currentAvailableSpace) {
                                throw new \Exception("Gagal! Rak {$rackModel->location_code} tidak muat. Sisa kapasitas: {$currentAvailableSpace}, mencoba memasukkan: {$qtyToMove}.");
                            }

                            $virtualRackQty[$fromRack] -= $qtyToMove;
                            $virtualRackQty[$toRack] += $qtyToMove;
                        }
                    }

                    // Potong/Hapus Stok Rak Asal
                    $sourceQtyBefore = $sourceLocation->qty;
                    $sourceQtyAfter = $sourceQtyBefore - $qtyToMove;

                    if ($sourceQtyAfter <= 0) {
                        $sourceLocation->delete();
                    } else {
                        $sourceLocation->update(['qty' => $sourceQtyAfter]);
                    }

                    // Tambahkan ke Rak Tujuan
                    $batchCode = $sku.'-'.$carbonDate->format('Ymd');

                    $targetLoc = ProductLocation::where('product_sku', $sku)
                        ->where('rack_id', $toRack)
                        ->where('batch_code', $batchCode)
                        ->whereDate('expired_at', $formattedExpiredAt)
                        ->first();

                    if ($targetLoc) {
                        $targetLoc->increment('qty', $qtyToMove);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $sku,
                            'rack_id' => $toRack,
                            'batch_code' => $batchCode,
                            'qty' => $qtyToMove,
                            'expired_at' => $carbonDate,
                        ]);
                    }

                    $customNote = $item['notes'] ?? 'Pindah stok internal';

                    // Catat Item Transaksi
                    $transaction->items()->create([
                        'product_sku' => $sku,
                        'qty' => $qtyToMove,
                        'qty_before' => $sourceQtyBefore,
                        'qty_after' => $sourceQtyAfter,
                        'rack_id' => $fromRack,
                        'target_rack_id' => $toRack,
                        'expired_at' => $formattedExpiredAt,
                        'notes' => $customNote,
                    ]);

                    // Stock Ledger Out & In
                    StockLedger::create([
                        'product_sku' => $sku,
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

                    StockLedger::create([
                        'product_sku' => $sku,
                        'transaction_no' => $transactionNo,
                        'type' => 'MOVE',
                        'rack_id' => $toRack,
                        'expired_at' => $formattedExpiredAt,
                        'qty' => $qtyToMove,
                        'balance_before' => $stokAwalGlobal,
                        'balance_after' => $stokAwalGlobal,
                        'user_id' => auth()->id(),
                        'note' => $customNote,
                    ]);
                }

                $transaction->update(['total_qty' => $totalQtyMoved]);

                // Step 3: Evakuasi & Non-aktifkan Rak (Pemicu Maintenance Otomatis Jika Kosong)
                foreach (array_keys($evacuateRackIds) as $rackIdToDisable) {
                    $remainingQtyInRack = ProductLocation::where('rack_id', $rackIdToDisable)
                        ->where('qty', '>', 0)
                        ->sum('qty');

                    if ($remainingQtyInRack <= 0) {
                        $rackModel = Rack::find($rackIdToDisable);
                        if ($rackModel && ! str_contains(strtolower($rackModel->location_code), 'ld')) {
                            $rackModel->update([
                                'is_maintenance' => true,
                                'is_active' => false,
                            ]);
                        }
                        ProductLocation::where('rack_id', $rackIdToDisable)->where('qty', '<=', 0)->delete();
                    }
                }
            } else {
                foreach ($request->items as $item) {
                    if ($item['qty'] <= 0) {
                        throw new \Exception('Qty harus lebih dari 0!');
                    }
                    $product = Product::where('sku', $item['product_sku'])->lockForUpdate()->first();

                    if (! $product) {
                        throw new \Exception("Produk SKU {$item['product_sku']} tidak ditemukan!");
                    }

                    if (! $product->is_active) {
                        throw new \Exception("Gagal Transaksi! Produk '{$product->product_name}' (SKU: {$item['product_sku']}) berstatus TIDAK AKTIF.");
                    }

                    $stokAwalGlobal = $product->stock;

                    if ($request->type === 'IN') {
                        $formattedExpiredAt = Carbon::parse($item['expired_at'])->format('Y-m-d');
                        $batchCode = $item['product_sku'].'-'.Carbon::parse($formattedExpiredAt)->format('Ymd');

                        // A. Tambah stok global produk
                        $product->increment('stock', $item['qty']);

                        // B. Tambah / Buat stok lokasi di ProductLocation
                        $location = ProductLocation::where('product_sku', $item['product_sku'])
                            ->where('rack_id', $item['rack_id'])
                            ->where('batch_code', $batchCode)
                            ->whereDate('expired_at', $formattedExpiredAt)
                            ->first();

                        $qtyBefore = $location ? $location->qty : 0;
                        $qtyAfter = $qtyBefore + $item['qty'];

                        if ($location) {
                            $location->increment('qty', $item['qty']);
                        } else {
                            ProductLocation::create([
                                'product_sku' => $item['product_sku'],
                                'rack_id' => $item['rack_id'],
                                'batch_code' => $batchCode,
                                'qty' => $item['qty'],
                                'expired_at' => $formattedExpiredAt,
                            ]);
                        }

                        // C. Catat Item Transaksi
                        $transaction->items()->create([
                            'product_sku' => $item['product_sku'],
                            'qty' => $item['qty'],
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'rack_id' => $item['rack_id'],
                            'expired_at' => $formattedExpiredAt,
                            'notes' => $item['notes'] ?? null,
                        ]);

                        // D. Catat ke StockLedger
                        StockLedger::create([
                            'product_sku' => $item['product_sku'],
                            'transaction_no' => $transactionNo,
                            'type' => 'IN',
                            'rack_id' => $item['rack_id'],
                            'expired_at' => $formattedExpiredAt,
                            'qty' => $item['qty'],
                            'balance_before' => $stokAwalGlobal,
                            'balance_after' => $stokAwalGlobal + $item['qty'],
                            'user_id' => auth()->id(),
                            'note' => $item['notes'] ?? null,
                        ]);
                    } elseif ($request->type === 'OUT') {
                        // --- LOGIKA FEFO (KELUAR) ---
                        $qtyNeeded = $item['qty'];

                        if ($product->stock < $qtyNeeded) {
                            throw new \Exception("Stok total {$product->product_name} tidak cukup!");
                        }

                        $fefoStrictOrder = ProductLocation::with(['product', 'rack'])
                            ->where('product_sku', $item['product_sku'])
                            ->where('qty', '>', 0)
                            ->orderBy('expired_at', 'asc')
                            ->get();

                        $urgentLocations = collect();
                        $accumulatedQty = 0;

                        foreach ($fefoStrictOrder as $loc) {
                            if ($accumulatedQty >= $qtyNeeded) {
                                break;
                            }

                            $urgentLocations->push($loc);
                            $accumulatedQty += $loc->qty;

                            if ($loc->rack_id != $item['rack_id']) {
                                $needsEvacuation = true;
                            }
                            $accumulatedQty += $loc->qty;
                        }

                        $mostUrgentLocation = $fefoStrictOrder->first();

                        $availableInSelectedRack = $fefoStrictOrder->where('rack_id', $item['rack_id'])->sum('qty');

                        $isAnotherRackMoreUrgent = $mostUrgentLocation && $mostUrgentLocation->rack_id != $item['rack_id'];

                        // JIKA STOK DI RAK TERSEBUT KURANG, DAN USER BELUM KLIK "YAKIN" (force_out tidak bernilai true)
                        if (($availableInSelectedRack < $qtyNeeded || $isAnotherRackMoreUrgent) && ! ($request->force_out ?? false)) {
                            $triggerReason = 'FEFO_VIOLATION'; // Default karena melanggar FEFO
                            if ($availableInSelectedRack < $qtyNeeded) {
                                $triggerReason = 'INSUFFICIENT_RACK_STOCK'; // Karena qty rak emang kurang
                            }

                            $fefoSuggestions = $urgentLocations->map(function ($loc) {
                                return [
                                    'product_sku' => $loc->product_sku,
                                    'product_name' => $loc->product->product_name ?? 'Produk',
                                    'current_rack_id' => $loc->rack_id,
                                    'current_rack_code' => $loc->rack->location_code ?? "ID {$loc->rack_id}",
                                    'rack_name' => $loc->rack->rack_name ?? '-',
                                    'qty' => $loc->qty,
                                    'recommended_move_qty' => $loc->qty,
                                    'expired_at' => $loc->expired_at ? Carbon::parse($loc->expired_at)->format('d M Y') : 'Tanpa EXP',
                                ];
                            })->values()->all();

                            return response()->json([
                                'status' => 'warning_insufficient_rack_stock',
                                'trigger_reason' => $triggerReason, // Oper indikator ini ke Vue
                                'message' => $triggerReason === 'INSUFFICIENT_RACK_STOCK'
                                    ? 'Stok di lokasi terpilih tidak mencukupi kuantiti yang diminta.'
                                    : 'Terdeteksi produk di rak internal memiliki tanggal expired lebih mendesak!',
                                'product_name' => $product->product_name,
                                'product_sku' => $item['product_sku'],
                                'qty_requested' => $qtyNeeded,
                                'qty_available_here' => $availableInSelectedRack,
                                'fefo_suggestions' => $fefoSuggestions,
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
                        // }
                        // elseif ($request->type === 'MOVE') {
                        //     // --- LOGIKA PEMINDAHAN (MOVE) BERSIH & MASSAL ---
                        //     $groupedItems = [];
                        //     $evacuateRackIds = [];
                        //     $totalQtyMoved = 0;

                        //     // Step 1: Grouping & Validasi Awal Payload
                        //     foreach ($request->items as $item) {
                        //         $sku = $item['product_sku'];
                        //         $qtyToMove = (int) $item['qty'];
                        //         $fromRack = $item['rack_id'];
                        //         $toRack = $item['target_rack_id'];

                        //         if ($fromRack == $toRack) {
                        //             throw new \Exception("Rak asal dan tujuan untuk SKU {$sku} tidak boleh sama!");
                        //         }

                        //         if (! empty($item['isEvacuation'])) {
                        //             $evacuateRackIds[$fromRack] = true;
                        //         }

                        //         // Ambil format tanggal Y-m-d murni
                        //         $formattedExpiredAt = Carbon::parse($item['expired_at'])->format('Y-m-d');
                        //         $groupKey = "{$sku}_{$fromRack}_{$toRack}_{$formattedExpiredAt}";

                        //         if (! isset($groupedItems[$groupKey])) {
                        //             $groupedItems[$groupKey] = [
                        //                 'product_sku' => $sku,
                        //                 'rack_id' => $fromRack,
                        //                 'target_rack_id' => $toRack,
                        //                 'qty' => 0,
                        //                 'expired_at' => $formattedExpiredAt,
                        //                 'notes' => $item['notes'] ?? 'Pindah stok internal',
                        //             ];
                        //         }

                        //         $groupedItems[$groupKey]['qty'] += $qtyToMove;
                        //         $totalQtyMoved += $qtyToMove;
                        //     }

                        //     // Step 2: Eksekusi Pindah Stok Massal
                        //     $virtualRackQty = [];

                        //     foreach ($groupedItems as $item) {
                        //         $sku = $item['product_sku'];
                        //         $qtyToMove = $item['qty'];
                        //         $fromRack = $item['rack_id'];
                        //         $toRack = $item['target_rack_id'];
                        //         $formattedExpiredAt = $item['expired_at'];
                        //         $carbonDate = Carbon::parse($formattedExpiredAt);

                        //         $product = Product::where('sku', $sku)->lockForUpdate()->first();
                        //         if (! $product) {
                        //             throw new \Exception("Produk SKU {$sku} tidak ditemukan!");
                        //         }
                        //         $stokAwalGlobal = $product->stock;

                        //         // Ambil stok lokasi asal (Pakai whereDate agar match presisi)
                        //         $sourceLocation = ProductLocation::where('product_sku', $sku)
                        //             ->where('rack_id', $fromRack)
                        //             ->whereDate('expired_at', $formattedExpiredAt)
                        //             ->lockForUpdate()
                        //             ->first();

                        //         if (! $sourceLocation) {
                        //             throw new \Exception("Stok untuk SKU {$sku} dengan expired {$formattedExpiredAt} tidak ditemukan di rak asal (Rak ID: {$fromRack})!");
                        //         }

                        //         if ($sourceLocation->qty < $qtyToMove) {
                        //             throw new \Exception("Gagal! Stok SKU {$sku} di rak asal tidak mencukupi. Sisa di DB: {$sourceLocation->qty}, Diminta: {$qtyToMove}.");
                        //         }

                        //         // Cek Kapasitas Rak Tujuan
                        //         $rackModel = Rack::find($toRack);
                        //         if ($rackModel) {
                        //             $isLoadingDock = str_contains(strtolower($rackModel->rack_name), 'loading') ||
                        //                              str_contains(strtolower($rackModel->location_code), 'ld');

                        //             if (! $isLoadingDock) {
                        //                 if (! isset($virtualRackQty[$toRack])) {
                        //                     $virtualRackQty[$toRack] = ProductLocation::where('rack_id', $toRack)->sum('qty');
                        //                 }
                        //                 if (! isset($virtualRackQty[$fromRack])) {
                        //                     $virtualRackQty[$fromRack] = ProductLocation::where('rack_id', $fromRack)->sum('qty');
                        //                 }

                        //                 $currentAvailableSpace = $rackModel->capacity - $virtualRackQty[$toRack];

                        //                 if ($qtyToMove > $currentAvailableSpace) {
                        //                     throw new \Exception("Gagal! Rak {$rackModel->location_code} tidak muat. Sisa kapasitas: {$currentAvailableSpace}, mencoba memasukkan: {$qtyToMove}.");
                        //                 }

                        //                 $virtualRackQty[$fromRack] -= $qtyToMove;
                        //                 $virtualRackQty[$toRack] += $qtyToMove;
                        //             }
                        //         }

                        //         // Potong/Hapus Stok Rak Asal
                        //         $sourceQtyBefore = $sourceLocation->qty;
                        //         $sourceQtyAfter = $sourceQtyBefore - $qtyToMove;

                        //         if ($sourceQtyAfter <= 0) {
                        //             $sourceLocation->delete();
                        //         } else {
                        //             $sourceLocation->update(['qty' => $sourceQtyAfter]);
                        //         }

                        //         // Tambahkan ke Rak Tujuan
                        //         $batchCode = $sku.'-'.$carbonDate->format('Ymd');

                        //         $targetLoc = ProductLocation::where('product_sku', $sku)
                        //             ->where('rack_id', $toRack)
                        //             ->where('batch_code', $batchCode)
                        //             ->whereDate('expired_at', $formattedExpiredAt)
                        //             ->first();

                        //         if ($targetLoc) {
                        //             $targetLoc->increment('qty', $qtyToMove);
                        //         } else {
                        //             ProductLocation::create([
                        //                 'product_sku' => $sku,
                        //                 'rack_id' => $toRack,
                        //                 'batch_code' => $batchCode,
                        //                 'qty' => $qtyToMove,
                        //                 'expired_at' => $carbonDate,
                        //             ]);
                        //         }

                        //         $customNote = $item['notes'] ?? 'Pindah stok internal';

                        //         // Item Transaksi
                        //         $transaction->items()->create([
                        //             'product_sku' => $sku,
                        //             'qty' => $qtyToMove,
                        //             'qty_before' => $sourceQtyBefore,
                        //             'qty_after' => $sourceQtyAfter,
                        //             'rack_id' => $fromRack,
                        //             'target_rack_id' => $toRack,
                        //             'expired_at' => $formattedExpiredAt,
                        //             'notes' => $customNote,
                        //         ]);

                        //         // Stock Ledger Out
                        //         StockLedger::create([
                        //             'product_sku' => $sku,
                        //             'transaction_no' => $transactionNo,
                        //             'type' => 'MOVE',
                        //             'rack_id' => $fromRack,
                        //             'expired_at' => $formattedExpiredAt,
                        //             'qty' => -$qtyToMove,
                        //             'balance_before' => $stokAwalGlobal,
                        //             'balance_after' => $stokAwalGlobal,
                        //             'user_id' => auth()->id(),
                        //             'note' => $customNote." (Keluar dari Rak {$fromRack})",
                        //         ]);

                        //         // Stock Ledger In
                        //         StockLedger::create([
                        //             'product_sku' => $sku,
                        //             'transaction_no' => $transactionNo,
                        //             'type' => 'MOVE',
                        //             'rack_id' => $toRack,
                        //             'expired_at' => $formattedExpiredAt,
                        //             'qty' => $qtyToMove,
                        //             'balance_before' => $stokAwalGlobal,
                        //             'balance_after' => $stokAwalGlobal,
                        //             'user_id' => auth()->id(),
                        //             'note' => $customNote." (Masuk ke Rak {$toRack})",
                        //         ]);
                        //     }

                        //     $transaction->update(['total_qty' => $totalQtyMoved]);

                        //     // Step 3: Pemicu Maintenance Otomatis Jika Rak Kosong
                        //     foreach (array_keys($evacuateRackIds) as $rackIdToDisable) {
                        //         $remainingQtyInRack = ProductLocation::where('rack_id', $rackIdToDisable)
                        //             ->where('qty', '>', 0)
                        //             ->sum('qty');

                        //         if ($remainingQtyInRack <= 0) {
                        //             $rackModel = Rack::find($rackIdToDisable);
                        //             if ($rackModel && ! str_contains(strtolower($rackModel->location_code), 'ld')) {
                        //                 $rackModel->update([
                        //                     'is_maintenance' => true,
                        //                     'is_active' => false,
                        //                 ]);
                        //             }
                        //             ProductLocation::where('rack_id', $rackIdToDisable)->where('qty', '<=', 0)->delete();
                        //         }
                        //     }
                    } elseif ($request->type === 'ADJUSTMENT') {
                        $rawExpired = $item['expired_at'];
                        if (str_contains($rawExpired, 'T')) {
                            $rawExpired = explode('T', $rawExpired)[0];
                        }

                        $formattedExpiredAt = Carbon::parse($rawExpired)->format('Y-m-d');

                        $loc = ProductLocation::where('product_sku', $item['product_sku'])
                            ->where('rack_id', $item['rack_id'])
                            ->whereBetween('expired_at', [
                                Carbon::parse($formattedExpiredAt)->subDay()->startOfDay(),
                                Carbon::parse($formattedExpiredAt)->addDay()->endOfDay(),
                            ])
                            ->first();

                        $actualExpiredAt = $loc ? Carbon::parse($loc->expired_at)->format('Y-m-d') : $formattedExpiredAt;
                        $batchCode = $loc ? $loc->batch_code : ($item['product_sku'].'-'.Carbon::parse($actualExpiredAt)->format('Ymd'));
                        $qtyBefore = $loc ? (int) $loc->qty : 0;

                        // $jenis = $request->jenis ?? ($item['type'] === 'IN' ? 'MASUK' : 'KELUAR');
                        // $inputQty = abs((int) $item['qty']);

                        // if ($jenis === 'KELUAR') {
                        //     $realQty = -$inputQty; // Negatif untuk pengurangan
                        // } else {
                        //     $realQty = $inputQty;  // Positif untuk penambahan
                        // }

                        // $qtyAfter = $qtyBefore + $realQty;

                        $qtyActual = (int) $item['qty'];

                        if ($qtyActual < 0) {
                            throw new \Exception("Gagal Adjustment! Stok di rak ini tidak mencukupi (Stok saat ini: {$qtyBefore}).");
                        }

                        $realQty = $qtyActual - $qtyBefore;

                        $qtyAfter = $qtyActual;

                        if (($product->stock + $realQty) < 0) {
                            throw new \Exception("Gagal Adjustment! Perubahan ini menyebabkan stok global {$product->product_name} menjadi negatif.");
                        }

                        $product->increment('stock', $realQty);

                        // if ($realQty > 0) {
                        //     $product->increment('stock', abs($realQty));
                        // } else {
                        //     $product->decrement('stock', abs($realQty));
                        // }

                        if ($loc) {
                            if ($qtyAfter == 0) {
                                // Jika setelah dihitung fisiknya nol, hapus baris lokasinya agar rapi
                                $loc->delete();
                            } else {
                                $loc->update(['qty' => $qtyAfter]);
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
                            'expired_at' => $actualExpiredAt,
                            'notes' => $item['notes'],
                        ]);

                        StockLedger::create([
                            'product_sku' => $item['product_sku'],
                            'transaction_no' => $transactionNo,
                            'type' => 'ADJUSTMENT',
                            'rack_id' => $item['rack_id'],
                            'expired_at' => $actualExpiredAt,
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
            }

            // if (! empty($request->stock_order_id) && in_array($request->type, ['IN', 'OUT'])) {
            //     $stockOrder = StockOrder::with('items')->find($request->stock_order_id);

            //     if ($stockOrder) {
            //         $hasShortage = false;
            //         $unfulfilledItems = [];

            //         foreach ($request->items as $item) {
            //             $orderItem = $stockOrder->items()
            //                 ->where('product_sku', $item['product_sku'])
            //                 ->first();

            //             if ($orderItem) {
            //                 $orderItem->increment('qty_fulfilled', $item['qty']);
            //             }
            //         }

            //         // Refresh data items untuk cek kalkulasi status order
            //         $stockOrder->refresh();

            //         $allFulfilled = $stockOrder->items->every(fn ($i) => $i->qty_fulfilled >= $i->qty_ordered);
            //         // $anyFulfilled = $stockOrder->items->some(fn ($i) => $i->qty_fulfilled > 0);

            //         // if ($allFulfilled) {
            //         //     $stockOrder->update(['status' => 'COMPLETED']);
            //         // } elseif ($anyFulfilled) {
            //         //     $stockOrder->update(['status' => 'PARTIAL']);
            //         // }

            //         if (! $allFulfilled) {
            //             // Hitung sisa item yang belum terpenuhi
            //             $unfulfilledItems = [];

            //             foreach ($stockOrder->items as $i) {
            //                 $remainingQty = $i->qty_ordered - $i->qty_fulfilled;
            //                 if ($remainingQty > 0) {
            //                     //$hasShortage = true;
            //                     $unfulfilledItems[] = [
            //                         'product_sku' => $i->product_sku,
            //                         'qty_ordered' => $remainingQty,
            //                         'unit_price' => $i->unit_price,
            //                     ];
            //                 }
            //             }

            //             // Jika ada barang yang kurang, buat Order Draft Baru otomatis!
            //             if (count($unfulfilledItems) > 0) {
            //                 $prefix = $stockOrder->type === 'INBOUND' ? 'PO' : 'SO';
            //                 $today = Carbon::today()->format('Ymd');

            //                 $lastOrder = StockOrder::query()->where('order_no', 'like', "{$prefix}-{$today}-%")
            //                     ->orderBy('order_no', 'desc')
            //                     ->first();

            //                 $nextSeq = $lastOrder ? ((int) substr($lastOrder->order_no, -3)) + 1 : 1;
            //                 $newOrderNo = sprintf('%s-%s-%03d', $prefix, $today, $nextSeq);

            //                 // 1. Buat Header Draft PO/SO Baru untuk Sisa Pengiriman
            //                 $draftOrder = StockOrder::create([
            //                     'order_no' => $newOrderNo,
            //                     'type' => $stockOrder->type,
            //                     'supplier_id' => $stockOrder->supplier_id,
            //                     'customer_id' => $stockOrder->customer_id,
            //                     'status' => 'DRAFT', // Tersimpan sebagai draft/pending
            //                     'order_date' => Carbon::now()->format('Y-m-d'),
            //                     'expected_date' => null, // Biarkan null agar diisi manual jadwal kirim barunya oleh user
            //                     'parent_id' => $stockOrder->id,
            //                     'notes' => "Lanjutan (Backorder) dari Order {$stockOrder->order_no}",
            //                 ]);

            //                 // 2. Buat Items Sisa
            //                 foreach ($unfulfilledItems as $draftItem) {
            //                     $draftOrder->items()->create([
            //                         'product_sku' => $draftItem['product_sku'],
            //                         'qty_ordered' => $draftItem['qty_ordered'],
            //                         'qty_fulfilled' => 0,
            //                         'unit_price' => $draftItem['unit_price'],
            //                     ]);
            //                 }

            //                 // 3. Set Status Order Lama Menjadi COMPLETED (karena sisanya sudah dilimpahkan ke Draft PO Baru)
            //                 $stockOrder->update(['status' => 'COMPLETED']);
            //             }
            //         } else {
            //             // Jika semua barang pas/lengkap
            //             $stockOrder->update(['status' => 'COMPLETED']);
            //         }
            //     }
            // }
            if (! empty($request->stock_order_id) && in_array($request->type, ['IN', 'OUT'])) {
                $stockOrder = StockOrder::with('items')->find($request->stock_order_id);

                if ($stockOrder) {
                    // 1. Update qty_fulfilled per item yang ditransaksikan
                    foreach ($request->items as $item) {
                        $orderItem = $stockOrder->items()
                            ->where('product_sku', $item['product_sku'])
                            ->first();

                        if ($orderItem) {
                            $orderItem->increment('qty_fulfilled', $item['qty']);
                        }
                    }

                    // 2. Refresh data items untuk kalkulasi status presisi
                    $stockOrder->refresh();

                    $totalOrdered = $stockOrder->items->sum('qty_ordered');
                    $totalFulfilled = $stockOrder->items->sum('qty_fulfilled');

                    // 3. Pengecekan per SKU (apakah semua SKU sudah fulfilled 100%)
                    $isAllItemsFulfilled = $stockOrder->items->every(function ($item) {
                        return $item->qty_fulfilled >= $item->qty_ordered;
                    });

                    if ($isAllItemsFulfilled) {
                        // Jika SEMUA SKU & QTY sudah terpenuhi 100%
                        $stockOrder->update(['status' => 'COMPLETED']);
                    } elseif ($totalFulfilled > 0) {
                        // Jika baru SEBAGIAN SKU / QTY yang terpenuhi (Pengiriman Bertahap)
                        $stockOrder->update(['status' => 'PARTIAL']);
                    }
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
        $this->authorize('Transaksi');

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
        $this->authorize('Transaksi');

        return DB::transaction(function () use ($id) {
            // 1. Cari transaksi yang aktif (belum di-delete) beserta seluruh itemnya
            $transaction = StockTransaction::with('items')->findOrFail($id);

            if (! $transaction->created_at->isToday()) {
                throw new \Exception("Akses Ditolak! Transaksi {$transaction->transaction_no} sudah dikunci karena melewati hari penginputan.");
            }

            if (! in_array($transaction->type, ['IN', 'MOVE', 'OUT'])) {
                throw new \Exception('Sistem saat ini baru mendukung pembatalan transaksi Masuk (IN), Keluar (OUT), dan Pindah (MOVE)!');
            }

            foreach ($transaction->items as $item) {
                $product = Product::where('sku', $item->product_sku)->lockForUpdate()->first();
                if (! $product) {
                    throw new \Exception("Produk SKU {$item->product_sku} tidak ditemukan!");
                }

                $formattedExpiredAt = Carbon::parse($item->expired_at)->format('Y-m-d');
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
                    $product->increment('stock', $item->qty);

                    $location = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $item->rack_id)
                        ->where('batch_code', $batchCode)
                        ->whereDate('expired_at', $formattedExpiredAt)
                        ->lockForUpdate()
                        ->first();

                    if ($location) {
                        $location->increment('qty', $item->qty);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $item->product_sku,
                            'rack_id' => $item->rack_id,
                            'batch_code' => $batchCode,
                            'qty' => $item->qty,
                            'expired_at' => $formattedExpiredAt,
                        ]);
                    }
                } elseif ($transaction->type === 'MOVE') {
                    $fromRackId = $item->rack_id;        // Rak Asal semula
                    $toRackId = $item->target_rack_id; // Rak Tujuan transaksi sebelumnya

                    // 1. Tarik stok kembali dari RAK TUJUAN
                    $targetLocation = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $toRackId)
                        ->where('batch_code', $batchCode)
                        ->whereDate('expired_at', $formattedExpiredAt)
                        ->lockForUpdate()
                        ->first();

                    if (! $targetLocation || $targetLocation->qty < $item->qty) {
                        throw new \Exception(
                            "Gagal Batal Move! Stok SKU {$item->product_sku} di rak tujuan (ID: {$toRackId}) ".
                            'sudah berkurang/berpindah oleh transaksi lain.'
                        );
                    }

                    if ($targetLocation->qty - $item->qty <= 0) {
                        $targetLocation->delete();
                    } else {
                        $targetLocation->decrement('qty', $item->qty);
                    }

                    // 2. Kembalikan stok ke RAK ASAL
                    $sourceLocation = ProductLocation::where('product_sku', $item->product_sku)
                        ->where('rack_id', $fromRackId)
                        ->where('batch_code', $batchCode)
                        ->whereDate('expired_at', $formattedExpiredAt)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceLocation) {
                        $sourceLocation->increment('qty', $item->qty);
                    } else {
                        ProductLocation::create([
                            'product_sku' => $item->product_sku,
                            'rack_id' => $fromRackId,
                            'batch_code' => $batchCode,
                            'qty' => $item->qty,
                            'expired_at' => $formattedExpiredAt,
                        ]);
                    }
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

            if ($transaction->stock_order_id) {
                $stockOrder = StockOrder::with('items')->find($transaction->stock_order_id);
                if ($stockOrder) {
                    foreach ($transaction->items as $item) {
                        $orderItem = $stockOrder->items()->where('product_sku', $item->product_sku)->first();
                        if ($orderItem) {
                            $newFulfilled = max(0, $orderItem->qty_fulfilled - $item->qty);
                            $orderItem->update(['qty_fulfilled' => $newFulfilled]);
                        }
                    }

                    // Recalculate Status Order
                    $stockOrder->refresh();
                    $allFulfilled = $stockOrder->items->every(fn ($i) => $i->qty_fulfilled >= $i->qty_ordered);
                    $anyFulfilled = $stockOrder->items->some(fn ($i) => $i->qty_fulfilled > 0);

                    if ($allFulfilled) {
                        $stockOrder->update(['status' => 'COMPLETED']);
                    } elseif ($anyFulfilled) {
                        $stockOrder->update(['status' => 'PARTIAL']);
                    } else {
                        $stockOrder->update(['status' => 'PENDING']);
                    }
                }
            }

            $transaction->update([
                'deleted_by' => auth()->id() ?? User::first()?->id,
            ]);

            // 2. Lakukan Soft Delete pada data header transaksi utama
            $transaction->delete();

            return response()->json([
                'message' => "Transaksi {$transaction->transaction_no} berhasil dibatalkan dan stok telah disesuaikan.",
            ]);
        });
    }

    public function checkFefoBeforeOut(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_sku' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $itemsNeedEvacuation = [];
        $requiresEvacuation = false;

        foreach ($request->items as $item) {
            $sku = $item['product_sku'];
            $qtyRequested = (int) $item['qty'];

            // 1. Ambil info produk
            $product = Product::where('sku', $sku)->first();
            $productName = $product ? ($product->product_name) : $sku;

            // 2. Query TUNGGAL: Ambil SELURUH stok fisik produk di gudang, DIURUTKAN KETAT BERDASARKAN FEFO
            $allLocations = ProductLocation::with('rack')
                ->where('product_sku', $sku)
                ->where('qty', '>', 0)
                ->whereHas('rack', function ($q): void {
                    $q->where('is_maintenance', false)
                        ->where('is_active', true);
                })
                ->orderBy('expired_at', 'asc')
                ->get();

            $accumulatedQty = 0; // Total akumulasi stok yang berhasil diambil dari berbagai batch

            // 3. CONTINUOUS LOOP: Eksekusi berlanjut sesuai urutan Expired
            foreach ($allLocations as $loc) {
                // Jika kebutuhan user sudah terpenuhi 100%, STOP perulangan!
                if ($accumulatedQty >= $qtyRequested) {
                    break;
                }

                $remainingNeeded = $qtyRequested - $accumulatedQty; // Sisa Qty yang masih kurang
                $qtyToTake = min($loc->qty, $remainingNeeded);       // Porsi Qty yang diambil dari batch ini

                // Cek apakah lokasi stok saat ini ada di Loading Dock (LD)
                $isLoadingDock = str_contains(strtolower($loc->rack->rack_name ?? ''), 'loading') ||
                                 str_contains(strtolower($loc->rack->location_code ?? ''), 'ld');

                // 4. PEMISAHAN LOGIKA RAK:
                if ($isLoadingDock) {
                    // A. Stok di Loading Dock:
                    // -> TETAP DIHITUNG mengurangi sisa kebutuhan user ($accumulatedQty bertambah)
                    // -> TAPI TIDAK DIMUSUKKAN ke $itemsNeedEvacuation (karena tidak perlu di-MOVE)
                } else {
                    // B. Stok di Rak Internal (Non-LD):
                    // -> TETAP DIHITUNG mengurangi sisa kebutuhan user
                    // -> DAN WAJIB DIMUNCULKAN di alert evakuasi modal
                    $requiresEvacuation = true;

                    $formattedDate = $loc->expired_at
                        ? Carbon::parse($loc->expired_at)->translatedFormat('d M Y')
                        : '-';
                    $rawDate = $loc->expired_at
                        ? Carbon::parse($loc->expired_at)->format('Y-m-d')
                        : '';

                    $itemsNeedEvacuation[] = [
                        'product_sku' => $sku,
                        'product_name' => $productName,
                        'expired_at' => $formattedDate,
                        'expired_at_raw' => $rawDate,
                        'current_rack_code' => $loc->rack->location_code ?? 'RAK-INTERNAL',
                        'current_rack_id' => $loc->rack_id,
                        'available_qty' => $loc->qty,
                        'recommended_move_qty' => $qtyToTake, // Presisi QTY yang wajib di-MOVE ke LD
                        'qty' => $qtyToTake,
                        'reason' => 'Perlu dipindahkan ke Loading Dock (FEFO Strict)',
                    ];
                }

                // Tambahkan kuantitas yang berhasil tercover ke akumulasi
                $accumulatedQty += $qtyToTake;
            }
        }

        // Jika ada setidaknya 1 item rak internal yang harus dipindah ke LD
        if ($requiresEvacuation && count($itemsNeedEvacuation) > 0) {
            return response()->json([
                'status' => 'requires_evacuation',
                'message' => 'Terdapat barang di rak internal yang harus dipindahkan ke Loading Dock terlebih dahulu sesuai urutan Expired (FEFO).',
                'fefo_suggestions' => $itemsNeedEvacuation,
            ], 200);
        }

        // Jika seluruh kebutuhan Qty sudah terpenuhi secara aman murni dari Loading Dock
        return response()->json([
            'status' => 'success',
            'message' => 'Stok di Loading Dock aman dan sudah memenuhi aturan FEFO.',
        ], 200);
    }
}
