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
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Arsip Dokumen & Surat (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth & Storage Public
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

    // Storage Public Mock Upload (for local development & CORS upload testing)
    Route::options('/storage/upload-mock', [StorageController::class, 'uploadMock']);
    Route::put('/storage/upload-mock', [StorageController::class, 'uploadMock']);
    Route::post('/storage/upload-mock', [StorageController::class, 'uploadMock']);

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
        Route::get('/documents/pending-approvals', [DocumentController::class, 'pendingApprovals']);
        Route::get('/documents/bundle', [DocumentController::class, 'bundle']);
        Route::patch('/documents/{document}/approve', [DocumentController::class, 'approve']);
        Route::patch('/documents/{document}/reject', [DocumentController::class, 'reject']);
        Route::patch('/documents/{document}/review-kkp', [DocumentController::class, 'reviewKKP']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::apiResource('/documents', DocumentController::class);

        // Auditor Assignments
        Route::get('/auditor-assignments/my-clients', [\App\Http\Controllers\Api\Auditor\AuditorAssignmentController::class, 'myClients']);
        Route::apiResource('/auditor-assignments', \App\Http\Controllers\Api\Auditor\AuditorAssignmentController::class);

        // Storage / R2 Presign
        Route::post('/storage/presign', [StorageController::class, 'presign']);
        Route::delete('/storage/{key}', [StorageController::class, 'destroy']);

        // Categories
        Route::apiResource('/categories', CategoryController::class)->except(['show']);

        // Letters
        Route::get('/letters/generate-number', [LetterController::class, 'generateNumber']);
        Route::patch('/letters/{letter}/lock', [LetterController::class, 'lock']);
        Route::apiResource('/letters', LetterController::class);

        // Receipts / Kwitansi Transaksi
        Route::get('/receipts/stats', [\App\Http\Controllers\Api\Receipt\ReceiptController::class, 'stats']);
        Route::get('/receipts/generate-number', [\App\Http\Controllers\Api\Receipt\ReceiptController::class, 'generateNumber']);
        Route::get('/receipts/{receipt}/download', [\App\Http\Controllers\Api\Receipt\ReceiptController::class, 'download']);
        Route::apiResource('/receipts', \App\Http\Controllers\Api\Receipt\ReceiptController::class);

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
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::apiResource('/roles', RoleController::class);

        // Activity Logs
        Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // System Settings & KAP Letterhead Profile
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);
        Route::get('/settings/kap-profile', [SettingController::class, 'getKapProfile']);
        Route::put('/settings/kap-profile', [SettingController::class, 'updateKapProfile']);
        Route::post('/settings/kap-logo', [SettingController::class, 'uploadLogo']);

        // Document Requests (Mitra <-> Klien Flow)
        Route::get('/document-requests', [\App\Http\Controllers\Api\DocumentRequest\DocumentRequestController::class, 'index']);
        Route::post('/document-requests', [\App\Http\Controllers\Api\DocumentRequest\DocumentRequestController::class, 'store']);
        Route::get('/document-requests/{documentRequest}', [\App\Http\Controllers\Api\DocumentRequest\DocumentRequestController::class, 'show']);
        Route::post('/document-requests/{documentRequest}/fulfill', [\App\Http\Controllers\Api\DocumentRequest\DocumentRequestController::class, 'fulfill']);
        Route::post('/document-requests/{documentRequest}/review', [\App\Http\Controllers\Api\DocumentRequest\DocumentRequestController::class, 'review']);

        // Notifications (In-App)
        Route::get('/notifications', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/mark-all-read', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'destroy']);
    });
});
