<?php

namespace Database\Seeders;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@kaap-arsip.com')->first();
        $staff = User::where('email', 'staff@kaap-arsip.com')->first();

        $adminId = $admin ? $admin->id : 1;
        $staffId = $staff ? $staff->id : 2;

        $sampleLogs = [
            [
                'user_id' => $adminId,
                'action' => ActivityAction::LOGIN,
                'module' => ActivityModule::AUTH,
                'description' => 'Super Admin berhasil login ke dalam sistem arsip',
                'resource_type' => 'User',
                'resource_id' => $adminId,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'created_at' => now()->subMinutes(120),
            ],
            [
                'user_id' => $adminId,
                'action' => ActivityAction::UPDATE,
                'module' => ActivityModule::SYSTEM,
                'description' => 'Memperbarui profil & format kop surat resmi KAP',
                'resource_type' => 'SystemSetting',
                'resource_id' => 1,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0',
                'created_at' => now()->subMinutes(90),
            ],
            [
                'user_id' => $staffId,
                'action' => ActivityAction::UPLOAD,
                'module' => ActivityModule::DOCUMENT,
                'description' => 'Mengunggah dokumen audit "Laporan_Audit_PT_Sinarmas_2025.pdf" (3.4 MB)',
                'resource_type' => 'Document',
                'resource_id' => 1,
                'ip_address' => '192.168.1.45',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121.0.0.0',
                'created_at' => now()->subMinutes(60),
            ],
            [
                'user_id' => $staffId,
                'action' => ActivityAction::CREATE,
                'module' => ActivityModule::LETTER,
                'description' => 'Menerbitkan surat keluar nomor 015/SK-KAP/VIII/2026 ke PT. Mitra Sejahtera',
                'resource_type' => 'Letter',
                'resource_id' => 1,
                'ip_address' => '192.168.1.45',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'created_at' => now()->subMinutes(45),
            ],
            [
                'user_id' => $staffId,
                'action' => ActivityAction::DOWNLOAD,
                'module' => ActivityModule::DOCUMENT,
                'description' => 'Mengunduh presigned URL berkas "Surat_Perikatan_2026.pdf"',
                'resource_type' => 'Document',
                'resource_id' => 2,
                'ip_address' => '192.168.1.45',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'created_at' => now()->subMinutes(30),
            ],
            [
                'user_id' => $adminId,
                'action' => ActivityAction::CREATE,
                'module' => ActivityModule::MITRA,
                'description' => 'Menambahkan data mitra baru "PT. Nusantara Jaya Abadi"',
                'resource_type' => 'Mitra',
                'resource_id' => 1,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subMinutes(15),
            ],
            [
                'user_id' => $adminId,
                'action' => ActivityAction::EXPORT,
                'module' => ActivityModule::REPORT,
                'description' => 'Mengekspor laporan rekapitulasi mitra ke format Microsoft Excel (.xls)',
                'resource_type' => 'Report',
                'resource_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subMinutes(5),
            ],
        ];

        foreach ($sampleLogs as $log) {
            ActivityLog::create($log);
        }
    }
}
