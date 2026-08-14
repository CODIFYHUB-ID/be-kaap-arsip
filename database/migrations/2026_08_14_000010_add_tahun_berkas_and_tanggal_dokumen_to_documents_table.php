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
            if (! Schema::hasColumn('documents', 'tahun_berkas')) {
                $table->string('tahun_berkas', 10)->nullable()->after('description')->index();
            }
            if (! Schema::hasColumn('documents', 'tanggal_dokumen')) {
                $table->date('tanggal_dokumen')->nullable()->after('tahun_berkas')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'tanggal_dokumen')) {
                $table->dropColumn('tanggal_dokumen');
            }
            if (Schema::hasColumn('documents', 'tahun_berkas')) {
                $table->dropColumn('tahun_berkas');
            }
        });
    }
};
