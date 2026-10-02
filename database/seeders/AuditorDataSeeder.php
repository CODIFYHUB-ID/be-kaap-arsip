<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Document;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditorDataSeeder extends Seeder
{
    public function run(): void
    {
        $auditor = User::where('email', 'auditor@kaap-arsip.com')->first();
        if (! $auditor) return;

        $catKKP = Category::firstOrCreate(['name' => 'Kertas Kerja Pemeriksaan (KKP)'], ['description' => 'Working papers auditor']);
        $catBukti = Category::firstOrCreate(['name' => 'Bukti Audit Klien'], ['description' => 'Rekening koran, faktur, buku besar']);
        $catKonfirmasi = Category::firstOrCreate(['name' => 'Surat Konfirmasi'], ['description' => 'Konfirmasi pihak ketiga']);

        // Sample KKP (Working Papers)
        Document::updateOrCreate(
            ['file_key' => 'kkp/kkp-kas-bank-2025-pt-maju.xlsx'],
            [
                'mitra_id' => 1,
                'category_id' => $catKKP->id,
                'uploaded_by' => $auditor->id,
                'file_name' => 'KKP_A100_Kas_dan_Setara_Kas_2025.xlsx',
                'file_size' => 1450000,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx',
                'description' => 'Kertas Kerja Pemeriksaan akun Kas & Bank PT Maju Bersama tahun buku 2025.',
                'tahun_berkas' => '2025',
                'tanggal_dokumen' => '2025-02-15',
                'is_working_paper' => true,
                'review_status' => 'reviewed',
                'reviewed_by' => 1,
                'reviewed_at' => now(),
                'review_notes' => 'Telah direviu oleh Senior Partner. Rekonsiliasi bank telah diverifikasi klir.',
            ]
        );

        Document::updateOrCreate(
            ['file_key' => 'kkp/kkp-piutang-usaha-2025-pt-maju.xlsx'],
            [
                'mitra_id' => 1,
                'category_id' => $catKKP->id,
                'uploaded_by' => $auditor->id,
                'file_name' => 'KKP_B200_Piutang_Usaha_2025.xlsx',
                'file_size' => 1820000,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx',
                'description' => 'Substantive testing piutang usaha & aging schedule debitur PT Maju Bersama.',
                'tahun_berkas' => '2025',
                'tanggal_dokumen' => '2025-02-18',
                'is_working_paper' => true,
                'review_status' => 'revision_needed',
                'reviewed_by' => 1,
                'reviewed_at' => now(),
                'review_notes' => 'Perlu penelusuran lebih lanjut atas selisih konfirmasi PT Global Distribusi.',
            ]
        );

        // Sample Bukti Audit (Client Evidence)
        Document::updateOrCreate(
            ['file_key' => 'bukti/rek-koran-des-2025-pt-maju.pdf'],
            [
                'mitra_id' => 1,
                'category_id' => $catBukti->id,
                'uploaded_by' => 3,
                'file_name' => 'Rekening_Koran_BCA_Desember_2025.pdf',
                'file_size' => 2850000,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'description' => 'Rekening koran BCA Rek. 8820192837 periode Desember 2025.',
                'tahun_berkas' => '2025',
                'tanggal_dokumen' => '2025-12-31',
                'is_working_paper' => false,
            ]
        );

        Document::updateOrCreate(
            ['file_key' => 'bukti/buku-besar-kas-2025-pt-maju.xlsx'],
            [
                'mitra_id' => 1,
                'category_id' => $catBukti->id,
                'uploaded_by' => 3,
                'file_name' => 'General_Ledger_Kas_Bank_FY2025.xlsx',
                'file_size' => 4500000,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx',
                'description' => 'Buku besar akun kas dan bank seluruh cabang tahun 2025.',
                'tahun_berkas' => '2025',
                'tanggal_dokumen' => '2025-12-31',
                'is_working_paper' => false,
            ]
        );

        // Sample Confirmation Letters
        Letter::updateOrCreate(
            ['letter_number' => '045/KAP-SN/KNF/II/2025'],
            [
                'mitra_id' => 1,
                'category_id' => $catKonfirmasi->id,
                'type' => 'outgoing',
                'subject' => 'Surat Konfirmasi Saldo Rekening Bank Mandiri',
                'sender' => 'KAP Sinuraya & Rekan',
                'recipient' => 'PT Bank Mandiri (Persero) Tbk - Cabang Thamrin',
                'status' => 'final',
                'letter_date' => '2025-02-10',
                'confirmation_type' => 'bank',
                'confirmation_status' => 'menunggu_jawaban',
                'third_party_name' => 'PT Bank Mandiri (Persero) Tbk',
                'description' => 'Konfirmasi saldo rekening per 31 Desember 2025 untuk PT Maju Bersama.',
                'created_by' => $auditor->id,
            ]
        );

        Letter::updateOrCreate(
            ['letter_number' => '048/KAP-SN/KNF/II/2025'],
            [
                'mitra_id' => 1,
                'category_id' => $catKonfirmasi->id,
                'type' => 'outgoing',
                'subject' => 'Surat Konfirmasi Saldo Piutang Usaha PT Global Distribusi',
                'sender' => 'KAP Sinuraya & Rekan',
                'recipient' => 'PT Global Distribusi Utama',
                'status' => 'final',
                'letter_date' => '2025-02-12',
                'confirmation_type' => 'piutang',
                'confirmation_status' => 'selisih',
                'third_party_name' => 'PT Global Distribusi Utama',
                'confirmation_reply_date' => '2025-02-28',
                'exception_notes' => 'Pihak ketiga mencatat saldo Rp 420.000.000, terdapat selisih Rp 30.000.000 karena pelunasan giro masih in-transit per 31 Des 2025.',
                'description' => 'Konfirmasi piutang usaha perikatan audit PT Maju Bersama.',
                'created_by' => $auditor->id,
            ]
        );

        Letter::updateOrCreate(
            ['letter_number' => '052/KAP-SN/KNF/II/2025'],
            [
                'mitra_id' => 1,
                'category_id' => $catKonfirmasi->id,
                'type' => 'outgoing',
                'subject' => 'Surat Konfirmasi Utang Usaha PT Supplier Baja Nusantara',
                'sender' => 'KAP Sinuraya & Rekan',
                'recipient' => 'PT Supplier Baja Nusantara',
                'status' => 'final',
                'letter_date' => '2025-02-15',
                'confirmation_type' => 'utang',
                'confirmation_status' => 'terjawab',
                'third_party_name' => 'PT Supplier Baja Nusantara',
                'confirmation_reply_date' => '2025-02-24',
                'description' => 'Jawaban konfirmasi utang cocok (Rp 180.000.000) tanpa selisih.',
                'created_by' => $auditor->id,
            ]
        );
    }
}
