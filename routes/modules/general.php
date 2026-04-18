<?php

use App\Http\Controllers\GeneralAuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [GeneralAuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [GeneralAuthController::class, 'logout']);
    Route::put('update-password', [GeneralAuthController::class, 'updatePassword']);
});
