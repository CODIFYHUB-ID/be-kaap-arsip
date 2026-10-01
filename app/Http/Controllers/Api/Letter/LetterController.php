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
        $filters = $request->only(['type', 'search', 'mitra_id', 'category_id', 'year']);
        $perPage = (int) $request->get('per_page', 20);

        $letters = $this->letterService->getPaginated($filters, $perPage, $request->user());
        return $this->paginated($letters, 'Daftar surat berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|string|in:incoming,outgoing',
            'letter_number' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'letter_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'description' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
        ]);

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

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|string|in:incoming,outgoing',
            'letter_number' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'letter_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'description' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
        ]);

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

        $this->letterService->delete($letter);
        return $this->success(null, 'Surat berhasil dihapus.');
    }
}
