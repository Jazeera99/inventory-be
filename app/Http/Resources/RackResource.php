<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RackResource extends JsonResource
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
            'location_code' => $this->location_code,
            'rack_name' => $this->rack_name,
            'column_number' => $this->column_number,
            'level_number' => $this->level_number,
            'is_active' => (bool) $this->is_active,
            'is_maintenance' => (bool) $this->is_maintenance,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
