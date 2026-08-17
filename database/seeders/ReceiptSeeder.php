<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Receipt;
use App\Models\Mitra;
use App\Models\User;
use Carbon\Carbon;

class ReceiptSeeder extends Seeder
{
    public function run(): void
    {
        $mitraIds = Mitra::pluck('id')->toArray();
        $userId = User::first()->id ?? null;

        if (empty($mitraIds)) {
            return;
        }

        $receipts = [
            [
                'receipt_number' => 'INV/2026/08/001',
                'amount' => 15000000,
                'description' => 'Pembayaran jasa audit tahap 1',
                'date_offset' => -10
            ],
            [
                'receipt_number' => 'INV/2026/08/002',
                'amount' => 5000000,
                'description' => 'Pembayaran jasa konsultasi pajak Agustus',
                'date_offset' => -5
            ],
            [
                'receipt_number' => 'INV/2026/08/003',
                'amount' => 25000000,
                'description' => 'Pelunasan jasa audit tahunan',
                'date_offset' => -2
            ],
            [
                'receipt_number' => 'INV/2026/08/004',
                'amount' => 7500000,
                'description' => 'Pembayaran review laporan keuangan',
                'date_offset' => 0
            ],
            [
                'receipt_number' => 'INV/2026/08/005',
                'amount' => 12000000,
                'description' => 'Uang muka pengerjaan proyek sistem',
                'date_offset' => -1
            ]
        ];

        foreach ($receipts as $data) {
            Receipt::firstOrCreate([
                'receipt_number' => $data['receipt_number']
            ], [
                'mitra_id' => $mitraIds[array_rand($mitraIds)],
                'amount' => $data['amount'],
                'transaction_date' => Carbon::now()->addDays($data['date_offset']),
                'description' => $data['description'],
                'created_by' => $userId,
            ]);
        }
    }
}
