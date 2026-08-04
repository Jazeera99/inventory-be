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
        Schema::create('racks', function (Blueprint $table): void {
            $table->id();
            $table->string('location_code')->unique();
            $table->string('rack_name');
            $table->integer('column_number');
            $table->integer('level_number');
            $table->integer('capacity')->default(15);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_maintenance')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
