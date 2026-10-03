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
        if (!Schema::hasTable('generated_letters')) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_letters');
    }
};
