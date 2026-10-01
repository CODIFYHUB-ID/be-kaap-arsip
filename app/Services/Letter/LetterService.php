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

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('letter_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
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

    public function create(array $data, int $userId): Letter
    {
        $data['created_by'] = $userId;
        $letter = Letter::create($data)->load(['mitra', 'category', 'document', 'creator']);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::CREATE,
            module: ActivityModule::LETTER,
            description: "Menerbitkan surat ({$letter->type}) No. {$letter->letter_number} - {$letter->subject}",
            resourceType: 'Letter',
            resourceId: $letter->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        $typeLabel = $letter->type === 'incoming' ? 'Surat Masuk' : 'Surat Keluar';
        $targetUrl = $letter->type === 'incoming' ? '/surat/masuk' : '/surat/keluar';

        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: "{$typeLabel} Baru",
            message: "No. {$letter->letter_number}: {$letter->subject}",
            type: "letter",
            url: $targetUrl,
            meta: [
                'letter_id' => $letter->id,
                'letter_number' => $letter->letter_number,
                'type' => $letter->type,
            ],
            excludeUserId: $userId
        );

        return $letter;
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
