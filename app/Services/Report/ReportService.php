<?php

namespace App\Services\Report;

use App\Models\Document;
use App\Models\Mitra;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getDocumentReport(array $filters = []): array
    {
        $search = $filters['search'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $mitraId = $filters['mitra_id'] ?? null;
        $tabCategory = $filters['tab_category'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        // Fetch all documents
        $docQuery = Document::with(['mitra', 'category', 'uploader']);
        if ($startDate) {
            $docQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $docQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($search) {
            $docQuery->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($categoryId) {
            $docQuery->where('category_id', $categoryId);
        }
        if ($mitraId) {
            $docQuery->where('mitra_id', $mitraId);
        }
        $docs = $docQuery->latest()->get();

        // Map into unified list
        $allCollected = collect();

        foreach ($docs as $doc) {
            $catName = $doc->category ? $doc->category->name : 'Dokumen Umum';
            $allCollected->push([
                'id' => 'D-' . $doc->id,
                'doc_code' => 'DOC-' . now()->format('ym') . '-' . str_pad($doc->id, 4, '0', STR_PAD_LEFT),
                'file_name' => $doc->file_name,
                'extension' => $doc->extension ?: 'pdf',
                'category' => [
                    'id' => $doc->category_id ?: 1,
                    'name' => $catName,
                ],
                'mitra' => $doc->mitra ? [
                    'id' => $doc->mitra->id,
                    'name' => $doc->mitra->name,
                    'code' => $doc->mitra->code,
                    'company_name' => $doc->mitra->company_name,
                ] : null,
                'created_at' => $doc->created_at->toISOString(),
                'file_size' => $doc->file_size ?: 1024000,
                'status' => 'Aktif',
            ]);
        }

        $totalCombined = $allCollected->count();

        $stats = [
            ['key' => 'total', 'label' => 'Total Dokumen', 'count' => $totalCombined, 'percentageChange' => 8.5, 'iconType' => 'document'],
            ['key' => 'dokumen', 'label' => 'Kategori Dokumen', 'count' => $totalCombined, 'percentageChange' => 9.1, 'iconType' => 'folder'],
        ];

        // Apply tab filter if active
        $filteredCollection = $allCollected;
        if ($tabCategory && $tabCategory !== 'all') {
            $filteredCollection = $allCollected->filter(function ($item) use ($tabCategory) {
                $cName = strtolower($item['category']['name']);
                $target = strtolower($tabCategory);
                return str_contains($cName, $target);
            })->values();
        }

        // Calculate dynamic breakdown for Donut Chart based on active/filtered data & DB categories
        $colors = ['#10b981', '#2563eb', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899', '#3b82f6'];
        $breakdown = [];
        $i = 0;
        $filteredTotal = $filteredCollection->count();

        $allCategories = \App\Models\Category::all();

        if ($allCategories->isNotEmpty()) {
            foreach ($allCategories as $cat) {
                $cnt = $filteredCollection->filter(function ($item) use ($cat) {
                    return (isset($item['category']['id']) && (int)$item['category']['id'] === (int)$cat->id)
                        || (isset($item['category']['name']) && strtolower($item['category']['name']) === strtolower($cat->name));
                })->count();

                $pct = $filteredTotal > 0 ? round(($cnt / $filteredTotal) * 100) : 0;
                $breakdown[] = [
                    'name' => $cat->name,
                    'count' => $cnt,
                    'percentage' => $pct,
                    'color' => $colors[$i % count($colors)],
                ];
                $i++;
            }
        } else {
            $grouped = $filteredCollection->groupBy(fn($item) => $item['category']['name']);
            foreach ($grouped as $catName => $grpItems) {
                $cnt = $grpItems->count();
                $pct = $filteredTotal > 0 ? round(($cnt / $filteredTotal) * 100) : 0;
                $breakdown[] = [
                    'name' => $catName,
                    'count' => $cnt,
                    'percentage' => $pct,
                    'color' => $colors[$i % count($colors)],
                ];
                $i++;
            }
        }

        // Manual pagination
        $paginatedData = $filteredCollection->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'total' => $filteredTotal,
            'stats' => $stats,
            'breakdown' => $breakdown,
            'documents' => [
                'data' => $paginatedData,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $filteredTotal,
                'last_page' => (int) max(1, ceil($filteredTotal / $perPage)),
            ],
        ];
    }

    public function getMitraReport(array $filters = []): array
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        $query = Mitra::query()->withCount(['documents']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('npwp', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Global stats calculation
        $allMitras = Mitra::withCount(['documents'])->get();
        $totalMitra = $allMitras->count();
        $activeMitra = $allMitras->where('status', 'active')->count();
        $totalDocs = (int) $allMitras->sum('documents_count');
        $totalLetters = 0;
        $totalReceipts = 0;

        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        return [
            'stats' => [
                'total_mitra' => $totalMitra,
                'active_mitra' => $activeMitra,
                'inactive_mitra' => max(0, $totalMitra - $activeMitra),
                'total_documents' => $totalDocs,
                'total_letters' => $totalLetters,
                'total_receipts' => $totalReceipts,
            ],
            'mitras' => [
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    public function getStorageReport(): array
    {
        $totalBytes = Document::sum('file_size');

        $byExtension = Document::select('extension', DB::raw('count(*) as count'), DB::raw('sum(file_size) as total_size'))
            ->groupBy('extension')
            ->get();

        $byCategory = Document::join('categories', 'documents.category_id', '=', 'categories.id')
            ->select('categories.name as category_name', DB::raw('count(documents.id) as count'), DB::raw('sum(documents.file_size) as total_size'))
            ->groupBy('categories.id', 'categories.name')
            ->get();

        return [
            'total_bytes' => (int) $totalBytes,
            'by_extension' => $byExtension,
            'by_category' => $byCategory,
        ];
    }
}
