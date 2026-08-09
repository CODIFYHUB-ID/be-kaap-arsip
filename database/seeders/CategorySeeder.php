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
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
