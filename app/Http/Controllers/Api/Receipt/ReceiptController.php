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

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'mitra_id', 'year', 'date_from', 'date_to']);
        $perPage = (int) $request->get('per_page', 20);

        $receipts = $this->receiptService->getPaginated($filters, $perPage);
        return $this->paginated($receipts, 'Daftar kwitansi berhasil diambil.');
    }

    public function stats(): JsonResponse
    {
        $stats = $this->receiptService->getStats();
        return $this->success($stats, 'Statistik kwitansi berhasil diambil.');
    }

    public function generateNumber(): JsonResponse
    {
        $number = $this->receiptService->generateReceiptNumber();
        return $this->success(['receipt_number' => $number], 'Nomor kwitansi otomatis berhasil di-generate.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'receipt_number' => 'nullable|string|max:100|unique:receipts,receipt_number',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
            'file_key' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'extension' => 'nullable|string|max:20',
        ]);

        $receipt = $this->receiptService->create(
            data: $validated,
            userId: $request->user()->id,
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        return $this->success($receipt, 'Kwitansi berhasil ditambahkan.', 201);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        return $this->success($receipt->load(['mitra', 'creator']), 'Detail kwitansi berhasil diambil.');
    }

    public function update(Request $request, Receipt $receipt): JsonResponse
    {
        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'receipt_number' => 'required|string|max:100|unique:receipts,receipt_number,' . $receipt->id,
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
            'file_key' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'extension' => 'nullable|string|max:20',
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
