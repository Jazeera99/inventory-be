<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierStoreRequest;
use App\Http\Requests\SupplierUpdateRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('Lihat Supplier');

        $search = $request->input('search');

        $suppliers = Supplier::query()
            ->when($search, function ($query, $search) {
                $query->where('supplier_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return SupplierResource::collection($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierStoreRequest $request, Supplier $supplier)
    {
        $this->authorize('Daftar Supplier');

        $supplier = Supplier::create($request->validated());

        return (new SupplierResource($supplier))
            ->additional(['message' => 'Supplier berhasil ditambahkan.']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierUpdateRequest $request, Supplier $supplier)
    {
        $this->authorize('Daftar Supplier');

        $supplier->update($request->validated());

        return (new SupplierResource($supplier))
            ->additional(['message' => 'Supplier berhasil diperbarui.']);
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggleStatus(Supplier $supplier)
    {
        $supplier->update(['is_active' => !$supplier->is_active]);

        return response()->json([
            'message' => 'Status supplier berhasil diperbarui.',
            'is_active' => $supplier->is_active,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $this->authorize('Daftar Supplier');

        $supplier->delete($supplier->id);

        return response()->json([
            'message' => 'Supplier berhasil dihapus.',
        ]);
    }
}
