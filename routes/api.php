<?php

use App\Http\Controllers\MaskController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

// 藥房
Route::get('/pharmacies/open', [PharmacyController::class, 'getOpenPharmacies']);
Route::get('/pharmacies/masks/count', [PharmacyController::class, 'getPharmaciesByMaskCount']);

// 口罩
Route::get('/search', [MaskController::class, 'search']);
Route::get('/pharmacies/{pharmacyId}/masks', [MaskController::class, 'getMasksByPharmacy']);
Route::get('/masks/summary', [MaskController::class, 'getMaskSalesSummary']);

// 用戶
Route::get('/top-members', [MemberController::class, 'topMembers']);

// 交易相關
Route::post('/purchase', [TransactionController::class, 'purchase']);
