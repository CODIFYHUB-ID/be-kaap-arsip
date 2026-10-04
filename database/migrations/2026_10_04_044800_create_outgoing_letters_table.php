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
        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->id();
            $table->string('letter_type', 50)->index(); // penawaran, keterangan
            $table->string('letter_number')->unique();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('version')->default(1);
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('client_name')->index();
            $table->string('client_legal_entity', 50)->nullable()->default('PT');
            $table->string('client_address_street')->nullable();
            $table->string('client_address_kelurahan')->nullable();
            $table->string('client_address_kecamatan')->nullable();
            $table->string('client_address_city', 100)->nullable()->default('Medan');
            $table->string('client_address_province', 100)->nullable()->default('Sumatera Utara');
            $table->string('client_address_postal_code', 20)->nullable();
            $table->text('client_address_full')->nullable();
            $table->date('letter_date')->nullable()->index();
            $table->string('city', 100)->default('Medan');
            $table->string('subject')->default('Penawaran Audit');

            // Field Khusus Surat Penawaran
            $table->string('audit_type')->nullable()->default('Audit Umum (general audit)');
            $table->string('fiscal_year_end_date')->nullable()->default('31 Desember 2025');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('terbilang')->nullable();
            $table->string('tax_note')->nullable()->default('Termasuk pajak-pajak yang berlaku');
            $table->boolean('tax_note_enabled')->default(true);
            $table->string('salutation', 50)->nullable()->default('Bapak');

            // Field Khusus Cover Note / Surat Keterangan
            $table->string('fiscal_year', 20)->nullable()->default('2025');
            $table->string('target_completion_date')->nullable()->default('17 Oktober 2026');

            // Tanda Tangan, Stempel & Status
            $table->string('signatory_name')->default('Rizki Syahputra, SE, M.Si, CPA');
            $table->string('signatory_title')->default('Partner');
            $table->longText('signature_stamp_url')->nullable();
            $table->boolean('show_signature_stamp')->default(true);
            $table->integer('signature_stamp_width')->default(140);
            $table->string('status', 30)->default('final')->index(); // draft, final, printed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('outgoing_letters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
    }
};
