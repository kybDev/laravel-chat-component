<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('chat.database.connection', 'chat'))->create('chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations');
            $table->foreignId('message_id')->nullable()->constrained('chat_messages');
            $table->string('type')->default('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('chat.database.connection', 'chat'))->dropIfExists('chat_notifications');
    }
};
