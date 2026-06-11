<?php

namespace App\Http\Requests;

use App\Models\ProductLocation;
use App\Models\Rack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockTransactionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|IN:in,out,IN,OUT,MOVE,ADJUSTMENT',
            'date' => 'required|date',
            // 'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_sku' => 'required|exists:products,sku',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.target_rack_id' => 'nullable|required_if:type,MOVE|exists:racks,id',
            'items.*.expired_at' => 'nullable|required_if:type,IN,ADJUSTMENT|date',
            'items.*.notes' => 'nullable|string',
        ];
    }

    /**
     * Logika Kapasitas Rak ditaruh di sini (withValidator)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = strtoupper($this->input('type'));
            $items = $this->input('items', []);

            // Hanya cek kapasitas jika barang MASUK (IN) atau PINDAH RAK (MOVE)
            if (! in_array($type, ['IN', 'MOVE'])) {
                return;
            }

            // 1. Hitung total qty baru yang mau dimasukkan per Rak ID
            $incomingTotalsPerRack = [];
            foreach ($items as $item) {
                $qty = (int) $item['qty'];

                // Jika MOVE, yang dicek adalah target_rack_id. Jika IN, yang dicek rack_id.
                $destRackId = ($type === 'MOVE') ? ($item['target_rack_id'] ?? null) : $item['rack_id'];

                if ($destRackId) {
                    if (! isset($incomingTotalsPerRack[$destRackId])) {
                        $incomingTotalsPerRack[$destRackId] = 0;
                    }
                    $incomingTotalsPerRack[$destRackId] += $qty;
                }
            }

            // 2. Bandingkan dengan kapasitas asli di database
            foreach ($incomingTotalsPerRack as $rackId => $totalIncomingQty) {
                $rack = Rack::find($rackId);
                if (! $rack) {
                    continue;
                }

                // Ambil jumlah qty barang yang sudah ada di rak tersebut sekarang
                $currentStockInRack = ProductLocation::where('rack_id', $rackId)->sum('qty');

                // Cari sisa space kosong
                $availableSpace = $rack->capacity - $currentStockInRack;

                // Jika qty baru > sisa space kosong, gagalkan!
                if ($totalIncomingQty > $availableSpace) {
                    // Cari baris item mana di array yang bikin penuh, lalu kasi alert error
                    foreach ($items as $index => $item) {
                        $checkId = ($type === 'MOVE') ? ($item['target_rack_id'] ?? null) : $item['rack_id'];
                        if ($checkId == $rackId) {
                            $validator->errors()->add(
                                "items.{$index}.qty",
                                "Gagal! Rak {$rack->location_code} penuh. Kapasitas sisa: {$availableSpace}, Anda mencoba memasukkan total: {$totalIncomingQty}."
                            );
                        }
                    }
                }
            }
        });
    }
}
