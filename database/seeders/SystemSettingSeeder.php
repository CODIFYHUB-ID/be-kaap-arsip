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
            // KAP Profile & Kop Surat Settings
            [
                'key' => 'kap_name',
                'value' => 'KANTOR AKUNTAN PUBLIK DRS. SELAMAT SINURAYA & REKAN',
                'type' => 'string',
                'description' => 'Nama Resmi Kantor Akuntan Publik',
            ],
            [
                'key' => 'kap_tagline',
                'value' => 'Registered Public Accountants & Business Advisors',
                'type' => 'string',
                'description' => 'Slogan / Sub-heading KAP',
            ],
            [
                'key' => 'kap_license_no',
                'value' => 'Keputusan Menteri Keuangan No. KEP-234/KM.1/2021',
                'type' => 'string',
                'description' => 'Nomor Izin Usaha KAP Kemenkeu RI',
            ],
            [
                'key' => 'kap_ojk_no',
                'value' => 'STTD.AP-098/PM.22/2022',
                'type' => 'string',
                'description' => 'Nomor STTD Otoritas Jasa Keuangan (OJK)',
            ],
            [
                'key' => 'kap_iapi_no',
                'value' => 'Anggota Institut Akuntan Publik Indonesia (IAPI)',
                'type' => 'string',
                'description' => 'Nomor Keanggotaan IAPI',
            ],
            [
                'key' => 'kap_leader_name',
                'value' => 'Drs. Selamat Sinuraya, M.Si., Ak., CA., CPA',
                'type' => 'string',
                'description' => 'Nama Pimpinan Rekan KAP',
            ],
            [
                'key' => 'kap_leader_license',
                'value' => 'AP. 0456',
                'type' => 'string',
                'description' => 'Nomor Izin Akuntan Publik (AP)',
            ],
            [
                'key' => 'kap_leader_position',
                'value' => 'Pemimpin Rekan (Managing Partner)',
                'type' => 'string',
                'description' => 'Jabatan Pimpinan Rekan',
            ],
            [
                'key' => 'kap_address',
                'value' => 'Jl. Setia Budi No. 88 Komplek Bisnis Medan',
                'type' => 'string',
                'description' => 'Alamat Kantor Utama',
            ],
            [
                'key' => 'kap_city',
                'value' => 'Medan, Sumatera Utara',
                'type' => 'string',
                'description' => 'Kota & Provinsi',
            ],
            [
                'key' => 'kap_postal_code',
                'value' => '20132',
                'type' => 'string',
                'description' => 'Kode Pos Kantor',
            ],
            [
                'key' => 'kap_phone',
                'value' => '(061) 821-4567 / +62 812-6000-8899',
                'type' => 'string',
                'description' => 'Nomor Telepon Kantor',
            ],
            [
                'key' => 'kap_email',
                'value' => 'info@kap-sinuraya.com',
                'type' => 'string',
                'description' => 'Email Resmi KAP',
            ],
            [
                'key' => 'kap_website',
                'value' => 'www.kap-sinuraya.com',
                'type' => 'string',
                'description' => 'Website Resmi KAP',
            ],
            [
                'key' => 'kap_branch_address',
                'value' => 'Kantor Cabang: Gedung Menara Thamrin Lt. 9, Jakarta Pusat',
                'type' => 'string',
                'description' => 'Alamat Kantor Cabang (Opsional)',
            ],
            [
                'key' => 'kap_logo_url',
                'value' => '/image/logo.jpeg',
                'type' => 'string',
                'description' => 'URL Logo Resmi KAP',
            ],
            [
                'key' => 'kap_kop_layout',
                'value' => 'standard',
                'type' => 'string',
                'description' => 'Layout Kop Surat (standard / centered / modern)',
            ],
            [
                'key' => 'kap_divider_style',
                'value' => 'double',
                'type' => 'string',
                'description' => 'Gaya Garis Pembatas Kop Surat (double / single / accent)',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
