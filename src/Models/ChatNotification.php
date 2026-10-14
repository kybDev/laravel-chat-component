<?php

namespace Vendor\LaravelChat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatNotification extends Model
{
    protected $table = 'chat_notifications';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = config('chat.database.connection', 'chat');
    }
}
