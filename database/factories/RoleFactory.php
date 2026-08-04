<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_name' => $this->faker->unique()->jobTitle(),
            'permissions' => $this->faker->randomElements([
                'Manajemen Rak',
                'Daftar Produk',
                'Transaksi',
                'Laporan Stok',
                'Manajemen User',
            ], 3),
        ];
    }
}
