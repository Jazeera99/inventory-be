<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockLedgerIndexRequest;
use App\Http\Resources\StockLedgerResource;
use App\Models\ProductLocation;
use App\Models\StockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLedgerController extends Controller
{
    public function index(StockLedgerIndexRequest $request)
    {
        // $query = StockLedger::with(['rack', 'user'])
        //     ->where('product_sku', $request->sku);

        // if ($request->start_date) {
        //     $query->whereDate('created_at', '>=', $request->start_date);
        // }
        // if ($request->end_date) {
        //     $query->whereDate('created_at', '<=', $request->end_date);
        // }
        // if ($request->filled('expired_at')) {
        //     $query->whereDate('expired_at', $request->expired_at);
        // }

        // $ledgers = $query->orderBy('created_at', 'asc')->get();

        // $prevTransaction = StockLedger::where('product_sku', $request->sku)
        //     ->when($request->start_date, function ($q) use ($request): void {
        //         $q->where('created_at', '<', $request->start_date);
        //     })
        //     ->orderBy('created_at', 'desc')
        //     ->first();

        // // Hitung meta data sederhana
        // $initial = $prevTransaction ? $prevTransaction->balance_after : 0;
        // $final = $ledgers->last() ? $ledgers->last()->balance_after : 0;

        // return response()->json([
        //     'data' => StockLedgerResource::collection($ledgers),
        //     'meta' => [
        //         'sku' => $request->sku,
        //         'initial_balance' => (int) $initial,
        //         'final_balance' => (int) $final,
        //         'period' => [
        //             'start' => $request->start_date ?? 'Awal',
        //             'end' => $request->end_date ?? now()->toDateString(),
        //         ],
        //     ],
        // ]);

        $baseQuery = StockLedger::with(['rack', 'user'])
            ->where('product_sku', $request->sku);

        // Jika user memfilter berdasarkan expired tertentu, perhitungan saldo berjalan dikunci pada expired itu
        if ($request->filled('expired_at')) {
            $baseQuery->whereDate('expired_at', $request->expired_at);
        }

        // Ambil koleksi data murni diurutkan dari transaksi paling lama (asc)
        $allLedgers = $baseQuery->orderBy('created_at', 'asc')->get();

        // 2. Filter data untuk baris tabel yang mau ditampilkan sesuai range tanggal (jika ada filter tanggal)
        $filteredLedgers = $allLedgers->filter(function ($ledger) use ($request) {
            if ($request->start_date && $ledger->created_at->format('Y-m-d') < $request->start_date) {
                return false;
            }
            if ($request->end_date && $ledger->created_at->format('Y-m-d') > $request->end_date) {
                return false;
            }

            return true;
        });

        // 3. LOGIKA JALUR SALDO BERJALAN (RUNNING BALANCE) - STANDAR AUDIT GUDANG
        $runningBalance = 0;
        $initialBalance = 0;
        $hasFoundStart = false;

        foreach ($allLedgers as $ledger) {
            // Simpan nilai running balance SEBELUM ditambah/dikurang qty saat ini
            $balanceBeforeThisTrx = $runningBalance;

            $actualQty = 0;
            $ledgerType = strtoupper(trim($ledger->type));

            if ($ledgerType === 'IN') {
                $actualQty = abs($ledger->qty);
            } elseif ($ledgerType === 'OUT') {
                $actualQty = -abs($ledger->qty);
            } elseif ($ledgerType === 'ADJUSTMENT') {
                // Jika adjustment, gunakan selisih perubahan qty-nya
                if ($ledger->qty != 0) {
                    $actualQty = $ledger->qty;
                } else {
                    $actualQty = $ledger->qty_after - $ledger->qty_before;
                }
            } elseif ($ledger->type === 'MOVE') {
                // MOVE adalah perpindahan antar rak, secara akumulasi total SKU global TIDAK BERUBAH
                // Kecuali jika Anda sedang memfilter per EXPIRED_AT / RAK tertentu.
                if ($request->filled('expired_at')) {
                    $actualQty = $ledger->qty;
                    // $actualQty = $ledger->qty_after - $ledger->qty_before;
                } else {
                    $actualQty = 0;
                }
            }
            $runningBalance += $actualQty;

            // Timpa saldo database dengan saldo berjalan yang asli (khusus range/expired terfilter)
            $ledger->balance_before = $balanceBeforeThisTrx;
            $ledger->balance_after = $runningBalance;

            // Tentukan nilai Stok Awal untuk periode yang dipilih
            if ($request->filled('start_date')) {
                if ($ledger->created_at->format('Y-m-d') >= $request->start_date && ! $hasFoundStart) {
                    $initialBalance = $balanceBeforeThisTrx;
                    $hasFoundStart = true;
                }
            }
        }

        // Jika filter start_date tidak diisi, berarti Stok Awal murni dimulai dari 0 sebelum transaksi pertama
        if (! $request->filled('start_date')) {
            $initialBalance = 0;
        }

        // Jika filter start_date diisi tapi tidak ada transaksi sama sekali di dalam range tersebut
        if ($request->filled('start_date') && ! $hasFoundStart) {
            $initialBalance = $runningBalance;
        }

        // Nilai Akhir adalah posisi running balance terakhir dari seluruh records data yang ada
        $finalBalance = $filteredLedgers->last() ? $filteredLedgers->last()->balance_after : $initialBalance;

        return response()->json([
            'data' => StockLedgerResource::collection($filteredLedgers),
            'meta' => [
                'sku' => $request->sku,
                'initial_balance' => (int) $initialBalance,
                'final_balance' => (int) $finalBalance,
                'period' => [
                    'start' => $request->start_date ?? 'Awal',
                    'end' => $request->end_date ?? now()->toDateString(),
                ],
            ],
        ]);
    }

    public function getExpiredOptions(Request $request)
    {
        $request->validate(['sku' => 'required|string']);

        // Ambil tanggal expired unik dari tabel product_locations
        $dates = ProductLocation::where('product_sku', $request->sku)
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            ->distinct()
            ->orderBy('expired_at', 'asc')
            ->pluck('expired_at');

        return response()->json($dates);
    }

    public function summary(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable',
            'end_date' => 'nullable|after_or_equal:start_date',
        ]);

        $hasStartDate = $request->filled('start_date');
        $hasEndDate = $request->filled('end_date');

        // Jika kosong, startDate diset dari awal waktu seeder (Mei) atau awal tahun aman
        $startDate = $hasStartDate
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::parse('2026-01-01')->startOfDay();

        $endDate = $hasEndDate
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        $products = DB::table('products')
            ->select('sku', 'product_name as produkNama')
            ->get();

        $summaryData = $products->map(function ($product) use ($startDate, $endDate, $hasStartDate) {
            $allLedgers = DB::table('stock_ledgers')
                ->where('product_sku', $product->sku)
                ->orderBy('created_at', 'asc')
                ->get();

            $runningBalance = 0;
            $stokAwal = 0;
            $totalMasuk = 0;
            $totalKeluar = 0;
            $totalAdj = 0;
            $totalMoveDisplay = 0;
            $hasFoundStart = false;

            foreach ($allLedgers as $ledger) {
                $ledgerDate = Carbon::parse($ledger->created_at);

                // Definisikan kuantitas aktual berdasarkan tipe transaksi (SAMA DENGAN INDEX)
                $actualQty = 0;

                $ledgerType = strtoupper(trim($ledger->type));

                if ($ledgerType === 'IN') {
                    $actualQty = abs($ledger->qty);
                } elseif ($ledgerType === 'OUT') {
                    $actualQty = -abs($ledger->qty);
                } elseif ($ledgerType === 'ADJUSTMENT') {
                    if ($ledger->qty != 0) {
                        $actualQty = $ledger->qty;
                    } else {
                        $actualQty = $ledger->qty_after - $ledger->qty_before;
                    }
                } elseif ($ledgerType === 'MOVE') {
                    // Ikuti aturan index: secara global MOVE tidak mengubah total akumulasi SKU
                    $actualQty = 0;
                }

                // Catat posisi Stok Awal tepat sebelum range tanggal filter dimulai
                // if ($ledgerDate >= $startDate && ! $hasFoundStart) {
                //     $stokAwal = $runningBalance;
                //     $hasFoundStart = true;
                // }

                if ($hasStartDate && $ledgerDate->gte($startDate) && ! $hasFoundStart) {
                    $stokAwal = $runningBalance;
                    $hasFoundStart = true;
                }

                // Jalankan saldo berjalan
                $runningBalance += $actualQty;

                // Jika transaksi berada di dalam periode filter berjalan, kelompokkan mutasinya
                if ($ledgerDate->between($startDate, $endDate)) {
                    if ($ledgerType === 'IN') {
                        $totalMasuk += abs($ledger->qty);
                    } elseif ($ledgerType === 'OUT') {
                        $totalKeluar += abs($ledger->qty);
                    } elseif ($ledgerType === 'ADJUSTMENT') {
                        $totalAdj += $actualQty;
                    } elseif ($ledgerType === 'MOVE' && $ledger->qty > 0) {
                        $totalMoveDisplay += $ledger->qty;
                    }
                }
            }

            // $stokAwal = DB::table('stock_ledgers')
            //     ->where('product_sku', $product->sku)
            //     ->where('created_at', '<', $startDate)
            //     ->sum(DB::raw("
            //         CASE
            //             WHEN type = 'IN' THEN ABS(qty)
            //             WHEN type = 'OUT' THEN -ABS(qty)
            //             WHEN type = 'ADJUSTMENT' THEN qty
            //             ELSE 0
            //         END
            //     "));

            // // 2. Hitung Total Masuk (Periode berjalan)
            // $totalMasuk = DB::table('stock_ledgers')
            //     ->where('product_sku', $product->sku)
            //     ->whereBetween('created_at', [$startDate, $endDate])
            //     ->where('type', 'IN')
            //     ->sum(DB::raw('ABS(qty)'));

            // // 3. Hitung Total Keluar (Periode berjalan)
            // $totalKeluar = DB::table('stock_ledgers')
            //     ->where('product_sku', $product->sku)
            //     ->whereBetween('created_at', [$startDate, $endDate])
            //     ->where('type', 'OUT')
            //     ->sum(DB::raw('ABS(qty)'));
            // // $totalKeluar = abs($totalKeluar);

            // // 4. Hitung Total Adjustment (Periode berjalan)
            // $totalAdj = DB::table('stock_ledgers')
            //     ->where('product_sku', $product->sku)
            //     ->whereBetween('created_at', [$startDate, $endDate])
            //     ->where('type', '=', 'ADJUSTMENT')
            //     ->sum('qty');

            // // $netMove = DB::table('stock_ledgers')
            // //     ->where('product_sku', $product->sku)
            // //     ->whereBetween('created_at', [$startDate, $endDate])
            // //     ->where('type', 'MOVE')
            // //     ->sum(DB::raw('qty_after - qty_before'));

            // $netMove = 0;

            // // Kebutuhan display total pemindahan fisik (bukan nilai mutasi bersih)
            // $totalMoveDisplay = DB::table('stock_ledgers')
            //     ->where('product_sku', $product->sku)
            //     ->whereBetween('created_at', [$startDate, $endDate])
            //     ->where('type', 'MOVE')
            //     ->where('qty', '>', 0)
            //     ->sum('qty');

            // // 5. Kalkulasi Stok Akhir
            // $stokAkhir = $stokAwal + $totalMasuk - $totalKeluar + $totalAdj + $netMove;

            if (! $hasStartDate || ! $hasFoundStart) {
                $stokAwal = 0;
            }

            // Stok akhir didapat dari posisi running balance terakhir
            $stokAkhir = $runningBalance;

            $productLocations = DB::table('product_locations')
                ->leftJoin('racks', 'product_locations.rack_id', '=', 'racks.id')
                ->where('product_locations.product_sku', $product->sku)
                ->where('product_locations.qty', '>', 0)
                ->orderBy('product_locations.expired_at', 'asc')
                ->select(
                    DB::raw('COALESCE(racks.location_code, product_locations.rack_id) as nama_rak'),
                    'product_locations.expired_at',
                    'product_locations.qty'
                )
                ->get();

            $firstLocation = $productLocations->first();

            // $expiredTerdekat = ($firstLocation && $firstLocation->expired_at)
            // ? date('Y-m-d', strtotime($firstLocation->expired_at))
            // : '-';

            $expiredTerdekat = ($firstLocation && $firstLocation->expired_at && $firstLocation->expired_at !== '0000-00-00')
            ? date('Y-m-d', strtotime($firstLocation->expired_at))
            : '-';

            // 6. Ambil tanggal expired terdekat
            // if ($stokAkhir <= 0) {
            //     $expiredTerdekat = '-';
            // } else {
            //     $expiredTerdekat = ($firstLocation && $firstLocation->expired_at)
            //         ? date('Y-m-d', strtotime($firstLocation->expired_at))
            //         : '-';
            // }

            // 7. Cari tahu kapan transaksi terakhir terjadi (diambil dari created_at)
            $lastUpdate = DB::table('stock_ledgers')
                ->where('product_sku', $product->sku)
                ->orderBy('created_at', 'desc')
                ->value('created_at');

            $lokasiDetail = $productLocations->map(function ($loc) {
                return [
                    'rackName' => $loc->nama_rak ?? 'Tanpa Rak',
                    'expiredAt' => ($loc->expired_at && $loc->expired_at !== '0000-00-00') ? date('Y-m-d', strtotime($loc->expired_at)) : 'Tanpa Expired',
                    'qty' => (int) $loc->qty,
                ];
            });

            return [
                'sku' => $product->sku,
                'produkNama' => $product->produkNama,
                'stokAwal' => (int) $stokAwal,
                'totalMasuk' => (int) $totalMasuk,
                'totalKeluar' => (int) $totalKeluar,
                'totalAdj' => (int) $totalAdj,
                'totalMove' => (int) $totalMoveDisplay,
                'stokAkhir' => (int) $stokAkhir,
                // Memperbaiki bug typo 'Y-m-day' menjadi 'Y-m-d'
                'expiredTerdekat' => $expiredTerdekat ?? '-',
                'lastUpdate' => $lastUpdate ?? '-',
                'locations' => $lokasiDetail,
            ];
        });

        return response()->json([
            'data' => $summaryData,
        ]);
    }
}
