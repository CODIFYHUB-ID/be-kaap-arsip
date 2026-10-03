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
        Schema::table('document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('document_templates', 'kop_type')) {
                $table->string('kop_type')->default('biasa')->after('default_letterhead_id');
            }
            if (!Schema::hasColumn('document_templates', 'number_format')) {
                $table->string('number_format')->nullable()->after('kop_type');
            }
            if (!Schema::hasColumn('document_templates', 'opening_text')) {
                $table->text('opening_text')->nullable()->after('number_format');
            }
            if (!Schema::hasColumn('document_templates', 'scope_text')) {
                $table->text('scope_text')->nullable()->after('opening_text');
            }
            if (!Schema::hasColumn('document_templates', 'closing_text')) {
                $table->text('closing_text')->nullable()->after('scope_text');
            }
            if (!Schema::hasColumn('document_templates', 'signatory_city')) {
                $table->string('signatory_city')->default('Medan')->after('closing_text');
            }
            if (!Schema::hasColumn('document_templates', 'signatory_name')) {
                $table->string('signatory_name')->default('Rizki Syahputra, SE, M.Si, CPA')->after('signatory_city');
            }
            if (!Schema::hasColumn('document_templates', 'signatory_title')) {
                $table->string('signatory_title')->default('Partner')->after('signatory_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn([
                'kop_type',
                'number_format',
                'opening_text',
                'scope_text',
                'closing_text',
                'signatory_city',
                'signatory_name',
                'signatory_title',
            ]);
        });
    }
};
