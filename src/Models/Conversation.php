<?php

namespace Vendor\LaravelChat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $table = 'chat_conversations';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = config('chat.database.connection', 'chat');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')
            ->ofMany(['created_at' => 'max', 'id' => 'max']);
    }
}
