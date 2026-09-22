<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('slug');
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'name_key']);
            $table->unique(['category_id', 'slug']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('requires_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('hex', 9)->nullable();
            $table->string('swatch_class', 64)->nullable();
            $table->boolean('is_multicolor')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('search_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('term')->index();
            $table->string('canonical')->index();
            $table->string('type', 24)->default('alias');
            $table->unsignedTinyInteger('weight')->default(5);
            $table->timestamps();

            $table->unique(['term', 'canonical']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_aliases');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('units');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('categories');
    }
};
