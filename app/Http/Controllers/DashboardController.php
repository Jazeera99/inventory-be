<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\StockLedger;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getDashboardSummary()
    {
        // 1. Total Produk Terdaftar
        $totalProduk = Product::count();

        // 2. Total Rak Terdaftar
        $totalRak = Rack::count();

        // 3. Total Semua Stok Fisik di Gudang Saat Ini
        $totalStok = (int) Product::sum('stock');

        // 4. Breakdown Stok Per SKU untuk Tooltip/Hover
        $stokPerProduk = ProductLocation::with(['product', 'rack'])
            ->where('qty', '>', 0)
            ->get()
            ->groupBy('product_sku')
            ->map(function ($locations, $sku) {
                $firstLoc = $locations->first();

                return [
                    'sku' => $sku,
                    'nama' => $firstLoc->product ? $firstLoc->product->product_name : 'Produk Tidak Diketahui',
                    'stok' => (int) ($firstLoc->product ? $firstLoc->product->stock : $locations->sum('qty')),
                    'sebaran' => $locations->map(function ($loc) {
                        return [
                            'id' => $loc->id,
                            'kodeLokasi' => $loc->rack ? $loc->rack->location_code : 'Tanpa Rak',
                            'qty' => (int) $loc->qty,
                            'expiredAt' => $loc->expired_at ? $loc->expired_at->toIso8601String() : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();
        // 5. Total Transaksi Mutasi Khusus Hari Ini saja
        $transaksiHariIni = StockLedger::query()->whereDate('created_at', Carbon::today())->count();

        // 6. 5 Transaksi Terbaru (Mutasi)
        $transaksiTerbaru = StockLedger::with(['product', 'user', 'rack'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($ledger) {
                return [
                    'id' => $ledger->id,
                    'tanggal' => $ledger->created_at->toIso8601String(),
                    'tipe' => $ledger->type, // IN, OUT, MOVE, ADJUSTMENT
                    'sku' => $ledger->product_sku,
                    'nama_produk' => $ledger->product ? $ledger->product->product_name : 'Produk Tidak Diketahui',
                    'qty' => (int) $ledger->qty,
                    'keterangan' => $ledger->note ?? "Transaksi {$ledger->type}",
                ];
            });

        // 7. Daftar Stok Menipis (Di bawah 20 Pcs per Rak)
        $stokMenipis = Product::query()->whereColumn('stock', '<', 'min_stock')
            ->orderBy('stock', 'asc')
            ->get()
            ->map(function ($prod, $index) {
                return [
                    'id' => $prod->sku,
                    'produkSku' => $prod->sku,
                    'produkNama' => $prod->product_name,
                    'quantity' => (int) $prod->stock,
                    'minStock' => (int) $prod->min_stock,
                ];
            });

        $today = Carbon::today();
        $targetDate = Carbon::today()->addDays(90);

        $produkExpiringAlert = ProductLocation::with(['product', 'rack'])
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            // Include stok yang sudah expired; sebelumnya data ini terlewat karena
            // query hanya mengambil tanggal mulai hari ini.
            ->where('expired_at', '<=', $targetDate->endOfDay())
            ->orderBy('expired_at', 'asc')
            ->get()
            ->map(function ($loc) use ($today) {
                $expDate = Carbon::parse($loc->expired_at);
                $daysLeft = (int) $today->diffInDays($expDate, false);

                return [
                    'id' => $loc->id,
                    'sku' => $loc->product_sku,
                    'product_name' => $loc->product ? $loc->product->product_name : 'Produk Tidak Diketahui',
                    'qty' => (int) $loc->qty,
                    'expired_at' => $loc->expired_at ? $expDate->format('Y-m-d') : null,
                    'days_left' => $daysLeft,
                    'rack_code' => $loc->rack ? $loc->rack->location_code : 'Gudang Utama',
                    // Supplier tercatat pada purchase order, bukan langsung pada produk.
                    'supplier_id' => null,
                    'supplier_name' => '-',
                ];
            });

        return response()->json([
            'cards' => [
                'totalProduk' => $totalProduk,
                'totalRak' => $totalRak,
                'totalStok' => $totalStok,
                'transaksiHariIni' => $transaksiHariIni,
            ],
            'stokPerProduk' => $stokPerProduk,
            'transaksiTerbaru' => $transaksiTerbaru,
            'stokMenipis' => $stokMenipis,
            'produkExpiringAlert' => $produkExpiringAlert,
        ]);
    }
}
