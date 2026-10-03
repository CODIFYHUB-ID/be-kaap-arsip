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
        // 1. Tabel Master Template Berkas (Surat Tugas, Kontrak, dsb.)
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // e.g. surat_tugas
            $table->string('name'); // e.g. Template Surat Tugas Audit
            $table->string('category')->default('surat_tugas')->index();
            $table->string('kop_type')->default('biasa'); // biasa | amplop | kontrak
            $table->string('title')->default('SURAT TUGAS');
            $table->string('number_format')->default('No. {nomor}/ ST-SSR / {bulan_romawi} / {tahun}');
            $table->text('opening_text')->nullable();
            $table->text('scope_text')->nullable();
            $table->text('closing_text')->nullable();
            $table->string('signatory_city')->default('Medan');
            $table->string('signatory_name')->default('Rizki Syahputra, SE, M.Si, CPA');
            $table->string('signatory_title')->default('Partner');
            $table->longText('body_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabel Surat yang Dihasilkan (Generated Letters)
        Schema::create('generated_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('letter_type')->default('surat_tugas')->index();
            $table->string('letter_number')->index();
            $table->string('client_name')->index();
            $table->string('audit_type')->nullable(); // General Audit, Review, etc.
            $table->string('period_end_date')->nullable(); // e.g. 31 Desember 2025
            $table->json('assigned_auditors')->nullable(); // [{"name": "Fahri Yusuf", "role": "Ketua Tim"}]
            $table->string('kop_type')->default('biasa'); // biasa | amplop | kontrak
            $table->date('letter_date')->nullable()->index();
            $table->string('city')->default('Medan');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->text('opening_text')->nullable();
            $table->text('scope_text')->nullable();
            $table->text('closing_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('status')->default('final')->index(); // draft | final | printed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_letters');
        Schema::dropIfExists('document_templates');
    }
};
