# Laravel Chat Component

`kybdev/laravel-chat-component` is a Laravel 11 package that provides user search, direct and group conversations, private per-user notes, participant-scoped messages, unread counts, and a draggable notification bell. Its controllers, services, middleware, routes, migrations, models, views, and assets live in the package.

> **Notification delivery:** The bell polls the package endpoint every five seconds while the page is visible. Sound is opt-in because browsers require a user gesture before playing audio. This does not require a separate WebSocket service.

## Requirements

- PHP 8.2 or newer
- Laravel 11
- Composer

## Installation

Install the package in the Laravel application's root directory:

```sh
composer require kybdev/laravel-chat-component
```

Laravel discovers the package service provider automatically. To check discovery and the installed version:

```sh
composer show kybdev/laravel-chat-component
php artisan package:discover
```

The package merges its default configuration at `config/chat.php`. Publish that file if you want to customize it in the host application:

```sh
php artisan vendor:publish --tag=laravel-chat-config
```

Publishing is optional. If published, edit the host application's `config/chat.php`; that file takes precedence over the package defaults.

## Configure the chat database

By default, the package uses a Laravel database connection named `chat`. Add that connection in the host application's `config/database.php`. For example, to use a separate SQLite database:

```php
'connections' => [
    // Other connections...

    'chat' => [
        'driver' => 'sqlite',
        'database' => database_path('chat.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
],
```

Create the SQLite file if it does not already exist:

```sh
# macOS/Linux
touch database/chat.sqlite
```

On Windows PowerShell:

```powershell
New-Item -ItemType File -Path database\chat.sqlite -Force
```

For MySQL, define the `chat` connection with the appropriate `driver`, `host`, `port`, `database`, `username`, `password`, `charset`, and `collation` values instead.

The connection name can be changed with `CHAT_DB_CONNECTION` in the environment (default: `chat`). Make sure it exactly matches the connection key in `config/database.php`.

## Run the package migrations

The service provider automatically loads the package migrations. Run them against the configured chat connection:

```sh
php artisan migrate --database=chat --path=vendor/kybdev/laravel-chat-component/database/migrations
```

The explicit migration path ensures only package tables are created on the chat connection instead of running the host application's migrations there too. If you changed `CHAT_DB_CONNECTION`, use that connection name instead of `chat`. The migrations create:

- `chat_conversations`
- `chat_participants`
- `chat_messages`
- `chat_attachments`
- `chat_notifications`

The package models and migrations use `chat.database.connection` for their connection. The default is `chat`.

## Authentication and user resolution

The default route middleware is `web,auth` and the package's `EnsureAuthenticatedChatUser`. Every package endpoint requires an authenticated Laravel guard user; development request attributes alone do not grant access. The package's `EnsureChatUser` also resolves the current user when storing a message.

The default resolver, `Vendor\LaravelChat\Support\DefaultChatUserResolver`, tries Laravel's authenticated user first, then the request's user, and then user identifiers in request `user` or `acl` attributes. When resolving an identifier from request attributes, it loads the configured user model.

Set `CHAT_USER_MODEL` to the fully qualified class name of the host application's user model if it is not Laravel's configured user model. To provide custom user resolution, implement `Vendor\LaravelChat\Contracts\ChatUserResolver`, then set `CHAT_AUTH_RESOLVER` to the resolver class name. For example:

```dotenv
CHAT_USER_MODEL=App\Models\User
CHAT_AUTH_RESOLVER=App\Chat\MyChatUserResolver
```

Set `CHAT_USER_MODEL` to the host application's authenticatable Eloquent model. The selected model must have a primary key compatible with the package's unsigned-big-integer `user_id` columns. The chat tables do not create foreign keys to the host application's users table.

## User search and conversations

The Messages page searches the configured user model's available `name`, `first_name`, `last_name`, `username`, and `email` columns. It returns at most 20 matches and excludes the current user. Starting a direct conversation reuses an existing conversation with the same two participants.

Create a group by selecting at least two other users and a group name. The creator is added as owner; only that owner can add members later. Groups support up to 50 participants.

The first time a user opens Messages, the package creates a private **My Notes** conversation. It contains only that user's participant record and is pinned first in that user's list.

