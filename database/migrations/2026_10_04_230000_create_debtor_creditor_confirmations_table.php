<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Paket Konfirmasi Utang & Piutang (Header Paket)
        Schema::create('debtor_creditor_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('package_number')->unique();
            $table->string('confirmation_type', 50)->default('piutang_positif'); // piutang_positif, utang_positif
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('version')->default(1);
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('client_name')->index();

            // Kop Surat Klien & TTD Klien
            $table->longText('client_letterhead_url')->nullable();
            $table->longText('client_signature_stamp_url')->nullable();
            $table->boolean('show_client_stamp')->default(true);

            // Bagian A (Surat Permintaan)
            $table->string('letter_city', 100)->default('Medan');
            $table->date('letter_date')->nullable();
            $table->string('subject')->default('Konfirmasi Saldo Piutang');
            $table->string('balance_date')->default('31 Desember 2025');
            $table->integer('response_deadline_days')->default(7);

            // Penanggung Jawab Auditor ("up.")
            $table->string('auditor_pic_name')->default('Fahri Yusuf, S. Ak., M.Tr.Ak.');
            $table->string('auditor_pic_phone')->default('0853-7234-5304');
            $table->text('auditor_correspondence_address')->nullable();

            // Penandatangan Klien
            $table->string('client_signatory_name')->default('Ai Ni');
            $table->string('client_signatory_title')->default('Head Finance & Accounting');

            // Pengaturan Tampilan & Catatan
            $table->boolean('show_cut_line')->default(true);
            $table->boolean('show_note')->default(true);
            $table->text('note_text')->nullable()->default('Note : Sebelum dikirim konfirmasi ini dicopy terlebih dahulu sebagai pertinggal');

            // Rekap Saldo
            $table->decimal('total_nominal', 18, 2)->default(0);
            $table->decimal('general_ledger_nominal', 18, 2)->nullable();

            // Status paket
            $table->string('status', 30)->default('draft')->index(); // draft, sent, completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('debtor_creditor_confirmations')->nullOnDelete();
        });

        // 2. Daftar Penerima (1 baris = 1 surat / 1 sheet)
        Schema::create('debtor_creditor_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confirmation_id')->constrained('debtor_creditor_confirmations')->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->string('recipient_name');
            $table->string('recipient_type', 30)->default('badan_usaha'); // badan_usaha, perorangan
            $table->text('recipient_address')->nullable();
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('signatory_title')->nullable()->default('Direktur,');
            $table->string('status', 30)->default('draft')->index(); // draft, sent, confirmed_valid, confirmed_difference, unanswered
            $table->date('sent_date')->nullable();
            $table->date('response_date')->nullable();
            $table->text('difference_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debtor_creditor_recipients');
        Schema::dropIfExists('debtor_creditor_confirmations');
    }
};
