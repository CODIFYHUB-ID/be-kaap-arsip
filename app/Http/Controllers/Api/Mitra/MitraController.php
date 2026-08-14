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

        $mitras = $this->mitraService->getPaginated($filters, $perPage);
        return $this->paginated($mitras, 'Daftar mitra berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:mitras,code',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $mitra = $this->mitraService->create($validated);
        return $this->success($mitra, 'Mitra berhasil ditambahkan.', 211);
    }

    public function show(Mitra $mitra): JsonResponse
    {
        return $this->success($mitra->load(['documents', 'letters']), 'Detail mitra berhasil diambil.');
    }

    public function update(Request $request, Mitra $mitra): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:mitras,code,' . $mitra->id,
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $updated = $this->mitraService->update($mitra, $validated);
        return $this->success($updated, 'Data mitra berhasil diperbarui.');
    }

    public function destroy(Mitra $mitra): JsonResponse
    {
        $this->mitraService->delete($mitra);
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

        if ($request->filled('tahun_berkas')) {
            $query->where('tahun_berkas', $request->get('tahun_berkas'));
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
}
