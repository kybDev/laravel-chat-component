<aside
    class="lc-notification-widget"
    id="lc-notification-widget"
    data-updates-url="{{ route('chat.updates') }}"
    data-chat-url="{{ route('chat.index') }}"
    aria-label="Message notifications"
>
    <section class="lc-notification-panel" id="lc-notification-panel" aria-label="Message notifications" hidden>
        <header class="lc-notification-panel-header">
            <div>
                <p class="lc-eyebrow">MESSAGES</p>
                <h2>Notifications</h2>
            </div>
            <button class="lc-panel-close" id="lc-panel-close" type="button" aria-label="Close notifications">×</button>
        </header>
        <p class="lc-notification-empty" id="lc-notification-empty">You are all caught up.</p>
        <ul class="lc-notification-list" id="lc-notification-list" aria-live="polite"></ul>
        <footer class="lc-notification-panel-footer">
            <button class="lc-sound-toggle" id="lc-sound-toggle" type="button" aria-pressed="false">
                Turn sound on
            </button>
            <a class="lc-open-messages" href="{{ route('chat.index') }}">Open messages</a>
        </footer>
    </section>

    <button
        class="lc-bell-trigger"
        id="lc-bell-trigger"
        type="button"
        aria-label="Open message notifications"
        aria-controls="lc-notification-panel"
        aria-expanded="false"
        title="Drag to move; click to open messages"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
        </svg>
        <span class="lc-unread-badge" id="lc-unread-badge" hidden>0</span>
    </button>
    <div class="lc-toast-stack" id="lc-toast-stack" role="status" aria-live="polite"></div>
</aside>

<script src="{{ route('chat.assets.js') }}" defer></script>
