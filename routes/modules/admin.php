<?php

use App\Http\Controllers\AdminInitController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLocationController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\StockLedgerController;
use App\Http\Controllers\StockTransactionController;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\Route;

Route::get('auth/init', AdminInitController::class)->name('auth.init');

Route::prefix('admin/roles')->controller(AdminRoleController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.role.index');
    Route::post('', 'store')->name('admin.role.store');
    Route::put('{role}', 'update')->name('admin.role.update');
    Route::delete('{role}', 'destroy')->name('admin.role.destroy');
});

Route::prefix('admin/users')->controller(AdminUserController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.user.index');
    Route::post('', 'store')->name('admin.user.store');
    Route::get('{user}', 'show')->name('admin.user.show');
    Route::put('{user}', 'update')->name('admin.user.update');
    Route::patch('{user}/toggle-status', 'toggleStatus')->name('admin.user.toggle-status');
});

Route::prefix('admin/racks')->controller(RackController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.rack.index');
    Route::post('', 'store')->name('admin.rack.store');
    Route::post('generate', 'generate')->name('admin.rack.generate');
    Route::patch('{rack}/toggle-maintenance', 'toggleMaintenance')->name('admin.rack.toggle-maintenance');
});

Route::prefix('admin/categories')->controller(CategoryController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.category.index');
    Route::post('', 'store')->name('admin.category.store');
    Route::get('{category}', 'show')->name('admin.category.show');
    Route::put('{category}', 'update')->name('admin.category.update');
    Route::patch('{category}/toggle-active', 'toggleActive')->name('admin.category.toggle-active');
});

// Route::prefix('admin/products')->controller(ProductController::class)->group(function (): void {
//     Route::get('', 'index')->name('admin.product.index');
//     Route::post('', 'store')->name('admin.product.store');
//     Route::get('{product:sku}', 'show')->name('admin.product.show');
//     Route::put('{product:sku}', 'update')->name('admin.product.update');
//     Route::patch('{product:sku}/toggle-active', 'toggleActive')->name('admin.product.toggle-active');
//     Route::delete('{product:sku}', 'destroy')->name('admin.product.destroy');
// });

Route::prefix('admin/products')->controller(ProductController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.product.index')->can('viewAny', Product::class);
    Route::post('', 'store')->name('admin.product.store')->can('create', Product::class);
    Route::put('{product:sku}', 'update')->name('admin.product.update')->can('update', 'product');
    Route::delete('{product:sku}', 'destroy')->name('admin.product.destroy')->can('delete', 'product');
    Route::patch('{product:sku}/toggle-active', 'toggleActive')->name('admin.product.toggle-active')->can('toggleActive', 'product');
});

Route::prefix('admin/product-locations')->controller(ProductLocationController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.product-location.index')->can('viewAny', ProductLocation::class);
    Route::post('', 'store')->name('admin.product-location.store')->can('create', ProductLocation::class);
    Route::get('{product_location}', 'show')->name('admin.product-location.show')->can('view', 'product_location');
});

// Rute untuk Stock Transactions (Riwayat Masuk/Keluar)
Route::prefix('admin/stock-transactions')->controller(StockTransactionController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.stock-transaction.index')->can('viewAny', StockTransaction::class);
    Route::post('', 'store')->name('admin.stock-transaction.store')->can('create', StockTransaction::class);
    // Rute show menggunakan transaction_no sebagai parameter agar bisa diklik
    Route::get('{transaction_no}', 'show')->name('admin.stock-transaction.show')->can('view', 'stockTransaction');
    Route::delete('{id}', 'destroy')->name('admin.stock-transaction.destroy');
});

Route::prefix('admin/stock-ledger')->controller(StockLedgerController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.stock-ledger.index');
    Route::get('expired-options', 'getExpiredOptions');
    Route::get('summary', 'summary')->name('admin.stock-ledger.summary');
});

Route::prefix('admin/dashboard')->controller(DashboardController::class)->group(function (): void {
    Route::get('summary', 'getDashboardSummary')->name('admin.dashboard.summary');
});
