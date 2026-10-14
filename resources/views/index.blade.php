<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Messages</title>
    <link rel="stylesheet" href="{{ route('chat.assets.css') }}">
</head>
<body class="lc-page">
    <main
        class="lc-chat-page"
        id="lc-chat-page"
        data-conversations-url="{{ route('chat.conversations') }}"
        data-messages-url="{{ route('chat.messages', ['conversation' => '__ID__']) }}"
        data-store-url="{{ route('chat.store') }}"
        data-user-id="{{ auth()->user()->getAuthIdentifier() }}"
        data-user-search-url="{{ route('chat.workspace.users') }}"
        data-direct-url="{{ route('chat.workspace.direct') }}"
        data-group-url="{{ route('chat.workspace.groups') }}"
        data-group-members-url="{{ route('chat.workspace.groups.members', ['conversation' => '__ID__']) }}"
        data-group-members-info-url="{{ route('chat.workspace.groups.members.index', ['conversation' => '__ID__']) }}"
    >
        <header class="lc-page-header">
            <div>
                <p class="lc-eyebrow">PAIMIS MESSAGING</p>
                <h1>Messages</h1>
            </div>
            <span class="lc-connection-state" id="lc-connection-state">Connecting…</span>
        </header>

        <section class="lc-chat-layout" aria-label="Chat">
            <aside class="lc-conversation-sidebar">
                <div class="lc-workspace-tools">
                    <h3>Find people</h3>
                    <label class="lc-sr-only" for="lc-user-search">Search users</label>
                    <input id="lc-user-search" type="search" minlength="2" maxlength="100"
                        placeholder="Search by name or email">
                    <p class="lc-workspace-status" id="lc-search-status" role="status"></p>
                    <ul class="lc-user-results" id="lc-user-results"></ul>

                    <form id="lc-create-group-form">
                        <h3>Create a group</h3>
                        <label class="lc-sr-only" for="lc-group-name">Group name</label>
                        <input id="lc-group-name" name="name" maxlength="255" required placeholder="Group name">
                        <button type="submit" id="lc-create-group-button" disabled>Create group with selected users</button>
                    </form>

                    <div class="lc-workspace-group-actions" id="lc-group-member-actions" hidden>
                        <button type="button" id="lc-add-group-members" disabled>Add selected users to this group</button>
                    </div>
                    <p class="lc-workspace-status" id="lc-workspace-status" role="status"></p>
                </div>

                <div class="lc-section-heading">
                    <h2>Conversations</h2>
                    <span id="lc-conversation-count">0</span>
                </div>
                <p class="lc-empty-list" id="lc-empty-conversations" hidden>
                    You do not have any conversations yet.
                </p>
                <ul class="lc-conversation-list" id="lc-conversation-list" aria-label="Your conversations"></ul>
            </aside>

            <section class="lc-thread" aria-label="Selected conversation">
                <header class="lc-thread-header">
                    <div>
                        <p class="lc-eyebrow">CONVERSATION</p>
                        <h2 id="lc-thread-title">Choose a conversation</h2>
                    </div>
                </header>
                <div class="lc-message-list" id="lc-message-list" aria-live="polite">
                    <p class="lc-thread-placeholder" id="lc-thread-placeholder">
                        Select a conversation to read and send messages.
                    </p>
                </div>
                <form class="lc-compose-form" id="lc-compose-form">
                    <label class="lc-sr-only" for="lc-message-input">Write a message</label>
                    <textarea id="lc-message-input" name="body" rows="2" maxlength="10000"
                        placeholder="Write a message…" disabled required></textarea>
                    <button type="submit" id="lc-send-button" disabled>Send</button>
                    <p class="lc-form-error" id="lc-form-error" role="alert" hidden></p>
                </form>
            </section>
        </section>
    </main>

    @include('laravel-chat::components.notification-widget')
    <script src="{{ route('chat.assets.workspace.js') }}" defer></script>
</body>
</html>
