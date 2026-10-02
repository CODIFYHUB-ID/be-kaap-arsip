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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('approved')->after('uploaded_by')->index(); // pending, approved, rejected
            $table->boolean('is_final_archive')->default(false)->after('approval_status')->index();
            $table->foreignId('approved_by')->nullable()->after('is_final_archive')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'is_final_archive',
                'approved_by',
                'approved_at',
                'approval_notes',
            ]);
        });
    }
};
