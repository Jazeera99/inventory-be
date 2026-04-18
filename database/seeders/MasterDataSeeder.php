<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Category::create(['name' => 'Sembako']);
        \App\Models\Category::create(['name' => 'Minuman']);
        \App\Models\Category::create(['name' => 'Kebutuhan Rumah']);

        \App\Models\Rack::create(['rack_code' => 'A-01', 'warehouse_name' => 'Gudang Utama']);
        \App\Models\Rack::create(['rack_code' => 'B-02', 'warehouse_name' => 'Gudang Utama']);

        \App\Models\Role::create(['role_name' => 'Admin', 'permissions' => '{"all":true}']);
    }
}