## Add the floating notification bell to the host layout

The package messages page includes the bell automatically. To show it on every page in the host application, load its stylesheet in the authenticated layout's `<head>` and include the widget in the body (for example, at the bottom of the sidebar component):

```blade
<link rel="stylesheet" href="{{ route('chat.assets.css') }}">

@include('laravel-chat::components.notification-widget')
```

The widget loads its JavaScript through a package route, and both assets are served by the package, so you do not need to publish or compile them. The bell shows the total unread count, lists unread conversations, and displays an in-page alert when a new message arrives. Click **Turn sound on** once to enable the notification chime; browsers do not allow websites to play sound before the user opts in.

Drag the bell to move it anywhere in the viewport. Its location is saved in that browser's local storage. If the host layout has a sidebar, add a normal route link to `route('chat.index')`; the sample PAIMIS-IMEE sidebar integration uses that route.

## Routes

The default route prefix is `messages`, configurable with `CHAT_ROUTE_PREFIX`.

| Method | Default URL | Route name | Purpose |
| --- | --- | --- | --- |
| GET | `/messages` | `chat.index` | Renders the messages page and conversation list. |
| GET | `/messages/assets/chat.css` | `chat.assets.css` | Serves the package stylesheet. |
| GET | `/messages/assets/chat.js` | `chat.assets.js` | Serves the page and notification-widget JavaScript. |
| GET | `/messages/assets/workspace.js` | `chat.assets.workspace.js` | Serves the package conversation-management UI. |
| GET | `/messages/updates` | `chat.updates` | Returns unread counts and incoming messages since the optional `since` timestamp. |
| GET | `/messages/conversations` | `chat.conversations` | Lists the authenticated user's conversations and unread counts. |
| GET | `/messages/conversations/{conversation}/messages` | `chat.messages` | Lists messages and marks the returned messages as read. |
| POST | `/messages/messages` | `chat.store` | Stores a message in a conversation the user belongs to. |
| GET | `/messages/users?q=...` | `chat.workspace.users` | Searches chat users. |
| POST | `/messages/direct` | `chat.workspace.direct` | Creates or reuses a direct conversation. |
| POST | `/messages/groups` | `chat.workspace.groups` | Creates a group with selected members. |
| GET | `/messages/groups/{conversation}/members` | `chat.workspace.groups.members.index` | Lists group members for a participant. |
| POST | `/messages/groups/{conversation}/members` | `chat.workspace.groups.members` | Adds members as the group owner. |
| GET | `/api/messages/conversations` | `chat.api.conversations` | API route for listing conversations. |
| POST | `/api/messages/messages` | `chat.api.store` | API route for storing a message in a conversation the user belongs to. |

The POST routes accept JSON or form data with these fields:

```json
{
  "conversation_id": 1,
  "body": "Hello",
  "type": "text"
}
```

`conversation_id` is required and must be an integer. `body` is optional text up to 10,000 characters. `type` is optional and must be `text`, `image`, or `file`; it defaults to `text`. The sending user must be a participant in the target conversation.

Successful message creation returns HTTP 201 with a JSON `message` object. The authenticated user's ID is stored as the message author.

### Route middleware

Routes default to `web`, `auth`, and the package's authenticated-user middleware. Configure host-specific guards and user resolution in the published `config/chat.php`; do not remove the authenticated-user check.

## Creating conversations

Host application code can use the package service and models to create conversations and add participants:

```php
use Vendor\LaravelChat\Services\ConversationService;

$chat = app(ConversationService::class);

$conversation = $chat->createConversation([
    'subject' => 'Project discussion',
    'type' => 'group',
]);

$chat->addParticipant($conversation, $firstUserId);
$chat->addParticipant($conversation, $secondUserId);
```

`subject` is optional and `type` defaults to `direct`. Add the intended participants when creating a conversation; the package does not currently create them automatically.

## Optional published resources

The package can also copy its JavaScript and CSS into the host application's resource folders for applications that want to bundle or customize them:

```sh
php artisan vendor:publish --tag=laravel-chat-assets
```

