<?php

use App\Http\Controllers\Kiosk\KonfigurasiController;
use App\Http\Controllers\Kiosk\UjiIntegrasiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('config', [KonfigurasiController::class, 'tampil']);

    // SEMENTARA (1.19b). Hapus setelah A membuktikan cookie, CSRF, dan CORS.
    Route::post('uji-integrasi', [UjiIntegrasiController::class, 'kirim']);
});
