<?php

namespace Vendor\LaravelChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\LaravelChat\Http\Requests\StoreChatMessageRequest;
use Vendor\LaravelChat\Models\ChatMessage;
use Vendor\LaravelChat\Models\Conversation;

class ChatController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'package' => 'laravel-chat',
            'route_prefix' => config('chat.routes.prefix', 'messages'),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user?->getAuthIdentifier() ?? $request->user()?->id))
            ->with('messages')
            ->latest('updated_at')
            ->get();

        return response()->json($conversations);
    }

    public function messages(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()->with('author')->latest('created_at')->get();

        return response()->json($messages);
    }

    public function store(StoreChatMessageRequest $request): JsonResponse
    {
        $message = ChatMessage::query()->create([
            'conversation_id' => $request->input('conversation_id'),
            'user_id' => $request->user()?->getAuthIdentifier() ?? $request->user()?->id,
            'body' => $request->input('body'),
            'type' => $request->input('type', 'text'),
        ]);

        return response()->json([
            'message' => $message->load('author'),
        ], 201);
    }
}
