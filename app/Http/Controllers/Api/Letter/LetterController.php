<?php

namespace App\Http\Controllers\Api\Letter;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Services\Letter\LetterService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LetterService $letterService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'search', 'mitra_id', 'category_id', 'year', 'confirmation_type', 'confirmation_status', 'is_confirmation']);
        $perPage = (int) $request->get('per_page', 20);

        $letters = $this->letterService->getPaginated($filters, $perPage, $request->user());
        return $this->paginated($letters, 'Daftar surat berhasil diambil.');
    }

    public function generateNumber(Request $request): JsonResponse
    {
        $categoryId = $request->get('category_id') ? (int) $request->get('category_id') : null;
        $date = $request->get('date') ?: null;
        $type = $request->get('type', 'outgoing');

        $number = $this->letterService->generateLetterNumber($categoryId, $date, $type);
        return $this->success(['letter_number' => $number], 'Nomor surat otomatis berhasil digenerate.');
    }

    public function lock(Request $request, Letter $letter): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            return $this->error('Hanya Admin atau Sekretariat yang dapat mengunci nomor surat resmi.', 403);
        }

        $locked = $this->letterService->lockLetter($letter, $user->id);
        return $this->success($locked, 'Nomor surat resmi berhasil difinalkan dan dikunci.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|string|in:incoming,outgoing',
            'letter_number' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'sender' => 'nullable|string|max:255',
            'recipient' => 'nullable|string|max:255',
            'disposition' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final',
            'letter_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'description' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
            'confirmation_type' => 'nullable|string|in:bank,piutang,utang,lainnya',
            'confirmation_status' => 'nullable|string|in:draft,terkirim,menunggu_jawaban,terjawab,selisih',
            'third_party_name' => 'nullable|string|max:255',
            'confirmation_reply_date' => 'nullable|date',
            'exception_notes' => 'nullable|string',
        ]);

        // Auto-assign strictly to own company for Klien users
        if ($user && $user->isKlien() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $validated['mitra_id'] = $user->mitra_id;
        }

        // Auto-assign or validate mitra_id for Mitra users
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            if (empty($validated['mitra_id'])) {
                $validated['mitra_id'] = $user->mitra_id;
            } elseif ($validated['mitra_id'] != $user->mitra_id) {
                $isAllowed = \App\Models\Mitra::where('id', $validated['mitra_id'])->where('created_by', $user->id)->exists();
                if (! $isAllowed) {
                    return $this->error('Anda tidak berwenang membuat surat untuk mitra ini.', 403);
                }
            }
        }

        // Validate mitra_id for Auditor users
        if ($user && $user->isAuditor() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $user->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            if (! empty($validated['mitra_id']) && ! in_array($validated['mitra_id'], $assignedMitraIds)) {
                return $this->error('Akses ditolak. Anda hanya berwenang mengelola surat untuk klien yang ditugaskan kepada Anda.', 403);
            }
        }

        $letter = $this->letterService->create($validated, $request->user()->id);
        return $this->success($letter, 'Surat berhasil ditambahkan.', 201);
    }

    public function show(Request $request, Letter $letter): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $letter->mitra_id == $user->mitra_id)
                || $letter->created_by == $user->id
                || ($letter->mitra && $letter->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses ke surat ini.', 403);
            }
        }

        if ($user && $user->isAuditor() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $user->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $isOwner = ($letter->mitra_id && in_array($letter->mitra_id, $assignedMitraIds))
                || $letter->created_by == $user->id;

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses ke surat klien di luar penugasan Anda.', 403);
            }
        }

        return $this->success($letter->load(['mitra', 'category', 'document', 'creator']), 'Detail surat berhasil diambil.');
    }

    public function update(Request $request, Letter $letter): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $letter->mitra_id == $user->mitra_id)
                || $letter->created_by == $user->id
                || ($letter->mitra && $letter->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk mengubah surat ini.', 403);
            }
        }

        if ($user && $user->isAuditor() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $user->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $isOwner = ($letter->mitra_id && in_array($letter->mitra_id, $assignedMitraIds))
                || $letter->created_by == $user->id;

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk mengubah surat klien di luar penugasan Anda.', 403);
            }
        }

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'sometimes|required|string|in:incoming,outgoing',
            'letter_number' => 'sometimes|required|string|max:255',
            'subject' => 'sometimes|required|string|max:255',
            'sender' => 'nullable|string|max:255',
            'recipient' => 'nullable|string|max:255',
            'disposition' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final',
            'letter_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'description' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
            'confirmation_type' => 'nullable|string|in:bank,piutang,utang,lainnya',
            'confirmation_status' => 'nullable|string|in:draft,terkirim,menunggu_jawaban,terjawab,selisih',
            'third_party_name' => 'nullable|string|max:255',
            'confirmation_reply_date' => 'nullable|date',
            'exception_notes' => 'nullable|string',
        ]);

        // If letter is already final, prevent changing official letter_number unless Owner/Super Admin
        if ($letter->status === 'final' && ! $user->hasAnyRole(['Owner', 'Super Admin'])) {
            unset($validated['letter_number']);
        }

        $updated = $this->letterService->update($letter, $validated);
        return $this->success($updated, 'Surat berhasil diperbarui.');
    }

    public function destroy(Request $request, Letter $letter): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $letter->mitra_id == $user->mitra_id)
                || $letter->created_by == $user->id
                || ($letter->mitra && $letter->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk menghapus surat ini.', 403);
            }
        }

        if ($user && $user->isAuditor() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $user->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $isOwner = ($letter->mitra_id && in_array($letter->mitra_id, $assignedMitraIds))
                || $letter->created_by == $user->id;

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk menghapus surat klien di luar penugasan Anda.', 403);
            }
        }

        $this->letterService->delete($letter);
        return $this->success(null, 'Surat berhasil dihapus.');
    }
}
