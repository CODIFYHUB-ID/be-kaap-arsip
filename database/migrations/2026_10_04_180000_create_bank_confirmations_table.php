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
        // 1. Data bank milik klien (bisa multi bank per klien)
        if (!Schema::hasTable('client_banks')) {
            Schema::create('client_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mitra_id')->constrained('mitras')->cascadeOnDelete();
                $table->string('bank_name');
                $table->text('bank_address')->nullable();
                $table->string('account_number')->nullable();
                $table->timestamps();
            });
        }

        // 2. Dokumen Konfirmasi Bank (2 halaman: Surat & Formulir Konfirmasi)
        Schema::create('bank_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_number')->unique();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('version')->default(1);
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('client_name')->index();

            // Kop Klien & TTD Klien
            $table->longText('client_letterhead_url')->nullable();
            $table->longText('client_signature_stamp_url')->nullable();
            $table->boolean('show_client_stamp')->default(true);

            // Bagian A: Surat Permintaan Konfirmasi (Halaman 1)
            $table->string('letter_city', 100)->default('Medan');
            $table->date('letter_date')->nullable();
            $table->string('subject')->default('Konfirmasi Saldo Bank');
            $table->string('bank_name')->default('Bank Sumut');
            $table->text('bank_address')->nullable();
            $table->string('balance_date')->default('31 Desember 2021');
            $table->string('client_signatory_name')->default('Drs. H. Amansyah Nasution, MSP');
            $table->string('client_signatory_title')->default('Ketua');

            // Bagian B: Formulir Konfirmasi (Halaman 2)
            $table->string('confirmation_date')->nullable();
            $table->boolean('fill_mode_giro')->default(false);
            $table->boolean('fill_mode_deposito')->default(false);
            $table->boolean('fill_mode_loan')->default(false);
            $table->boolean('fill_mode_other')->default(false);

            // Detail penutup bank
            $table->string('bank_signatory_city')->default('Medan');
            $table->string('bank_signatory_title')->default('Direktur,');
            $table->string('bank_signatory_name')->nullable();

            // Catatan instruksi internal klien
            $table->boolean('show_note')->default(true);
            $table->string('note_text', 500)->nullable()->default('Note : Sebelum dikirim konfirmasi ini dicopy terlebih dahulu sebagai pertinggal');

            // Audit workflow & tracking
            $table->string('status', 30)->default('draft')->index(); // draft, sent, received
            $table->date('sent_date')->nullable();
            $table->date('received_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('bank_confirmations')->nullOnDelete();
        });

        // 3. Item isian rekening/deposito/pinjaman (Giro, Deposito, Pinjaman, Lainnya)
        Schema::create('bank_confirmation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_confirmation_id')->constrained('bank_confirmations')->cascadeOnDelete();
            $table->string('category', 50); // giro, deposito, loan, other
            $table->string('col_1')->nullable(); // e.g. No Rekening / No Bilyet / Jenis Fasilitas
            $table->decimal('amount_1', 15, 2)->nullable(); // Saldo / Pagu
            $table->string('col_2')->nullable(); // Jangka Waktu
            $table->string('col_3')->nullable(); // Bunga
            $table->decimal('amount_2', 15, 2)->nullable(); // Posisi Saldo / Tunggakan
            $table->text('remarks')->nullable(); // Catatan / Remarks
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_confirmation_items');
        Schema::dropIfExists('bank_confirmations');
        Schema::dropIfExists('client_banks');
    }
};
