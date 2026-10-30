<?php

use App\Http\Controllers\Auth\KataSandiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Kiosk\KonfigurasiController;
use App\Http\Controllers\Kiosk\UjiIntegrasiController;
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

Route::prefix('kiosk')->middleware('throttle:60,1')->group(function () {
    Route::get('config', [KonfigurasiController::class, 'tampil']);

    // SEMENTARA (1.19b). Hapus setelah A membuktikan cookie, CSRF, dan CORS.
    Route::post('uji-integrasi', [UjiIntegrasiController::class, 'kirim']);
});
