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
        Schema::table('mitras', function (Blueprint $table) {
            $table->string('npwp', 50)->nullable()->after('company_name');
            $table->string('bidang_usaha', 255)->nullable()->after('npwp');
            $table->string('direksi', 255)->nullable()->after('bidang_usaha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mitras', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'bidang_usaha', 'direksi']);
        });
    }
};
