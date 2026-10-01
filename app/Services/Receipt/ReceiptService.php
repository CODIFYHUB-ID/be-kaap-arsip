<?php

namespace App\Services\Receipt;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Receipt;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Storage\R2StorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReceiptService
{
    public function __construct(
        protected R2StorageService $r2StorageService,
        protected ActivityLogService $activityLogService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Receipt::with([
            'mitra:id,name,code,company_name',
            'creator:id,name',
        ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mitra_id']) && $filters['mitra_id'] !== 'all') {
            $query->where('mitra_id', $filters['mitra_id']);
        }

        if (! empty($filters['year']) && $filters['year'] !== 'all') {
            $year = $filters['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('transaction_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('transaction_date')->orderByDesc('created_at')->paginate($perPage);
    }

    public function getStats(): array
    {
        $totalCount = Receipt::count();
        $totalSizeBytes = (int) Receipt::sum('file_size');
        $totalAmount = (float) Receipt::sum('amount');
        $mitraCount = Receipt::whereNotNull('mitra_id')->distinct('mitra_id')->count('mitra_id');

        return [
            'total_count' => $totalCount,
            'total_size_bytes' => $totalSizeBytes,
            'total_amount' => $totalAmount,
            'mitra_count' => $mitraCount,
        ];
    }

    public function generateReceiptNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "KW/{$year}/{$month}/";

        $last = Receipt::withTrashed()
            ->where('receipt_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('receipt_number');

        if ($last) {
            $parts = explode('/', $last);
            $lastSeq = (int) end($parts);
            $nextSeq = str_pad((string) ($lastSeq + 1), 3, '0', STR_PAD_LEFT);
        } else {
            $nextSeq = '001';
        }

        return "{$prefix}{$nextSeq}";
    }

    public function create(array $data, int $userId, ?string $ip = null, ?string $ua = null): Receipt
    {
        $data['created_by'] = $userId;

        if (empty($data['receipt_number'])) {
            $data['receipt_number'] = $this->generateReceiptNumber();
        }

        $receipt = Receipt::create($data);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPLOAD,
            module: ActivityModule::DOCUMENT,
            description: "Unggah kwitansi: {$receipt->receipt_number}",
            resourceType: Receipt::class,
            resourceId: $receipt->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        $formattedAmount = "Rp " . number_format($receipt->amount, 0, ',', '.');
        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: "Kwitansi Baru Diterbitkan",
            message: "No. {$receipt->receipt_number} ({$formattedAmount})",
            type: "receipt",
            url: "/transaksi/kwitansi",
            meta: [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'amount' => $receipt->amount,
            ],
            excludeUserId: $userId
        );

        return $receipt->load(['mitra', 'creator']);
    }

    public function update(Receipt $receipt, array $data, int $userId, ?string $ip = null, ?string $ua = null): Receipt
    {
        $receipt->update($data);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPDATE,
            module: ActivityModule::DOCUMENT,
            description: "Update data kwitansi: {$receipt->receipt_number}",
            resourceType: Receipt::class,
            resourceId: $receipt->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        return $receipt->load(['mitra', 'creator']);
    }

    public function delete(Receipt $receipt, int $userId, ?string $ip = null, ?string $ua = null): bool
    {
        $num = $receipt->receipt_number;
        $id = $receipt->id;

        $deleted = $receipt->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: $userId,
                action: ActivityAction::DELETE,
                module: ActivityModule::DOCUMENT,
                description: "Hapus kwitansi ke Recycle Bin: {$num}",
                resourceType: Receipt::class,
                resourceId: $id,
                ipAddress: $ip,
                userAgent: $ua
            );
        }

        return (bool) $deleted;
    }

    public function getDownloadUrl(Receipt $receipt, int $userId, ?string $ip = null, ?string $ua = null): ?string
    {
        if (empty($receipt->file_key)) {
            return null;
        }

        $url = $this->r2StorageService->generatePresignedDownloadUrl($receipt->file_key, $receipt->file_name ?? "{$receipt->receipt_number}.pdf");

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::DOWNLOAD,
            module: ActivityModule::DOCUMENT,
            description: "Download berkas scan kwitansi: {$receipt->receipt_number}",
            resourceType: Receipt::class,
            resourceId: $receipt->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        return $url;
    }
}
