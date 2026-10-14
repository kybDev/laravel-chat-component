<?php

namespace Vendor\LaravelChat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatParticipant extends Model
{
    protected $table = 'chat_participants';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = config('chat.database.connection', 'chat');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function unreadMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')
            ->whereColumn('chat_messages.user_id', '!=', 'chat_participants.user_id')
            ->where(function ($query) {
                $query->whereNull('chat_participants.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_participants.last_read_at');
            });
    }
}
