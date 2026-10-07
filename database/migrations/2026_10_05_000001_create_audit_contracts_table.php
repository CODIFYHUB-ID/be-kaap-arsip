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
        if (!Schema::hasTable('audit_contracts')) {
            Schema::create('audit_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number')->unique()->index(); // e.g. 286 / PKA-RS / SSR / MDN / 2026
                $table->date('contract_date')->nullable()->index();
                $table->string('city')->default('Medan');
                $table->string('ref_text')->nullable(); // e.g. Surat perikatan audit untuk tahun yang berakhir...
                
                // Pihak Kedua (Klien / Mitra)
                $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
                $table->string('client_name')->index(); // PT. Palma Abadi Transindo
                $table->text('client_address')->nullable();
                $table->string('client_pic_name')->nullable(); // Willy Hidayat
                $table->string('client_pic_title')->nullable()->default('Direktur Utama'); // Direktur Utama

                // Ketentuan Audit & Periode
                $table->string('accounting_standard')->default('Standar Akuntansi Keuangan Entitas Privat (“SAK EP”) yang dikeluarkan oleh IAI');
                $table->string('period_end_date')->default('31 Desember 2025');

                // Fee & Termin Pembayaran
                $table->decimal('fee_amount', 15, 2)->default(0);
                $table->string('fee_terbilang')->nullable(); // Tiga Puluh Juta Rupiah
                $table->integer('report_copies')->default(3); // 3 (tiga) copy
                $table->json('payment_terms')->nullable(); // [{"percentage": 50, "description": "pada saat kami memulai tugas kami"}, ...]
                $table->string('accommodation_note', 500)->nullable()->default('Seluruh biaya survei dan akomodasi yang kami perlukan untuk ke lapangan menjadi tanggungan manajemen perusahaan.');

                // Format Kop & Penandatangan Pihak Pertama (KAP)
                $table->string('kop_type', 30)->default('kontrak'); // biasa | amplop | kontrak | tanpa_kop
                $table->string('signatory_name')->nullable()->default('Rizki Syahputra, SE, M.Si, CPA');
                $table->string('signatory_title')->nullable()->default('Partner');

                // Custom Rich Text Override
                $table->longText('custom_html')->nullable();

                // Status & Tracking
                $table->string('status', 30)->default('draft')->index(); // draft | final | signed
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_contracts');
    }
};
