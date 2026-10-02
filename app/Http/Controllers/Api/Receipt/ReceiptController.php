<?php

namespace App\Http\Controllers\Api\Receipt;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Receipt\ReceiptService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReceiptService $receiptService
    ) {}

    protected function checkKwitansiAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (($user->isMitra() || $user->isAuditor()) && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->forbidden('Akses ditolak. Role Anda tidak memiliki izin akses modul kwitansi dan boundary keuangan.');
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $filters = $request->only(['search', 'mitra_id', 'receipt_type', 'year', 'date_from', 'date_to']);
        $perPage = (int) $request->get('per_page', 20);

        $receipts = $this->receiptService->getPaginated($filters, $perPage);
        return $this->paginated($receipts, 'Daftar kwitansi berhasil diambil.');
    }

    public function stats(Request $request): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $stats = $this->receiptService->getStats();
        return $this->success($stats, 'Statistik kwitansi berhasil diambil.');
    }

    public function generateNumber(Request $request): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $number = $this->receiptService->generateReceiptNumber();
        return $this->success(['receipt_number' => $number], 'Nomor kwitansi otomatis berhasil di-generate.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;
        $validated = $request->validate([
            'input_mode' => 'nullable|string|in:manual,upload',
            'mitra_id' => 'nullable|exists:mitras,id',
            'receipt_number' => 'nullable|string|max:100|unique:receipts,receipt_number',
            'receipt_type' => 'nullable|string|in:dp,termin,pelunasan,operasional',
            'payment_method' => 'nullable|string|in:transfer,cash,giro',
            'payer_name' => 'nullable|string|max:255',
            'category_transaction' => 'nullable|string|max:100',
            'bank_account_destination' => 'nullable|string|max:150',
            'amount' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_pph23_percent' => 'nullable|numeric|min:0|max:100',
            'tax_ppn_percent' => 'nullable|numeric|min:0|max:100',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
            'file_key' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'extension' => 'nullable|string|max:20',
            'items' => 'nullable|array',
            'items.*.item_order' => 'nullable|integer',
            'items.*.expense_category' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $receipt = $this->receiptService->create(
            data: $validated,
            userId: $request->user()->id,
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        return $this->success($receipt, 'Kwitansi berhasil ditambahkan.', 201);
    }

    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        return $this->success($receipt->load(['mitra', 'creator', 'items']), 'Detail kwitansi berhasil diambil.');
    }

    public function update(Request $request, Receipt $receipt): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $validated = $request->validate([
            'input_mode' => 'nullable|string|in:manual,upload',
            'mitra_id' => 'nullable|exists:mitras,id',
            'receipt_number' => 'required|string|max:100|unique:receipts,receipt_number,' . $receipt->id,
            'receipt_type' => 'nullable|string|in:dp,termin,pelunasan,operasional',
            'payment_method' => 'nullable|string|in:transfer,cash,giro',
            'payer_name' => 'nullable|string|max:255',
            'category_transaction' => 'nullable|string|max:100',
            'bank_account_destination' => 'nullable|string|max:150',
            'amount' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_pph23_percent' => 'nullable|numeric|min:0|max:100',
            'tax_ppn_percent' => 'nullable|numeric|min:0|max:100',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
            'file_key' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'extension' => 'nullable|string|max:20',
            'items' => 'nullable|array',
            'items.*.item_order' => 'nullable|integer',
            'items.*.expense_category' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $updated = $this->receiptService->update(
            receipt: $receipt,
            data: $validated,
            userId: $request->user()->id,
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        return $this->success($updated, 'Kwitansi berhasil diperbarui.');
    }

    public function destroy(Request $request, Receipt $receipt): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $this->receiptService->delete(
            receipt: $receipt,
            userId: $request->user()->id,
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        return $this->success(null, 'Kwitansi berhasil dipindahkan ke Recycle Bin.');
    }

    public function download(Request $request, Receipt $receipt): JsonResponse
    {
        if ($deny = $this->checkKwitansiAccess($request)) return $deny;

        $url = $this->receiptService->getDownloadUrl(
            receipt: $receipt,
            userId: $request->user()->id,
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        if (! $url) {
            return $this->error('File scan kwitansi tidak ditemukan di R2 storage.', 404);
        }

        return $this->success(['download_url' => $url], 'Presigned download URL berhasil dibuat.');
    }
}
