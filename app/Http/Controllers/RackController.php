<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackStoreRequest;
use App\Http\Requests\RackUpdateRequest;
use App\Http\Resources\RackResource;
use App\Models\ProductLocation;
use App\Models\Rack;
use Illuminate\Http\Request;

class RackController extends Controller
{
    public function generate(Request $request)
    {
        $this->authorize('Manajemen Rak');

        $request->validate([
            'rack_name' => 'required|string',
            'total_column' => 'required|integer|max:10',
            'total_level' => 'required|integer|max:5',
            'capacity' => 'required|integer|min:1',
        ]);

        $cleanName = $request->rack_name;
        if (str_starts_with(strtolower($cleanName), 'rak ')) {
            $cleanName = substr($cleanName, 4);
        }
        $cleanName = strtoupper(trim($cleanName));

        $alreadyExistingCodes = [];
        $generatedCodes = [];

        \DB::transaction(function () use ($request, $cleanName, &$alreadyExistingCodes, &$generatedCodes): void {
            for ($col = 1; $col <= $request->total_column; $col++) {
                for ($lvl = 1; $lvl <= $request->total_level; $lvl++) {
                    $expectedCode = $cleanName.$col.'-'.$lvl;

                    // Cek apakah kode lokasi ini sudah terdaftar di database
                    $exists = Rack::where('location_code', $expectedCode)->exists();

                    if ($exists) {
                        $alreadyExistingCodes[] = $expectedCode;
                    } else {
                        // Jika belum ada, baru kita create aman tanpa takut duplicate entry
                        Rack::create([
                            'rack_name' => 'Rak '.$cleanName,
                            'column_number' => $col,
                            'level_number' => $lvl,
                            'capacity' => $request->capacity,
                            'location_code' => null, // Biarkan diisi otomatis oleh Model boot
                        ]);
                        $generatedCodes[] = $expectedCode;
                    }
                }
            }
        });

        if (empty($generatedCodes)) {
            return response()->json([
                'status' => 'info',
                'message' => 'Proses dibatalkan. Semua rak dalam jangkauan ini ('.implode(', ', $alreadyExistingCodes).') sudah terdaftar di database.',
                'already_exists' => $alreadyExistingCodes,
                'generated' => [],
            ], 200);
        }

        // KONDISI 2: Jika sebagian sudah ada, dan sebagian baru berhasil dibuat
        if (! empty($alreadyExistingCodes)) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Berhasil men-generate '.count($generatedCodes).' lokasi rak baru ('.implode(', ', $generatedCodes).'). Namun, '.count($alreadyExistingCodes).' lokasi rak dilewati karena sudah ada ('.implode(', ', $alreadyExistingCodes).').',
                'already_exists' => $alreadyExistingCodes,
                'generated' => $generatedCodes,
            ], 200);
        }

        // KONDISI 3: Murni sukses total (semua koordinat berhasil dibuat baru)
        return response()->json([
            'status' => 'success',
            'message' => 'Semua lokasi rak ('.implode(', ', $generatedCodes).') berhasil digenerate!',
            'already_exists' => [],
            'generated' => $generatedCodes,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('Lihat Rak');

        $racks = Rack::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('rack_name', 'like', "%{$search}%")
                        ->orWhere('location_code', 'like', "%{$search}%");
                });
            })
            ->paginate(50);

        return RackResource::collection($racks);
    }

    public function store(RackStoreRequest $request)
    {
        $this->authorize('Manajemen Rak');

        // dd(auth()->user()->role->permissions);

        // $data = $request->validated();

        // $this->authorize('Manajemen Rak');

        $rack = Rack::create($request->validated());

        return new RackResource($rack);
    }

    public function update(RackUpdateRequest $request, Rack $rack)
    {
        $this->authorize('Manajemen Rak');

        $rack->update($request->validated());

        return new RackResource($rack);
    }

    public function toggleMaintenance(Rack $rack)
    {
        $this->authorize('Manajemen Rak');

        $targetStatus = ! $rack->is_maintenance;

        if ($targetStatus === true) {
            $totalCurrentQty = ProductLocation::where('rack_id', $rack->id)
                ->where('qty', '>', 0)
                ->sum('qty');

            if ($totalCurrentQty > 0) {
                // $rack->update([
                //     'is_maintenance' => true,
                //     'is_active' => false,
                // ]);

                // Jangan toggle dulu, kirim warning ke frontend agar dialihkan ke menu pindah produk
                return response()->json([
                    'status' => 'warning',
                    'action' => 'evacuation_required',
                    'message' => "Gagal menonaktifkan rak. Rak {$rack->location_code} tidak dapat di-disable karena masih menampung {$totalCurrentQty} produk. Anda harus mengevakuasi seluruh stok ini terlebih dahulu melalui menu Pindah Produk!",
                    'total_evacuate_qty' => $totalCurrentQty,
                    'source_rack_id' => $rack->id,
                    'redirect_to' => '/transaksi/pindah-produk',
                ], 200); // Menggunakan status 200 dengan payload custom agar dibaca lancar oleh Axios
            }
        }

        $rack->update([
            'is_maintenance' => ! $rack->is_maintenance,
            'is_active' => $rack->is_maintenance,
        ]);

        return new RackResource($rack);
    }

    public function recommendations(Request $request)
    {
        $this->authorize('Lihat Rak');
        // Fitur ini bisa diakses saat transaksi pindah produk
        $request->validate([
            'qty_needed' => 'required|integer|min:1',
            'current_rack_id' => 'nullable|integer',
            'form_items' => 'nullable|array',
            'form_items.*.qty' => 'integer|min:1',
            'form_items.*.target_rack_id' => 'nullable|integer',
            'form_items.*.rack_id' => 'nullable|integer',
        ]);

        $qtyNeeded = (int) $request->get('qty_needed');
        $currentRackId = $request->get('current_rack_id');
        $formItems = $request->get('form_items', []);

        // 1. Ambil rak yang tidak maintenance & bukan rak asal
        $racks = Rack::where('is_maintenance', false)
            ->where('is_active', true)
            ->when($currentRackId, function ($query) use ($currentRackId): void {
                $query->where('id', '!=', $currentRackId);
            })
            ->get();

        $frontendAllocatedQty = [];
        foreach ($formItems as $item) {
            // Jika transaksi MOVE pakai target_rack_id, jika transaksi IN pakai rack_id
            $destRackId = $item['target_rack_id'] ?? $item['rack_id'] ?? null;
            if ($destRackId) {
                if (! isset($frontendAllocatedQty[$destRackId])) {
                    $frontendAllocatedQty[$destRackId] = 0;
                }
                $frontendAllocatedQty[$destRackId] += (int) $item['qty'];
            }
        }

        $recommendedRacks = $racks->map(function ($rack) use ($frontendAllocatedQty) {
            // 2. Hitung sisa kapasitas: Kolom tabel Anda adalah 'capacity'
            $dbStoredQty = ProductLocation::where('rack_id', $rack->id)->sum('qty');

            // Hitung stok terpakai dari form frontend saat ini untuk rak ini
            $frontendQty = $frontendAllocatedQty[$rack->id] ?? 0;

            // Jika area ini Loading Dock, buat kapasitasnya sangat besar (unlimited)
            $isLoadingDock = str_contains(strtolower($rack->rack_name), 'loading') || str_contains(strtolower($rack->location_code), 'ld');

            if ($isLoadingDock) {
                $rack->available_capacity = 999999; // bypass limit
            } else {
                // Sisa kapasitas = Kapasitas Rak - Stok di DB - Stok yang sedang diinput di form
                $rack->available_capacity = $rack->capacity - $dbStoredQty - $frontendQty;
            }

            return $rack;
        })
            ->filter(function ($rack) use ($qtyNeeded) {
                // 3. Hanya ambil rak yang muat menampung qty evakuasi
                return $rack->available_capacity >= $qtyNeeded;
            })
        // 4. Urutkan dari sisa kapasitas terkecil ke terbesar (agar dapet yang paling pas/efisien)
            ->sortBy('available_capacity')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $recommendedRacks,
        ]);
    }

    public function getContentsForEvacuation(Rack $rack)
    {
        $this->authorize('Lihat Rak');

        // Ambil semua lokasi produk yang kuantitasnya > 0 di rak ini
        $contents = ProductLocation::where('rack_id', $rack->id)
            ->where('qty', '>', 0)
            ->get();

        if ($contents->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'source_rack_code' => $rack->location_code,
                'items' => [],
                'recommended_target_rack_id' => null,
            ]);
        }

        // Hitung total quantity barang yang dievakuasi dari rak ini
        $totalQtyEvacuated = $contents->sum('qty');

        // CARI 1 RAK TUNGGAL YANG MUAT MENAMPUNG SEMUA BARANG TERSEBUT
        $recommendedRack = Rack::where('id', '!=', $rack->id)
            ->where('is_maintenance', false)
            ->where('location_code', '!=', 'LD-01')
            ->get()
            ->map(function ($r) {
                // Hitung kapasitas tersisa di setiap rak alternatif
                $currentStoredQty = ProductLocation::where('rack_id', $r->id)->sum('qty');
                $r->available_capacity = $r->capacity - $currentStoredQty;

                return $r;
            })
            ->filter(function ($r) use ($totalQtyEvacuated) {
                // Hanya pilih rak yang sisa ruangnya cukup untuk total kuantitas evakuasi
                return $r->available_capacity >= $totalQtyEvacuated;
            })
            ->sortBy('available_capacity') // Ambil yang kapasitasnya paling efisien (paling pas)
            ->first();

        // Jika tidak ada rak yang muat sekaligus dalam 1 wadah, cari saja rak aktif alternatif terdekat
        if (! $recommendedRack) {
            $recommendedRack = Rack::where('id', '!=', $rack->id)
                ->where('is_maintenance', false)
                ->where('location_code', '!=', 'LD-01')
                ->first();
        }

        // Kembalikan data barang beserta ID rak target rekomendasi ke frontend
        $formattedItems = $contents->map(function ($item) {
            return [
                'product_sku' => $item->product_sku,
                'qty' => (int) $item->qty,
                'expired_at' => $item->expired_at,
                'rack_id' => (int) $item->rack_id,
                'isValid' => true,
            ];
        })->toArray();

        return response()->json([
            'status' => 'success',
            'source_rack_code' => $rack->location_code,
            'recommended_target_rack_id' => $recommendedRack ? $recommendedRack->id : null,
            'items' => $formattedItems,
        ]);
    }
}
