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
        // 1. Create auditor_assignments table
        if (!Schema::hasTable('auditor_assignments')) {
            Schema::create('auditor_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('mitra_id')->constrained('mitras')->cascadeOnDelete();
                $table->string('tahun_buku', 10)->index();
                $table->string('role_in_team', 50)->default('Senior Auditor'); // Senior Auditor, Junior Auditor, Team Leader
                $table->string('status', 30)->default('active')->index(); // active, completed
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['auditor_id', 'mitra_id', 'tahun_buku'], 'auditor_assignment_unique');
            });
        }

        // 2. Add working paper (KKP) columns to documents
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'is_working_paper')) {
                $table->boolean('is_working_paper')->default(false)->after('is_final_archive')->index();
            }
            if (!Schema::hasColumn('documents', 'review_status')) {
                $table->string('review_status', 30)->default('draft')->after('is_working_paper')->index(); // draft, in_review, reviewed
            }
            if (!Schema::hasColumn('documents', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('documents', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });

        // 3. Add confirmation letter tracking columns to letters
        Schema::table('letters', function (Blueprint $table) {
            if (!Schema::hasColumn('letters', 'confirmation_type')) {
                $table->string('confirmation_type', 50)->nullable()->index(); // bank, piutang, utang, lainnya
            }
            if (!Schema::hasColumn('letters', 'confirmation_status')) {
                $table->string('confirmation_status', 50)->nullable()->index(); // draft, sent, pending_reply, replied, exception
            }
            if (!Schema::hasColumn('letters', 'third_party_name')) {
                $table->string('third_party_name', 150)->nullable();
            }
            if (!Schema::hasColumn('letters', 'confirmation_reply_date')) {
                $table->date('confirmation_reply_date')->nullable();
            }
            if (!Schema::hasColumn('letters', 'exception_notes')) {
                $table->text('exception_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditor_assignments');

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
            }
            $colsToDrop = [];
            foreach (['is_working_paper', 'review_status', 'reviewed_by', 'reviewed_at', 'review_notes'] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        Schema::table('letters', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['confirmation_type', 'confirmation_status', 'third_party_name', 'confirmation_reply_date', 'exception_notes'] as $col) {
                if (Schema::hasColumn('letters', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }
};
