<?php

namespace App\Services\Letter;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Letter;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LetterService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 20, ?\App\Models\User $currentUser = null): LengthAwarePaginator
    {
        $currentUser = $currentUser ?? auth()->user();

        $query = Letter::with([
            'mitra:id,name,code,company_name',
            'category:id,name',
            'document:id,file_name,file_size,mime_type,extension',
            'creator:id,name',
        ]);

        // Scope to Mitra's own letters if logged in as Mitra
        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $query->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
        }

        // Scope to Auditor assigned clients if logged in as Auditor
        if ($currentUser && $currentUser->isAuditor() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $currentUser->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $query->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('created_by', $currentUser->id);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['confirmation_type']) && $filters['confirmation_type'] !== 'all') {
            $query->where('confirmation_type', $filters['confirmation_type']);
        }

        if (! empty($filters['confirmation_status']) && $filters['confirmation_status'] !== 'all') {
            $query->where('confirmation_status', $filters['confirmation_status']);
        }

        if (! empty($filters['is_confirmation'])) {
            $query->where(function ($q) {
                $q->whereNotNull('confirmation_type')
                  ->orWhereNotNull('confirmation_status')
                  ->orWhereHas('category', function ($cq) {
                      $cq->where('name', 'like', '%konfirmasi%');
                  });
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('letter_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('third_party_name', 'like', "%{$search}%")
                  ->orWhere('sender', 'like', "%{$search}%")
                  ->orWhere('recipient', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mitra_id'])) {
            $query->where('mitra_id', $filters['mitra_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['year']) && $filters['year'] !== 'all') {
            $year = $filters['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhereYear('received_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }

        return $query->orderByDesc('letter_date')->paginate($perPage);
    }

    public static function romanMonth(int $month): string
    {
        $romans = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];
        return $romans[$month] ?? 'I';
    }

    public function getCategoryCode(?int $categoryId): string
    {
        if (! $categoryId) {
            return 'UMM';
        }
        $category = \App\Models\Category::find($categoryId);
        if (! $category) {
            return 'UMM';
        }

        $name = strtolower($category->name);
        if (str_contains($name, 'penawaran')) return 'PNW';
        if (str_contains($name, 'perikatan') || str_contains($name, 'kontrak')) return 'PRK';
        if (str_contains($name, 'konfirmasi')) return 'KNF';
        if (str_contains($name, 'keterangan')) return 'KET';
        if (str_contains($name, 'audit')) return 'AUD';
        if (str_contains($name, 'legal')) return 'LGL';

        return strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category->name), 0, 3)) ?: 'UMM';
    }

    /**
     * Generate KAP Sinuraya standard letter number:
     * Format: [NoUrut]/KAP-SN/[KodeKategori]/[BulanRomawi]/[Tahun]
     * Concurrency-safe & Unique
     */
    public function generateLetterNumber(?int $categoryId = null, ?string $date = null, string $type = 'outgoing'): string
    {
        $timestamp = $date ? strtotime($date) : time();
        $year = date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $romanMonth = self::romanMonth($month);
        $catCode = $this->getCategoryCode($categoryId);

        // Lock to avoid race conditions
        $prefix = "/KAP-SN/{$catCode}/{$romanMonth}/{$year}";
        
        $count = Letter::withTrashed()
            ->where('type', $type)
            ->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhereYear('created_at', $year);
            })
            ->count();

        $seq = $count + 1;
        do {
            $formattedSeq = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $candidate = "{$formattedSeq}{$prefix}";
            $exists = Letter::withTrashed()->where('letter_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $seq++;
        } while ($seq < 9999);

        return "999{$prefix}";
    }

    public function create(array $data, int $userId): Letter
    {
        $data['created_by'] = $userId;

        // Auto generate official number if not specified or set to auto
        if (empty($data['letter_number']) || $data['letter_number'] === 'auto') {
            $data['letter_number'] = $this->generateLetterNumber(
                $data['category_id'] ?? null,
                $data['letter_date'] ?? null,
                $data['type'] ?? 'outgoing'
            );
        }

        if (empty($data['status'])) {
            $data['status'] = 'final';
        }

        $letter = Letter::create($data)->load(['mitra', 'category', 'document', 'creator']);

        $typeVal = is_object($letter->type) ? ($letter->type->value ?? (string) $letter->type) : (string) $letter->type;
        $typeLabel = $typeVal === 'incoming' ? 'Surat Masuk' : 'Surat Keluar';
        $targetUrl = $typeVal === 'incoming' ? '/surat/masuk' : '/surat/keluar';

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::CREATE,
            module: ActivityModule::LETTER,
            description: "Menerbitkan surat ({$typeVal}) No. {$letter->letter_number} - {$letter->subject}",
            resourceType: 'Letter',
            resourceId: $letter->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        // 1. Broadcast global notification
        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: "{$typeLabel} Baru",
            message: "No. {$letter->letter_number}: {$letter->subject}",
            type: "letter",
            url: $targetUrl,
            meta: [
                'letter_id' => $letter->id,
                'letter_number' => $letter->letter_number,
                'type' => $typeVal,
            ],
            excludeUserId: $userId
        );

        // 2. Specific Partner Notification (B.3: Distribusi Surat ke Partner Penanggung Jawab)
        if ($letter->mitra_id) {
            $partnerUser = \App\Models\User::where('mitra_id', $letter->mitra_id)->first();
            if (! $partnerUser) {
                $mitra = \App\Models\Mitra::find($letter->mitra_id);
                if ($mitra && $mitra->created_by) {
                    $partnerUser = \App\Models\User::find($mitra->created_by);
                }
            }

            if ($partnerUser && $partnerUser->id !== $userId) {
                \App\Services\Notification\NotificationDispatcher::notifyUser(
                    userId: $partnerUser->id,
                    title: "Surat Ditugaskan ke Portofolio Anda",
                    message: "No. {$letter->letter_number} ({$letter->subject}) telah dikaitkan dengan klien binaan Anda.",
                    type: "letter",
                    url: $targetUrl,
                    meta: [
                        'letter_id' => $letter->id,
                        'letter_number' => $letter->letter_number,
                        'mitra_id' => $letter->mitra_id,
                    ]
                );
            }
        }

        return $letter;
    }

    public function lockLetter(Letter $letter, int $userId): Letter
    {
        $letter->update(['status' => 'final']);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPDATE,
            module: ActivityModule::LETTER,
            description: "Finalisasi & Penguncian nomor surat resmi: {$letter->letter_number}",
            resourceType: 'Letter',
            resourceId: $letter->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $letter->load(['mitra', 'category', 'document', 'creator']);
    }

    public function update(Letter $letter, array $data): Letter
    {
        $letter->update($data);
        $updated = $letter->load(['mitra', 'category', 'document', 'creator']);

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::UPDATE,
            module: ActivityModule::LETTER,
            description: "Memperbarui surat No. {$letter->letter_number} - {$letter->subject}",
            resourceType: 'Letter',
            resourceId: $letter->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $updated;
    }

    public function delete(Letter $letter): bool
    {
        $letterNo = $letter->letter_number;
        $subject = $letter->subject;
        $letterId = $letter->id;

        if ($letter->document_id) {
            \App\Models\Document::where('id', $letter->document_id)->delete();
        }
        $deleted = $letter->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: auth()->id(),
                action: ActivityAction::DELETE,
                module: ActivityModule::LETTER,
                description: "Menghapus surat No. {$letterNo} - {$subject}",
                resourceType: 'Letter',
                resourceId: $letterId,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent()
            );
        }

        return $deleted;
    }
}
