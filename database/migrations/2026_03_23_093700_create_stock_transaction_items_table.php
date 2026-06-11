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
        Schema::create('stock_transaction_items', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_no');
            $table->foreign('transaction_no')->references('transaction_no')->on('stock_transactions');
            $table->string('product_sku');
            $table->foreign('product_sku')->references('sku')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('rack_id')->nullable();
            $table->integer('target_rack_id')->nullable();
            $table->integer('qty');
            $table->integer('qty_before')->nullable();
            $table->integer('qty_after')->nullable();
            $table->date('expired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transaction_items');
    }
};
