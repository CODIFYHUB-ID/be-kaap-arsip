<?php

namespace App\Http\Controllers\Api\AuditContract;

use App\Http\Controllers\Controller;
use App\Models\AuditContract;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditContractController extends Controller
{
    use ApiResponse;

    /**
     * List all audit contracts with search & filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditContract::query()->with(['mitra', 'creator']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('client_pic_name', 'like', "%{$search}%")
                  ->orWhere('signatory_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $contracts = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($contracts, 'Daftar surat kontrak berhasil diambil.');
    }

    /**
     * Store newly created audit contract
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_number' => 'required|string|max:255',
            'contract_date' => 'nullable|string',
            'contract_date_text' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'ref_text' => 'nullable|string|max:255',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_address' => 'nullable|string',
            'client_pic_name' => 'nullable|string|max:255',
            'client_pic_title' => 'nullable|string|max:255',
            'accounting_standard' => 'nullable|string|max:255',
            'period_end_date' => 'nullable|string|max:255',
            'fee_amount' => 'nullable|numeric|min:0',
            'fee_terbilang' => 'nullable|string|max:255',
            'report_copies' => 'nullable|integer|min:1',
            'payment_terms' => 'nullable|array',
            'payment_terms.*.percentage' => 'nullable|numeric',
            'payment_terms.*.description' => 'nullable|string',
            'accommodation_note' => 'nullable|string|max:500',
            'kop_type' => 'required|string|in:biasa,amplop,kontrak,tanpa_kop',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'custom_html' => 'nullable|string',
            'custom_content_html' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final,signed',
        ]);

        if (empty($validated['custom_html']) && !empty($request->input('custom_content_html'))) {
            $validated['custom_html'] = $request->input('custom_content_html');
        }

        if (empty($validated['contract_date']) || !strtotime($validated['contract_date'])) {
            $validated['contract_date'] = now()->toDateString();
        }

        $validated['created_by'] = $request->user()?->id;

        $contract = AuditContract::create($validated);
        $contract->load(['mitra', 'creator']);

        return $this->success($contract, 'Surat kontrak perikatan audit berhasil disimpan.', 201);
    }

    /**
     * Show single audit contract
     */
    public function show(int $id): JsonResponse
    {
        $contract = AuditContract::with(['mitra', 'creator'])->find($id);

        if (!$contract) {
            return $this->error('Surat kontrak tidak ditemukan.', null, 404);
        }

        return $this->success($contract, 'Detail surat kontrak berhasil diambil.');
    }

    /**
     * Update existing audit contract
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $contract = AuditContract::find($id);

        if (!$contract) {
            return $this->error('Surat kontrak tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'contract_number' => 'nullable|string|max:255',
            'contract_date' => 'nullable|string',
            'contract_date_text' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'ref_text' => 'nullable|string|max:255',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'nullable|string|max:255',
            'client_address' => 'nullable|string',
            'client_pic_name' => 'nullable|string|max:255',
            'client_pic_title' => 'nullable|string|max:255',
            'accounting_standard' => 'nullable|string|max:255',
            'period_end_date' => 'nullable|string|max:255',
            'fee_amount' => 'nullable|numeric|min:0',
            'fee_terbilang' => 'nullable|string|max:255',
            'report_copies' => 'nullable|integer|min:1',
            'payment_terms' => 'nullable|array',
            'payment_terms.*.percentage' => 'nullable|numeric',
            'payment_terms.*.description' => 'nullable|string',
            'accommodation_note' => 'nullable|string|max:500',
            'kop_type' => 'nullable|string|in:biasa,amplop,kontrak,tanpa_kop',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'custom_html' => 'nullable|string',
            'custom_content_html' => 'nullable|string',
            'status' => 'nullable|string|in:draft,final,signed',
        ]);

        if (empty($validated['custom_html']) && !empty($request->input('custom_content_html'))) {
            $validated['custom_html'] = $request->input('custom_content_html');
        }

        if (isset($validated['contract_date']) && (!strtotime($validated['contract_date']))) {
            unset($validated['contract_date']);
        }

        $contract->update($validated);
        $contract->load(['mitra', 'creator']);

        return $this->success($contract, 'Surat kontrak berhasil diperbarui.');
    }

    /**
     * Delete audit contract (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $contract = AuditContract::find($id);

        if (!$contract) {
            return $this->error('Surat kontrak tidak ditemukan.', null, 404);
        }

        $contract->delete();

        return $this->success(null, 'Surat kontrak berhasil dihapus.');
    }

    /**
     * Generate default sequential contract number
     * Format: {seq} / PKA-RS / SSR / MDN / {year}
     */
    public function nextNumber(): JsonResponse
    {
        $count = AuditContract::withTrashed()->count();
        $nextNum = str_pad($count + 286, 3, '0', STR_PAD_LEFT);
        $year = date('Y');

        $formatted = "{$nextNum} / PKA-RS / SSR / MDN / {$year}";

        return $this->success([
            'next_number' => $formatted,
            'sequence' => $count + 1,
        ], 'Nomor surat kontrak berikutnya berhasil digenerate.');
    }
}
