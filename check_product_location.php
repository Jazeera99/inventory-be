<?php
require __DIR__ . '/vendor/autoload.php';
putenv('APP_ENV=local');
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\ProductLocation;
$rows = ProductLocation::where('product_sku', 'BER-PAN-SUP-KAR-10KG-405')->where('rack_id', 11)->get();
$out = [];
foreach ($rows as $r) {
    $out[] = ['id' => $r->id, 'sku' => $r->product_sku, 'rack' => $r->rack_id, 'expired_at' => $r->expired_at, 'qty' => $r->qty];
}
echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
