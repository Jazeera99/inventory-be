<?php

namespace App\Policies;

use App\Models\User;

class ProductLocationPolicy
{
    /**
     * All Role can edit
     */
    public function create(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }

    /**
     * All Role can look list and details
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }

    public function view(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }
}
