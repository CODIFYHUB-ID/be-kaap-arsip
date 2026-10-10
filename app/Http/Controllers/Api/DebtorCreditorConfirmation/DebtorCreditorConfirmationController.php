<?php

namespace App\Http\Controllers\Api\DebtorCreditorConfirmation;

use App\Http\Controllers\Controller;
use App\Models\DebtorCreditorConfirmation;
use App\Models\DebtorCreditorRecipient;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtorCreditorConfirmationController extends Controller
{
    use ApiResponse;

    /**
     * List all packages with filter by confirmation_type, mitra_id, search & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = DebtorCreditorConfirmation::query()->with(['mitra', 'creator', 'parent', 'recipients']);

        if ($type = $request->input('confirmation_type')) {
            if ($type !== 'all') {
                $query->where('confirmation_type', $type);
            }
        }

        if ($mitraId = $request->input('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('package_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($clientName = $request->input('client_name')) {
            $query->where('client_name', 'like', "%{$clientName}%");
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $list = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($list, 'Daftar paket konfirmasi utang & piutang berhasil diambil.');
    }

    /**
     * Generate next package number
     * Format: KP/{Tahun}/{Bulan Romawi}/{No Urut}. e.g. KP/2026/VIII/001
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $type = $request->input('confirmation_type', 'piutang_positif');
        $prefix = str_starts_with($type, 'utang') ? 'KU' : 'KP';
        $year = (int) date('Y');
        $month = (int) date('n');

        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];
        $roman = $romanMonths[$month] ?? 'VIII';

        $seq = DebtorCreditorConfirmation::withTrashed()->whereYear('created_at', $year)->count() + 1;

        do {
            $formattedSeq = str_pad($seq, 3, '0', STR_PAD_LEFT);
            $candidateNumber = "{$prefix}/{$year}/{$roman}/{$formattedSeq}";
            $exists = DebtorCreditorConfirmation::withTrashed()->where('package_number', $candidateNumber)->exists();
            if (!$exists) {
                break;
            }
            $seq++;
        } while (true);

        return $this->success([
            'recommended_number' => $candidateNumber,
            'seq_number' => $formattedSeq,
            'year' => (string) $year,
        ], 'Nomor paket konfirmasi berhasil digenerate.');
    }

    /**
     * Get single package with all recipients
     */
    public function show(int $id): JsonResponse
    {
        $item = DebtorCreditorConfirmation::with(['mitra', 'creator', 'parent', 'revisions', 'recipients'])->find($id);

        if (!$item) {
            return $this->notFound('Paket konfirmasi tidak ditemukan.');
        }

        return $this->success($item, 'Detail paket konfirmasi berhasil diambil.');
    }

    /**
     * Store new package with multiple recipients
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_number' => 'required|string|max:255|unique:debtor_creditor_confirmations,package_number',
            'confirmation_type' => 'required|string|in:piutang_positif,utang_positif',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_letterhead_url' => 'nullable|string',
            'client_signature_stamp_url' => 'nullable|string',
            'show_client_stamp' => 'nullable|boolean',
            'letter_city' => 'nullable|string|max:100',
            'letter_date' => 'nullable|date',
            'subject' => 'nullable|string|max:255',
            'balance_date' => 'required|string|max:255',
            'response_deadline_days' => 'nullable|integer|min:1',
            'auditor_pic_name' => 'nullable|string|max:255',
            'auditor_pic_phone' => 'nullable|string|max:255',
            'auditor_correspondence_address' => 'nullable|string',
            'client_signatory_name' => 'nullable|string|max:255',
            'client_signatory_title' => 'nullable|string|max:255',
            'show_cut_line' => 'nullable|boolean',
            'show_note' => 'nullable|boolean',
            'note_text' => 'nullable|string',
            'total_nominal' => 'nullable|numeric',
            'general_ledger_nominal' => 'nullable|numeric',
            'status' => 'nullable|string|in:draft,sent,completed',
            'recipients' => 'required|array|min:1',
            'recipients.*.recipient_name' => 'required|string|max:255',
            'recipients.*.recipient_type' => 'required|string|in:badan_usaha,perorangan',
            'recipients.*.recipient_address' => 'nullable|string',
            'recipients.*.nominal' => 'required|numeric|min:0',
            'recipients.*.signatory_title' => 'nullable|string|max:255',
            'recipients.*.status' => 'nullable|string',
            'recipients.*.difference_notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = $request->user();
            $validated['created_by'] = $user ? $user->id : null;
            $recipientsData = $validated['recipients'];
            unset($validated['recipients']);

            // Hitung total nominal otomatis
            $calcTotal = 0;
            foreach ($recipientsData as $r) {
                $calcTotal += (float) ($r['nominal'] ?? 0);
            }
            $validated['total_nominal'] = $calcTotal;

            $confirmation = DebtorCreditorConfirmation::create($validated);

            foreach ($recipientsData as $idx => $r) {
                DebtorCreditorRecipient::create([
                    'confirmation_id' => $confirmation->id,
                    'order' => $idx + 1,
                    'recipient_name' => $r['recipient_name'],
                    'recipient_type' => $r['recipient_type'],
                    'recipient_address' => $r['recipient_address'] ?? null,
                    'nominal' => $r['nominal'],
                    'signatory_title' => $r['signatory_title'] ?? ($r['recipient_type'] === 'badan_usaha' ? 'Direktur,' : null),
                    'status' => $r['status'] ?? 'draft',
                    'difference_notes' => $r['difference_notes'] ?? null,
                ]);
            }

            return $this->created(
                $confirmation->load(['mitra', 'creator', 'recipients']),
                'Paket konfirmasi piutang/utang berhasil disimpan.'
            );
        });
    }

    /**
     * Update package and its recipients
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $confirmation = DebtorCreditorConfirmation::find($id);

        if (!$confirmation) {
            return $this->notFound('Paket konfirmasi tidak ditemukan.');
        }

        $validated = $request->validate([
            'package_number' => "required|string|max:255|unique:debtor_creditor_confirmations,package_number,{$id}",
            'confirmation_type' => 'required|string|in:piutang_positif,utang_positif',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_letterhead_url' => 'nullable|string',
            'client_signature_stamp_url' => 'nullable|string',
            'show_client_stamp' => 'nullable|boolean',
            'letter_city' => 'nullable|string|max:100',
            'letter_date' => 'nullable|date',
            'subject' => 'nullable|string|max:255',
            'balance_date' => 'required|string|max:255',
            'response_deadline_days' => 'nullable|integer|min:1',
            'auditor_pic_name' => 'nullable|string|max:255',
            'auditor_pic_phone' => 'nullable|string|max:255',
            'auditor_correspondence_address' => 'nullable|string',
            'client_signatory_name' => 'nullable|string|max:255',
            'client_signatory_title' => 'nullable|string|max:255',
            'show_cut_line' => 'nullable|boolean',
            'show_note' => 'nullable|boolean',
            'note_text' => 'nullable|string',
            'total_nominal' => 'nullable|numeric',
            'general_ledger_nominal' => 'nullable|numeric',
            'status' => 'nullable|string|in:draft,sent,completed',
            'recipients' => 'nullable|array|min:1',
            'recipients.*.recipient_name' => 'required|string|max:255',
            'recipients.*.recipient_type' => 'required|string|in:badan_usaha,perorangan',
            'recipients.*.recipient_address' => 'nullable|string',
            'recipients.*.nominal' => 'required|numeric|min:0',
            'recipients.*.signatory_title' => 'nullable|string|max:255',
            'recipients.*.status' => 'nullable|string',
            'recipients.*.difference_notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($confirmation, $validated) {
            $recipientsData = $validated['recipients'] ?? null;
            unset($validated['recipients']);

            if ($recipientsData !== null) {
                $calcTotal = 0;
                foreach ($recipientsData as $r) {
                    $calcTotal += (float) ($r['nominal'] ?? 0);
                }
                $validated['total_nominal'] = $calcTotal;
            }

            $confirmation->update($validated);

            if ($recipientsData !== null) {
                $confirmation->recipients()->delete();
                foreach ($recipientsData as $idx => $r) {
                    DebtorCreditorRecipient::create([
                        'confirmation_id' => $confirmation->id,
                        'order' => $idx + 1,
                        'recipient_name' => $r['recipient_name'],
                        'recipient_type' => $r['recipient_type'],
                        'recipient_address' => $r['recipient_address'] ?? null,
                        'nominal' => $r['nominal'],
                        'signatory_title' => $r['signatory_title'] ?? ($r['recipient_type'] === 'badan_usaha' ? 'Direktur,' : null),
                        'status' => $r['status'] ?? 'draft',
                        'difference_notes' => $r['difference_notes'] ?? null,
                    ]);
                }
            }

            return $this->success(
                $confirmation->load(['mitra', 'creator', 'recipients']),
                'Paket konfirmasi berhasil diperbarui.'
            );
        });
    }

    /**
     * Create revision version of package
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $original = DebtorCreditorConfirmation::with('recipients')->find($id);

        if (!$original) {
            return $this->notFound('Paket sumber tidak ditemukan.');
        }

        return DB::transaction(function () use ($original, $request) {
            $rootParentId = $original->parent_id ?: $original->id;
            $latestVersion = DebtorCreditorConfirmation::where('parent_id', $rootParentId)
                ->orWhere('id', $rootParentId)
                ->max('version');

            $nextVersion = ($latestVersion ?: $original->version) + 1;
            $newNumber = $original->package_number . "-REV{$nextVersion}";

            $suffix = 1;
            while (DebtorCreditorConfirmation::where('package_number', $newNumber)->exists()) {
                $newNumber = $original->package_number . "-REV{$nextVersion}." . $suffix++;
            }

            $cloneData = $original->toArray();
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at'], $cloneData['deleted_at'], $cloneData['recipients']);

            $cloneData['parent_id'] = $rootParentId;
            $cloneData['version'] = $nextVersion;
            $cloneData['package_number'] = $newNumber;
            $cloneData['created_by'] = $request->user() ? $request->user()->id : null;
            $cloneData['status'] = 'draft';

            $newConfirmation = DebtorCreditorConfirmation::create($cloneData);

            foreach ($original->recipients as $r) {
                $rData = $r->toArray();
                unset($rData['id'], $rData['created_at'], $rData['updated_at']);
                $rData['confirmation_id'] = $newConfirmation->id;
                DebtorCreditorRecipient::create($rData);
            }

            return $this->created(
                $newConfirmation->load(['mitra', 'creator', 'recipients']),
                "Revisi versi {$nextVersion} berhasil dibuat."
            );
        });
    }

    /**
     * Duplicate package
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $original = DebtorCreditorConfirmation::with('recipients')->find($id);

        if (!$original) {
            return $this->notFound('Paket sumber tidak ditemukan.');
        }

        return DB::transaction(function () use ($original, $request) {
            $year = (int) date('Y');
            $count = DebtorCreditorConfirmation::whereYear('created_at', $year)->count();
            $nextSeq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $newNumber = "KP/{$year}/VIII/{$nextSeq}";

            $cloneData = $original->toArray();
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at'], $cloneData['deleted_at'], $cloneData['recipients']);

            $cloneData['parent_id'] = null;
            $cloneData['version'] = 1;
            $cloneData['package_number'] = $newNumber;
            $cloneData['created_by'] = $request->user() ? $request->user()->id : null;
            $cloneData['status'] = 'draft';

            $newConfirmation = DebtorCreditorConfirmation::create($cloneData);

            foreach ($original->recipients as $r) {
                $rData = $r->toArray();
                unset($rData['id'], $rData['created_at'], $rData['updated_at']);
                $rData['confirmation_id'] = $newConfirmation->id;
                DebtorCreditorRecipient::create($rData);
            }

            return $this->created(
                $newConfirmation->load(['mitra', 'creator', 'recipients']),
                'Paket konfirmasi berhasil diduplikasi.'
            );
        });
    }

    /**
     * Delete package (Soft Delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $confirmation = DebtorCreditorConfirmation::find($id);

        if (!$confirmation) {
            return $this->notFound('Paket konfirmasi tidak ditemukan.');
        }

        $confirmation->delete();

        return $this->success(null, 'Paket konfirmasi berhasil dihapus.');
    }
}
