<?php

use App\Http\Controllers\AdminInitController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLocationController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\StockLedgerController;
use App\Http\Controllers\StockOrderController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\SupplierController;
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
    Route::post('recommendations', 'recommendations')->name('admin.rack.recommendations');
    Route::put('{rack}', 'update')->name('admin.rack.update');
    Route::post('generate', 'generate')->name('admin.rack.generate');
    Route::patch('{rack}/toggle-maintenance', 'toggleMaintenance')->name('admin.rack.toggle-maintenance');
    Route::get('{rack}/evacuation-contents', 'getContentsForEvacuation')->name('admin.rack.evacuation-contents');
});

Route::prefix('admin/categories')->controller(CategoryController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.category.index');
    Route::post('', 'store')->name('admin.category.store');
    Route::get('{category}', 'show')->name('admin.category.show');
    Route::put('{category}', 'update')->name('admin.category.update');
    Route::patch('{category}/toggle-active', 'toggleActive')->name('admin.category.toggle-active');
});

Route::prefix('admin/suppliers')->controller(SupplierController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.supplier.index');
    Route::post('', 'store')->name('admin.supplier.store');
    Route::get('{supplier}', 'show')->name('admin.supplier.show');
    Route::put('{supplier}', 'update')->name('admin.supplier.update');
    Route::patch('{supplier}/toggle-active', 'toggleActive')->name('admin.supplier.toggle-active');
});

Route::prefix('admin/customers')->controller(CustomerController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.customer.index');
    Route::post('', 'store')->name('admin.customer.store');
    Route::get('{customer}', 'show')->name('admin.customer.show');
    Route::put('{customer}', 'update')->name('admin.customer.update');
    Route::patch('{customer}/toggle-active', 'toggleActive')->name('admin.customer.toggle-active');
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
    Route::get('', 'index')->name('admin.product.index');
    Route::post('', 'store')->name('admin.product.store');
    Route::put('{product:sku}', 'update')->name('admin.product.update');
    Route::delete('{product:sku}', 'destroy')->name('admin.product.destroy');
    Route::patch('{product:sku}/toggle-active', 'toggleActive')->name('admin.product.toggle-active');
});

Route::prefix('admin/product-locations')->controller(ProductLocationController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.product-location.index');
    Route::post('', 'store')->name('admin.product-location.store');
    Route::get('{product_location}', 'show')->name('admin.product-location.show');
});

Route::prefix('admin/stock-orders')->controller(StockOrderController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.stock-order.index');
    Route::post('', 'store')->name('admin.stock-order.store');
    Route::get('{stock_order}', 'show')->name('admin.stock-order.show');
    Route::put('{stock_order}', 'update')->name('admin.stock-order.update');
    Route::post('{id}/cancel', 'cancel')->name('admin.stock-order.cancel');
});

// Rute untuk Stock Transactions (Riwayat Masuk/Keluar)
Route::prefix('admin/stock-transactions')->controller(StockTransactionController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.stock-transaction.index');
    Route::post('', 'store')->name('admin.stock-transaction.store');
    Route::get('{transaction_no}', 'show')->name('admin.stock-transaction.show');
    Route::delete('{id}', 'destroy')->name('admin.stock-transaction.destroy');
    Route::post('check-fefo', 'checkFefoBeforeOut')->name('admin.stock-transaction.check-fefo');
});

Route::prefix('admin/stock-ledger')->controller(StockLedgerController::class)->group(function (): void {
    Route::get('', 'index')->name('admin.stock-ledger.index');
    Route::get('expired-options', 'getExpiredOptions');
    Route::get('summary', 'summary')->name('admin.stock-ledger.summary');
});

Route::prefix('admin/dashboard')->controller(DashboardController::class)->group(function (): void {
    Route::get('summary', 'getDashboardSummary')->name('admin.dashboard.summary');
});
