<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\Admin\ItemController;
use App\Http\Controllers\Api\Admin\ItemStockController;
use App\Http\Controllers\Api\Admin\LoanController;
use App\Http\Controllers\Api\Admin\RepairController;
use App\Http\Controllers\Api\Admin\ReturnClaimController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\SummaryController;
use App\Http\Controllers\Api\Admin\UserController;
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

Route::get('loans', [LoanController::class, 'index']);
Route::get('loans/{loan}', [LoanController::class, 'tampil']);
Route::patch('loans/{loan}', [LoanController::class, 'koreksi']);
Route::post('loans/{loan}/extensions', [LoanController::class, 'perpanjang']);
Route::post('loans/{loan}/cancel', [LoanController::class, 'batal']);

Route::get('return-claims', [ReturnClaimController::class, 'index']);
Route::get('return-claims/{klaim}', [ReturnClaimController::class, 'tampil']);
Route::post('return-claims/{klaim}/verify', [ReturnClaimController::class, 'verifikasi']);

Route::get('summary', [SummaryController::class, 'index']);

Route::get('settings', [SettingController::class, 'index']);
Route::put('settings', [SettingController::class, 'perbarui']);

Route::get('audit-logs', [AuditLogController::class, 'index']);

Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'simpan']);
Route::patch('users/{user}', [UserController::class, 'ubah']);
