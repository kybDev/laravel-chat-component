<?php

namespace Vendor\LaravelChat\Services;

use Illuminate\Support\Facades\DB;
use Vendor\LaravelChat\Models\Conversation;

class ChatWorkspaceService
{
    public function ensurePrivateNotes(int|string $userId): Conversation
    {
        return DB::connection(config('chat.database.connection', 'chat'))->transaction(function () use ($userId) {
            $conversation = Conversation::query()->firstOrCreate(
                ['slug' => 'private-notes-'.$userId],
                ['subject' => 'My Notes', 'type' => 'notes'],
            );

            $conversation->participants()->firstOrCreate(
                ['user_id' => $userId],
                ['role' => 'owner'],
            );

            return $conversation;
        });
    }

    /**
     * @param  list<int|string>  $memberIds
     */
    public function createConversation(string $subject, string $type, int|string $ownerId, array $memberIds): Conversation
    {
        return DB::connection(config('chat.database.connection', 'chat'))->transaction(function () use ($subject, $type, $ownerId, $memberIds) {
            $conversation = Conversation::query()->create([
                'subject' => $subject,
                'type' => $type,
            ]);

            $conversation->participants()->create([
                'user_id' => $ownerId,
                'role' => 'owner',
            ]);

            foreach (array_unique($memberIds) as $memberId) {
                if ((string) $memberId === (string) $ownerId) {
                    continue;
                }

                $conversation->participants()->firstOrCreate(
                    ['user_id' => $memberId],
                    ['role' => 'member'],
                );
            }

            return $conversation;
        });
    }

    public function addMember(Conversation $conversation, int|string $userId): void
    {
        $conversation->participants()->firstOrCreate(
            ['user_id' => $userId],
            ['role' => 'member'],
        );
    }

    public function isOwner(Conversation $conversation, int|string $userId): bool
    {
        return $conversation->participants()
            ->where('user_id', $userId)
            ->where('role', 'owner')
            ->exists();
    }
}
