<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\GeneralAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [GeneralAuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    // User Management
    Route::prefix('users')->controller(AdminUserController::class)->group(function (): void {
        Route::get('', 'index')->name('user.index');
        Route::post('', 'store')->name('user.store');
        Route::get('{user}', 'show')->name('user.show');
        Route::put('{user}', 'update')->name('user.update');
        Route::patch('{user}/toggle-status', 'toggleStatus')->name('user.toggle-status');
    });

    // Product Management
    Route::prefix('products')->controller(ProductController::class)->group(function (): void {
        Route::get('', 'index')->name('products.index');
        Route::post('', 'store')->name('products.store');
    });
});

// Route public atau user biasa
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
