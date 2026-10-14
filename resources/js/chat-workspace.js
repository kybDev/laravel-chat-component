(() => {
    const page = document.getElementById('lc-chat-page');
    if (!page) {
        return;
    }

    const searchInput = document.getElementById('lc-user-search');
    const searchStatus = document.getElementById('lc-search-status');
    const results = document.getElementById('lc-user-results');
    const groupForm = document.getElementById('lc-create-group-form');
    const groupName = document.getElementById('lc-group-name');
    const createGroupButton = document.getElementById('lc-create-group-button');
    const groupActions = document.getElementById('lc-group-member-actions');
    const addMembersButton = document.getElementById('lc-add-group-members');
    const workspaceStatus = document.getElementById('lc-workspace-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const selectedUsers = new Map();
    let currentGroupMembers = new Set();
    let canManageCurrentGroup = false;
    let selectedConversationId = null;
    let searchTimer;

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

    const showStatus = (message = '', isError = false) => {
        workspaceStatus.textContent = message;
        workspaceStatus.style.color = isError ? '#b91c1c' : '';
    };

    const selectedMemberIds = (excludeExisting = false) => [...selectedUsers.keys()]
        .filter((id) => !excludeExisting || !currentGroupMembers.has(id));

    const updateActions = () => {
        createGroupButton.disabled = selectedUsers.size < 2;
        addMembersButton.disabled = !canManageCurrentGroup || selectedMemberIds(true).length === 0;
    };

    const renderUsers = (users) => {
        results.replaceChildren();

        users.forEach((user) => {
            const item = document.createElement('li');
            const label = document.createElement('label');
            const checkbox = document.createElement('input');
            const name = document.createElement('span');
            const directButton = document.createElement('button');
            const id = String(user.id);

            checkbox.type = 'checkbox';
            checkbox.checked = selectedUsers.has(id);
            checkbox.setAttribute('aria-label', `Select ${user.name} for a group`);
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    selectedUsers.set(id, user.name);
                } else {
                    selectedUsers.delete(id);
                }
                updateActions();
            });
            name.textContent = user.name;
            label.append(checkbox, name);

            directButton.type = 'button';
            directButton.textContent = 'Message';
            directButton.addEventListener('click', () => startDirect(user.id));
            item.append(label, directButton);
            results.append(item);
        });
    };

    const searchUsers = async () => {
        const query = searchInput.value.trim();
        results.replaceChildren();
        if (query.length < 2) {
            searchStatus.textContent = query ? 'Enter at least 2 characters.' : '';
            return;
        }

        searchStatus.textContent = 'Searching…';
        const url = new URL(page.dataset.userSearchUrl, window.location.href);
        url.searchParams.set('q', query);
        try {
            const users = await requestJson(url.toString());
            renderUsers(users);
            searchStatus.textContent = users.length ? `${users.length} users found.` : 'No users found.';
        } catch (error) {
            searchStatus.textContent = error.status === 401
                ? 'Your session has expired. Sign in again.'
                : 'Could not search users. Please try again.';
            console.error('Could not search chat users.', error);
        }
    };

    const navigateToConversation = (conversationId) => {
        const url = new URL(window.location.href);
        url.searchParams.set('conversation', conversationId);
        window.location.assign(url.toString());
    };

    const startDirect = async (userId) => {
        showStatus();
        try {
            const result = await requestJson(page.dataset.directUrl, {
                method: 'POST',
                body: JSON.stringify({ user_id: userId }),
            });
            navigateToConversation(result.conversation_id);
        } catch (error) {
            showStatus('Could not start the conversation. Please try again.', true);
            console.error('Could not start a direct chat.', error);
        }
    };

    groupForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showStatus();
        try {
            const result = await requestJson(page.dataset.groupUrl, {
                method: 'POST',
                body: JSON.stringify({
                    name: groupName.value.trim(),
                    member_ids: selectedMemberIds(),
                }),
            });
            navigateToConversation(result.conversation_id);
        } catch (error) {
            showStatus('Choose a group name and at least two different users.', true);
            console.error('Could not create chat group.', error);
        }
    });

    const loadGroupMembers = async (conversationId) => {
        selectedConversationId = String(conversationId);
        currentGroupMembers = new Set();
        canManageCurrentGroup = false;
        groupActions.hidden = true;
        updateActions();

        const infoUrl = page.dataset.groupMembersInfoUrl.replace(
            '__ID__',
            encodeURIComponent(conversationId),
        );
        try {
            const data = await requestJson(infoUrl);
            currentGroupMembers = new Set(data.member_ids.map(String));
            canManageCurrentGroup = data.can_manage;
            groupActions.hidden = !canManageCurrentGroup;
            updateActions();
        } catch (error) {
            console.error('Could not load chat group members.', error);
        }
    };

    addMembersButton.addEventListener('click', async () => {
        if (!selectedConversationId || !canManageCurrentGroup) {
            return;
        }
        showStatus();
        const url = page.dataset.groupMembersUrl.replace(
            '__ID__',
            encodeURIComponent(selectedConversationId),
        );

        try {
            await requestJson(url, {
                method: 'POST',
                body: JSON.stringify({ member_ids: selectedMemberIds(true) }),
            });
            showStatus('Members added.');
            await loadGroupMembers(selectedConversationId);
        } catch (error) {
            showStatus(error.status === 403
                ? 'Only the group creator can add members.'
                : 'Could not add the selected members. Please try again.', true);
            console.error('Could not add chat group members.', error);
        }
    });

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchUsers, 250);
    });

    document.addEventListener('laravel-chat:conversation-opened', (event) => {
        const { id, type } = event.detail;
        selectedConversationId = String(id);
        if (type === 'group') {
            loadGroupMembers(id);
        } else {
            groupActions.hidden = true;
            canManageCurrentGroup = false;
            updateActions();
        }
    });

    updateActions();
})();
