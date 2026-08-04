<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $margin = $this->selling_price - $this->purchase_price;
        $marginPercentage = $this->purchase_price > 0 ? round(($margin / $this->purchase_price) * 100, 2) : 0;

        return [
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'brand' => $this->brand,
            'type' => $this->type,
            'packaging' => $this->packaging,
            'size' => $this->size,
            'pricing' => [
                'purchase_price' => (float) $this->purchase_price,
                'selling_price' => (float) $this->selling_price,
                'margin_amount' => (float) $margin,
                'margin_percentage' => $marginPercentage . '%',
                'holding_cost_per_day' => (float) $this->holding_cost_per_day,
            ],
            'unit' => $this->unit,

            'min_stock' => $this->min_stock,
            'stock' => $this->stock,
            'exp_warning_days' => $this->exp_warning_days,
            'is_active' => (bool) $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
