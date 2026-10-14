<?php

namespace Vendor\LaravelChat\Services;

use Vendor\LaravelChat\Models\Conversation;

class ConversationService
{
    public function createConversation(array $attributes): Conversation
    {
        return Conversation::query()->create($attributes);
    }

    public function addParticipant(Conversation $conversation, int $userId): void
    {
        $conversation->participants()->firstOrCreate([
            'user_id' => $userId,
        ]);
    }
}
