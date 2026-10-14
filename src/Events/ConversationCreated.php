<?php

namespace Vendor\LaravelChat\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Vendor\LaravelChat\Models\Conversation;

class ConversationCreated
{
    use Dispatchable;

    public function __construct(public Conversation $conversation)
    {
    }
}
