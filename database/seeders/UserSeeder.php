<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $Role = Role::where('role_name', 'Superadmin')->first();

        User::updateOrCreate([
            'username' => 'admin',
            'password' => bcrypt('admin123'),
            'full_name' => 'Administrator Utama',
            'role_id' => $Role->id,
            'is_active' => true,
        ]);
    }
}
