<?php

use App\Http\Controllers\Api\ActivityLog\ActivityLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Document\DocumentController;
use App\Http\Controllers\Api\Letter\LetterController;
use App\Http\Controllers\Api\Mitra\MitraController;
use App\Http\Controllers\Api\RecycleBin\RecycleBinController;
use App\Http\Controllers\Api\Report\ReportController;
use App\Http\Controllers\Api\Setting\SettingController;
use App\Http\Controllers\Api\Storage\StorageController;
use App\Http\Controllers\Api\User\PermissionController;
use App\Http\Controllers\Api\User\RoleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Arsip Dokumen & Surat (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth Public
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected API Routes (Sanctum Auth)
    Route::middleware('auth:sanctum')->group(function () {

        // Auth Private
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Mitras
        Route::get('/mitras/{mitra}/documents', [MitraController::class, 'documents']);
        Route::apiResource('/mitras', MitraController::class);

        // Documents
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::apiResource('/documents', DocumentController::class);

        // Storage / R2 Presign
        Route::post('/storage/presign', [StorageController::class, 'presign']);
        Route::delete('/storage/{key}', [StorageController::class, 'destroy']);

        // Categories
        Route::apiResource('/categories', CategoryController::class)->except(['show']);

        // Letters
        Route::apiResource('/letters', LetterController::class);

        // Reports
        Route::get('/reports/documents', [ReportController::class, 'documents']);
        Route::get('/reports/mitras', [ReportController::class, 'mitras']);
        Route::get('/reports/storage', [ReportController::class, 'storage']);
        Route::post('/reports/export', [ReportController::class, 'export']);

        // Recycle Bin
        Route::get('/recycle-bin', [RecycleBinController::class, 'index']);
        Route::post('/recycle-bin/{id}/restore', [RecycleBinController::class, 'restore']);
        Route::delete('/recycle-bin/{id}', [RecycleBinController::class, 'destroy']);

        // Users, Roles & Permissions
        Route::apiResource('/users', UserController::class);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::get('/permissions', [PermissionController::class, 'index']);

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // System Settings
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);
    });
});
