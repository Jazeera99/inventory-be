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
        Schema::create('product_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('product_sku')->index();
            $table->foreign('product_sku')->references('sku')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('rack_id')->constrained();
            $table->integer('qty')->default(0);
            $table->string('batch_code')->index();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->date('expired_at')->nullable();
            $table->enum('status', ['AVAILABLE', 'QUARANTINE', 'EXPIRED_RETUR'])->default('AVAILABLE');
            $table->timestamps();
            $table->unique(['product_sku', 'rack_id', 'batch_code'], 'product_locations_primary_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_locations');
    }
};
