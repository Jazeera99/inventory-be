<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RouteAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Akses ditolak.');
        }

        $permissions = $user->role->permissions ?? [];

        if (in_array('*', $permissions)) {
            return $next($request);
        }

        $adminPermissions = [
            'Manajemen Rak',
            'Lihat Rak',
            'Daftar Kategori',
            'Lihat Kategori',
            'Daftar Produk',
            'Lihat Produk',
            'Daftar Supplier',
            'Daftar Customer',
            'Transaksi',
            'Laporan Stok',
            'Manajemen User',
            'Hak Akses',
        ];

        // Cek apakah user punya salah satu dari daftar di atas
        if (count(array_intersect($adminPermissions, $permissions)) > 0) {
            return $next($request);
        }

        abort(403, 'Akses ditolak.');
    }
}
