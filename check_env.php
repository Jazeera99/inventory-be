<?php
require __DIR__ . '/vendor/autoload.php';
putenv('APP_ENV=local');
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
var_dump(getenv('DB_CONNECTION'));
var_dump(env('DB_CONNECTION'));
if (function_exists('config')) {
    var_dump(config('database.default'));
} else {
    var_dump('no config');
}
