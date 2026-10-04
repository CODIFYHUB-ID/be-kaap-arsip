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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('version')->default(1);
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_city', 100)->default('Medan');
            $table->date('invoice_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->boolean('tax_pph23_enabled')->default(true);
            $table->decimal('tax_pph23_percent', 5, 2)->default(2.00);
            $table->decimal('tax_pph23_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('terbilang')->nullable();
            $table->string('total_label')->default('Sisa biaya audit tahun {tahun}');
            $table->string('signatory_city', 100)->default('Medan');
            $table->string('signatory_name')->default('Rizki Syahputra, SE, M.Si, CPA');
            $table->string('signatory_title')->default('Partner');
            $table->string('signatory_signature_url')->nullable();
            $table->json('footer_nb')->nullable();
            $table->string('status', 30)->default('final')->index(); // draft, final, printed, paid
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->integer('item_order')->default(1);
            $table->text('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
