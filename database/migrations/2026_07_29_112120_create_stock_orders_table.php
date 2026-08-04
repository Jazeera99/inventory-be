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
        Schema::create('stock_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->unique(); // e.g. PO-202607-001 atau SO-202607-001
            $table->enum('type', ['INBOUND', 'OUTBOUND']);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'PENDING', 'PARTIAL', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->date('order_date');
            $table->date('expected_date')->nullable(); // Tanggal estimasi barang datang/dikirim
            $table->foreignId('parent_id')->nullable()->constrained('stock_orders')->nullOnDelete(); // Melacak turunan Backorder jika dikirim bertahap
            $table->text('cancel_reason')->nullable(); // Catatan jika pesanan di-skip atau di-cancel
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_orders');
    }
};
