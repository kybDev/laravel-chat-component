<?php

namespace Vendor\LaravelChat\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelChat\Models\Conversation;
use Vendor\LaravelChat\Services\ChatWorkspaceService;

class ChatWorkspaceController extends Controller
{
    public function __construct(private readonly ChatWorkspaceService $chat) {}

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $userModel = $this->userModel();
        $columns = $this->columns($userModel);
        $searchable = array_values(array_intersect(
            ['name', 'first_name', 'last_name', 'email', 'username'],
            $columns,
        ));

        if ($searchable === []) {
            throw new \RuntimeException('The users table has no supported chat search columns.');
        }

        $query = trim($validated['q']);
        $users = $userModel->newQuery()
            ->where(function (Builder $builder) use ($searchable, $query) {
                foreach ($searchable as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($column, 'like', '%'.$query.'%');
                }
            })
            ->whereKeyNot($request->user()->getAuthIdentifier())
            ->limit(20)
            ->get(array_values(array_unique(array_merge([$userModel->getKeyName()], $searchable))));

        return response()->json($users->map(fn (Model $user) => [
            'id' => $user->getAuthIdentifier(),
            'name' => $this->displayName($user, $searchable),
        ]));
    }

    public function createDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = $this->userModel()->newQuery()->findOrFail($validated['user_id']);
        $userId = $request->user()->getAuthIdentifier();

        if ((string) $user->getAuthIdentifier() === (string) $userId) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot start a direct conversation with yourself.',
            ]);
        }

        $columns = $this->columns($user);
        $subject = 'Chat with '.$this->displayName($user, array_values(array_intersect(
            ['name', 'first_name', 'last_name', 'email', 'username'],
            $columns,
        )));

        $conversation = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->getAuthIdentifier()))
            ->whereDoesntHave('participants', fn ($query) => $query
                ->whereNotIn('user_id', [$userId, $user->getAuthIdentifier()]))
            ->first();

        if ($conversation) {
            return response()->json(['conversation_id' => $conversation->id]);
        }

        $conversation = $this->chat->createConversation($subject, 'direct', $userId, [
            $user->getAuthIdentifier(),
        ]);

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'member_ids' => ['required', 'array', 'min:2', 'max:49'],
            'member_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $memberIds = $this->validMemberIds($validated['member_ids']);

        if (in_array((string) $request->user()->getAuthIdentifier(), array_map('strval', $memberIds), true)) {
            throw ValidationException::withMessages([
                'member_ids' => 'Group members should not include you; you are added automatically.',
            ]);
        }

        $conversation = $this->chat->createConversation(
            trim($validated['name']),
            'group',
            $request->user()->getAuthIdentifier(),
            $memberIds,
        );

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function addMembers(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->type === 'group', 404);
        abort_unless($this->chat->isOwner($conversation, $request->user()->getAuthIdentifier()), 403);

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:49'],
            'member_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $memberIds = $this->validMemberIds($validated['member_ids']);
        $currentIds = $conversation->participants()->pluck('user_id')->map('strval')->all();
        $currentIds[] = (string) $request->user()->getAuthIdentifier();

        if (array_intersect(array_map('strval', $memberIds), $currentIds) !== []) {
            throw ValidationException::withMessages([
                'member_ids' => 'Choose only users who are not already in this group.',
            ]);
        }

        if (count(array_unique(array_merge($currentIds, array_map('strval', $memberIds)))) > 50) {
            throw ValidationException::withMessages([
                'member_ids' => 'A group can have at most 50 members.',
            ]);
        }

        DB::connection(config('chat.database.connection', 'chat'))->transaction(function () use ($conversation, $memberIds) {
            foreach ($memberIds as $memberId) {
                $this->chat->addMember($conversation, $memberId);
            }
        });

        return response()->json(['added' => count($memberIds)]);
    }

    public function groupMembers(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->type === 'group', 404);
        abort_unless($conversation->participants()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->exists(), 404);

        return response()->json([
            'can_manage' => $this->chat->isOwner($conversation, $request->user()->getAuthIdentifier()),
            'member_ids' => $conversation->participants()->pluck('user_id'),
        ]);
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    private function validMemberIds(array $memberIds): array
    {
        $userModel = $this->userModel();
        $users = $userModel->newQuery()->whereKey($memberIds)->get([$userModel->getKeyName()]);

        if ($users->count() !== count($memberIds)) {
            throw ValidationException::withMessages([
                'member_ids' => 'One or more selected users no longer exist.',
            ]);
        }

        return $users->map(fn (Model $user) => $user->getKey())->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function displayName(Model $user, array $columns): string
    {
        $first = in_array('first_name', $columns, true) ? trim((string) $user->getAttribute('first_name')) : '';
        $last = in_array('last_name', $columns, true) ? trim((string) $user->getAttribute('last_name')) : '';
        $name = trim($first.' '.$last);

        if ($name !== '') {
            return $name;
        }

        foreach (['name', 'username', 'email'] as $column) {
            if (in_array($column, $columns, true) && filled($value = $user->getAttribute($column))) {
                return (string) $value;
            }
        }

        return 'User #'.$user->getAuthIdentifier();
    }

    private function userModel(): Model
    {
        $class = config('chat.auth.user_model');
        if (! is_string($class) || ! class_exists($class)) {
            throw new \RuntimeException('Configure chat.auth.user_model with the application user model.');
        }

        $model = new $class;
        if (! $model instanceof Model || ! $model instanceof Authenticatable) {
            throw new \RuntimeException('The configured chat user model must be an authenticatable Eloquent model.');
        }

        return $model;
    }

    /**
     * @return list<string>
     */
    private function columns(Model $user): array
    {
        return $user->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($user->getTable());
    }
}
