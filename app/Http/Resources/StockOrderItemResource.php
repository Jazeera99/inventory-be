<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOrderItemResource extends JsonResource
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
            'stock_order_id' => $this->stock_order_id,
            'product_sku' => $this->product_sku,
            'product_name' => $this->product->product_name ?? null,
            'qty_ordered' => $this->qty_ordered,
            'qty_fulfilled' => $this->qty_fulfilled,
            'qty_remaining' => max(0, $this->qty_ordered - $this->qty_fulfilled),
        ];
    }
}
