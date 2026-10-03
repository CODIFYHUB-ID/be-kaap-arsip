<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Mitra;

class MitraSeeder extends Seeder
{
    public function run(): void
    {
        $mitras = [
            [
                'code' => 'M-001',
                'name' => 'Budi Santoso',
                'company_name' => 'PT Maju Bersama',
                'address' => 'Jl. Sudirman No. 12, Jakarta',
                'phone' => '081234567890',
                'email' => 'contact@majubersama.com',
                'status' => 'active',
                'notes' => 'Klien Prioritas',
            ],
            [
                'code' => 'M-002',
                'name' => 'Andi Wijaya',
                'company_name' => 'CV Sukses Makmur',
                'address' => 'Jl. Merdeka No. 45, Bandung',
                'phone' => '081987654321',
                'email' => 'info@suksesmakmur.co.id',
                'status' => 'active',
                'notes' => 'Klien Reguler',
            ],
            [
                'code' => 'M-003',
                'name' => 'Siti Aminah',
                'company_name' => 'PT Karya Cipta',
                'address' => 'Jl. Thamrin No. 88, Surabaya',
                'phone' => '082122334455',
                'email' => 'admin@karyacipta.id',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-004',
                'name' => 'Hendra Gunawan',
                'company_name' => 'UD Jaya Abadi',
                'address' => 'Jl. Diponegoro No. 1, Semarang',
                'phone' => '081233445566',
                'email' => 'hendra@jayaabadi.com',
                'status' => 'active',
                'notes' => 'Mitra baru',
            ],
            [
                'code' => 'M-005',
                'name' => 'Dewi Lestari',
                'company_name' => 'PT Global Informatika',
                'address' => 'Jl. Gatot Subroto No. 23, Medan',
                'phone' => '082211335577',
                'email' => 'hello@globalinfo.co.id',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-006',
                'name' => 'Rizky Pratama',
                'company_name' => 'PT Nusantara Tekno',
                'address' => 'Jl. Ahmad Yani No. 99, Makassar',
                'phone' => '081355779911',
                'email' => 'rizky@nusantaratek.com',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-007',
                'name' => 'Ayu Wandira',
                'company_name' => 'CV Bintang Terang',
                'address' => 'Jl. Pahlawan No. 56, Palembang',
                'phone' => '085244668800',
                'email' => 'ayu@bintangterang.net',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-008',
                'name' => 'Rudi Hartono',
                'company_name' => 'PT Konstruksi Handal',
                'address' => 'Jl. Gajah Mada No. 11, Balikpapan',
                'phone' => '081266880022',
                'email' => 'contact@konstruksihandal.id',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-009',
                'name' => 'Lina Marlina',
                'company_name' => 'UD Berkah Jaya',
                'address' => 'Jl. Veteran No. 34, Denpasar',
                'phone' => '081877991133',
                'email' => 'lina@berkahjaya.com',
                'status' => 'active',
                'notes' => '-',
            ],
            [
                'code' => 'M-010',
                'name' => 'Dimas Anggara',
                'company_name' => 'PT Solusi Cerdas',
                'address' => 'Jl. Imam Bonjol No. 77, Yogyakarta',
                'phone' => '082399113355',
                'email' => 'info@solusicerdas.co.id',
                'status' => 'inactive',
                'notes' => 'Tidak aktif sementara',
            ]
        ];

        foreach ($mitras as $mitraData) {
            $mitra = Mitra::firstOrCreate(
                ['code' => $mitraData['code']],
                $mitraData
            );
        }
    }
}
