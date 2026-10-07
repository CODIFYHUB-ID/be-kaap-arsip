<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan kategori Surat Tugas sudah ada di database
        Category::firstOrCreate(
            ['name' => 'Surat Tugas'],
            ['description' => 'Surat tugas pemeriksaan tim auditor']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
