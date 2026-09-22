<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('shop_id');
            $table->integer('subcategory_id');
            $table->integer('unit_id');
            $table->string('name', 160);
            $table->string('title', 220);
            $table->text('description');
            $table->string('custom_unit', 40)->nullable();
            $table->integer('unit_count')->default(1);
            $table->unsignedBigInteger('price_paise');
            $table->unsignedBigInteger('offer_price_paise')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['shop_id', 'status', 'deleted_at']);
            $table->index(['subcategory_id', 'status', 'deleted_at']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('color_id');
            $table->timestamps();

            $table->unique(['product_id', 'color_id']);
        });

        Schema::create('product_search_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id')->unique();
            $table->longText('document');
            $table->json('field_map');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_documents');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
