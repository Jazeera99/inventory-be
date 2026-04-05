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
        Gate::allowIf(function (User $user) use ($roles) {
            return in_array($user->role, array_map(fn ($role) => $role, $roles));
        });
    }
}
