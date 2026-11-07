<?php

use App\Http\Controllers\Api\Kiosk\CategoryController;
use App\Http\Controllers\Api\Kiosk\DivisionController;
use App\Http\Controllers\Api\Kiosk\ItemController;
use App\Http\Controllers\Api\Kiosk\LoanController;
use App\Http\Controllers\Kiosk\KonfigurasiController;
use App\Http\Controllers\Kiosk\UjiIntegrasiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:kiosk-baca')->group(function () {
    Route::get('config', [KonfigurasiController::class, 'tampil']);
    Route::get('items', [ItemController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('divisions', [DivisionController::class, 'index']);
});

Route::middleware('throttle:kiosk-tulis')->group(function () {
    Route::post('loans', [LoanController::class, 'buat']);
});

// SEMENTARA (1.19b). Hapus setelah A membuktikan cookie, CSRF, dan CORS.
Route::middleware('throttle:60,1')->group(function () {
    Route::post('uji-integrasi', [UjiIntegrasiController::class, 'kirim']);
});
