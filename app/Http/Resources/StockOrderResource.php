<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $parentTransactions = $this->parent?->stockTransactions ?? collect();
        $parentTrxItems = $parentTransactions->pluck('items')->flatten();
        $canViewPrice = $request->user()->can('Lihat Harga') || $request->user()->hasRole('Superadmin');

        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'type' => $this->type,
            'status' => $this->status,
            'order_date' => $this->order_date ? Carbon::parse($this->order_date)->format('Y-m-d') : null,
            'expected_date' => $this->expected_date ? Carbon::parse($this->expected_date)->format('Y-m-d') : null,
            'parent_id' => $this->parent_id,
            'parent_order_no' => $this->parent?->order_no ?? null,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier ?? null,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ?? null,
            //'items' => StockOrderItemResource::collection($this->whenLoaded('items')),
            'items' => $this->items->map(function ($item) use ($parentTrxItems, $canViewPrice) {
                $originalExp = null;
                if ($this->type === 'RETURN_IN') {
                    $matchedTrxItem = $parentTrxItems->firstWhere('product_sku', $item->product_sku);
                    $originalExp = $matchedTrxItem?->expired_at;
                }

                return [
                    'id' => $item->id,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product->product_name ?? null,
                    'qty_ordered' => $item->qty_ordered,
                    'qty_fulfilled' => $item->qty_fulfilled,
                    'qty_remaining' => max(0, $item->qty_ordered - $item->qty_fulfilled),
                    'unit_price' => $canViewPrice ? (float) $item->unit_price : null,
                    'subtotal' => $canViewPrice ? (float) ($item->qty_ordered * $item->unit_price) : null,
                    'suggested_expired_at' => $originalExp ? Carbon::parse($originalExp)->format('Y-m-d') : null,
                ];
            }),
            'returns' => $this->whenLoaded('returnOrders', fn () => $this->returnOrders
                ->filter(fn ($return) => in_array($return->type, ['RETURN_IN', 'RETURN_OUT']))
                ->map(fn ($return) => [
                    'id' => $return->id,
                    'order_no' => $return->order_no,
                    'type' => $return->type,
                    'status' => $return->status,
                    'items' => $return->items->map(fn ($item) => [
                        'product_sku' => $item->product_sku,
                        'qty_ordered' => $item->qty_ordered,
                    ]),
                ])->values()),
            'transactions' => StockTransactionResource::collection($this->whenLoaded('stockTransactions')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
