<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the products.
     */
    public function index(): JsonResource
    {
        $this->authorize('Lihat Produk');

        $products = Product::with('category')->latest()->get();

        return ProductResource::collection($products);
    }

    public function store(ProductStoreRequest $request)
    {
        $this->authorize('Daftar Produk');
        $data = $request->validated();

        if (! empty($data['sku'])) {
            // Pastikan SKU manual di-format rapi (Uppercase, tanpa spasi berlebih)
            $data['sku'] = strtoupper(trim(str_replace(' ', '', $data['sku'])));

            // Validasi manual tambahan jika di Request belum di-check unique
            $existingProduct = Product::query()->where('sku', $data['sku'])->first();
            if ($existingProduct) {
                return response()->json([
                    'message' => 'Gagal membuat produk. SKU "'.$data['sku'].'" sudah digunakan oleh produk "'.$existingProduct->product_name.'"!',
                ], 422);
            }
        } else {
            // Ambil Identitas Dasar (Wajib)
            $category = Category::findOrFail($data['category_id']);
            $codes = [];

            // Fungsi helper lokal untuk ambil 3 huruf uppercase
            $getShort = function ($value) {
                return strtoupper(substr(str_replace(' ', '', $value), 0, 3));
            };

            // SKU berdasarkan urutan field yang ada
            $codes[] = $getShort($category->category_name);
            $codes[] = $getShort($data['brand'] ?? 'XXX');

            // Tambahkan field nullable jika ada isinya
            if (! empty($data['type'])) {
                $codes[] = $getShort($data['type']);
            }
            if (! empty($data['packaging'])) {
                $codes[] = $getShort($data['packaging']);
            }
            if (! empty($data['size'])) {
                $codes[] = strtoupper(substr(str_replace(' ', '', $data['size']), 0, 8));
            }

            // Gabungkan sementara untuk mencari prefix unik di database
            $prefix = implode('-', $codes);

            // $lastProduct = Product::where('sku', 'like', $prefix.'-%', 'and')->orderBy('sku', 'desc')->first();
            $lastProduct = Product::query()->where('sku', 'like', $prefix.'-%')
                ->orderBy('sku', 'desc')
                ->first();
            $increment = $lastProduct ? ((int) substr($lastProduct->sku, -3) + 1) : 1;
            $number = str_pad($increment, 3, '0', STR_PAD_LEFT);

            // 4. Final SKU: SEM-BIM-GEL-PLT-PCS-500-001
            $data['sku'] = $prefix.'-'.$number;
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Produk berhasil dibuat dengan SKU detail',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $this->authorize('Daftar Produk');
        $data = $request->validated();

        $oldSku = $product->sku;

        $inputSku = ! empty($data['sku']) ? strtoupper(trim(str_replace(' ', '', $data['sku']))) : null;

        // 1. JIKA USER MENGISI / MENGEDIT SKU MANUALLY
        if ($inputSku) {
            $data['sku'] = $inputSku;

            // Validasi agar tidak bentrok dengan produk lain jika SKU-nya berubah
            if ($data['sku'] !== $oldSku) {
                $existingProduct = Product::query()->where('sku', $data['sku'])->first();
                if ($existingProduct) {
                    return response()->json([
                        'message' => 'Gagal memperbarui! SKU "'.$data['sku'].'" sudah digunakan oleh produk "'.$existingProduct->product_name.'".',
                    ], 422);
                }
            }

            // 1. CEK APAKAH USER MENGUBAH / MENGISI SKU MANUALLY SAAT UPDATE
            // if (! empty($data['sku'])) {
            //     $data['sku'] = strtoupper(trim(str_replace(' ', '', $data['sku'])));

            //     // Validasi agar tidak bentrok dengan produk lain
            //     if ($data['sku'] !== $oldSku) {
            //         $existingProduct = Product::where('sku', $data['sku'])->first();
            //         if ($existingProduct) {
            //             return response()->json([
            //                 'message' => 'Gagal memperbarui! SKU "'.$data['sku'].'" sudah digunakan oleh produk "'.$existingProduct->product_name.'".',
            //             ], 422);
            //         }
            //     }
        } elseif (
            // Regenerasi SKU jika identitas fisik produk diubah oleh user
            $product->category_id != $data['category_id'] ||
            $product->brand != $data['brand'] ||
            ($product->type ?? '') != ($data['type'] ?? '') ||
            ($product->packaging ?? '') != ($data['packaging'] ?? '') ||
            ($product->size ?? '') != ($data['size'] ?? '')) {
            $category = Category::findOrFail($data['category_id']);
            $codes = [];

            $getShort = function ($value) {
                return strtoupper(substr(str_replace(' ', '', $value), 0, 3));
            };

            $codes[] = $getShort($category->category_name);
            $codes[] = $getShort($data['brand'] ?? 'XXX');

            if (! empty($data['type'])) {
                $codes[] = $getShort($data['type']);
            }
            if (! empty($data['packaging'])) {
                $codes[] = $getShort($data['packaging']);
            }
            if (! empty($data['size'])) {
                $codes[] = strtoupper(substr(str_replace(' ', '', $data['size']), 0, 8));
            }

            $prefix = implode('-', $codes);

            // Cari urutan terakhir dengan mengecualikan ID produk ini sendiri agar tidak bentrok
            $lastProduct = Product::query()->where('sku', 'like', $prefix.'-%')
                ->where('sku', '!=', $oldSku)
                ->orderBy('sku', 'desc')
                ->first();

            $increment = $lastProduct ? ((int) substr($lastProduct->sku, -3) + 1) : 1;
            $number = str_pad($increment, 3, '0', STR_PAD_LEFT);

            $data['sku'] = $prefix.'-'.$number;
        } else {
            // Jika input SKU kosong dan fisik tidak diubah, pertahankan SKU lama produk
            $data['sku'] = $oldSku;
        }

        $updateData = array_intersect_key($data, array_flip([
            'sku',
            'product_name',
            'category_id',
            'brand',
            'type',
            'packaging',
            'size',
            'purchase_price',
            'selling_price',
            'holding_cost_per_day',
            'min_stock',
            'exp_warning_days',
            'is_active',
        ]));

        // Eksekusi Update ke Database menggunakan DB::table
        DB::table('products')->where('sku', $oldSku)->update($updateData);

        // Ambil data produk yang sudah ter-update
        $updatedProduct = Product::query()->where('sku', $data['sku'])->firstOrFail();

        // $product->update($data);

        return response()->json([
            'message' => 'Data produk berhasil diperbarui',
            'data' => new ProductResource($updatedProduct),
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Product $product): JsonResponse
    {
        $this->authorize('Daftar Produk');

        $this->authorize('toggleActive', $product);

        if ($product->is_active) {
            // Ambil data stok yang Qty-nya > 0 di tiap rak/lokasi
            // Catatan: Sesuaikan nama relasi 'locations' atau 'productLocations' dengan model Product Anda
            $stockDetails = $product->locations()
                ->with('rack')
                ->where('qty', '>', 0)
                ->get()
                ->map(function ($item) {
                    return [
                        'location_code' => $item->rack?->location_code ?? 'N/A',
                        'qty' => $item->qty,
                        'expired_at' => $item->expired_at ? date('d-m-Y', strtotime($item->expired_at)) : 'Tanpa Expired',
                    ];
                });

            $totalStok = $stockDetails->sum('qty');

            // Jika masih ada stok fisik (> 0), gagalkan disable & kirim rincian stoknya
            if ($totalStok > 0) {
                return response()->json([
                    'message' => 'Gagal menonaktifkan! Produk masih memiliki sisa stok aktif.',
                    'data' => [
                        'sku' => $product->sku,
                        'product_name' => $product->product_name,
                        'total_qty' => $totalStok,
                        'stocks' => $stockDetails,
                    ],
                ], 422);
            }
        }

        $product->is_active = ! $product->is_active;
        $product->save();

        return response()->json([
            'message' => $product->is_active ? 'Produk berhasil diaktifkan kembali.' : 'Produk berhasil dinonaktifkan.',
            'is_active' => (bool) $product->is_active,
        ]);
    }

    public function nearExpiredProducts(): JsonResponse
    {
        $this->authorize('Lihat Produk');

        // Ambil semua lokasi barang yang tanggal expired-nya <= (Hari ini + exp_warning_days)
        $nearExpired = DB::table('product_locations')
            ->join('products', 'product_locations.product_sku', '=', 'products.sku')
            ->join('racks', 'product_locations.rack_id', '=', 'racks.id')
            ->select(
                'products.sku',
                'products.product_name',
                'product_locations.batch_code',
                'product_locations.qty',
                'product_locations.expired_at',
                'racks.rack_name',
                DB::raw('DATEDIFF(product_locations.expired_at, NOW()) as days_remaining')
            )
            ->where('product_locations.qty', '>', 0)
            ->whereNotNull('product_locations.expired_at')
            ->whereRaw('DATEDIFF(product_locations.expired_at, NOW()) <= products.exp_warning_days')
            ->orderBy('product_locations.expired_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Daftar batch produk mendekati expired / butuh tindakan retur atau diskon',
            'total_items' => $nearExpired->count(),
            'data' => $nearExpired->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'batch_code' => $item->batch_code,
                    'qty' => $item->qty,
                    'rack_name' => $item->rack_name,
                    'expired_at' => $item->expired_at,
                    'days_remaining' => $item->days_remaining,
                    'action_recommendation' => $item->days_remaining <= 7
                        ? 'RETUR_TO_SUPPLIER / QUARANTINE'
                        : 'CLEARANCE_SALE_DISCOUNT',
                ];
            }),
        ]);
    }
}
