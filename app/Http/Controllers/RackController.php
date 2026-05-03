<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackStoreRequest;
use App\Http\Resources\RackResource;
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
        ]);

        $racks = [];
        for ($col = 1; $col <= $request->total_column; $col++) {
            for ($lvl = 1; $lvl <= $request->total_level; $lvl++) {
                $racks[] = [
                    'rack_name' => 'Rak '.strtoupper($request->rack_name),
                    'column_number' => $col,
                    'level_number' => $lvl,
                    'location_code' => strtoupper($request->rack_name).$col.'-'.$lvl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert banyak data sekaligus, lebih cepat!
        Rack::insert($racks);

        return response()->json(['message' => 'Rak berhasil digenerate!']);
    }

    public function index()
    {
        $this->authorize('Manajemen Rak');

        $racks = Rack::paginate();

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

    // public function update(RackUpdateRequest $request, Rack $rack)
    // {
    //     $this->authorize('Manajemen Rak');

    //     $rack->update($request->validated());

    //     return new RackResource($rack);
    // }

    public function toggleMaintenance(Rack $rack)
    {
        $this->authorize('Manajemen Rak');

        $rack->update([
            'is_maintenance' => ! $rack->is_maintenance,
            'is_active' => $rack->is_maintenance,
        ]);

        return new RackResource($rack);
    }
}
