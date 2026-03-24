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
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('product_sku');
            $table->foreign('product_sku')->references('sku')->on('products');
            $table->string('transaction_ref');
            $table->enum('type', ['MASUK', 'KELUAR', 'ADJ', 'PINDAH']);
            $table->foreignId('rack_id')->constrained('racks');
            $table->integer('quantity');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->foreignId('user_id')->constrained('users');
            $table->string('user_name_snapshot');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
