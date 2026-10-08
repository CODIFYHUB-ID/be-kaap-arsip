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
        // 1. Tabel Utama Dokumen Pemeriksaan Fisik (Opname)
        Schema::create('physical_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number')->unique(); // e.g. OP-CASH/2026/001, OP-PERS/2026/001, OP-ASET/2026/001
            $table->string('type', 30)->index(); // 'cash', 'persediaan', 'aset_tetap'
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('client_name')->index();
            $table->string('audit_period', 100)->nullable(); // e.g. "Tahun Buku 2025"

            // Jadwal & Lokasi Opname
            $table->string('day', 20)->nullable(); // e.g. "Senin"
            $table->date('audit_date')->nullable()->index(); // e.g. "2026-10-08"
            $table->string('location_or_cashier', 255)->nullable(); // Bagian Kasir / Gudang / Lokasi Aset
            $table->string('time_start', 10)->nullable(); // "09:00"
            $table->string('time_end', 10)->nullable(); // "12:00"

            // Data Terstruktur Payload (JSON) untuk fleksibilitas KKP kompleks
            $table->json('audit_data')->nullable();

            // Total Ringkasan
            $table->decimal('total_amount', 18, 2)->default(0); // Total Kas Fisik / Total Nilai / Total Item
            $table->integer('total_items_count')->default(0);

            // Audit Workflow Status
            $table->string('status', 30)->default('draft')->index(); // draft, completed, archived

            // Keterkaitan Arsip & R2 Storage
            $table->string('r2_file_key')->nullable()->index(); // Key file di Cloudflare R2
            $table->string('r2_file_url')->nullable(); // Direct download / preview URL
            $table->foreignId('archived_document_id')->nullable()->constrained('documents')->nullOnDelete(); // Auto-sync ke Inventory Dokumen

            // Signatures
            $table->string('pic_name')->nullable();
            $table->string('pic_title')->nullable();
            $table->string('auditor_name')->nullable();
            $table->string('auditor_title')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabel Item Detail Pemeriksaan Fisik (Normalized untuk querying / reporting)
        Schema::create('physical_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_audit_id')->constrained('physical_audits')->cascadeOnDelete();
            $table->string('category', 100)->nullable(); // 'uang_kertas', 'uang_logam', 'Bangunan', 'Kendaraan', 'Persediaan', dll
            $table->string('item_name')->nullable();
            $table->string('unit', 50)->nullable(); // Lembar, Keping, Unit, Set, Drum, Pcs
            $table->decimal('nominal', 15, 2)->default(0); // Nominal pecahan (utk cash)
            $table->decimal('quantity', 15, 2)->default(0); // Jumlah fisik lembar / unit
            $table->decimal('subtotal', 15, 2)->default(0); // nominal * quantity
            $table->boolean('condition_good')->default(true); // Keadaan Baik
            $table->boolean('condition_bad')->default(false); // Keadaan Rusak
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_audit_items');
        Schema::dropIfExists('physical_audits');
    }
};
