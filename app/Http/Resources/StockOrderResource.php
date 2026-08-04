<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'type' => $this->type,
            'status' => $this->status,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_date' => $this->expected_date?->format('Y-m-d'),
            'parent_id' => $this->parent_id,
            'parent_order_no' => $this->parent?->order_no ?? null,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier ?? null,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ?? null,
            //'items' => StockOrderItemResource::collection($this->whenLoaded('items')),
            'items' => $this->items->map(fn($item) => [
                'id' => $item->id,
                'product_sku' => $item->product_sku,
                'product_name' => $item->product->product_name ?? null,
                'qty_ordered' => $item->qty_ordered,
                'qty_fulfilled' => $item->qty_fulfilled,
                'qty_remaining' => max(0, $item->qty_ordered - $item->qty_fulfilled),
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) ($item->qty_ordered * $item->unit_price),
            ]),
            'transactions' => StockTransactionResource::collection($this->whenLoaded('stockTransactions')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
