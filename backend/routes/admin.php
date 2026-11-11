<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\Admin\ItemController;
use App\Http\Controllers\Api\Admin\ItemStockController;
use App\Http\Controllers\Api\Admin\RepairController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CategoryController::class, 'index']);
Route::post('categories', [CategoryController::class, 'simpan']);
Route::patch('categories/{kategori}', [CategoryController::class, 'ubah']);

Route::get('divisions', [DivisionController::class, 'index']);
Route::post('divisions', [DivisionController::class, 'simpan']);
Route::patch('divisions/{divisi}', [DivisionController::class, 'ubah']);

Route::get('items', [ItemController::class, 'index']);
Route::post('items', [ItemController::class, 'simpan']);
Route::get('items/{item}', [ItemController::class, 'tampil']);
Route::put('items/{item}', [ItemController::class, 'ubah']);
Route::delete('items/{item}', [ItemController::class, 'hapus']);

Route::post('items/{item}/stock-adjustments', [ItemStockController::class, 'penyesuaian']);
Route::post('items/{item}/mark-damaged', [ItemStockController::class, 'tandaiRusak']);
Route::post('items/{item}/repair-complete', [ItemStockController::class, 'selesaiPerbaikan']);
Route::post('items/{item}/write-off', [ItemStockController::class, 'afkir']);

Route::get('repairs', [RepairController::class, 'index']);
