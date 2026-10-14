<?php

namespace Vendor\LaravelChat\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Vendor\LaravelChat\Http\Requests\StoreChatMessageRequest;
use Vendor\LaravelChat\Models\ChatMessage;
use Vendor\LaravelChat\Models\ChatParticipant;
use Vendor\LaravelChat\Models\Conversation;

class ChatController extends Controller
{
    public function index()
    {
        return view('laravel-chat::index');
    }

    public function conversations(Request $request)
    {
        $userId = $request->user()->getAuthIdentifier();

        $participants = ChatParticipant::query()
            ->where('user_id', $userId)
            ->with('conversation.latestMessage.author')
            ->withCount('unreadMessages')
            ->get()
            ->sortBy(fn (ChatParticipant $participant) => $participant->conversation->type === 'notes' ? 0 : 1)
            ->values();

        return response()->json($participants
            ->map(fn (ChatParticipant $participant) => $this->conversationSummary($participant))
            ->values());
    }

    public function updates(Request $request)
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $userId = $request->user()->getAuthIdentifier();
        $checkedAt = now();
        $participants = ChatParticipant::query()
            ->where('user_id', $userId)
            ->with('conversation.latestMessage.author')
            ->withCount('unreadMessages')
            ->get()
            ->sortBy(fn (ChatParticipant $participant) => $participant->conversation->type === 'notes' ? 0 : 1)
            ->values();
        $conversationIds = $participants->pluck('conversation_id');
        $conversations = $participants
            ->map(fn (ChatParticipant $participant) => $this->conversationSummary($participant))
            ->values();
        $incomingMessages = collect();

        if ($conversationIds->isNotEmpty() && isset($validated['since'])) {
            $incomingMessages = ChatMessage::query()
                ->whereIn('conversation_id', $conversationIds)
                ->where('user_id', '!=', $userId)
                ->where('created_at', '>', Carbon::parse($validated['since']))
                ->where('created_at', '<=', $checkedAt)
                ->with(['author', 'conversation'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'conversation_subject' => $message->conversation?->subject,
                    'author' => $message->author?->name ?? 'A participant',
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]);
        }

        return response()->json([
            'checked_at' => $checkedAt->toIso8601String(),
            'unread_count' => $conversations->sum('unread_count'),
            'conversations' => $conversations,
            'new_messages' => $incomingMessages,
        ]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $participant = $this->requireParticipant($conversation, $request->user()->getAuthIdentifier());
        $messages = $conversation->messages()
            ->with('author')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isNotEmpty()) {
            $participant->update([
                'last_read_at' => $messages->last()->created_at,
            ]);
        }

        return response()->json($messages
            ->map(fn (ChatMessage $message) => $this->serializeMessage($message))
            ->values());
    }

    public function store(StoreChatMessageRequest $request)
    {
        $validated = $request->validated();
        $conversation = Conversation::query()->findOrFail($validated['conversation_id']);
        $this->requireParticipant($conversation, $request->user()->getAuthIdentifier());

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'body' => $validated['body'] ?? null,
            'type' => $validated['type'] ?? 'text',
        ]);

        return response()->json([
            'message' => $this->serializeMessage($message->load('author')),
        ], 201);
    }

    public function stylesheet(): BinaryFileResponse
    {
        return response()->file(dirname(__DIR__, 3).'/resources/css/chat.css', [
            'Content-Type' => 'text/css; charset=UTF-8',
        ]);
    }

    public function script(): BinaryFileResponse
    {
        return response()->file(dirname(__DIR__, 3).'/resources/js/chat.js', [
            'Content-Type' => 'application/javascript; charset=UTF-8',
        ]);
    }

    public function workspaceScript(): BinaryFileResponse
    {
        return response()->file(dirname(__DIR__, 3).'/resources/js/chat-workspace.js', [
            'Content-Type' => 'application/javascript; charset=UTF-8',
        ]);
    }

    private function conversationSummary(ChatParticipant $participant): array
    {
        $conversation = $participant->conversation;
        $latestMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'type' => $conversation->type,
            'current_user_role' => $participant->role,
            'unread_count' => $participant->unread_messages_count,
            'latest_message' => $latestMessage ? [
                'body' => $latestMessage->body,
                'author' => $latestMessage->author?->name ?? 'A participant',
                'created_at' => $latestMessage->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function requireParticipant(Conversation $conversation, int|string $userId): ChatParticipant
    {
        $participant = $conversation->participants()
            ->where('user_id', $userId)
            ->first();

        abort_unless($participant, 404);

        return $participant;
    }

    private function serializeMessage(ChatMessage $message): array
    {
        $author = $message->author;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'type' => $message->type,
            'created_at' => $message->created_at?->toIso8601String(),
            'author' => $author ? [
                'id' => $author->getAuthIdentifier(),
                'name' => $author->name ?? 'Participant',
            ] : null,
        ];
    }
}
