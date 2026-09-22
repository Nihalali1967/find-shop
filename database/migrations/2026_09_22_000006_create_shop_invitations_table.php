<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('intended_phone', 20)->index();
            $table->string('claim_token_hash', 64)->unique();
            $table->json('profile')->nullable();
            $table->string('intended_status', 32)->default('active');
            $table->integer('created_by_admin_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->integer('shop_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_invitations');
    }
};
