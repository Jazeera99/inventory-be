<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
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
            'username' => $this->username,
            'full_name' => $this->full_name,
            'is_active' => (bool) $this->is_active,
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->role_name,
                'permissions' => $this->role->permissions,
            ],
        ];
    }
}