This copies files to `resources/js/vendor/laravel-chat` and `resources/css/vendor/laravel-chat`. Include them in the application's frontend entry points and build them using the host application's frontend tooling if needed. The default package page and notification widget use the package-served assets and need no build step.

The package also provides a Blade template. Its views can be copied to the host application with:

```sh
php artisan vendor:publish --tag=laravel-chat-views
```

The default `/messages` route renders the package page. If you publish and customize its view, preserve the expected page and widget element IDs or update the accompanying JavaScript to match.

## Configuration reference

The default settings are in `config/chat.php`:

| Setting | Environment variable | Default | Notes |
| --- | --- | --- | --- |
| `enabled` | `CHAT_ENABLED` | `true` | Reserved setting; it does not currently disable package routes or behavior. |
| `database.connection` | `CHAT_DB_CONNECTION` | `chat` | Database connection used by package migrations and models. |
| `database.migrations` | `CHAT_MIGRATIONS_PATH` | `database/migrations` | Present in config, but not currently used to select the package migration path. |
| `database.table_prefix` | `CHAT_TABLE_PREFIX` | `chat_` | Present in config; table names are currently hard-coded as `chat_*`. |
| `auth.mode` | `CHAT_AUTH_MODE` | `auto` | Reserved setting; resolution currently uses the configured resolver. |
| `auth.guard` | `CHAT_AUTH_GUARD` | `web` | Guard used by the package authenticated-user middleware. |
| `auth.resolver` | `CHAT_AUTH_RESOLVER` | `Vendor\LaravelChat\Support\DefaultChatUserResolver` | Class used by `EnsureChatUser` to resolve the current user. |
| `auth.user_model` | `CHAT_USER_MODEL` | Laravel's configured user model | Used when resolving a user ID from request attributes and for message author relations. |
| `routes.prefix` | `CHAT_ROUTE_PREFIX` | `messages` | Prefix for the package's web and API URLs. |
| `routes.middleware` | published config | `web,auth,EnsureAuthenticatedChatUser` | Middleware shared by the web and API groups. Keep an authenticated-user check in this list. |
| `storage.disk` | `CHAT_STORAGE_DISK` | `local` | Reserved setting; attachment uploads are not implemented. |
| `storage.max_size_kb` | `CHAT_STORAGE_MAX_SIZE_KB` | `10240` | Reserved setting; attachment uploads are not implemented. |
| `notifications.enabled` | `CHAT_NOTIFICATIONS_ENABLED` | `true` | Reserved setting; notification delivery is not implemented. |
| `notifications.driver` | `CHAT_NOTIFICATIONS_DRIVER` | `database` | Reserved setting; notification delivery is not implemented. |
| `realtime.enabled` | `CHAT_REALTIME_ENABLED` | `true` | Reserved setting; realtime updates are not implemented. |
| `realtime.driver` | `CHAT_REALTIME_DRIVER` | `auto` | Reserved setting; realtime updates are not implemented. |

## Current limitations

- Direct/group conversation creation and group member additions are available through the package's protected workspace routes.
- The page and message endpoints check conversation membership. Group creation is limited to the creator for adding members.
- Attachments are not uploaded or persisted. The page currently sends text messages only.
- Notifications use five-second polling while the page is visible; the package does not broadcast over WebSockets.
- The `chat_notifications` table and notification/realtime configuration values are scaffolding and are not used by the polling widget.
- Table prefix and migration-path settings are present in the config file but are not currently applied.

Host-specific user models, guards, and database connections are configured by the application in `config/chat.php` and `config/database.php`.

## Local package development

For local work on the package alongside a host application, use a Composer path repository in the host application's `composer.json` rather than adding the package source to the host application's PSR-4 autoload rules:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-chat",
      "options": {
        "symlink": true,
        "versions": {
          "kybdev/laravel-chat-component": "0.2.0"
        }
      }
    }
  ],
  "require": {
    "kybdev/laravel-chat-component": "^0.2.0"
  }
}
```

Adjust the path to the package checkout relative to the host application. Then resolve the local package with:

```sh
composer update kybdev/laravel-chat-component
```

The symlink makes package source edits visible to the host application immediately. Remove the path repository from the host application's manifest when returning to Packagist installation.
