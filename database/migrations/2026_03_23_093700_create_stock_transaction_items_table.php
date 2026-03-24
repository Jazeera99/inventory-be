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
        Schema::create('stock_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no');
            $table->foreign('transaction_no')->references('transaction_no')->on('stock_transactions');
            $table->string('product_sku');
            $table->foreign('product_sku')->references('sku')->on('products');
            $table->integer('rack_from_id')->nullable();
            $table->integer('rack_to_id')->nullable();
            $table->integer('quantity');
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
