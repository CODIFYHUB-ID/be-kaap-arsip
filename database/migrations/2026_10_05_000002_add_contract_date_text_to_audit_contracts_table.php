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
        if (Schema::hasTable('audit_contracts')) {
            Schema::table('audit_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('audit_contracts', 'contract_date_text')) {
                    $table->string('contract_date_text')->nullable()->after('contract_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_contracts')) {
            Schema::table('audit_contracts', function (Blueprint $table) {
                if (Schema::hasColumn('audit_contracts', 'contract_date_text')) {
                    $table->dropColumn('contract_date_text');
                }
            });
        }
    }
};
