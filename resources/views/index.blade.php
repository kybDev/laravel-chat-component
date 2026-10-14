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
</body>
</html>
