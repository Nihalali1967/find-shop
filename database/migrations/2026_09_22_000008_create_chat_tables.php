<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id')->nullable();
            $table->integer('client_id');
            $table->integer('shop_id');
            $table->json('context_snapshot')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['client_id', 'product_id']);
            $table->index(['shop_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->integer('conversation_id');
            $table->string('participant_type', 16);
            $table->unsignedBigInteger('participant_id');
            $table->integer('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'participant_type', 'participant_id'], 'participants_unique');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->integer('conversation_id');
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id');
            $table->string('client_message_id', 64)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->unique(['conversation_id', 'sender_type', 'client_message_id'], 'messages_idempotency');
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->string('event_key', 120);
            $table->string('type', 48);
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['recipient_type', 'recipient_id', 'event_key'], 'notifications_dedupe');
            $table->index(['recipient_type', 'recipient_id', 'read_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
