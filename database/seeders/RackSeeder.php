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
        Rack::factory()->loadingDock()->create();

        $rackLetters = ['A', 'B'];
        $maxColumns = 10;
        $maxLevels = 5;

        foreach ($rackLetters as $letter) {
            for ($col = 1; $col <= $maxColumns; $col++) {
                for ($lvl = 1; $lvl <= $maxLevels; $lvl++) {
                    // Contoh pengkondisian khusus: misal baris terakhir Rak A diset maintenance
                    $isMaintenance = ($letter === 'A' && $col === 10 && $lvl === 5);

                    Rack::create([
                        'location_code' => "{$letter}{$col}-{$lvl}",
                        'rack_name' => "Rak {$letter}",
                        'column_number' => $col,
                        'level_number' => $lvl,
                        'capacity' => 15,
                        'is_active' => ! $isMaintenance,
                        'is_maintenance' => $isMaintenance,
                    ]);
                }
            }
        }

        // Rack::create([
        //     'rack_name' => 'Loading Dock',
        //     'column_number' => 0,
        //     'level_number' => 0,
        //     'capacity' => 0,
        //     'is_active' => true,
        //     'is_maintenance' => false,
        // ]);

        // Rack::factory()->count(10)->create();

        // Rack::factory()->maintenance()->create([
        //     'rack_name' => 'Rak A',
        //     'capacity' => 15,
        // ]);
    }
}
