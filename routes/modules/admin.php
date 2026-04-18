<?php

use App\Http\Controllers\AdminInitController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminUserController;
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
