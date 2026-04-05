<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\AdminUserController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function () {

    // User Management
    Route::prefix('users')->controller(AdminUserController::class)->group(function (): void {
        Route::get('', 'index')->name('users.index');
        Route::post('', 'store')->name('users.store');
        Route::get('{user}', 'show')->name('users.show');
        Route::put('{user}', 'update')->name('users.update');
        Route::patch('{user}/toggle-status', 'toggleStatus')->name('users.toggle-status');
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
