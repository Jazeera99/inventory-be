<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductLocationStoreRequest;
use App\Http\Resources\ProductLocationResource;
use App\Models\ProductLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductLocationController extends Controller
{
    /**
     * Menampilkan semua daftar lokasi produk (untuk tabel/stok opname)
     */
    public function index(Request $request)
    {
        $query = ProductLocation::with(['product', 'rack'])
            ->where('qty', '>', 0); // Hanya ambil yang stoknya masih ada

        $query->when($request->search, function ($q, $search): void {
            $q->where('product_sku', 'like', "%{$search}%")
                ->orWhere('batch_code', 'like', "%{$search}%");
        });

        // LOGIKA BARU: Jika minta per_page=all, ambil semua data tanpa paginasi
        // Ini sangat berguna untuk dropdown "Dari Lokasi" di frontend
        if ($request->per_page === 'all') {
            return ProductLocationResource::collection($query->get());
        }

        // Defaultnya tetap pakai paginasi untuk tampilan tabel biasa
        $locations = $query->paginate($request->per_page ?? 10);

        // $locations = ProductLocation::with(['product', 'rack'])
        //     ->when($request->search, function ($query, $search): void {
        //         $query->where('product_sku', 'like', "%{$search}%")
        //             ->orWhere('batch_code', 'like', "%{$search}%");
        //     })
        //     ->paginate($request->per_page ?? 10);

        return ProductLocationResource::collection($locations);
    }

    /**
     * Menyimpan lokasi produk baru secara manual
     * (Biasanya digunakan jika ada penyesuaian stok/pindah rak manual)
     */
    public function store(ProductLocationStoreRequest $request)
    {
        // Logika batch_code otomatis: SKU + Tanggal Expired Ymd
        $batchCode = $request->product_sku.'-'.date('Ymd', strtotime($request->expired_at));

        DB::table('product_locations')
            ->where('product_sku', $request->product_sku)
            ->where('rack_id', $request->rack_id)
            ->where('batch_code', $batchCode)
            ->decrement('qty', $request->qty);

        $location = ProductLocation::updateOrCreate(
            [
                'product_sku' => $request->product_sku,
                'rack_id' => $request->target_rack_id,
                'batch_code' => $batchCode,
            ],
            [
                'qty' => $request->actual_qty,
                'expired_at' => $request->expired_at,
            ]
        );

        return new ProductLocationResource($location->refresh());
    }

    /**
     * Menampilkan detail satu lokasi (untuk scan barcode rak)
     */
    public function show(ProductLocation $productLocation)
    {
        return new ProductLocationResource($productLocation->load(['product', 'rack']));
    }
}
