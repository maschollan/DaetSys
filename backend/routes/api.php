<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocsAuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.health');

// Autentikasi publik
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.login');

// Sesi halaman dokumentasi: cookie akses docs, bukan bearer token
Route::post('/docs/login', [DocsAuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.docs.login');
Route::post('/docs/logout', [DocsAuthController::class, 'logout'])
    ->name('api.docs.logout');

Route::middleware(['auth:sanctum', 'throttle:api', 'active.user'])->group(function (): void {
    // Sesi pengguna
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('api.profile.update');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('api.change-password');

    // Dashboard (4 sub-endpoint wajib)
    Route::prefix('dashboard')->group(function (): void {
        Route::get('/summary', [DashboardController::class, 'summary'])->name('api.dashboard.summary');
        Route::get('/assets-by-location', [DashboardController::class, 'assetsByLocation'])->name('api.dashboard.assets-by-location');
        Route::get('/assets-by-status', [DashboardController::class, 'assetsByStatus'])->name('api.dashboard.assets-by-status');
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities'])->name('api.dashboard.recent-activities');
        Route::get('/pending-transfers', [DashboardController::class, 'pendingTransfers'])->name('api.dashboard.pending-transfers');
    });

    // Aset
    Route::get('/assets/qr/{qr_token}', [AssetController::class, 'byQrToken'])->name('api.assets.qr');
    Route::get('/assets/{asset}/history', [AssetController::class, 'history'])->name('api.assets.history');
    Route::post('/assets', [AssetController::class, 'store'])
        ->middleware('idempotent')
        ->name('api.assets.store');
    Route::apiResource('assets', AssetController::class)->except(['store']);

    // Kategori aset
    Route::apiResource('asset-categories', AssetCategoryController::class);

    // Lokasi
    Route::apiResource('locations', LocationController::class);

    // Transfer
    Route::post('/transfers', [TransferController::class, 'store'])
        ->middleware('idempotent')
        ->name('api.transfers.store');
    Route::apiResource('transfers', TransferController::class)->only(['index', 'show']);
    Route::post('/transfers/{transfer}/approve', [TransferController::class, 'approve'])
        ->middleware(['throttle:api', 'idempotent'])
        ->name('api.transfers.approve');
    Route::post('/transfers/{transfer}/reject', [TransferController::class, 'reject'])
        ->middleware(['throttle:api', 'idempotent'])
        ->name('api.transfers.reject');
    Route::post('/transfers/{transfer}/handover', [TransferController::class, 'handover'])
        ->middleware(['throttle:api', 'idempotent'])
        ->name('api.transfers.handover');
    Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])
        ->middleware(['throttle:api', 'idempotent'])
        ->name('api.transfers.receive');
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])
        ->middleware(['throttle:api', 'idempotent'])
        ->name('api.transfers.cancel');

    // Audit trail
    Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show']);

    // Laporan
    Route::prefix('reports')->middleware('throttle:reports')->group(function (): void {
        Route::get('/assets', [ReportController::class, 'assets'])->name('api.reports.assets');
        Route::get('/transfers', [ReportController::class, 'transfers'])->name('api.reports.transfers');
        Route::get('/activity-logs', [ReportController::class, 'activityLogs'])->name('api.reports.activity-logs');
    });

    // Notifikasi
    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
    });

    // Manajemen pengguna
    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('api.users.reset-password');
});
