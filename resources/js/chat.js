(() => {
    const widget = document.getElementById('lc-notification-widget');
    const page = document.getElementById('lc-chat-page');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.method === 'POST' && csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...options.headers,
            },
            ...options,
        });

        if (!response.ok) {
            const error = new Error(`Chat request failed (${response.status}).`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    };

    if (widget) {
        const trigger = document.getElementById('lc-bell-trigger');
        const panel = document.getElementById('lc-notification-panel');
        const closeButton = document.getElementById('lc-panel-close');
        const badge = document.getElementById('lc-unread-badge');
        const list = document.getElementById('lc-notification-list');
        const empty = document.getElementById('lc-notification-empty');
        const toastStack = document.getElementById('lc-toast-stack');
        const soundToggle = document.getElementById('lc-sound-toggle');
        const updatesUrl = widget.dataset.updatesUrl;
        const chatUrl = widget.dataset.chatUrl;
        const storageKey = 'laravel-chat-position';
        let cursor = new Date().toISOString();
        let audioContext;
        let dragged = false;
        let pointerStart;
        let isPolling = false;

        const soundEnabled = () => window.localStorage.getItem('laravel-chat-sound') === 'on';
        const updateSoundButton = () => {
            const enabled = soundEnabled();
            soundToggle.textContent = enabled ? 'Turn sound off' : 'Turn sound on';
            soundToggle.setAttribute('aria-pressed', String(enabled));
        };

        const playBell = async () => {
            if (!soundEnabled()) {
                return;
            }

            try {
                const AudioContextType = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextType) {
                    console.warn('Message sound is unavailable because this browser does not support Web Audio.');
                    return;
                }

                audioContext ??= new AudioContextType();
                if (audioContext.state === 'suspended') {
                    await audioContext.resume();
                }

                [880, 1174].forEach((frequency, index) => {
                    const oscillator = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    const start = audioContext.currentTime + index * 0.16;
                    oscillator.type = 'sine';
                    oscillator.frequency.value = frequency;
                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.24);
                    oscillator.connect(gain);
                    gain.connect(audioContext.destination);
                    oscillator.start(start);
                    oscillator.stop(start + 0.25);
                });
            } catch (error) {
                console.warn('Could not play the message notification sound.', error);
            }
        };

        const conversationHref = (conversationId) => {
            const url = new URL(chatUrl, window.location.href);
            url.searchParams.set('conversation', conversationId);
            return url.toString();
        };

        const showToast = (message) => {
            const toast = document.createElement('div');
            const link = document.createElement('a');
            const title = document.createElement('strong');
            const body = document.createElement('span');
            toast.className = 'lc-toast';
            link.href = conversationHref(message.conversation_id);
            title.textContent = message.author || 'New message';
            body.textContent = message.body || 'Sent an attachment';
            link.append(title, body);
            toast.append(link);
            toastStack.prepend(toast);
            window.setTimeout(() => toast.remove(), 9000);
        };

        const renderNotifications = (conversations) => {
            const unread = conversations.filter((conversation) => conversation.unread_count > 0);
            list.replaceChildren();
            empty.hidden = unread.length > 0;

            unread.forEach((conversation) => {
                const item = document.createElement('li');
                const link = document.createElement('a');
                const title = document.createElement('strong');
                const preview = document.createElement('span');
                const count = document.createElement('span');
                item.className = 'lc-notification-item';
                link.href = conversationHref(conversation.id);
                title.textContent = conversation.subject || `Conversation #${conversation.id}`;
                preview.textContent = conversation.latest_message?.body || 'New messages';
                count.textContent = `${conversation.unread_count} unread`;
                link.append(title, preview, count);
                item.append(link);
                list.append(item);
            });
        };

        const applyUpdate = (data, shouldNotify) => {
            const unreadCount = Number(data.unread_count) || 0;
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            badge.hidden = unreadCount === 0;
            const sidebarBadge = document.getElementById('lc-sidebar-unread');
            if (sidebarBadge) {
                sidebarBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                sidebarBadge.hidden = unreadCount === 0;
            }
            renderNotifications(data.conversations || []);

            if (shouldNotify) {
                (data.new_messages || []).forEach(showToast);
                if (data.new_messages?.length) {
                    playBell();
                }
            }

            document.dispatchEvent(new CustomEvent('laravel-chat:updates', { detail: data }));
        };

        const poll = async (shouldNotify = true) => {
            if (isPolling) {
                return;
            }

            isPolling = true;
            const url = new URL(updatesUrl, window.location.href);
            url.searchParams.set('since', cursor);
            try {
                const data = await requestJson(url.toString());
                cursor = data.checked_at || new Date().toISOString();
                applyUpdate(data, shouldNotify);
                widget.dataset.chatConnected = 'true';
            } catch (error) {
                widget.dataset.chatConnected = 'false';
                if (error.status === 401 || error.status === 403) {
                    console.warn('Message notifications stopped because this session is no longer authorized.');
                    return;
                }
                console.warn('Could not refresh message notifications. The package will retry.', error);
            } finally {
                isPolling = false;
            }
        };

        const setPanelOpen = (open) => {
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', String(open));
            widget.classList.toggle('is-panel-below', open && trigger.getBoundingClientRect().top < 300);
        };

        trigger.addEventListener('click', () => {
            if (dragged) {
                dragged = false;
                return;
            }
            setPanelOpen(panel.hidden);
        });
        closeButton.addEventListener('click', () => setPanelOpen(false));
        document.addEventListener('pointerdown', (event) => {
            if (!widget.contains(event.target)) {
                setPanelOpen(false);
            }
        });
        soundToggle.addEventListener('click', async () => {
            window.localStorage.setItem('laravel-chat-sound', soundEnabled() ? 'off' : 'on');
            updateSoundButton();
            if (soundEnabled()) {
                await playBell();
            }
        });

        try {
            const savedPosition = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            if (savedPosition && Number.isFinite(savedPosition.left) && Number.isFinite(savedPosition.top)) {
                widget.style.left = `${savedPosition.left}px`;
                widget.style.top = `${savedPosition.top}px`;
                widget.style.right = 'auto';
                widget.style.bottom = 'auto';
            }
        } catch (error) {
            console.warn('Could not restore the saved message button position.', error);
        }

        trigger.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }
            const rect = widget.getBoundingClientRect();
            pointerStart = {
                id: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                left: rect.left,
                top: rect.top,
                moved: false,
            };
            trigger.setPointerCapture(event.pointerId);
        });
        trigger.addEventListener('pointermove', (event) => {
            if (!pointerStart || pointerStart.id !== event.pointerId) {
                return;
            }
            const deltaX = event.clientX - pointerStart.x;
            const deltaY = event.clientY - pointerStart.y;
            if (!pointerStart.moved && Math.hypot(deltaX, deltaY) < 5) {
                return;
            }

            pointerStart.moved = true;
            dragged = true;
            const left = Math.max(8, Math.min(window.innerWidth - widget.offsetWidth - 8, pointerStart.left + deltaX));
            const top = Math.max(8, Math.min(window.innerHeight - widget.offsetHeight - 8, pointerStart.top + deltaY));
            widget.style.left = `${left}px`;
            widget.style.top = `${top}px`;
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        });
        trigger.addEventListener('pointerup', () => {
            if (pointerStart?.moved) {
                const rect = widget.getBoundingClientRect();
                window.localStorage.setItem(storageKey, JSON.stringify({ left: rect.left, top: rect.top }));
                window.setTimeout(() => {
                    dragged = false;
                }, 0);
            }
            pointerStart = undefined;
        });
        trigger.addEventListener('pointercancel', () => {
            pointerStart = undefined;
        });
        window.addEventListener('resize', () => {
            const rect = widget.getBoundingClientRect();
            const left = Math.max(8, Math.min(window.innerWidth - widget.offsetWidth - 8, rect.left));
            const top = Math.max(8, Math.min(window.innerHeight - widget.offsetHeight - 8, rect.top));
            widget.style.left = `${left}px`;
            widget.style.top = `${top}px`;
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        });

        updateSoundButton();
        poll(false);
        window.setInterval(() => {
            if (!document.hidden) {
                poll(true);
            }
        }, 5000);
    }

    if (!page) {
        return;
    }

    const list = document.getElementById('lc-conversation-list');
    const empty = document.getElementById('lc-empty-conversations');
    const count = document.getElementById('lc-conversation-count');
    const messageList = document.getElementById('lc-message-list');
    const placeholder = document.getElementById('lc-thread-placeholder');
    const threadTitle = document.getElementById('lc-thread-title');
    const form = document.getElementById('lc-compose-form');
    const input = document.getElementById('lc-message-input');
    const sendButton = document.getElementById('lc-send-button');
    const formError = document.getElementById('lc-form-error');
    const connectionState = document.getElementById('lc-connection-state');
    const userId = String(page.dataset.userId);
    let conversations = [];
    let selectedId;

    const setError = (message = '') => {
        formError.textContent = message;
        formError.hidden = !message;
    };

    const renderConversations = () => {
        list.replaceChildren();
        empty.hidden = conversations.length > 0;
        count.textContent = String(conversations.length);

        conversations.forEach((conversation) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            const title = document.createElement('span');
            const unread = document.createElement('span');
            const preview = document.createElement('span');
            item.className = 'lc-conversation-item';
            item.dataset.conversationId = String(conversation.id);
            item.dataset.conversationType = conversation.type;
            button.type = 'button';
            button.className = 'lc-conversation-button';
            button.setAttribute('aria-current', String(String(conversation.id) === String(selectedId)));
            title.className = 'lc-conversation-title';
            title.textContent = conversation.subject || `Conversation #${conversation.id}`;
            unread.className = 'lc-conversation-unread';
            unread.textContent = conversation.unread_count > 99 ? '99+' : String(conversation.unread_count);
            unread.hidden = !conversation.unread_count;
            preview.className = 'lc-conversation-preview';
            preview.textContent = conversation.latest_message?.body || 'No messages yet';
            button.append(title, unread, preview);
            button.addEventListener('click', () => openConversation(conversation.id));
            item.append(button);
            list.append(item);
        });
    };

    const renderMessages = (messages) => {
        messageList.replaceChildren();
        if (!messages.length) {
            placeholder.hidden = false;
            placeholder.textContent = 'No messages yet. Start the conversation.';
            messageList.append(placeholder);
            return;
        }

        placeholder.hidden = true;
        messages.forEach((message) => {
            const article = document.createElement('article');
            const author = document.createElement('span');
            const body = document.createElement('p');
            const time = document.createElement('time');
            const messageAuthor = message.author?.name || 'Participant';
            article.className = `lc-message${String(message.user_id) === userId ? ' is-own' : ''}`;
            author.className = 'lc-message-author';
            author.textContent = messageAuthor;
            body.className = 'lc-message-body';
            body.textContent = message.body || 'Attachment';
            time.className = 'lc-message-time';
            time.dateTime = message.created_at || '';
            time.textContent = message.created_at ? new Date(message.created_at).toLocaleString() : '';
            article.append(author, body, time);
            messageList.append(article);
        });
        messageList.scrollTop = messageList.scrollHeight;
    };

    const openConversation = async (conversationId) => {
        selectedId = String(conversationId);
        renderConversations();
        const conversation = conversations.find((item) => String(item.id) === selectedId);
        document.dispatchEvent(new CustomEvent('laravel-chat:conversation-opened', {
            detail: {
                id: selectedId,
                type: conversation?.type || 'direct',
                role: conversation?.current_user_role || 'member',
            },
        }));
        threadTitle.textContent = conversation?.subject
            || `Conversation #${conversationId}`;
        input.disabled = false;
        sendButton.disabled = false;
        setError();
        const url = page.dataset.messagesUrl.replace('__ID__', encodeURIComponent(conversationId));
        try {
            const messages = await requestJson(url);
            renderMessages(messages);
            if (conversation) {
                conversation.unread_count = 0;
                renderConversations();
            }
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('conversation', conversationId);
            window.history.replaceState({}, '', currentUrl);
        } catch (error) {
            setError(error.status === 404
                ? 'This conversation is unavailable or you are not a participant.'
                : 'Could not load this conversation. Please try again.');
            console.error('Could not load chat conversation.', error);
        }
    };

    const loadConversations = async () => {
        try {
            conversations = await requestJson(page.dataset.conversationsUrl);
            connectionState.textContent = 'Connected';
            renderConversations();
            const requestedId = new URL(window.location.href).searchParams.get('conversation');
            if (requestedId && conversations.some((item) => String(item.id) === requestedId)) {
                await openConversation(requestedId);
            } else if (selectedId && conversations.some((item) => String(item.id) === selectedId)) {
                renderConversations();
            } else if (conversations.length) {
                await openConversation(conversations[0].id);
            }
        } catch (error) {
            connectionState.textContent = 'Connection problem';
            setError('Could not load your conversations. Refresh the page to try again.');
            console.error('Could not load chat conversations.', error);
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedId) {
            return;
        }

        const body = input.value.trim();
        if (!body) {
            return;
        }

        sendButton.disabled = true;
        setError();
        try {
            await requestJson(page.dataset.storeUrl, {
                method: 'POST',
                body: JSON.stringify({
                    conversation_id: Number(selectedId),
                    body,
                    type: 'text',
                }),
            });
            input.value = '';
            await openConversation(selectedId);
            await loadConversations();
        } catch (error) {
            setError(error.status === 422
                ? 'The message is invalid. Please check it and try again.'
                : 'Could not send your message. Please try again.');
            console.error('Could not send chat message.', error);
        } finally {
            sendButton.disabled = !selectedId;
        }
    });

    document.addEventListener('laravel-chat:updates', (event) => {
        const data = event.detail;
        if (!data?.conversations) {
            return;
        }

        conversations = data.conversations;
        renderConversations();
        connectionState.textContent = 'Connected';
        if (selectedId && data.new_messages?.some((message) => String(message.conversation_id) === selectedId)) {
            openConversation(selectedId);
        }
    });

    loadConversations();
})();
