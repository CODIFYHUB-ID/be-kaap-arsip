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
        // 1. Add user_id and created_by to mitras table
        Schema::table('mitras', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        // 2. Add mitra_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('mitra_id')->nullable()->after('role_id')->constrained('mitras')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['mitra_id']);
            $table->dropColumn('mitra_id');
        });

        Schema::table('mitras', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['user_id', 'created_by']);
        });
    }
};
