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

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        // 1. Fetch all letters
        $letterQuery = Letter::with(['mitra', 'category', 'document']);

        if ($startDate) {
            $letterQuery->whereDate('letter_date', '>=', $startDate);
        }
        if ($endDate) {
            $letterQuery->whereDate('letter_date', '<=', $endDate);
        }

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

        $letters = $letterQuery->latest()->get();

        // 2. Fetch all documents
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

        // 3. Map into unified list
        $allCollected = collect();

        foreach ($letters as $let) {
            $typeName = $let->type === 'incoming' ? 'Surat Masuk' : ($let->type === 'outgoing' ? 'Surat Keluar' : 'Surat Keterangan');
            $catName = $let->category ? $let->category->name : $typeName;

            $allCollected->push([
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

        // 4. Calculate simplified stats (Total, Surat Masuk, Surat Keluar, Dokumen Perikatan)
        $suratMasukCount = $allCollected->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'masuk'))->count();
        $suratKeluarCount = $allCollected->filter(fn($i) => str_contains(strtolower($i['category']['name']), 'keluar'))->count();
        $dokumenCount = max(0, $totalCombined - ($suratMasukCount + $suratKeluarCount));

        $stats = [
            ['key' => 'total', 'label' => 'Total Surat & Dokumen', 'count' => $totalCombined, 'percentageChange' => 8.5, 'iconType' => 'document'],
            ['key' => 'surat_masuk', 'label' => 'Surat Masuk', 'count' => $suratMasukCount, 'percentageChange' => 8.5, 'iconType' => 'inbox'],
            ['key' => 'surat_keluar', 'label' => 'Surat Keluar', 'count' => $suratKeluarCount, 'percentageChange' => 6.2, 'iconType' => 'send'],
            ['key' => 'dokumen', 'label' => 'Dokumen Perikatan', 'count' => $dokumenCount, 'percentageChange' => 9.1, 'iconType' => 'folder'],
        ];

        // 5. Apply tab filter if active
        $filteredCollection = $allCollected;
        if ($tabCategory && $tabCategory !== 'all') {
            $filteredCollection = $allCollected->filter(function ($item) use ($tabCategory) {
                $cName = strtolower($item['category']['name']);
                $target = strtolower($tabCategory);
                if ($target === 'surat masuk') return str_contains($cName, 'masuk');
                if ($target === 'surat keluar') return str_contains($cName, 'keluar');
                if ($target === 'surat keterangan') return str_contains($cName, 'keterangan');
                if ($target === 'surat penawaran') return str_contains($cName, 'penawaran');
                if ($target === 'surat kontrak') return str_contains($cName, 'kontrak');
                return str_contains($cName, $target);
            })->values();
        }

        // 6. Calculate breakdown for Donut Chart based on active/filtered data
        $grouped = $filteredCollection->groupBy(fn($i) => $i['category']['name']);
        $colors = ['#10b981', '#2563eb', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899'];
        $breakdown = [];
        $i = 0;

        $filteredTotal = $filteredCollection->count();

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

        if (empty($breakdown)) {
            $breakdown = [
                ['name' => 'Surat Masuk', 'percentage' => 0, 'count' => 0, 'color' => '#10b981'],
                ['name' => 'Surat Keluar', 'percentage' => 0, 'count' => 0, 'color' => '#2563eb'],
                ['name' => 'Surat Keterangan', 'percentage' => 0, 'count' => 0, 'color' => '#f59e0b'],
                ['name' => 'Surat Penawaran Audit', 'percentage' => 0, 'count' => 0, 'color' => '#06b6d4'],
                ['name' => 'Surat Kontrak', 'percentage' => 0, 'count' => 0, 'color' => '#8b5cf6'],
            ];
        }

        // 7. Manual pagination
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
