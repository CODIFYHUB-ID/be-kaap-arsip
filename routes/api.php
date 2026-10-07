<?php

use App\Http\Controllers\Api\ActivityLog\ActivityLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Document\DocumentController;
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
        Route::get('/mitras/{mitra}/generated-source-documents', [MitraController::class, 'generatedSourceDocuments']);
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

        // Master Document Templates (Surat Tugas, etc.)
        Route::get('/document-templates', [\App\Http\Controllers\Api\DocumentTemplate\DocumentTemplateController::class, 'index']);
        Route::get('/document-templates/{code}', [\App\Http\Controllers\Api\DocumentTemplate\DocumentTemplateController::class, 'show']);
        Route::put('/document-templates/{code}', [\App\Http\Controllers\Api\DocumentTemplate\DocumentTemplateController::class, 'update']);

        // Generated Letters (Surat Tugas, etc.)
        Route::get('/generated-letters/next-number', [\App\Http\Controllers\Api\DocumentGeneration\DocumentGenerationController::class, 'nextNumber']);
        Route::apiResource('/generated-letters', \App\Http\Controllers\Api\DocumentGeneration\DocumentGenerationController::class);

        // Invoices / Kwitansi Module
        Route::get('/invoices/next-number', [\App\Http\Controllers\Api\Invoice\InvoiceController::class, 'nextNumber']);
        Route::post('/invoices/{id}/revise', [\App\Http\Controllers\Api\Invoice\InvoiceController::class, 'revise']);
        Route::post('/invoices/{id}/duplicate', [\App\Http\Controllers\Api\Invoice\InvoiceController::class, 'duplicate']);
        Route::apiResource('/invoices', \App\Http\Controllers\Api\Invoice\InvoiceController::class);

        // Outgoing Letters (Surat Penawaran, Surat Keterangan / Cover Note)
        Route::get('/outgoing-letters/next-number', [\App\Http\Controllers\Api\OutgoingLetter\OutgoingLetterController::class, 'nextNumber']);
        Route::post('/outgoing-letters/{id}/revise', [\App\Http\Controllers\Api\OutgoingLetter\OutgoingLetterController::class, 'revise']);
        Route::post('/outgoing-letters/{id}/duplicate', [\App\Http\Controllers\Api\OutgoingLetter\OutgoingLetterController::class, 'duplicate']);
        Route::apiResource('/outgoing-letters', \App\Http\Controllers\Api\OutgoingLetter\OutgoingLetterController::class);

        // Bank Confirmations (Konfirmasi Bank) Module
        Route::get('/bank-confirmations/next-number', [\App\Http\Controllers\Api\BankConfirmation\BankConfirmationController::class, 'nextNumber']);
        Route::get('/bank-confirmations/client-banks/{mitraId}', [\App\Http\Controllers\Api\BankConfirmation\BankConfirmationController::class, 'getClientBanks']);
        Route::post('/bank-confirmations/{id}/revise', [\App\Http\Controllers\Api\BankConfirmation\BankConfirmationController::class, 'revise']);
        Route::post('/bank-confirmations/{id}/duplicate', [\App\Http\Controllers\Api\BankConfirmation\BankConfirmationController::class, 'duplicate']);
        Route::apiResource('/bank-confirmations', \App\Http\Controllers\Api\BankConfirmation\BankConfirmationController::class);

        // Debtor & Creditor Confirmations (Konfirmasi Utang & Piutang) Module
        Route::get('/debtor-creditor-confirmations/next-number', [\App\Http\Controllers\Api\DebtorCreditorConfirmation\DebtorCreditorConfirmationController::class, 'nextNumber']);
        Route::post('/debtor-creditor-confirmations/{id}/revise', [\App\Http\Controllers\Api\DebtorCreditorConfirmation\DebtorCreditorConfirmationController::class, 'revise']);
        Route::post('/debtor-creditor-confirmations/{id}/duplicate', [\App\Http\Controllers\Api\DebtorCreditorConfirmation\DebtorCreditorConfirmationController::class, 'duplicate']);
        Route::apiResource('/debtor-creditor-confirmations', \App\Http\Controllers\Api\DebtorCreditorConfirmation\DebtorCreditorConfirmationController::class);

        // Audit Contracts (Surat Kontrak Perikatan Audit) Module
        Route::get('/audit-contracts/next-number', [\App\Http\Controllers\Api\AuditContract\AuditContractController::class, 'nextNumber']);
        Route::apiResource('/audit-contracts', \App\Http\Controllers\Api\AuditContract\AuditContractController::class);

        // Notifications (In-App)
        Route::get('/notifications', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/mark-all-read', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\Notification\NotificationController::class, 'destroy']);
    });
});
