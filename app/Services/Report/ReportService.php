<?php

namespace App\Services\Report;

use App\Models\Document;
use App\Models\Letter;
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

        // 1. Fetch all letters
        $letterQuery = Letter::with(['mitra', 'category', 'document']);

        if ($search) {
            $letterQuery->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('letter_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($categoryId) {
            $letterQuery->where('category_id', $categoryId);
        }
        if ($mitraId) {
            $letterQuery->where('mitra_id', $mitraId);
        }

        if ($tabCategory && $tabCategory !== 'all') {
            $letterQuery->where(function ($q) use ($tabCategory) {
                if ($tabCategory === 'Surat Masuk') {
                    $q->where('type', 'incoming');
                } elseif ($tabCategory === 'Surat Keluar') {
                    $q->where('type', 'outgoing');
                } else {
                    $q->whereHas('category', function ($cq) use ($tabCategory) {
                        $cq->where('name', 'like', "%{$tabCategory}%");
                    });
                }
            });
        }

        $letters = $letterQuery->latest()->get();

        // 2. Also fetch documents
        $docQuery = Document::with(['mitra', 'category', 'uploader']);
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
        if ($tabCategory && $tabCategory !== 'all') {
            $docQuery->whereHas('category', function ($q) use ($tabCategory) {
                $q->where('name', 'like', "%{$tabCategory}%");
            });
        }
        $docs = $docQuery->latest()->get();

        // 3. Map into unified list
        $combined = collect();

        foreach ($letters as $let) {
            $typeName = $let->type === 'incoming' ? 'Surat Masuk' : ($let->type === 'outgoing' ? 'Surat Keluar' : 'Surat');
            $catName = $let->category ? $let->category->name : $typeName;

            $combined->push([
                'id' => 'L-' . $let->id,
                'doc_code' => $let->letter_number ?: ('SURAT-' . str_pad($let->id, 4, '0', STR_PAD_LEFT)),
                'file_name' => $let->subject . ($let->document ? ' (' . $let->document->file_name . ')' : '.pdf'),
                'extension' => $let->document ? $let->document->extension : 'pdf',
                'category' => [
                    'id' => $let->category_id ?: 3,
                    'name' => $catName,
                ],
                'mitra' => $let->mitra ? [
                    'id' => $let->mitra->id,
                    'name' => $let->mitra->name,
                    'code' => $let->mitra->code,
                    'company_name' => $let->mitra->company_name,
                ] : null,
                'created_at' => ($let->letter_date ?: $let->created_at)->toISOString(),
                'file_size' => $let->document ? $let->document->file_size : 524288,
                'status' => 'Aktif',
            ]);
        }

        foreach ($docs as $doc) {
            $combined->push([
                'id' => 'D-' . $doc->id,
                'doc_code' => 'DOC-' . now()->format('ym') . '-' . str_pad($doc->id, 4, '0', STR_PAD_LEFT),
                'file_name' => $doc->file_name,
                'extension' => $doc->extension ?: 'pdf',
                'category' => [
                    'id' => $doc->category_id ?: 1,
                    'name' => $doc->category ? $doc->category->name : 'Surat Kontrak',
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

        $totalCombined = $combined->count();

        // 4. Calculate 5 Surat Category Counts
        $suratMasukCount = $combined->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'masuk'))->count();
        $suratKeluarCount = $combined->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'keluar'))->count();
        $suratKeteranganCount = $combined->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'keterangan'))->count();
        $suratPenawaranCount = $combined->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'penawaran'))->count();
        $suratKontrakCount = $combined->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'kontrak'))->count();

        $stats = [
            ['key' => 'surat_masuk', 'label' => 'Surat Masuk', 'count' => $suratMasukCount, 'percentageChange' => 8.5, 'iconType' => 'inbox'],
            ['key' => 'surat_keluar', 'label' => 'Surat Keluar', 'count' => $suratKeluarCount, 'percentageChange' => 6.2, 'iconType' => 'send'],
            ['key' => 'surat_keterangan', 'label' => 'Surat Keterangan', 'count' => $suratKeteranganCount, 'percentageChange' => 9.1, 'iconType' => 'file_check'],
            ['key' => 'surat_penawaran', 'label' => 'Surat Penawaran Audit', 'count' => $suratPenawaranCount, 'percentageChange' => 4.3, 'iconType' => 'file_text'],
            ['key' => 'surat_kontrak', 'label' => 'Surat Kontrak', 'count' => $suratKontrakCount, 'percentageChange' => 7.0, 'iconType' => 'signature'],
        ];

        // 5. Calculate breakdown for Donut Chart
        $grouped = $combined->groupBy(fn($i) => $i['category']['name']);
        $colors = ['#10b981', '#2563eb', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899'];
        $breakdown = [];
        $i = 0;

        foreach ($grouped as $catName => $grpItems) {
            $cnt = $grpItems->count();
            $pct = $totalCombined > 0 ? round(($cnt / $totalCombined) * 100) : 0;
            $breakdown[] = [
                'name' => $catName,
                'count' => $cnt,
                'percentage' => $pct,
                'color' => $colors[$i % count($colors)],
            ];
            $i++;
        }

        if (empty($breakdown)) {
            $breakdown = [
                ['name' => 'Surat Masuk', 'percentage' => 0, 'count' => 0, 'color' => '#10b981'],
                ['name' => 'Surat Keluar', 'percentage' => 0, 'count' => 0, 'color' => '#2563eb'],
                ['name' => 'Surat Keterangan', 'percentage' => 0, 'count' => 0, 'color' => '#f59e0b'],
                ['name' => 'Surat Penawaran Audit', 'percentage' => 0, 'count' => 0, 'color' => '#06b6d4'],
                ['name' => 'Surat Kontrak', 'percentage' => 0, 'count' => 0, 'color' => '#8b5cf6'],
            ];
        }

        // 6. Manual pagination
        $paginatedData = $combined->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'total' => $totalCombined,
            'stats' => $stats,
            'breakdown' => $breakdown,
            'documents' => [
                'data' => $paginatedData,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCombined,
                'last_page' => (int) max(1, ceil($totalCombined / $perPage)),
            ],
        ];
    }

    public function getMitraReport(): array
    {
        return Mitra::withCount(['documents', 'letters'])->get()->toArray();
    }

    public function getStorageReport(): array
    {
        $totalBytes = Document::sum('file_size');

        $byExtension = Document::select('extension', DB::raw('count(*) as count'), DB::raw('sum(file_size) as total_size'))
            ->groupBy('extension')
            ->get();

        return [
            'total_bytes' => (int) $totalBytes,
            'by_extension' => $byExtension,
        ];
    }
}
