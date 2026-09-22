<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_grants', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('purpose', 32)->index();
            $table->string('token_hash', 64)->unique();
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_grants');
    }
};
