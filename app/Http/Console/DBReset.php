<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DummySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

class DBReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset {--D|dummy} {--stock=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop database, rerun migration, seeds data.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Telescope::stopRecording();

        $resetTime = $this->getElapsedTime(function (): void {
            // $this->createDatabaseIfNotExists();

            $this->migrateFresh();

            DB::transaction(function (): void {
                $this->seedRequired();

                if ($this->option('dummy')) {
                    $this->seedDummy();
                }
            });

            if ($this->option('stock')) {
                $this->line('Update all stock to: '.$this->option('stock'));
                DB::table('product_variant_stocks')->update(['stock' => $this->option('stock')]);
            }
        });

        $this->line("<info>Total time:</info> ({$resetTime}ms)");

        Telescope::startRecording();
    }

    /**
     * Create database if not exists.
     */
    // private function createDatabaseIfNotExists(): void
    // {
    //     $username = config('database.connections.mysql.username');
    //     $password = config('database.connections.mysql.password');
    //     $dbName = config('database.connections.mysql.database');
    //     $this->line("<comment>Create database if not exists:</comment> {$dbName}");

    //     try {
    //         // Test database connection
    //         DB::connection()->getPdo();
    //         $this->line('<info>Database OK!</info>');
    //     } catch (\Exception $e) {
    //         $result = false;
    //         $processTime = $this->getElapsedTime(function () use ($username, $password, $dbName, &$result) {
    //             $host = config('database.connections.mysql.host');
    //             $pdo = new \PDO("mysql:host=$host", $username, $password);
    //             $result = $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbName}");
    //         });
    //         if ($result == true) {
    //             $this->line("<info>Database created:</info>  ({$processTime}ms)");
    //         }
    //     }
    //     $this->line('');
    // }

    /**
     * Call migrate fresh and output elapsed time.
     */
    private function migrateFresh(): void
    {
        $this->line('<comment>Running migrate fresh</comment>');
        $migrationTime = $this->getElapsedTime(function (): void {
            Artisan::call('migrate:fresh');
        });
        $this->line("<info>Database migrated:</info> {$migrationTime}ms");
        $this->line('');
    }

    /**
     * Call db seed required data seeder.
     */
    private function seedRequired(): void
    {
        $this->line('<info>Seeding Required Data</info>');
        $seedRequiredTime = $this->getElapsedTime(function (): void {
            $this->callSeeders(DatabaseSeeder::$seeder);
        });
        $this->line("<info>Required data seeded:</info> {$seedRequiredTime}ms");
        $this->line('');
    }

    /**
     * Call db seed dummy data seeder.
     */
    private function seedDummy(): void
    {
        $this->line('<info>Seeding Dummy Data</info>');
        $seedDummyTime = $this->getElapsedTime(function (): void {
            $this->callSeeders(DummySeeder::$seeder);
        });
        $this->line("<info>Dummy data seeded:</info> {$seedDummyTime}ms");
        $this->line('');
    }

    /**
     * Run DB seed command and output progress.
     *
     * @param  array<int,string>  $seeders
     */
    private function callSeeders(array $seeders): void
    {
        $countSeeders = count($seeders);

        foreach ($seeders as $key => $value) {
            $padCount = strlen((string) $countSeeders);
            $runTime = $this->getElapsedTime(function () use ($value, $countSeeders, $key, $padCount): void {
                $index = str_pad(($key + 1).'', $padCount, '0', STR_PAD_LEFT);
                $this->line("($index/$countSeeders) <comment>Seeding:</comment> $value");
                Artisan::call('db:seed', ['--class' => $value]);
            });
            $spaces = str_pad('', $padCount * 2 + 3, ' ', STR_PAD_LEFT);
            $this->line("$spaces <info>Seeded:</info>  {$runTime}ms");
        }
    }

    /**
     * Calculate time needed to execute the given callback.
     */
    private function getElapsedTime(callable $callback): string
    {
        $resetStart = microtime(true);

        $callback();

        return number_format((microtime(true) - $resetStart) * 1000, 2);
    }
}
