<?php

namespace Vendor\LaravelChat\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Vendor\LaravelChat\Models\Conversation;

class ConversationPolicy
{
    use HandlesAuthorization;

    public function viewAny($user): bool
    {
        return $user !== null;
    }

    public function view($user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->getAuthIdentifier())->exists();
    }
}
