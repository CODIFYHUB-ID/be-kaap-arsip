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
        Schema::table('letters', function (Blueprint $table) {
            $table->string('sender')->nullable()->after('subject');
            $table->string('recipient')->nullable()->after('sender');
            $table->text('disposition')->nullable()->after('description');
            $table->string('status', 20)->default('final')->index()->after('disposition'); // draft, final
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->string('receipt_type', 30)->default('pelunasan')->index()->after('receipt_number'); // dp, termin, pelunasan, operasional
            $table->string('payment_method', 30)->default('transfer')->after('receipt_type'); // transfer, cash, giro
            $table->string('payer_name')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['sender', 'recipient', 'disposition', 'status']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['receipt_type', 'payment_method', 'payer_name']);
        });
    }
};
