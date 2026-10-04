<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Mitra;
use App\Support\Terbilang;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use ApiResponse;

    /**
     * List all invoices with search, filter by mitra, status & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['items', 'mitra', 'creator', 'parent']);

        if ($mitraId = $request->input('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('signatory_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($invoices, 'Daftar invoice berhasil diambil.');
    }

    /**
     * Generate sequential invoice number preview
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $year = (int) date('Y');
        $count = Invoice::whereYear('created_at', $year)->count();
        $nextSeq = $count + 1;
        $paddedSeq = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

        $monthRoman = match ((int) date('n')) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => 'I'
        };

        $format = "{$paddedSeq}/INV/SSR/{$monthRoman}/{$year}";

        return $this->success([
            'sequence' => $nextSeq,
            'padded_sequence' => $paddedSeq,
            'prefix' => 'INV',
            'code' => 'SSR',
            'roman_month' => $monthRoman,
            'year' => $year,
            'formatted_number' => $format,
        ], 'Nomor invoice berikutnya berhasil digenerate.');
    }

    /**
     * Store newly created invoice
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100|unique:invoices,invoice_number',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'client_address' => 'nullable|string',
            'client_city' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'tax_pph23_enabled' => 'nullable|boolean',
            'tax_pph23_percent' => 'nullable|numeric|min:0',
            'total_label' => 'nullable|string|max:255',
            'signatory_city' => 'nullable|string|max:100',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signatory_signature_url' => 'nullable|string',
            'footer_nb' => 'nullable|array',
            'status' => 'nullable|string|in:draft,final,printed,paid',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Hitung subtotal dari baris items
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += (float) $item['amount'];
            }

            $taxEnabled = $validated['tax_pph23_enabled'] ?? true;
            $taxPercent = isset($validated['tax_pph23_percent']) ? (float) $validated['tax_pph23_percent'] : 2.0;
            $taxAmount = $taxEnabled ? ($subtotal * ($taxPercent / 100)) : 0;
            $totalAmount = max(0, $subtotal - $taxAmount);

            $terbilangText = Terbilang::convert($totalAmount);

            $defaultNb = [
                "Invoice ini juga sebagai kwitansi jika dana telah masuk ke rekening kami",
                "Harap transfer ke rekening Mandiri a.n Rizki Syahputra: 900.001.7917882",
                "NPWP: 42.416.854.0-111.000"
            ];

            $invoice = Invoice::create([
                'invoice_number' => $validated['invoice_number'],
                'version' => 1,
                'mitra_id' => $validated['mitra_id'] ?? null,
                'client_name' => $validated['client_name'],
                'client_address' => $validated['client_address'] ?? null,
                'client_city' => $validated['client_city'] ?? 'Medan',
                'invoice_date' => $validated['invoice_date'] ?? date('Y-m-d'),
                'subtotal' => $subtotal,
                'tax_pph23_enabled' => $taxEnabled,
                'tax_pph23_percent' => $taxPercent,
                'tax_pph23_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'terbilang' => $terbilangText,
                'total_label' => $validated['total_label'] ?? 'Sisa biaya audit tahun {tahun}',
                'signatory_city' => $validated['signatory_city'] ?? 'Medan',
                'signatory_name' => $validated['signatory_name'] ?? 'Rizki Syahputra, SE, M.Si, CPA',
                'signatory_title' => $validated['signatory_title'] ?? 'Partner',
                'signatory_signature_url' => $validated['signatory_signature_url'] ?? null,
                'footer_nb' => $validated['footer_nb'] ?? $defaultNb,
                'status' => $validated['status'] ?? 'final',
                'created_by' => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_order' => $index + 1,
                    'description' => $item['description'],
                    'amount' => (float) $item['amount'],
                ]);
            }

            $invoice->load(['items', 'mitra', 'creator']);

            return $this->success($invoice, 'Invoice berhasil dibuat dan disimpan.', 201);
        });
    }

    /**
     * Show single invoice
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with(['items', 'mitra', 'creator', 'parent', 'revisions.items'])->find($id);

        if (!$invoice) {
            return $this->error('Invoice tidak ditemukan.', null, 404);
        }

        return $this->success($invoice, 'Detail invoice berhasil diambil.');
    }

    /**
     * Update existing invoice
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return $this->error('Invoice tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'invoice_number' => "nullable|string|max:100|unique:invoices,invoice_number,{$id}",
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'nullable|string|max:255',
            'client_address' => 'nullable|string',
            'client_city' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'tax_pph23_enabled' => 'nullable|boolean',
            'tax_pph23_percent' => 'nullable|numeric|min:0',
            'total_label' => 'nullable|string|max:255',
            'signatory_city' => 'nullable|string|max:100',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signatory_signature_url' => 'nullable|string',
            'footer_nb' => 'nullable|array',
            'status' => 'nullable|string|in:draft,final,printed,paid',
            'items' => 'nullable|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($invoice, $validated) {
            if (isset($validated['items'])) {
                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal += (float) $item['amount'];
                }
                $invoice->subtotal = $subtotal;

                $taxEnabled = $validated['tax_pph23_enabled'] ?? $invoice->tax_pph23_enabled;
                $taxPercent = isset($validated['tax_pph23_percent']) ? (float) $validated['tax_pph23_percent'] : $invoice->tax_pph23_percent;
                $taxAmount = $taxEnabled ? ($subtotal * ($taxPercent / 100)) : 0;
                $totalAmount = max(0, $subtotal - $taxAmount);

                $invoice->tax_pph23_enabled = $taxEnabled;
                $invoice->tax_pph23_percent = $taxPercent;
                $invoice->tax_pph23_amount = $taxAmount;
                $invoice->total_amount = $totalAmount;
                $invoice->terbilang = Terbilang::convert($totalAmount);

                // Re-create items
                $invoice->items()->delete();
                foreach ($validated['items'] as $index => $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_order' => $index + 1,
                        'description' => $item['description'],
                        'amount' => (float) $item['amount'],
                    ]);
                }
            }

            if (isset($validated['invoice_number'])) $invoice->invoice_number = $validated['invoice_number'];
            if (isset($validated['mitra_id'])) $invoice->mitra_id = $validated['mitra_id'];
            if (isset($validated['client_name'])) $invoice->client_name = $validated['client_name'];
            if (isset($validated['client_address'])) $invoice->client_address = $validated['client_address'];
            if (isset($validated['client_city'])) $invoice->client_city = $validated['client_city'];
            if (isset($validated['invoice_date'])) $invoice->invoice_date = $validated['invoice_date'];
            if (isset($validated['total_label'])) $invoice->total_label = $validated['total_label'];
            if (isset($validated['signatory_city'])) $invoice->signatory_city = $validated['signatory_city'];
            if (isset($validated['signatory_name'])) $invoice->signatory_name = $validated['signatory_name'];
            if (isset($validated['signatory_title'])) $invoice->signatory_title = $validated['signatory_title'];
            if (isset($validated['signatory_signature_url'])) $invoice->signatory_signature_url = $validated['signatory_signature_url'];
            if (isset($validated['footer_nb'])) $invoice->footer_nb = $validated['footer_nb'];
            if (isset($validated['status'])) $invoice->status = $validated['status'];

            $invoice->save();
            $invoice->load(['items', 'mitra', 'creator']);

            return $this->success($invoice, 'Invoice berhasil diperbarui.');
        });
    }

    /**
     * Create Revision (Rev.1, Rev.2, etc.) without overwriting previous versions
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $original = Invoice::with('items')->find($id);

        if (!$original) {
            return $this->error('Invoice asal tidak ditemukan.', null, 404);
        }

        $rootId = $original->parent_id ?: $original->id;
        $maxVersion = Invoice::where('id', $rootId)->orWhere('parent_id', $rootId)->max('version') ?? 1;
        $nextVersion = $maxVersion + 1;
        $revNumber = "{$original->invoice_number}-Rev.{$nextVersion}";

        return DB::transaction(function () use ($original, $rootId, $nextVersion, $revNumber, $request) {
            $newInvoice = $original->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $newInvoice->invoice_number = $revNumber;
            $newInvoice->parent_id = $rootId;
            $newInvoice->version = $nextVersion;
            $newInvoice->status = 'draft';
            $newInvoice->created_by = $request->user()?->id;
            $newInvoice->save();

            foreach ($original->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $newInvoice->id,
                    'item_order' => $item->item_order,
                    'description' => $item->description,
                    'amount' => $item->amount,
                ]);
            }

            $newInvoice->load(['items', 'mitra', 'creator', 'parent']);

            return $this->success($newInvoice, "Revisi versi {$nextVersion} (Rev." . ($nextVersion - 1) . ") berhasil dibuat.", 201);
        });
    }

    /**
     * Duplicate invoice
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = Invoice::with('items')->find($id);

        if (!$source) {
            return $this->error('Invoice asal tidak ditemukan.', null, 404);
        }

        $nextSeq = Invoice::whereYear('created_at', date('Y'))->count() + 1;
        $paddedSeq = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
        $monthRoman = match ((int) date('n')) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => 'I'
        };
        $year = date('Y');
        $newNumber = "{$paddedSeq}/INV/SSR/{$monthRoman}/{$year}";

        return DB::transaction(function () use ($source, $newNumber, $request) {
            $newInvoice = $source->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $newInvoice->invoice_number = $newNumber;
            $newInvoice->parent_id = null;
            $newInvoice->version = 1;
            $newInvoice->invoice_date = date('Y-m-d');
            $newInvoice->status = 'draft';
            $newInvoice->created_by = $request->user()?->id;
            $newInvoice->save();

            foreach ($source->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $newInvoice->id,
                    'item_order' => $item->item_order,
                    'description' => $item->description,
                    'amount' => $item->amount,
                ]);
            }

            $newInvoice->load(['items', 'mitra', 'creator']);

            return $this->success($newInvoice, 'Invoice berhasil diduplikasi.', 201);
        });
    }

    /**
     * Delete invoice (Soft Delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return $this->error('Invoice tidak ditemukan.', null, 404);
        }

        $invoice->delete();

        return $this->success(null, 'Invoice berhasil dihapus.');
    }
}
