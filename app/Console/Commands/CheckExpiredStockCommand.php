<?php

namespace App\Console\Commands;

use App\Models\ProductLocation;
use App\Models\StockOrder;
use App\Models\Rack;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('app:check-expired-stock-command')]
#[Description('Command description')]
class CheckExpiredStockCommand extends Command
{
    protected $signature = 'stock:check-expired';
    protected $description = 'Pemeriksaan stok mendekati kedaluwarsa (90, 60, <30 Hari) dan isolasi karantina';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $day60 = $today->copy()->addDays(60);
    $day90 = $today->copy()->addDays(90);

    $nearExpiredLocations = ProductLocation::query()
        ->whereBetween('expired_at', [$day60, $day90])
        ->where('qty', '>', 0)
        ->get();

    $this->info("Ditemukan " . $nearExpiredLocations->count() . " lokasi item mendekati expired (60-90 hari).");

    // 2. Critical Warning (30 - 60 Hari)
    $day30 = $today->copy()->addDays(30);

    $criticalLocations = ProductLocation::query()
        ->whereBetween('expired_at', [$day30, $day60])
        ->where('qty', '>', 0)
        ->get();

    // 3. Mandatory Return & Lockdown (< 30 Hari)
    $quarantineLocations = ProductLocation::query()
        ->where('expired_at', '<=', $day30)
        ->where('qty', '>', 0)
        ->get();

        if ($quarantineLocations->isNotEmpty()) {
            DB::transaction(function () use ($quarantineLocations) {
                // Cari / Buat Rak Karantina
                $quarantineRack = Rack::firstOrCreate(
                    ['location_code' => 'RAK-KARANTINA'],
                    ['rack_name' => 'Rak Karantina Expired', 'capacity' => 999999, 'is_active' => true]
                );

                foreach ($quarantineLocations as $loc) {
                    if ($loc->rack_id == $quarantineRack->id) continue;

                    // Buat Draft PO Return (Outbound Return Order)
                    $todayStr = Carbon::today()->format('Ymd');
                    $returnOrderNo = "RE-PO-{$todayStr}-" . sprintf('%03d', rand(1, 999));

                    $draftReturn = StockOrder::create([
                        'order_no' => $returnOrderNo,
                        'type' => 'OUTBOUND',
                        'status' => 'DRAFT',
                        'order_date' => Carbon::now()->format('Y-m-d'),
                        'notes' => "RETUR OTOMATIS: Barang mendekati kedaluwarsa (<30 Hari) dari Rak ID: {$loc->rack_id}",
                    ]);

                    $draftReturn->items()->create([
                        'product_sku' => $loc->product_sku,
                        'qty_ordered' => $loc->qty,
                        'qty_fulfilled' => 0,
                        'unit_price' => 0,
                    ]);

                    // Pindahkan fisik stok di database ke Rak Karantina
                    $loc->update(['rack_id' => $quarantineRack->id]);
                }
            });

            $this->info("Stok < 30 hari berhasil dikarantina dan Draft Dokumen Retur (RE-PO) berhasil diterbitkan.");
        }

        return Command::SUCCESS;
    }
}
