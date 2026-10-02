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
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('input_mode', 20)->default('manual')->after('receipt_number'); // manual, upload
            $table->string('category_transaction', 100)->nullable()->after('payer_name');
            $table->string('bank_account_destination', 150)->nullable()->after('payment_method');
            $table->decimal('subtotal', 15, 2)->default(0)->after('transaction_date');
            $table->decimal('tax_pph23_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('tax_pph23_amount', 15, 2)->default(0)->after('tax_pph23_percent');
            $table->decimal('tax_ppn_percent', 5, 2)->default(0)->after('tax_pph23_amount');
            $table->decimal('tax_ppn_amount', 15, 2)->default(0)->after('tax_ppn_percent');
            $table->text('terbilang')->nullable()->after('amount');
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->integer('item_order')->default(1);
            $table->string('expense_category', 100);
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->string('unit', 50)->default('Paket');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_items');

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn([
                'input_mode',
                'category_transaction',
                'bank_account_destination',
                'subtotal',
                'tax_pph23_percent',
                'tax_pph23_amount',
                'tax_ppn_percent',
                'tax_ppn_amount',
                'terbilang',
            ]);
        });
    }
};
