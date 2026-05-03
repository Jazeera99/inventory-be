<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummySeeder extends Seeder
{
    /**
     * List of seeders for dummy data, used in app/Console/Commands/DBReset.php.
     */
    public static $seeder = [
        RoleSeeder::class,
        UserSeeder::class,

    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(self::$seeder);
    }
}
