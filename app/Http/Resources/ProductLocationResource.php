<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductLocationResource extends JsonResource
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
            'product_sku' => $this->product_sku,
            'product_name' => $this->product->product_name ?? null,
            'rack_id' => $this->rack_id,
            'rack_name' => $this->rack->rack_name ?? null,
            'qty' => $this->qty,
            'batch_code' => $this->batch_code,
            'expired_at' => $this->expired_at,
        ];
    }
}
