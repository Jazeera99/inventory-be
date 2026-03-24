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
        Schema::create('stock_adjustments', function (Blueprint $table) {
           $table->string('adjustment_no')->primary();
            $table->string('product_sku');
            $table->foreign('product_sku')->references('sku')->on('products');
            $table->foreignId('rack_id')->constrained('racks');
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->integer('total_qty');
            $table->dateTime('date');
            $table->foreignId('user_id')->constrained('users');
            $table->string('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
