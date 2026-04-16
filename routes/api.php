<?php

use Illuminate\Support\Facades\Route;

require base_path('routes/modules/general.php');

// 2. Group yang butuh login (Sanctum)
Route::middleware(['auth:sanctum'])->group(function (): void {
    // Khusus ADMIN (Hanya Superadmin/Admin yang bisa lewat)
    Route::middleware(['route.admin'])->group(function (): void {
        require base_path('routes/modules/admin.php');
    });

    // Khusus GUDANG
    Route::middleware(['route.warehouse'])->group(function (): void {
        require base_path('routes/modules/warehouse.php');
    });
});

// require base_path('routes/modules/general.php');

// // Route yang butuh Login & Role Admin
// Route::middleware(['auth:sanctum', 'route.admin'])->group(function () {
//     require base_path('routes/modules/admin.php');
// });

// // Route khusus orang Gudang
// Route::middleware(['auth:sanctum', 'route.warehouse'])->group(function () {
//     require base_path('routes/modules/warehouse.php');
// });
