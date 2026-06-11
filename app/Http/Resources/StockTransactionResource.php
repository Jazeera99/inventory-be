<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_no' => $this->transaction_no,
            'type' => $this->type,
            'jenis' => $this->items->first()?->qty >= 0 ? 'MASUK' : 'KELUAR',
            'total_qty' => abs($this->items->sum('qty')),
            'user_name' => $this->user ? $this->user->full_name : 'System',
            'is_cancelled' => $this->trashed(), // Bernilai true jika sudah di-soft delete
            'deleted_at' => $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null,
            'deleted_by_name' => $this->deletedByUser ? $this->deletedByUser->full_name : null,
            'items' => $this->items->map(fn ($item) => [
                'product_sku' => $item->product_sku,
                'product_name' => $item->product->product_name ?? null,
                'qty' => $this->type === 'OUT' ? -abs($item->qty) : $item->qty,

                // --- IN / OUT ---
                'rack_id' => $item->rack_id,
                'rack_name' => $item->rack ? $item->rack->location_code : '-',

                // --- MOVE ---
                'target_rack_id' => $item->target_rack_id,
                'target_rack_name' => $item->targetRack ? $item->targetRack->rack_name : '-',

                // --- ADJUSTMENT ---
                'qty_before' => $item->qty_before,
                'qty_after' => $item->qty_after,

                'expired_at' => $item->expired_at,
                'notes' => $item->notes,
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
