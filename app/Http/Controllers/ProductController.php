<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(): JsonResource
    {
        $products = Product::with('category')->latest()->get();

        return ProductResource::collection($products);
    }

    public function store(ProductStoreRequest $request)
    {
        $data = $request->validated();

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

        $lastProduct = Product::where('sku', 'like', $prefix.'-%', 'and')->orderBy('sku', 'desc')->first();
        $increment = $lastProduct ? ((int) substr($lastProduct->sku, -3) + 1) : 1;
        $number = str_pad($increment, 3, '0', STR_PAD_LEFT);

        // 4. Final SKU: SEM-BIM-GEL-PLT-PCS-500-001
        $data['sku'] = $prefix.'-'.$number;

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
        $data = $request->validated();

        // Catatan: Biasanya SKU tidak diupdate karena itu Primary Key yang berelasi ke Stok
        $product->update($data);

        return response()->json([
            'message' => 'Data produk berhasil diperbarui',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Product $product): JsonResponse
    {
        $this->authorize('toggleActive', $product);

        $product->is_active = ! $product->is_active;
        $product->save();

        return response()->json([
            'message' => 'Status produk berhasil diubah',
            'is_active' => (bool) $product->is_active,
        ]);
    }
}
