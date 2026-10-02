<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Surat Kontrak', 'description' => 'Dokumen perjanjian dan perikatan kerja sama'],
            ['name' => 'Surat Penawaran', 'description' => 'Surat penawaran jasa audit / konsul'],
            ['name' => 'Kwitansi', 'description' => 'Bukti pembayaran dan faktur'],
            ['name' => 'Surat Keterangan', 'description' => 'Surat keterangan resmi dari kantor'],
            ['name' => 'Laporan Audit', 'description' => 'Berkas laporan hasil pemeriksaan audit'],
            ['name' => 'Dokumen Legal', 'description' => 'Legalitas perusahaan, akta, dan perizinan'],
            ['name' => 'Kertas Kerja Pemeriksaan (KKP)', 'description' => 'Working papers dan catatan analisis pemeriksaan auditor'],
            ['name' => 'Bukti Audit Klien', 'description' => 'Rekening koran, laporan keuangan internal, faktur, buku besar'],
            ['name' => 'Surat Konfirmasi', 'description' => 'Konfirmasi bank, piutang, dan utang pihak ketiga'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
