<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentTemplate;
use App\Models\GeneratedLetter;
use App\Http\Controllers\Api\DocumentTemplate\DocumentTemplateController;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = DocumentTemplate::firstOrCreate(
            ['code' => 'surat_tugas'],
            DocumentTemplateController::getDefaultSuratTugasTemplate()
        );

        if (GeneratedLetter::where('letter_number', 'No. 113/ ST-SSR / VI / 2026')->count() === 0) {
            GeneratedLetter::create([
                'template_id' => $template->id,
                'letter_type' => 'surat_tugas',
                'letter_number' => 'No. 113/ ST-SSR / VI / 2026',
                'client_name' => 'PT. ALFO KONSTRUKSI ABADI',
                'audit_type' => 'General Audit',
                'period_end_date' => '31 Desember 2025',
                'assigned_auditors' => [
                    ['name' => 'Fahri Yusuf', 'role' => 'Ketua Tim'],
                    ['name' => 'Fadel Yusuf', 'role' => 'Anggota'],
                    ['name' => 'Dimas Fadliansyah', 'role' => 'Anggota']
                ],
                'kop_type' => 'biasa',
                'letter_date' => '2026-06-20',
                'city' => 'Medan',
                'signatory_name' => 'Rizki Syahputra, SE, M.Si, CPA',
                'signatory_title' => 'Partner',
                'status' => 'final'
            ]);
        }
    }
}
