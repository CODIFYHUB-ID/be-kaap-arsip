<?php

namespace App\Http\Controllers\Api\BankConfirmation;

use App\Http\Controllers\Controller;
use App\Models\BankConfirmation;
use App\Models\BankConfirmationItem;
use App\Models\ClientBank;
use App\Models\Mitra;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankConfirmationController extends Controller
{
    use ApiResponse;

    /**
     * List all bank confirmations with filter by mitra_id, search & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = BankConfirmation::query()->with(['mitra', 'creator', 'parent', 'items']);

        if ($mitraId = $request->input('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('confirmation_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $list = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($list, 'Daftar konfirmasi bank berhasil diambil.');
    }

    /**
     * Generate next confirmation number recommendation
     * e.g. KB/001/BAZNAS/II/2022
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $year = (int) date('Y');
        $count = BankConfirmation::whereYear('created_at', $year)->count();
        $nextSeq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $this->success([
            'seq_number' => $nextSeq,
            'year' => (string) $year,
            'recommended_number' => "KB/{$nextSeq}/KAP/{$year}",
        ], 'Rekomendasi nomor konfirmasi bank berhasil digenerate.');
    }

    /**
     * Get single Bank Confirmation
     */
    public function show(int $id): JsonResponse
    {
        $item = BankConfirmation::with(['mitra', 'creator', 'parent', 'revisions', 'items'])->find($id);

        if (!$item) {
            return $this->notFound('Dokumen konfirmasi bank tidak ditemukan.');
        }

        return $this->success($item, 'Detail konfirmasi bank berhasil diambil.');
    }

    /**
     * Store new Bank Confirmation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_number' => 'required|string|max:255|unique:bank_confirmations,confirmation_number',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_letterhead_url' => 'nullable|string',
            'client_signature_stamp_url' => 'nullable|string',
            'show_client_stamp' => 'nullable|boolean',
            'letter_city' => 'nullable|string|max:100',
            'letter_date' => 'nullable|date',
            'subject' => 'nullable|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_address' => 'nullable|string',
            'balance_date' => 'required|string|max:255',
            'client_signatory_name' => 'nullable|string|max:255',
            'client_signatory_title' => 'nullable|string|max:255',
            'confirmation_date' => 'nullable|string|max:255',
            'fill_mode_giro' => 'nullable|boolean',
            'fill_mode_deposito' => 'nullable|boolean',
            'fill_mode_loan' => 'nullable|boolean',
            'fill_mode_other' => 'nullable|boolean',
            'bank_signatory_city' => 'nullable|string|max:100',
            'bank_signatory_title' => 'nullable|string|max:255',
            'bank_signatory_name' => 'nullable|string|max:255',
            'show_note' => 'nullable|boolean',
            'note_text' => 'nullable|string',
            'status' => 'nullable|string|in:draft,sent,received',
            'items' => 'nullable|array',
            'items.*.category' => 'required|string|in:giro,deposito,loan,other',
            'items.*.col_1' => 'nullable|string',
            'items.*.amount_1' => 'nullable|numeric',
            'items.*.col_2' => 'nullable|string',
            'items.*.col_3' => 'nullable|string',
            'items.*.amount_2' => 'nullable|numeric',
            'items.*.remarks' => 'nullable|string',
            'items.*.order' => 'nullable|integer',
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                $user = $request->user();
                $validated['created_by'] = $user ? $user->id : null;
                $itemsData = $validated['items'] ?? [];
                unset($validated['items']);

                $confirmation = BankConfirmation::create($validated);

                foreach ($itemsData as $idx => $it) {
                    BankConfirmationItem::create([
                        'bank_confirmation_id' => $confirmation->id,
                        'category' => $it['category'],
                        'col_1' => $it['col_1'] ?? null,
                        'amount_1' => $it['amount_1'] ?? null,
                        'col_2' => $it['col_2'] ?? null,
                        'col_3' => $it['col_3'] ?? null,
                        'amount_2' => $it['amount_2'] ?? null,
                        'remarks' => $it['remarks'] ?? null,
                        'order' => $it['order'] ?? $idx,
                    ]);
                }

                // Simpan bank ke client_banks jika mitra_id ada agar tersimpan per klien
                if (!empty($validated['mitra_id']) && !empty($validated['bank_name'])) {
                    ClientBank::firstOrCreate([
                        'mitra_id' => $validated['mitra_id'],
                        'bank_name' => $validated['bank_name'],
                    ], [
                        'bank_address' => $validated['bank_address'] ?? null,
                    ]);
                }

                return $this->created(
                    $confirmation->load(['mitra', 'creator', 'items']),
                    'Dokumen konfirmasi bank berhasil disimpan.'
                );
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('BankConfirmation store error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return $this->error('Gagal menyimpan konfirmasi bank: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update Bank Confirmation
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $confirmation = BankConfirmation::find($id);

        if (!$confirmation) {
            return $this->notFound('Dokumen konfirmasi bank tidak ditemukan.');
        }

        $validated = $request->validate([
            'confirmation_number' => "required|string|max:255|unique:bank_confirmations,confirmation_number,{$id}",
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_letterhead_url' => 'nullable|string',
            'client_signature_stamp_url' => 'nullable|string',
            'show_client_stamp' => 'nullable|boolean',
            'letter_city' => 'nullable|string|max:100',
            'letter_date' => 'nullable|date',
            'subject' => 'nullable|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_address' => 'nullable|string',
            'balance_date' => 'required|string|max:255',
            'client_signatory_name' => 'nullable|string|max:255',
            'client_signatory_title' => 'nullable|string|max:255',
            'confirmation_date' => 'nullable|string|max:255',
            'fill_mode_giro' => 'nullable|boolean',
            'fill_mode_deposito' => 'nullable|boolean',
            'fill_mode_loan' => 'nullable|boolean',
            'fill_mode_other' => 'nullable|boolean',
            'bank_signatory_city' => 'nullable|string|max:100',
            'bank_signatory_title' => 'nullable|string|max:255',
            'bank_signatory_name' => 'nullable|string|max:255',
            'show_note' => 'nullable|boolean',
            'note_text' => 'nullable|string',
            'status' => 'nullable|string|in:draft,sent,received',
            'sent_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.category' => 'required|string|in:giro,deposito,loan,other',
            'items.*.col_1' => 'nullable|string',
            'items.*.amount_1' => 'nullable|numeric',
            'items.*.col_2' => 'nullable|string',
            'items.*.col_3' => 'nullable|string',
            'items.*.amount_2' => 'nullable|numeric',
            'items.*.remarks' => 'nullable|string',
            'items.*.order' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($confirmation, $validated) {
            $itemsData = $validated['items'] ?? null;
            unset($validated['items']);

            $confirmation->update($validated);

            if ($itemsData !== null) {
                $confirmation->items()->delete();
                foreach ($itemsData as $idx => $it) {
                    BankConfirmationItem::create([
                        'bank_confirmation_id' => $confirmation->id,
                        'category' => $it['category'],
                        'col_1' => $it['col_1'] ?? null,
                        'amount_1' => $it['amount_1'] ?? null,
                        'col_2' => $it['col_2'] ?? null,
                        'col_3' => $it['col_3'] ?? null,
                        'amount_2' => $it['amount_2'] ?? null,
                        'remarks' => $it['remarks'] ?? null,
                        'order' => $it['order'] ?? $idx,
                    ]);
                }
            }

            // Simpan bank ke client_banks
            if (!empty($validated['mitra_id']) && !empty($validated['bank_name'])) {
                ClientBank::firstOrCreate([
                    'mitra_id' => $validated['mitra_id'],
                    'bank_name' => $validated['bank_name'],
                ], [
                    'bank_address' => $validated['bank_address'] ?? null,
                ]);
            }

            return $this->success(
                $confirmation->load(['mitra', 'creator', 'items']),
                'Dokumen konfirmasi bank berhasil diperbarui.'
            );
        });
    }

    /**
     * Create revision version without overwriting existing
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $original = BankConfirmation::with('items')->find($id);

        if (!$original) {
            return $this->notFound('Dokumen sumber tidak ditemukan.');
        }

        return DB::transaction(function () use ($original, $request) {
            $rootParentId = $original->parent_id ?: $original->id;
            $latestVersion = BankConfirmation::where('parent_id', $rootParentId)
                ->orWhere('id', $rootParentId)
                ->max('version');

            $nextVersion = ($latestVersion ?: $original->version) + 1;
            $newNumber = $original->confirmation_number . "-REV{$nextVersion}";

            // Ensure unique number
            $suffix = 1;
            while (BankConfirmation::where('confirmation_number', $newNumber)->exists()) {
                $newNumber = $original->confirmation_number . "-REV{$nextVersion}." . $suffix++;
            }

            $cloneData = $original->toArray();
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at'], $cloneData['deleted_at'], $cloneData['items']);

            $cloneData['parent_id'] = $rootParentId;
            $cloneData['version'] = $nextVersion;
            $cloneData['confirmation_number'] = $newNumber;
            $cloneData['created_by'] = $request->user() ? $request->user()->id : null;
            $cloneData['status'] = 'draft';

            $newConfirmation = BankConfirmation::create($cloneData);

            // Replicate items
            foreach ($original->items as $it) {
                $itData = $it->toArray();
                unset($itData['id'], $itData['created_at'], $itData['updated_at']);
                $itData['bank_confirmation_id'] = $newConfirmation->id;
                BankConfirmationItem::create($itData);
            }

            return $this->created(
                $newConfirmation->load(['mitra', 'creator', 'items']),
                "Revisi versi {$nextVersion} berhasil dibuat."
            );
        });
    }

    /**
     * Duplicate confirmation as new record
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $original = BankConfirmation::with('items')->find($id);

        if (!$original) {
            return $this->notFound('Dokumen sumber tidak ditemukan.');
        }

        return DB::transaction(function () use ($original, $request) {
            $year = (int) date('Y');
            $count = BankConfirmation::whereYear('created_at', $year)->count();
            $nextSeq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $newNumber = "KB/{$nextSeq}/KAP/{$year}";

            $cloneData = $original->toArray();
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at'], $cloneData['deleted_at'], $cloneData['items']);

            $cloneData['parent_id'] = null;
            $cloneData['version'] = 1;
            $cloneData['confirmation_number'] = $newNumber;
            $cloneData['created_by'] = $request->user() ? $request->user()->id : null;
            $cloneData['status'] = 'draft';

            $newConfirmation = BankConfirmation::create($cloneData);

            foreach ($original->items as $it) {
                $itData = $it->toArray();
                unset($itData['id'], $itData['created_at'], $itData['updated_at']);
                $itData['bank_confirmation_id'] = $newConfirmation->id;
                BankConfirmationItem::create($itData);
            }

            return $this->created(
                $newConfirmation->load(['mitra', 'creator', 'items']),
                'Dokumen konfirmasi bank berhasil diduplikasi.'
            );
        });
    }

    /**
     * Delete Bank Confirmation (Soft Delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $confirmation = BankConfirmation::find($id);

        if (!$confirmation) {
            return $this->notFound('Dokumen konfirmasi bank tidak ditemukan.');
        }

        $confirmation->delete();

        return $this->success(null, 'Dokumen konfirmasi bank berhasil dihapus.');
    }

    /**
     * Get list of banks associated with a client (mitra)
     */
    public function getClientBanks(int $mitraId): JsonResponse
    {
        $banks = ClientBank::where('mitra_id', $mitraId)->orderBy('bank_name')->get();
        return $this->success($banks, 'Daftar bank klien berhasil diambil.');
    }
}
