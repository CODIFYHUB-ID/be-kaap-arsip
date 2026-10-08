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
                if (!Schema::hasColumn('audit_contracts', 'signature_stamp_url')) {
                    $table->longText('signature_stamp_url')->nullable()->after('signatory_title');
                }
                if (!Schema::hasColumn('audit_contracts', 'show_signature_stamp')) {
                    $table->boolean('show_signature_stamp')->default(true)->after('signature_stamp_url');
                }
                if (!Schema::hasColumn('audit_contracts', 'signature_stamp_width')) {
                    $table->integer('signature_stamp_width')->default(140)->after('show_signature_stamp');
                }
            });
        }

        if (Schema::hasTable('generated_letters')) {
            Schema::table('generated_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('generated_letters', 'signature_stamp_url')) {
                    $table->longText('signature_stamp_url')->nullable()->after('signatory_title');
                }
                if (!Schema::hasColumn('generated_letters', 'show_signature_stamp')) {
                    $table->boolean('show_signature_stamp')->default(true)->after('signature_stamp_url');
                }
                if (!Schema::hasColumn('generated_letters', 'signature_stamp_width')) {
                    $table->integer('signature_stamp_width')->default(140)->after('show_signature_stamp');
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
                $columns = [];
                if (Schema::hasColumn('audit_contracts', 'signature_stamp_url')) $columns[] = 'signature_stamp_url';
                if (Schema::hasColumn('audit_contracts', 'show_signature_stamp')) $columns[] = 'show_signature_stamp';
                if (Schema::hasColumn('audit_contracts', 'signature_stamp_width')) $columns[] = 'signature_stamp_width';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('generated_letters')) {
            Schema::table('generated_letters', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('generated_letters', 'signature_stamp_url')) $columns[] = 'signature_stamp_url';
                if (Schema::hasColumn('generated_letters', 'show_signature_stamp')) $columns[] = 'show_signature_stamp';
                if (Schema::hasColumn('generated_letters', 'signature_stamp_width')) $columns[] = 'signature_stamp_width';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
