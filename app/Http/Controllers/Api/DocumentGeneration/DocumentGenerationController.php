<?php

namespace App\Http\Controllers\Api\DocumentGeneration;

use App\Http\Controllers\Controller;
use App\Models\GeneratedLetter;
use App\Models\DocumentTemplate;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentGenerationController extends Controller
{
    use ApiResponse;

    /**
     * List all generated letters with search & filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = GeneratedLetter::query()->with(['template', 'mitra', 'creator']);

        if ($type = $request->input('letter_type')) {
            $query->where('letter_type', $type);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('letter_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('audit_type', 'like', "%{$search}%")
                  ->orWhere('signatory_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $letters = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($letters, 'Daftar surat tugas berhasil diambil.');
    }

    /**
     * Store newly created letter
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:document_templates,id',
            'mitra_id' => 'nullable|exists:mitras,id',
            'letter_type' => 'required|string',
            'letter_number' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'audit_type' => 'nullable|string|max:255',
            'period_end_date' => 'nullable|string|max:255',
            'assigned_auditors' => 'nullable|array',
            'assigned_auditors.*.name' => 'nullable|string',
            'assigned_auditors.*.role' => 'nullable|string',
            'kop_type' => 'required|string|in:biasa,amplop,kontrak,tanpa_kop',
            'letter_date' => 'nullable|date',
            'city' => 'nullable|string|max:100',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'opening_text' => 'nullable|string',
            'scope_text' => 'nullable|string',
            'closing_text' => 'nullable|string',
            'body_html' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final,printed',
        ]);

        $validated['created_by'] = $request->user()?->id;

        $letter = GeneratedLetter::create($validated);
        $letter->load(['template', 'mitra', 'creator']);

        return $this->success($letter, 'Surat tugas berhasil dibuat dan disimpan.', 201);
    }

    /**
     * Show single generated letter
     */
    public function show(int $id): JsonResponse
    {
        $letter = GeneratedLetter::with(['template', 'mitra', 'creator'])->find($id);

        if (!$letter) {
            return $this->error('Surat tugas tidak ditemukan.', null, 404);
        }

        return $this->success($letter, 'Detail surat tugas berhasil diambil.');
    }

    /**
     * Update existing generated letter
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $letter = GeneratedLetter::find($id);

        if (!$letter) {
            return $this->error('Surat tugas tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'template_id' => 'nullable|exists:document_templates,id',
            'mitra_id' => 'nullable|exists:mitras,id',
            'letter_type' => 'nullable|string',
            'letter_number' => 'nullable|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'audit_type' => 'nullable|string|max:255',
            'period_end_date' => 'nullable|string|max:255',
            'assigned_auditors' => 'nullable|array',
            'assigned_auditors.*.name' => 'nullable|string',
            'assigned_auditors.*.role' => 'nullable|string',
            'kop_type' => 'nullable|string|in:biasa,amplop,kontrak,tanpa_kop',
            'letter_date' => 'nullable|date',
            'city' => 'nullable|string|max:100',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'opening_text' => 'nullable|string',
            'scope_text' => 'nullable|string',
            'closing_text' => 'nullable|string',
            'body_html' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final,printed',
        ]);

        $letter->update($validated);
        $letter->load(['template', 'mitra', 'creator']);

        return $this->success($letter, 'Surat tugas berhasil diperbarui.');
    }

    /**
     * Delete generated letter (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $letter = GeneratedLetter::find($id);

        if (!$letter) {
            return $this->error('Surat tugas tidak ditemukan.', null, 404);
        }

        $letter->delete();

        return $this->success(null, 'Surat tugas berhasil dihapus.');
    }

    /**
     * Generate default sequential letter number
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $type = $request->input('letter_type', 'surat_tugas');
        $count = GeneratedLetter::where('letter_type', $type)->count();
        $nextNum = str_pad($count + 113, 3, '0', STR_PAD_LEFT);

        $monthRoman = match ((int) date('n')) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => 'I'
        };
        $year = date('Y');

        $format = "No. {$nextNum}/ ST-SSR / {$monthRoman} / {$year}";

        return $this->success([
            'next_number' => $format,
            'sequence' => $count + 113,
        ], 'Nomor surat otomatis berhasil dihitung.');
    }
}
