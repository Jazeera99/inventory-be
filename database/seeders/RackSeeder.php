<?php

namespace Database\Seeders;

use App\Models\Rack;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Rack::create([
            'location_code' => 'LD-01',
            'rack_name' => 'Loading Dock',
            'column_number' => 0,
            'level_number' => 0,
            'is_active' => true,
            'is_maintenance' => false,
        ]);

        Rack::factory()->count(3)->create();

        Rack::factory()->maintenance()->create([
            'location_code' => 'A2-1',
            'rack_name' => 'Rak A',
            'column_number' => 2,
            'level_number' => 1,
        ]);
    }
}
