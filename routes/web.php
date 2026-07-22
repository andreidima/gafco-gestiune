<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ConsumptionReportController;
use App\Http\Controllers\CustodyTransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\FieldModeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SupplierReceptionController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskWhatsAppController;
use App\Http\Controllers\TrackedAssetController;
use App\Http\Controllers\TransferApprovalController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
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

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('locations', LocationController::class)->only(['create', 'store', 'edit', 'update'])
        ->middleware('role:super-admin|admin|dispecer');
    Route::resource('locations', LocationController::class)->only(['index'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('catalog-items', CatalogItemController::class)->only(['create', 'store', 'edit', 'update'])
        ->middleware('role:super-admin|admin|dispecer|gestionar-baza');
    Route::resource('catalog-items', CatalogItemController::class)->only(['index'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('tracked-assets', TrackedAssetController::class)->only(['create', 'store', 'edit', 'update'])
        ->middleware('role:super-admin|admin|dispecer');
    Route::resource('tracked-assets', TrackedAssetController::class)->only(['index'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('tracked-assets', TrackedAssetController::class)->only(['show'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|sofer|muncitor|contabil');
    Route::resource('transfers', TransferController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    Route::post('transfers/{transfer}/archive', [TransferController::class, 'archive'])->name('transfers.archive');
    Route::put('transfer-approvals/{approval}', [TransferApprovalController::class, 'update'])->name('transfer-approvals.update');
    Route::resource('driver-requests', DriverRequestController::class)->only(['index']);
    Route::get('tasks/dispatch', [TaskController::class, 'dispatch'])->name('tasks.dispatch');
    Route::resource('tasks', TaskController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('tasks/{task}/transition', [TaskController::class, 'transition'])->name('tasks.transition');
    Route::post('tasks/{task}/assignments', [TaskAssignmentController::class, 'store'])->name('tasks.assignments.store');
    Route::post('task-assignments/{assignment}/respond', [TaskAssignmentController::class, 'respond'])->name('task-assignments.respond');
    Route::post('task-assignments/{assignment}/estimate', [TaskAssignmentController::class, 'estimate'])->name('task-assignments.estimate');
    Route::post('task-assignments/{assignment}/request-reassignment', [TaskAssignmentController::class, 'requestReassignment'])->name('task-assignments.request-reassignment');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::get('tasks/{task}/whatsapp', TaskWhatsAppController::class)->name('tasks.whatsapp');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::resource('supplier-receptions', SupplierReceptionController::class)->only(['index'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|contabil');
    Route::resource('supplier-receptions', SupplierReceptionController::class)->only(['create', 'store'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('consumption-reports', ConsumptionReportController::class)->only(['index'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|contabil');
    Route::resource('consumption-reports', ConsumptionReportController::class)->only(['create', 'store'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('custody-transfers', CustodyTransferController::class)->only(['store', 'update']);
    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('field/driver', [FieldModeController::class, 'driver'])->name('field.driver');
    Route::get('field/site-manager', [FieldModeController::class, 'siteManager'])->name('field.site-manager');
    Route::get('field/worker', [FieldModeController::class, 'worker'])->name('field.worker')
        ->middleware('role:super-admin|admin|dispecer|muncitor');
    Route::get('qr-scan', [QrScanController::class, 'index'])->name('qr-scan.index')
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|sofer|muncitor');
    Route::post('qr-scan', [QrScanController::class, 'lookup'])->name('qr-scan.lookup')
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|sofer|muncitor');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|contabil');

    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware('role:admin|super-admin');
});
