<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('chat.database.connection', 'chat'))->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations');
            $table->unsignedBigInteger('user_id');
            $table->text('body')->nullable();
            $table->string('type')->default('text');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection(config('chat.database.connection', 'chat'))->dropIfExists('chat_messages');
    }
};
