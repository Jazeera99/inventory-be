<?php

namespace App\Utils\Permission;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class Allow
{
    /**
     * Add roles allowed to access the resource.
     */
    public static function roles(Role ...$roles): void
    {
        $allowedValues = array_map(fn($r) => (string) $r->value, $roles);

        Gate::allowIf(function (User $user) use ($allowedValues) {
            return in_array((string) $user->role_id, $allowedValues);
        });
    }
}
