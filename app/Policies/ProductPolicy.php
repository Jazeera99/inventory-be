<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Ganti 'superadmin' sesuai dengan logic pengecekan role di aplikasimu
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null; // Lanjutkan ke pengecekan method spesifik jika bukan superadmin
    }

    /**
     * Helper untuk cek role yang diizinkan.
     * Superadmin (Role 1) & Warehouse (Role 2)
     */
    private function isAdminOrWarehouse(User $user): bool
    {
        return in_array($user->role_id, [1, 2]);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }

    public function view(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }

    public function update(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }

    public function delete(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }

    public function toggleActive(User $user): bool
    {
        return $this->isAdminOrWarehouse($user);
    }
}
