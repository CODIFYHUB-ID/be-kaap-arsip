<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Letter;
use App\Models\Mitra;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;

class LetterSeeder extends Seeder
{
    public function run(): void
    {
        $mitraIds = Mitra::pluck('id')->toArray();
        $categoryIds = Category::pluck('id')->toArray();
        $userId = User::first()->id ?? null;

        if (empty($mitraIds) || empty($categoryIds)) {
            return;
        }

        $letters = [
            [
                'type' => 'incoming',
                'letter_number' => '001/IN/VIII/2026',
                'subject' => 'Undangan Rapat Evaluasi',
                'description' => 'Undangan rapat evaluasi kinerja tahunan',
                'date_offset' => -5
            ],
            [
                'type' => 'outgoing',
                'letter_number' => '002/OUT/VIII/2026',
                'subject' => 'Penyampaian Laporan Audit',
                'description' => 'Penyampaian laporan hasil audit periode Q2',
                'date_offset' => -3
            ],
            [
                'type' => 'incoming',
                'letter_number' => '003/IN/VIII/2026',
                'subject' => 'Permintaan Penawaran Jasa',
                'description' => 'Permintaan proposal penawaran jasa konsultasi pajak',
                'date_offset' => -10
            ],
            [
                'type' => 'outgoing',
                'letter_number' => '004/OUT/VIII/2026',
                'subject' => 'Balasan Proposal Penawaran',
                'description' => 'Pengiriman draf proposal ke mitra',
                'date_offset' => -2
            ],
            [
                'type' => 'incoming',
                'letter_number' => '005/IN/VIII/2026',
                'subject' => 'Pemberitahuan Perubahan Jadwal',
                'description' => 'Perubahan jadwal kunjungan lapangan',
                'date_offset' => 0
            ]
        ];

        foreach ($letters as $index => $data) {
            Letter::firstOrCreate([
                'letter_number' => $data['letter_number']
            ], [
                'mitra_id' => $mitraIds[array_rand($mitraIds)],
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'type' => $data['type'],
                'subject' => $data['subject'],
                'letter_date' => Carbon::now()->addDays($data['date_offset']),
                'received_date' => $data['type'] === 'incoming' ? Carbon::now()->addDays($data['date_offset'] + 1) : null,
                'description' => $data['description'],
                'created_by' => $userId,
            ]);
        }
    }
}
