<?php

use App\Http\Controllers\Auth\KataSandiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\AkunAktif;
use App\Http\Middleware\SesiIdle;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);

    Route::middleware(['auth:sanctum', AkunAktif::class, SesiIdle::class])->group(function () {
        Route::get('me', [LoginController::class, 'me']);
        Route::post('logout', [LoginController::class, 'logout']);
        Route::post('change-password', [KataSandiController::class, 'ubah']);
    });
});
