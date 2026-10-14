<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Vendor\LaravelChat\Contracts\ChatUserResolver;
use Vendor\LaravelChat\Http\Middleware\EnsureAuthenticatedChatUser;
use Vendor\LaravelChat\Services\ChatWorkspaceService;

class ChatWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chat.database.connection' => 'chat',
            'chat.auth.user_model' => ChatWorkspaceTestUser::class,
            'database.connections.chat' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.connections.chat_users' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('chat');
        DB::purge('chat_users');
        Schema::connection('chat_users')->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
        foreach (glob(dirname(__DIR__).'/database/migrations/*.php') as $migrationPath) {
            $migration = include $migrationPath;
            $migration->up();
        }

        config(['chat.auth.resolver' => ChatWorkspaceTestUserResolver::class]);
        Auth::forgetGuards();
    }

    protected function tearDown(): void
    {
        ChatWorkspaceTestUserResolver::$user = null;
        DB::purge('chat');
        DB::purge('chat_users');

        parent::tearDown();
    }

    public function test_dev_identity_without_an_authenticated_guard_is_rejected(): void
    {
        ChatWorkspaceTestUserResolver::$user = new GenericUser([
            'id' => 123,
            'name' => 'Chat Test User',
        ]);
        $request = Request::create('/messages');
        $request->attributes->set('user', (object) ['user_id' => 123]);

        try {
            (new EnsureAuthenticatedChatUser)->handle($request, fn () => null);
            $this->fail('Expected requests without an authenticated guard user to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
    }

    public function test_resolved_user_is_set_on_auth_and_request(): void
    {
        $user = new GenericUser([
            'id' => 123,
            'name' => 'Chat Test User',
        ]);
        ChatWorkspaceTestUserResolver::$user = $user;

        $resolvedUser = null;
        Auth::setUser($user);
        $response = (new EnsureAuthenticatedChatUser)->handle(
            Request::create('/messages'),
            function (Request $request) use (&$resolvedUser) {
                $resolvedUser = $request->user();

                return response('passed');
            }
        );

        $this->assertSame('passed', $response->getContent());
        $this->assertSame($user, $resolvedUser);
        $this->assertSame($user, Auth::user());
        $this->assertSame([
            'web',
            'auth',
            EnsureAuthenticatedChatUser::class,
        ], config('chat.routes.middleware'));
    }

    public function test_each_user_gets_a_private_notes_conversation(): void
    {
        $chat = app(ChatWorkspaceService::class);
        $firstNotes = $chat->ensurePrivateNotes(101);
        $sameNotes = $chat->ensurePrivateNotes(101);
        $secondNotes = $chat->ensurePrivateNotes(202);

        $this->assertSame($firstNotes->id, $sameNotes->id);
        $this->assertNotSame($firstNotes->id, $secondNotes->id);
        $this->assertSame([101], $firstNotes->participants()->pluck('user_id')->all());
        $this->assertSame([202], $secondNotes->participants()->pluck('user_id')->all());
        $this->assertSame('notes', $firstNotes->type);
    }

    public function test_group_creator_can_add_members_and_members_are_not_owners(): void
    {
        $chat = app(ChatWorkspaceService::class);
        $group = $chat->createConversation('Operations', 'group', 101, [202, 303]);
        $chat->addMember($group, 404);

        $this->assertTrue($chat->isOwner($group, 101));
        $this->assertFalse($chat->isOwner($group, 202));
        $this->assertEqualsCanonicalizing(
            [101, 202, 303, 404],
            $group->participants()->pluck('user_id')->all(),
        );
    }

    public function test_authenticated_user_can_search_users_and_create_a_group(): void
    {
        $owner = ChatWorkspaceTestUser::query()->create([
            'name' => 'Alex Owner',
            'email' => 'owner@example.test',
        ]);
        $firstMember = ChatWorkspaceTestUser::query()->create([
            'name' => 'Bailey Member',
            'email' => 'bailey@example.test',
        ]);
        $secondMember = ChatWorkspaceTestUser::query()->create([
            'name' => 'Casey Member',
            'email' => 'casey@example.test',
        ]);

        $controller = app(\Vendor\LaravelChat\Http\Controllers\ChatWorkspaceController::class);
        $searchResponse = $controller->users($this->requestAs($owner, 'GET', [
            'q' => 'Bailey',
        ]));
        $this->assertSame(200, $searchResponse->getStatusCode());
        $this->assertSame([
            ['id' => $firstMember->id, 'name' => 'Bailey Member'],
        ], $searchResponse->getData(true));

        $response = $controller->createGroup($this->requestAs($owner, 'POST', [
            'name' => 'Operations',
            'member_ids' => [$firstMember->id, $secondMember->id],
        ]));
        $this->assertSame(201, $response->getStatusCode());
        $conversationId = $response->getData(true)['conversation_id'];
        $this->assertEqualsCanonicalizing(
            [$owner->id, $firstMember->id, $secondMember->id],
            \Vendor\LaravelChat\Models\Conversation::query()
                ->findOrFail($conversationId)
                ->participants()
                ->pluck('user_id')
                ->all(),
        );
    }

    public function test_direct_conversation_is_reused_for_the_same_users(): void
    {
        $owner = ChatWorkspaceTestUser::query()->create([
            'name' => 'Alex Owner',
            'email' => 'owner@example.test',
        ]);
        $member = ChatWorkspaceTestUser::query()->create([
            'name' => 'Bailey Member',
            'email' => 'bailey@example.test',
        ]);

        $controller = app(\Vendor\LaravelChat\Http\Controllers\ChatWorkspaceController::class);
        $firstResponse = $controller->createDirect($this->requestAs($owner, 'POST', [
            'user_id' => $member->id,
        ]));
        $secondResponse = $controller->createDirect($this->requestAs($owner, 'POST', [
            'user_id' => $member->id,
        ]));

        $this->assertSame(
            $firstResponse->getData(true)['conversation_id'],
            $secondResponse->getData(true)['conversation_id'],
        );
    }

    public function test_only_group_owner_can_add_members_through_the_workspace_controller(): void
    {
        $owner = ChatWorkspaceTestUser::query()->create([
            'name' => 'Alex Owner',
            'email' => 'owner@example.test',
        ]);
        $member = ChatWorkspaceTestUser::query()->create([
            'name' => 'Bailey Member',
            'email' => 'bailey@example.test',
        ]);
        $newMember = ChatWorkspaceTestUser::query()->create([
            'name' => 'Casey Member',
            'email' => 'casey@example.test',
        ]);
        $conversation = app(ChatWorkspaceService::class)->createConversation(
            'Operations',
            'group',
            $owner->id,
            [$member->id],
        );
        $controller = app(\Vendor\LaravelChat\Http\Controllers\ChatWorkspaceController::class);

        try {
            $controller->addMembers($this->requestAs($member, 'POST', [
                'member_ids' => [$newMember->id],
            ]), $conversation);
            $this->fail('Expected non-owners to be denied group member changes.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $response = $controller->addMembers($this->requestAs($owner, 'POST', [
            'member_ids' => [$newMember->id],
        ]), $conversation);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($conversation->participants()->where('user_id', $newMember->id)->exists());
    }

    private function requestAs(Model $user, string $method, array $parameters): Request
    {
        $request = Request::create('/messages', $method, $parameters);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

class ChatWorkspaceTestUserResolver implements ChatUserResolver
{
    public static ?Authenticatable $user = null;

    public function resolve(Request $request): mixed
    {
        return self::$user;
    }
}

class ChatWorkspaceTestUser extends \Illuminate\Foundation\Auth\User
{
    protected $connection = 'chat_users';

    protected $table = 'users';

    protected $guarded = [];
}
