<?php

namespace App\Http\Controllers\Api\OutgoingLetter;

use App\Http\Controllers\Controller;
use App\Models\OutgoingLetter;
use App\Models\Mitra;
use App\Support\Terbilang;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutgoingLetterController extends Controller
{
    use ApiResponse;

    /**
     * List all outgoing letters with filter by letter_type, mitra_id, search & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = OutgoingLetter::query()->with(['mitra', 'creator', 'parent']);

        if ($type = $request->input('letter_type')) {
            if ($type !== 'all') {
                $query->where('letter_type', $type);
            }
        }

        if ($mitraId = $request->input('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('letter_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('signatory_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $letters = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($letters, 'Daftar surat keluar berhasil diambil.');
    }

    /**
     * Generate sequential letter number per type
     * Format: {No Urut}/{Kode}/{Bulan Romawi}/{Tahun}. Contoh: 104/SSR-RS/VI/2026
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $type = $request->input('letter_type', 'penawaran');
        $year = (int) date('Y');

        // Saran auto-increment dari nomor terakhir per jenis surat
        $count = OutgoingLetter::where('letter_type', $type)->whereYear('created_at', $year)->count();
        // Base seed default nomor urut yang lazim jika belum ada record
        $baseSeq = $type === 'penawaran' ? 104 : 182;
        $nextSeq = $count > 0 ? ($baseSeq + $count) : $baseSeq;
        $paddedSeq = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

        $monthRoman = match ((int) date('n')) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => 'I'
        };

        $code = 'SSR-RS';
        $format = "{$paddedSeq}/{$code}/{$monthRoman}/{$year}";

        return $this->success([
            'sequence' => $nextSeq,
            'padded_sequence' => $paddedSeq,
            'code' => $code,
            'roman_month' => $monthRoman,
            'year' => $year,
            'formatted_number' => $format,
        ], 'Nomor surat keluar berikutnya berhasil digenerate.');
    }

    /**
     * Store newly created letter
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'letter_type' => 'required|string|in:penawaran,keterangan',
            'letter_number' => 'required|string|max:100|unique:outgoing_letters,letter_number',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_legal_entity' => 'nullable|string|max:50',
            'client_address_street' => 'nullable|string',
            'client_address_kelurahan' => 'nullable|string',
            'client_address_kecamatan' => 'nullable|string',
            'client_address_city' => 'nullable|string|max:100',
            'client_address_province' => 'nullable|string|max:100',
            'client_address_postal_code' => 'nullable|string|max:20',
            'client_address_full' => 'nullable|string',
            'letter_date' => 'nullable|date',
            'city' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'audit_type' => 'nullable|string|max:255',
            'fiscal_year_end_date' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'tax_note' => 'nullable|string|max:255',
            'tax_note_enabled' => 'nullable|boolean',
            'salutation' => 'nullable|string|max:50',
            'fiscal_year' => 'nullable|string|max:20',
            'target_completion_date' => 'nullable|string|max:255',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,final,printed',
            'update_master_client' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : 0;
            $terbilang = $amount > 0 ? Terbilang::convert($amount) : null;

            // Optional: jika ada opsi eksplisit "Perbarui data klien"
            if (!empty($validated['update_master_client']) && !empty($validated['mitra_id'])) {
                $mitra = Mitra::find($validated['mitra_id']);
                if ($mitra) {
                    $mitra->update([
                        'company_name' => $validated['client_name'],
                        'address' => $validated['client_address_full'] ?? $mitra->address,
                    ]);
                }
            }

            $letter = OutgoingLetter::create([
                'letter_type' => $validated['letter_type'],
                'letter_number' => $validated['letter_number'],
                'parent_id' => null,
                'version' => 1,
                'mitra_id' => $validated['mitra_id'] ?? null,
                'client_name' => $validated['client_name'],
                'client_legal_entity' => $validated['client_legal_entity'] ?? 'PT',
                'client_address_street' => $validated['client_address_street'] ?? null,
                'client_address_kelurahan' => $validated['client_address_kelurahan'] ?? null,
                'client_address_kecamatan' => $validated['client_address_kecamatan'] ?? null,
                'client_address_city' => $validated['client_address_city'] ?? 'Medan',
                'client_address_province' => $validated['client_address_province'] ?? 'Sumatera Utara',
                'client_address_postal_code' => $validated['client_address_postal_code'] ?? null,
                'client_address_full' => $validated['client_address_full'] ?? null,
                'letter_date' => $validated['letter_date'] ?? date('Y-m-d'),
                'city' => $validated['city'] ?? 'Medan',
                'subject' => $validated['subject'] ?? ($validated['letter_type'] === 'penawaran' ? 'Penawaran Audit' : 'Surat Keterangan'),
                'audit_type' => $validated['audit_type'] ?? 'Audit Umum (general audit)',
                'fiscal_year_end_date' => $validated['fiscal_year_end_date'] ?? '31 Desember 2025',
                'amount' => $amount,
                'terbilang' => $terbilang,
                'tax_note' => $validated['tax_note'] ?? 'Termasuk pajak-pajak yang berlaku',
                'tax_note_enabled' => $validated['tax_note_enabled'] ?? true,
                'salutation' => $validated['salutation'] ?? 'Bapak',
                'fiscal_year' => $validated['fiscal_year'] ?? '2025',
                'target_completion_date' => $validated['target_completion_date'] ?? '17 Oktober 2026',
                'signatory_name' => $validated['signatory_name'] ?? 'Rizki Syahputra, SE, M.Si, CPA',
                'signatory_title' => $validated['signatory_title'] ?? 'Partner',
                'signature_stamp_url' => $validated['signature_stamp_url'] ?? null,
                'show_signature_stamp' => $validated['show_signature_stamp'] ?? true,
                'signature_stamp_width' => $validated['signature_stamp_width'] ?? 140,
                'status' => $validated['status'] ?? 'final',
                'created_by' => $request->user()?->id,
            ]);

            $letter->load(['mitra', 'creator']);

            return $this->success($letter, 'Surat keluar berhasil dibuat dan disimpan.', 201);
        });
    }

    /**
     * Show single outgoing letter
     */
    public function show(int $id): JsonResponse
    {
        $letter = OutgoingLetter::with(['mitra', 'creator', 'parent', 'revisions'])->find($id);

        if (!$letter) {
            return $this->error('Surat keluar tidak ditemukan.', null, 404);
        }

        return $this->success($letter, 'Detail surat keluar berhasil diambil.');
    }

    /**
     * Update outgoing letter
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $letter = OutgoingLetter::find($id);

        if (!$letter) {
            return $this->error('Surat keluar tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'letter_number' => "nullable|string|max:100|unique:outgoing_letters,letter_number,{$id}",
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'nullable|string|max:255',
            'client_legal_entity' => 'nullable|string|max:50',
            'client_address_street' => 'nullable|string',
            'client_address_kelurahan' => 'nullable|string',
            'client_address_kecamatan' => 'nullable|string',
            'client_address_city' => 'nullable|string|max:100',
            'client_address_province' => 'nullable|string|max:100',
            'client_address_postal_code' => 'nullable|string|max:20',
            'client_address_full' => 'nullable|string',
            'letter_date' => 'nullable|date',
            'city' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'audit_type' => 'nullable|string|max:255',
            'fiscal_year_end_date' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'tax_note' => 'nullable|string|max:255',
            'tax_note_enabled' => 'nullable|boolean',
            'salutation' => 'nullable|string|max:50',
            'fiscal_year' => 'nullable|string|max:20',
            'target_completion_date' => 'nullable|string|max:255',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signature_stamp_url' => 'nullable|string',
            'show_signature_stamp' => 'nullable|boolean',
            'signature_stamp_width' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,final,printed',
            'update_master_client' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($letter, $validated) {
            if (isset($validated['amount'])) {
                $letter->amount = (float) $validated['amount'];
                $letter->terbilang = $letter->amount > 0 ? Terbilang::convert($letter->amount) : null;
            }

            if (!empty($validated['update_master_client']) && !empty($validated['mitra_id'])) {
                $mitra = Mitra::find($validated['mitra_id']);
                if ($mitra) {
                    $mitra->update([
                        'company_name' => $validated['client_name'] ?? $mitra->company_name,
                        'address' => $validated['client_address_full'] ?? $mitra->address,
                    ]);
                }
            }

            foreach ($validated as $key => $val) {
                if ($key !== 'amount' && $key !== 'update_master_client' && $val !== null) {
                    $letter->{$key} = $val;
                }
            }

            $letter->save();
            $letter->load(['mitra', 'creator']);

            return $this->success($letter, 'Surat keluar berhasil diperbarui.');
        });
    }

    /**
     * Create Revision (Rev.1, Rev.2, etc.) without overwriting previous versions
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $original = OutgoingLetter::find($id);

        if (!$original) {
            return $this->error('Surat asal tidak ditemukan.', null, 404);
        }

        $rootId = $original->parent_id ?: $original->id;
        $maxVersion = OutgoingLetter::where('id', $rootId)->orWhere('parent_id', $rootId)->max('version') ?? 1;
        $nextVersion = $maxVersion + 1;
        $revNumber = "{$original->letter_number}-Rev.{$nextVersion}";

        return DB::transaction(function () use ($original, $rootId, $nextVersion, $revNumber, $request) {
            $newLetter = $original->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $newLetter->letter_number = $revNumber;
            $newLetter->parent_id = $rootId;
            $newLetter->version = $nextVersion;
            $newLetter->status = 'draft';
            $newLetter->created_by = $request->user()?->id;
            $newLetter->save();

            $newLetter->load(['mitra', 'creator', 'parent']);

            return $this->success($newLetter, "Revisi versi {$nextVersion} (Rev." . ($nextVersion - 1) . ") berhasil dibuat.", 201);
        });
    }

    /**
     * Duplicate letter
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = OutgoingLetter::find($id);

        if (!$source) {
            return $this->error('Surat asal tidak ditemukan.', null, 404);
        }

        $year = (int) date('Y');
        $count = OutgoingLetter::where('letter_type', $source->letter_type)->whereYear('created_at', $year)->count();
        $baseSeq = $source->letter_type === 'penawaran' ? 104 : 182;
        $nextSeq = $baseSeq + $count + 1;
        $paddedSeq = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
        $monthRoman = match ((int) date('n')) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => 'I'
        };
        $newNumber = "{$paddedSeq}/SSR-RS/{$monthRoman}/{$year}";

        return DB::transaction(function () use ($source, $newNumber, $request) {
            $newLetter = $source->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $newLetter->letter_number = $newNumber;
            $newLetter->parent_id = null;
            $newLetter->version = 1;
            $newLetter->letter_date = date('Y-m-d');
            $newLetter->status = 'draft';
            $newLetter->created_by = $request->user()?->id;
            $newLetter->save();

            $newLetter->load(['mitra', 'creator']);

            return $this->success($newLetter, 'Surat keluar berhasil diduplikasi.', 201);
        });
    }

    /**
     * Delete letter (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $letter = OutgoingLetter::find($id);

        if (!$letter) {
            return $this->error('Surat keluar tidak ditemukan.', null, 404);
        }

        $letter->delete();

        return $this->success(null, 'Surat keluar berhasil dihapus.');
    }
}
