<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentApiController;
use App\Http\Controllers\ShipmentsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorAnalyticsController;
use App\Services\ShipmentSyncService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pengiriman', [ShipmentsController::class, 'index'])->name('shipments');
    Route::get('/analytics/vendors', [VendorAnalyticsController::class, 'index'])->name('analytics.vendors');
    Route::post('/pengiriman/export', [ShipmentsController::class, 'export'])->name('shipments.export');
    Route::get('/api/shipments/staging/{stagging}', [ShipmentApiController::class, 'staging'])->name('api.staging');
    Route::get('/api/shipments/bottleneck', [ShipmentApiController::class, 'bottleneck'])->name('api.bottleneck');
    Route::get('/api/shipments/detail', [ShipmentApiController::class, 'detail'])->name('api.shipment-detail');
    Route::post('/sync', function () {
        try {
            $count = app(ShipmentSyncService::class)->sync();

            return redirect()
                ->back()
                ->with('status', "Data pengiriman berhasil di-sync ({$count} baris).");
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->withErrors(['sync' => 'Sync gagal: '.$e->getMessage()]);
        }
    })->name('sync')->middleware('admin');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('admin')->resource('users', UserController::class);
});

require __DIR__.'/auth.php';
