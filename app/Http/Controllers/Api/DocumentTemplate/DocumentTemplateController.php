<?php

namespace App\Http\Controllers\Api\DocumentTemplate;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    use ApiResponse;

    /**
     * Get default template attributes for surat_tugas based on official KAP standard
     */
    public static function getDefaultSuratTugasTemplate(): array
    {
        return [
            'code' => 'surat_tugas',
            'name' => 'Template Surat Tugas Audit',
            'title' => 'SURAT TUGAS',
            'category' => 'surat_tugas',
            'description' => 'Template Surat Tugas Pemeriksaan Auditor KAP (General Audit)',
            'kop_type' => 'biasa',
            'number_format' => 'No. 113/ ST-SSR / VI / 2026',
            'opening_text' => 'Sehubungan dengan rencana pemeriksaan laporan keuangan ( General Audit ) {nama_pt} yang berakhir pada tanggal {tanggal_periode} dengan ini ditugaskan kepada saudara :',
            'scope_text' => 'Untuk melaksanakan tugas tersebut dan mulai sejak dikeluarkannya surat tugas ini. Pemeriksaan meliputi pemeriksaan fisik atas kas, persediaan dan aset, konfirmasi atas hutang dan piutang, pemeriksaan atas catatan dan bukti keuangan dan prosedur lainnya sesuai dengan standar auditing yang ditetapkan oleh Institut Akuntan Publik Indonesia.',
            'closing_text' => 'Demikian surat tugas ini disampaikan kepada saudara agar dapat dilaksanakan dengan sebaik-baiknya.',
            'signatory_city' => 'Medan',
            'signatory_name' => 'Rizki Syahputra, SE, M.Si, CPA',
            'signatory_title' => 'Partner',
            'is_active' => true,
        ];
    }

    /**
     * List all master document templates
     */
    public function index(): JsonResponse
    {
        $templates = DocumentTemplate::where('is_active', true)->get();

        // If surat_tugas doesn't exist yet, seed it automatically
        if (!$templates->contains('code', 'surat_tugas') && !$templates->contains('category', 'surat_tugas')) {
            $default = DocumentTemplate::create(self::getDefaultSuratTugasTemplate());
            $templates->push($default);
        }

        return $this->success($templates, 'Daftar master template berkas berhasil diambil.');
    }

    /**
     * Get specific template by code (e.g. surat_tugas or TPL-ST-01)
     */
    public function show(string $code): JsonResponse
    {
        $template = DocumentTemplate::where('code', $code)
            ->orWhere('category', $code)
            ->first();

        if (!$template && ($code === 'surat_tugas' || $code === 'surat-tugas')) {
            $template = DocumentTemplate::create(self::getDefaultSuratTugasTemplate());
        }

        if (!$template) {
            return $this->error('Template berkas tidak ditemukan.', null, 404);
        }

        return $this->success($template, 'Detail master template berkas berhasil diambil.');
    }

    /**
     * Update master template
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $template = DocumentTemplate::where('code', $code)
            ->orWhere('category', $code)
            ->first();

        if (!$template && ($code === 'surat_tugas' || $code === 'surat-tugas')) {
            $template = DocumentTemplate::create(self::getDefaultSuratTugasTemplate());
        }

        if (!$template) {
            return $this->error('Template berkas tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'kop_type' => 'nullable|string|in:biasa,amplop,kontrak,tanpa_kop',
            'number_format' => 'nullable|string|max:255',
            'opening_text' => 'nullable|string',
            'scope_text' => 'nullable|string',
            'closing_text' => 'nullable|string',
            'signatory_city' => 'nullable|string|max:100',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);

        return $this->success($template, 'Master template berkas berhasil diperbarui.');
    }
}
