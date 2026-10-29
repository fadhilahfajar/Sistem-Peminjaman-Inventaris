<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\AkunAktif;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);

    Route::middleware(['auth:sanctum', AkunAktif::class])->group(function () {
        Route::get('me', [LoginController::class, 'me']);
        Route::post('logout', [LoginController::class, 'logout']);
    });
});
