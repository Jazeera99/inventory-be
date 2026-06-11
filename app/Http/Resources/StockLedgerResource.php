<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $netChange = $this->qty_after - $this->qty_before;
        // $absoluteQty = abs($netChange);

        $masuk = 0;
        $keluar = 0;

        if ($this->type === 'IN') {
            $masuk = abs($this->qty);
        } elseif ($this->type === 'OUT') {
            $keluar = abs($this->qty);
        } elseif ($this->type === 'ADJUSTMENT') {
            // Cek dulu apakah menggunakan kolom qty langsung
            if ($this->qty > 0) {
                $masuk = $this->qty;
            } elseif ($this->qty < 0) {
                $keluar = abs($this->qty);
            } else {
                // Jika kolom qty bernilai 0, fallback ke selisih qty_after & qty_before
                $netChange = $this->balance_after - $this->balance_before;
                if ($netChange > 0) {
                    $masuk = abs($netChange);
                } elseif ($netChange < 0) {
                    $keluar = abs($netChange);
                }
            }
        } elseif ($this->type === 'MOVE') {
            // Untuk MOVE (Perpindahan Rak)
            // Nilai masuk/keluar hanya diisi jika user sedang memfilter berdasarkan expired_at tertentu

            if ($this->qty > 0) {
                $masuk = $this->qty;
            } elseif ($this->qty < 0) {
                $keluar = abs($this->qty);
            }
        }

        return [
            // 'waktu' => Carbon::parse($this->waktu)->format('d/m, H.i'),
            // 'no_trans' => $this->no_trans,
            // 'jenis' => $this->jenis,
            // 'lokasi' => $this->lokasi,
            // 'masuk' => in_array($this->jenis, ['IN', 'ADJUSTMENT']) ? $this->qty : '-',
            // 'keluar' => $this->jenis === 'OUT' ? $this->qty : '-',
            // 'saldo' => $this->saldo ?? 0,
            // 'keterangan' => $this->keterangan,
            // 'user' => $this->user,

            'date' => $this->created_at->format('d/m H:i'), // Waktu
            'transaction_no' => $this->transaction_no,      // No Transaksi
            'type' => $this->type,                          // Jenis (IN/OUT/ADJUSTMENT)
            'lokasi' => $this->rack ? $this->rack->location_code : '-', // Lokasi $item->rack ? $item->rack->location_code : '-',
            'expired_at' => $this->expired_at,              // Expired
            // Hitung kolom masuk/keluar secara otomatis
            'masuk' => (int) $masuk,
            'keluar' => (int) $keluar,
            'saldo' => (int) $this->balance_after,           // Saldo Akhir
            'user' => $this->user ? $this->user->full_name : 'System',
            'keterangan' => $this->note,
        ];
    }
}
