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
        $filters = $request->only(['type', 'search', 'mitra_id', 'category_id']);
        $perPage = (int) $request->get('per_page', 20);

        $letters = $this->letterService->getPaginated($filters, $perPage);
        return $this->paginated($letters, 'Daftar surat berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
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

        $letter = $this->letterService->create($validated, $request->user()->id);
        return $this->success($letter, 'Surat berhasil ditambahkan.', 201);
    }

    public function show(Letter $letter): JsonResponse
    {
        return $this->success($letter->load(['mitra', 'category', 'document', 'creator']), 'Detail surat berhasil diambil.');
    }

    public function update(Request $request, Letter $letter): JsonResponse
    {
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

    public function destroy(Letter $letter): JsonResponse
    {
        $this->letterService->delete($letter);
        return $this->success(null, 'Surat berhasil dihapus.');
    }
}
