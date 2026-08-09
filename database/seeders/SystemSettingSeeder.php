<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'app_name',
                'value' => 'Sistem Arsip KAP',
                'type' => 'string',
                'description' => 'Nama aplikasi sistem arsip',
            ],
            [
                'key' => 'max_upload_size',
                'value' => '52428800', // 50 MB in Bytes
                'type' => 'integer',
                'description' => 'Batas maksimal ukuran file yang diizinkan untuk diunggah (bytes)',
            ],
            [
                'key' => 'allowed_extensions',
                'value' => 'pdf,docx,xlsx,jpg,png,zip',
                'type' => 'string',
                'description' => 'Ekstensi file yang diizinkan sistem',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
