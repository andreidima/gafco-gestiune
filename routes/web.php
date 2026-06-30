<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierReceptionController;
use App\Http\Controllers\TrackedAssetController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('landing');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('locations', LocationController::class)->only(['index', 'store']);
    Route::resource('catalog-items', CatalogItemController::class)->only(['index', 'store']);
    Route::resource('tracked-assets', TrackedAssetController::class)->only(['index', 'store', 'show']);
    Route::resource('transfers', TransferController::class)->only(['index', 'store', 'update']);
    Route::resource('driver-requests', DriverRequestController::class)->only(['index', 'store', 'update']);
    Route::resource('supplier-receptions', SupplierReceptionController::class)->only(['index', 'store']);
    Route::get('qr-scan', [QrScanController::class, 'index'])->name('qr-scan.index');
    Route::post('qr-scan', [QrScanController::class, 'lookup'])->name('qr-scan.lookup');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::resource('users', UserController::class)->only(['index', 'store'])->middleware('role:admin|super-admin');
});
