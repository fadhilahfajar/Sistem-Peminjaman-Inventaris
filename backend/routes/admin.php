<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DivisionController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CategoryController::class, 'index']);
Route::post('categories', [CategoryController::class, 'simpan']);
Route::patch('categories/{kategori}', [CategoryController::class, 'ubah']);

Route::get('divisions', [DivisionController::class, 'index']);
Route::post('divisions', [DivisionController::class, 'simpan']);
Route::patch('divisions/{divisi}', [DivisionController::class, 'ubah']);
