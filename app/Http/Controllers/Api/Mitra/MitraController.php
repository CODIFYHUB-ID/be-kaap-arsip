<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Services\Mitra\MitraService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MitraController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MitraService $mitraService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);
        $perPage = (int) $request->get('per_page', 20);

        $mitras = $this->mitraService->getPaginated($filters, $perPage, $request->user());
        return $this->paginated($mitras, 'Daftar mitra berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:mitras,code',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'bidang_usaha' => 'nullable|string|max:255',
            'direksi' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $mitra = $this->mitraService->create($validated, $request->user());
        return $this->success($mitra, 'Mitra berhasil ditambahkan.', 201);
    }

    public function show(Request $request, Mitra $mitra): JsonResponse
    {
        $mitra->loadCount(['documents'])->loadSum('documents as total_size', 'file_size');
        return $this->success($mitra->load(['documents', 'user:id,name,email,status,last_login_at', 'creator:id,name']), 'Detail mitra berhasil diambil.');
    }

    public function update(Request $request, Mitra $mitra): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:mitras,code,' . $mitra->id,
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'bidang_usaha' => 'nullable|string|max:255',
            'direksi' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $updated = $this->mitraService->update($mitra, $validated, $request->user());
        return $this->success($updated, 'Data mitra berhasil diperbarui.');
    }

    public function destroy(Request $request, Mitra $mitra): JsonResponse
    {
        $this->mitraService->delete($mitra, $request->user());
        return $this->success(null, 'Mitra berhasil dihapus.');
    }

    public function documents(Request $request, Mitra $mitra): JsonResponse
    {
        $query = $mitra->documents()->with(['category', 'uploader'])->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tahun_berkas') && $request->get('tahun_berkas') !== 'all') {
            $year = $request->get('tahun_berkas');
            $query->where(function ($q) use ($year) {
                $q->where('tahun_berkas', $year)
                  ->orWhereYear('tanggal_dokumen', $year)
                  ->orWhere(function ($sub) use ($year) {
                      $sub->whereNull('tahun_berkas')
                          ->whereNull('tanggal_dokumen')
                          ->whereYear('created_at', $year);
                  });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = (int) $request->get('per_page', 20);
            $paginated = $query->paginate($perPage);
            return $this->paginated($paginated, 'Dokumen milik mitra berhasil diambil.');
        }

        $documents = $query->get();
        return $this->success($documents, 'Dokumen milik mitra berhasil diambil.');
    }

    public function generatedSourceDocuments(Request $request, Mitra $mitra): JsonResponse
    {
        $year = $request->get('tahun_berkas');
        $items = collect();

        // 1. Surat Tugas (GeneratedLetter)
        $lettersQuery = \App\Models\GeneratedLetter::where('mitra_id', $mitra->id);
        if ($year && $year !== 'all') {
            $lettersQuery->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhere('period_end_date', 'like', "%{$year}%")
                  ->orWhereYear('created_at', $year);
            });
        }
        foreach ($lettersQuery->orderByDesc('id')->get() as $item) {
            $items->push([
                'id' => $item->id,
                'source_type' => 'surat_tugas',
                'source_title' => 'Surat Tugas',
                'number' => $item->letter_number,
                'title' => 'Surat Tugas - ' . ($item->audit_type ?: 'Pemeriksaan Audit') . ' (' . $item->letter_number . ')',
                'date' => $item->letter_date ? $item->letter_date->format('Y-m-d') : $item->created_at->format('Y-m-d'),
                'target_category' => 'Surat Tugas',
                'url' => '/pembuatan-berkas/surat-tugas/' . $item->id,
            ]);
        }

        // 2. Kwitansi / Invoices
        $invoicesQuery = \App\Models\Invoice::where('mitra_id', $mitra->id);
        if ($year && $year !== 'all') {
            $invoicesQuery->where(function ($q) use ($year) {
                $q->whereYear('invoice_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }
        foreach ($invoicesQuery->orderByDesc('id')->get() as $item) {
            $items->push([
                'id' => $item->id,
                'source_type' => 'kwitansi',
                'source_title' => 'Kwitansi',
                'number' => $item->invoice_number,
                'title' => 'Kwitansi / Invoice (' . $item->invoice_number . ') - Rp ' . number_format($item->total_amount, 0, ',', '.'),
                'date' => $item->invoice_date ? $item->invoice_date->format('Y-m-d') : $item->created_at->format('Y-m-d'),
                'target_category' => 'Kwitansi',
                'url' => '/pembuatan-berkas/kwitansi/' . $item->id,
            ]);
        }

        // 3. Surat Keluar (Penawaran & Keterangan)
        $outgoingQuery = \App\Models\OutgoingLetter::where('mitra_id', $mitra->id);
        if ($year && $year !== 'all') {
            $outgoingQuery->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhere('fiscal_year', $year)
                  ->orWhereYear('created_at', $year);
            });
        }
        foreach ($outgoingQuery->orderByDesc('id')->get() as $item) {
            $cat = $item->letter_type === 'penawaran' ? 'Surat Penawaran' : 'Surat Keterangan';
            $items->push([
                'id' => $item->id,
                'source_type' => 'surat_keluar_' . $item->letter_type,
                'source_title' => $cat,
                'number' => $item->letter_number,
                'title' => $cat . ' (' . $item->letter_number . ')' . ($item->subject ? ' - ' . $item->subject : ''),
                'date' => $item->letter_date ? $item->letter_date->format('Y-m-d') : $item->created_at->format('Y-m-d'),
                'target_category' => $cat,
                'url' => '/pembuatan-berkas/surat-keluar/' . $item->id,
            ]);
        }

        // 4. Konfirmasi Bank
        $bankQuery = \App\Models\BankConfirmation::where('mitra_id', $mitra->id);
        if ($year && $year !== 'all') {
            $bankQuery->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhereYear('balance_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }
        foreach ($bankQuery->orderByDesc('id')->get() as $item) {
            $items->push([
                'id' => $item->id,
                'source_type' => 'konfirmasi_bank',
                'source_title' => 'Konfirmasi Bank',
                'number' => $item->confirmation_number,
                'title' => 'Konfirmasi Bank ' . ($item->bank_name ? '(' . $item->bank_name . ') ' : '') . '[' . $item->confirmation_number . ']',
                'date' => $item->letter_date ? $item->letter_date->format('Y-m-d') : $item->created_at->format('Y-m-d'),
                'target_category' => 'Surat Konfirmasi',
                'url' => '/pembuatan-berkas/konfirmasi-bank/' . $item->id,
            ]);
        }

        // 5. Konfirmasi Utang & Piutang
        $debtorQuery = \App\Models\DebtorCreditorConfirmation::where('mitra_id', $mitra->id);
        if ($year && $year !== 'all') {
            $debtorQuery->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhereYear('balance_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }
        foreach ($debtorQuery->orderByDesc('id')->get() as $item) {
            $typeLabel = $item->confirmation_type === 'payable' ? 'Konfirmasi Utang' : 'Konfirmasi Piutang';
            $items->push([
                'id' => $item->id,
                'source_type' => 'konfirmasi_utang_piutang',
                'source_title' => $typeLabel,
                'number' => $item->package_number,
                'title' => $typeLabel . ' [' . $item->package_number . ']',
                'date' => $item->letter_date ? $item->letter_date->format('Y-m-d') : $item->created_at->format('Y-m-d'),
                'target_category' => 'Surat Konfirmasi',
                'url' => '/pembuatan-berkas/konfirmasi-utang-piutang/' . $item->id,
            ]);
        }

        return $this->success($items->values(), 'Dokumen hasil pembuatan berkas berhasil diambil.');
    }
}
