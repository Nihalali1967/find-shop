<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->integer('owner_id')->unique();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            $table->string('locality');
            $table->string('pincode', 6)->index();
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('secondary_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'deleted_at']);
        });

        Schema::create('shop_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason', 500)->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_status_events');
        Schema::dropIfExists('shops');
    }
};
