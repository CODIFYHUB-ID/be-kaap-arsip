<?php

namespace App\Services\Receipt;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Storage\R2StorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(
        protected R2StorageService $r2StorageService,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Terbilang generator for Indonesian Rupiah
     */
    public static function terbilang(float $number): string
    {
        $number = floor(abs($number));
        $words = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];

        if ($number < 12) {
            $result = $words[(int) $number];
        } elseif ($number < 20) {
            $result = self::terbilang($number - 10) . " Belas";
        } elseif ($number < 100) {
            $result = self::terbilang((int) ($number / 10)) . " Puluh " . self::terbilang($number % 10);
        } elseif ($number < 200) {
            $result = "Seratus " . self::terbilang($number - 100);
        } elseif ($number < 1000) {
            $result = self::terbilang((int) ($number / 100)) . " Ratus " . self::terbilang($number % 100);
        } elseif ($number < 2000) {
            $result = "Seribu " . self::terbilang($number - 1000);
        } elseif ($number < 1000000) {
            $result = self::terbilang((int) ($number / 1000)) . " Ribu " . self::terbilang($number % 1000);
        } elseif ($number < 1000000000) {
            $result = self::terbilang((int) ($number / 1000000)) . " Juta " . self::terbilang($number % 1000000);
        } elseif ($number < 1000000000000) {
            $result = self::terbilang((int) ($number / 1000000000)) . " Miliar " . self::terbilang(fmod($number, 1000000000));
        } elseif ($number < 1000000000000000) {
            $result = self::terbilang((int) ($number / 1000000000000)) . " Triliun " . self::terbilang(fmod($number, 1000000000000));
        } else {
            $result = "";
        }

        return trim(preg_replace('/\s+/', ' ', $result));
    }

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Receipt::with([
            'mitra:id,name,code,company_name',
            'creator:id,name',
            'items',
        ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category_transaction', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['input_mode']) && $filters['input_mode'] !== 'all') {
            $query->where('input_mode', $filters['input_mode']);
        }

        if (! empty($filters['receipt_type']) && $filters['receipt_type'] !== 'all') {
            $query->where('receipt_type', $filters['receipt_type']);
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
        $month = (int) date('n');
        $romanMonth = \App\Services\Letter\LetterService::romanMonth($month);
        $prefix = "KW/{$year}/{$romanMonth}/";

        $count = Receipt::withTrashed()
            ->where(function ($q) use ($year) {
                $q->whereYear('transaction_date', $year)
                  ->orWhereYear('created_at', $year);
            })
            ->count();

        $seq = $count + 1;
        do {
            $formattedSeq = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}{$formattedSeq}";
            $exists = Receipt::withTrashed()->where('receipt_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $seq++;
        } while ($seq < 9999);

        return "{$prefix}999";
    }

    public function create(array $data, int $userId, ?string $ip = null, ?string $ua = null): Receipt
    {
        return DB::transaction(function () use ($data, $userId, $ip, $ua) {
            $inputMode = $data['input_mode'] ?? 'manual';
            $itemsData = $data['items'] ?? [];

            // If manual input with multiple line items, calculate totals automatically
            if ($inputMode === 'manual' && !empty($itemsData)) {
                $subtotal = 0;
                foreach ($itemsData as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $subtotal += ($qty * $price);
                }
                $data['subtotal'] = $subtotal;

                $pphPercent = isset($data['tax_pph23_percent']) ? (float) $data['tax_pph23_percent'] : 0;
                $pphAmount = round($subtotal * ($pphPercent / 100), 2);
                $data['tax_pph23_percent'] = $pphPercent;
                $data['tax_pph23_amount'] = $pphAmount;

                $ppnPercent = isset($data['tax_ppn_percent']) ? (float) $data['tax_ppn_percent'] : 0;
                $ppnAmount = round($subtotal * ($ppnPercent / 100), 2);
                $data['tax_ppn_percent'] = $ppnPercent;
                $data['tax_ppn_amount'] = $ppnAmount;

                $finalAmount = $subtotal - $pphAmount + $ppnAmount;
                $data['amount'] = $finalAmount;
                $data['terbilang'] = self::terbilang($finalAmount) . " Rupiah";
            } else {
                $nominal = (float) ($data['amount'] ?? 0);
                $data['subtotal'] = $nominal;
                $data['amount'] = $nominal;
                $data['terbilang'] = self::terbilang($nominal) . " Rupiah";
            }

            $data['created_by'] = $userId;

            if (empty($data['receipt_number'])) {
                $data['receipt_number'] = $this->generateReceiptNumber();
            }

            // Exclude items array from Receipt table attributes
            $receiptAttributes = collect($data)->except(['items'])->toArray();
            $receipt = Receipt::create($receiptAttributes);

            // Save line items for manual mode
            if ($inputMode === 'manual' && !empty($itemsData)) {
                $order = 1;
                foreach ($itemsData as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $total = $qty * $price;

                    ReceiptItem::create([
                        'receipt_id' => $receipt->id,
                        'item_order' => $item['item_order'] ?? $order++,
                        'expense_category' => $item['expense_category'] ?? 'Honorarium Jasa Audit (Audit Fee)',
                        'description' => $item['description'] ?? 'Rincian Layanan',
                        'quantity' => $qty,
                        'unit' => $item['unit'] ?? 'Paket',
                        'unit_price' => $price,
                        'total_price' => $total,
                    ]);
                }
            }

            $this->activityLogService->log(
                userId: $userId,
                action: ActivityAction::UPLOAD,
                module: ActivityModule::DOCUMENT,
                description: "Terbitkan kwitansi ({$inputMode}): {$receipt->receipt_number}",
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

            return $receipt->load(['mitra', 'creator', 'items']);
        });
    }

    public function update(Receipt $receipt, array $data, int $userId, ?string $ip = null, ?string $ua = null): Receipt
    {
        return DB::transaction(function () use ($receipt, $data, $userId, $ip, $ua) {
            $inputMode = $data['input_mode'] ?? $receipt->input_mode ?? 'manual';
            $itemsData = $data['items'] ?? null;

            if ($inputMode === 'manual' && $itemsData !== null && !empty($itemsData)) {
                $subtotal = 0;
                foreach ($itemsData as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $subtotal += ($qty * $price);
                }
                $data['subtotal'] = $subtotal;

                $pphPercent = isset($data['tax_pph23_percent']) ? (float) $data['tax_pph23_percent'] : (float) $receipt->tax_pph23_percent;
                $pphAmount = round($subtotal * ($pphPercent / 100), 2);
                $data['tax_pph23_percent'] = $pphPercent;
                $data['tax_pph23_amount'] = $pphAmount;

                $ppnPercent = isset($data['tax_ppn_percent']) ? (float) $data['tax_ppn_percent'] : (float) $receipt->tax_ppn_percent;
                $ppnAmount = round($subtotal * ($ppnPercent / 100), 2);
                $data['tax_ppn_percent'] = $ppnPercent;
                $data['tax_ppn_amount'] = $ppnAmount;

                $finalAmount = $subtotal - $pphAmount + $ppnAmount;
                $data['amount'] = $finalAmount;
                $data['terbilang'] = self::terbilang($finalAmount) . " Rupiah";

                // Recreate items
                $receipt->items()->delete();
                $order = 1;
                foreach ($itemsData as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $total = $qty * $price;

                    ReceiptItem::create([
                        'receipt_id' => $receipt->id,
                        'item_order' => $item['item_order'] ?? $order++,
                        'expense_category' => $item['expense_category'] ?? 'Honorarium Jasa Audit (Audit Fee)',
                        'description' => $item['description'] ?? 'Rincian Layanan',
                        'quantity' => $qty,
                        'unit' => $item['unit'] ?? 'Paket',
                        'unit_price' => $price,
                        'total_price' => $total,
                    ]);
                }
            } elseif (isset($data['amount'])) {
                $nominal = (float) $data['amount'];
                $data['amount'] = $nominal;
                $data['subtotal'] = $nominal;
                $data['terbilang'] = self::terbilang($nominal) . " Rupiah";
            }

            $receiptAttributes = collect($data)->except(['items'])->toArray();
            $receipt->update($receiptAttributes);

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

            return $receipt->load(['mitra', 'creator', 'items']);
        });
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
