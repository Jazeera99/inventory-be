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
        Schema::create('stock_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_order_id')->constrained('stock_orders')->onDelete('cascade');
            $table->string('product_sku');
            $table->foreign('product_sku')->references('sku')->on('products')->onDelete('cascade');
            $table->integer('qty_ordered');   // Minta 1.000
            $table->integer('qty_fulfilled')->default(0); // Baru terkirim/terima 600
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_order_items');
    }
};
