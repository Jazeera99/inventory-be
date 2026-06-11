<?php

namespace App\Policies;

use App\Models\User;

class StockTransactionPolicy
{
    /**
     * All Role can view transactions history
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }

    /**
     * All Role (1, 2, and 3) can input incoming/outgoing stock
     */
    public function create(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }

    public function view(User $user): bool
    {
        return in_array($user->role_id, [1, 2, 3]);
    }
}
