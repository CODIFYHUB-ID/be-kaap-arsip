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
        if (!Schema::hasTable('document_requests')) {
            Schema::create('document_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 50)->unique(); // REQ-2026-0001
                $table->foreignId('mitra_id')->constrained('mitras')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->string('tahun_buku', 10)->index();
                $table->date('due_date')->nullable();
                $table->string('priority', 20)->default('medium')->index(); // low, medium, high, urgent
                $table->string('status', 30)->default('requested')->index(); // requested, client_uploaded, approved, revision_needed
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
