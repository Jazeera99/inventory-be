<?php

namespace Database\Factories;

use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    protected $model = Rack::class;

    // private static $column = 1;

    // private static $level = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $rackLetter = 'A';
        // $locationCode = "{$rackLetter}".self::$column.'-'.self::$level;

        return [
            'location_code' => 'A1-1',
            'rack_name' => 'Rak A',
            'column_number' => 1,
            'level_number' => 1,
            'capacity' => 15,
            'is_active' => true,
            'is_maintenance' => false,
        ];

        // Logika sederhana: naikkan tingkat, jika sudah tingkat 3, pindah kolom
        // if (self::$level < 3) {
        //     self::$level++;
        // } else {
        //     self::$level = 1;
        //     self::$column++;
        // }

        // return $data;
    }

    public function loadingDock(): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_name' => 'Loading Dock',
            'location_code' => 'LD-01',
            'column_number' => 0,
            'level_number' => 0,
            'capacity' => 0,
            'is_active' => true,
            'is_maintenance' => false,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'is_maintenance' => true,
        ]);
    }
}
