<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Utils\Permission\Role as RoleEnum;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::updateOrCreate(
            ['id' => RoleEnum::SUPERADMIN->value],
            [
                'role_name' => 'Superadmin',
                'permissions' => ['*'],
            ]
        );

        Role::updateOrCreate(
            ['id' => RoleEnum::WAREHOUSE_MANAGER->value],
            [
                'role_name' => 'Warehouse Manager',
                'permissions' => ['Manajemen Rak', 'Laporan Stok'],
            ]
        );

        Role::updateOrCreate(
            ['id' => RoleEnum::STAFF_GUDANG->value],
            [
                'role_name' => 'Staff Gudang',
                'permissions' => ['Daftar Produk', 'Produk Masuk'],
            ]
        );
    }
}
