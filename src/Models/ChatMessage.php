<?php

namespace Vendor\LaravelChat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

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

    public function author(): BelongsTo
    {
        $userModel = config('chat.auth.user_model', config('auth.providers.users.model'));
        if (! is_string($userModel) || ! class_exists($userModel)) {
            throw new \RuntimeException('Configure chat.auth.user_model with the application user model.');
        }

        return $this->belongsTo($userModel, 'user_id');
    }
}
