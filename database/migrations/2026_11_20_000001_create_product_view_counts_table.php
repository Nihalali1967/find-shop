<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per product per day; keeps the table small and popularity queries cheap.
        Schema::create('product_view_counts', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->date('date');
            $table->unsignedInteger('views')->default(0);

            $table->unique(['product_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_view_counts');
    }
};
