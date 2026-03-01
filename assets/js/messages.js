// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Utility function to escape HTML
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function () {
    const conversationList = document.getElementById('conversationList');
    const chatContainer = document.getElementById('chatContainer');
    const chatMessages = document.getElementById('chatMessages');
    const chatAvatar = document.querySelector('.chat-avatar');
    const chatUsername = document.querySelector('.chat-username');
    const messageInput = document.getElementById('messageInput');
    const backToConversations = document.getElementById('backToConversations');
    const recipientInput = document.getElementById('recipient');
    const userSearchResults = document.getElementById('userSearchResults');
    const newMessageForm = document.getElementById('newMessageForm');
    const messageForm = document.getElementById('messageForm');
    const messageSearch = document.getElementById('messageSearch');

    let activeConversationUserId = null;
    let conversationsMap = {};
    let selectedRecipientId = null;
    let messagePollInterval = null; // For polling messages in active conversation
    let lastMessageId = null; // Track last message ID to detect new messages

    // Function to fetch conversations
    async function fetchConversations(searchQuery = '') {
        try {
            let url = 'backend/chat/get_conversations.php';
            if (searchQuery) {
                url += `?search=${encodeURIComponent(searchQuery)}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch conversations');
            }

            const data = await response.json();
            renderConversations(data.conversations || []);
        } catch (error) {
            console.error('Error fetching conversations:', error);
            if (conversationList) {
                conversationList.innerHTML = `
                    <div class="p-3 text-center text-muted">
                        <p>Unable to load conversations. Please try again later.</p>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-1"></i> Retry
                        </button>
                    </div>`;
            }
        }
    }

    function renderConversations(convs) {
        conversationList.innerHTML = '';
        if (!convs || convs.length === 0) {
            conversationList.innerHTML = '<div class="p-3 text-muted">No conversations yet.</div>';
            return;
        }
        conversationsMap = {};
        convs.forEach(c => {
            const userId = c.other_user_id || c.user_id;
            conversationsMap[userId] = c;
            const el = document.createElement('div');
            el.className = 'conversation-item';
            el.dataset.conversation = userId;
            let avatar = c.profile_pic || 'assets/img/default-avatar.png';
            if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/') && !avatar.startsWith('assets/')) {
                avatar = './' + avatar;
            }
            el.innerHTML = `
                <img src="${avatar}" alt="${escapeHtml(c.name)}" class="conversation-avatar" onerror="this.onerror=null; this.src='assets/img/default-avatar.png';">
                <div class="conversation-content">
                    <div class="conversation-header">
                        <h6 class="conversation-name" style="color: #222222;">${escapeHtml(c.name)}</h6>
                        <span class="conversation-time" style="color: #666666;">${c.last_message_time ? formatRelativeTime(c.last_message_time) : ''}</span>
                    </div>
                    <p class="conversation-preview" style="color: #666666;">${escapeHtml(c.last_message || 'No messages yet')}</p>
                </div>
                ${(c.unread_count || 0) > 0 ? `<div class="unread-badge">${c.unread_count}</div>` : ''}
            `;
            el.addEventListener('click', function () {
                openConversation(userId);
            });
            conversationList.appendChild(el);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>\"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": "&#39;" }[m]);
        });
    }

    function formatRelativeTime(timeString) {
        if (!timeString) return '';
        const d = new Date(timeString);
        const now = new Date();
        const diff = Math.floor((now - d) / 1000);
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return d.toLocaleDateString();
    }

    // Function to fetch messages for a conversation
    async function fetchMessages(userId) {
        try {
            const resp = await fetch(`backend/chat/get_messages.php?user_id=${userId}`, { credentials: 'include' });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Unable to load messages');
            return data.messages || [];
        } catch (err) {
            console.error('Error fetching messages:', err);
            return [];
        }
    }

    // Function to poll for new messages (check for updates)
    async function pollForNewMessages() {
        if (!activeConversationUserId) return;

        try {
            const messages = await fetchMessages(activeConversationUserId);
            if (!messages || messages.length === 0) return;

            // Check if there's a new message (compare with last known message ID)
            const latestMessage = messages[messages.length - 1];
            if (latestMessage && latestMessage.message_id !== lastMessageId) {
                // Check if we were at the bottom (user is viewing latest messages)
                const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;

                // Re-render messages (will include new ones)
                renderMessages(messages);

                // Update last message ID
                lastMessageId = latestMessage.message_id;

                // Auto-scroll if user was viewing latest messages
                if (wasAtBottom) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }

                // Also update conversation list to reflect new message
                fetchConversations();
            }
        } catch (err) {
            console.error('Error polling messages:', err);
        }
    }

    async function openConversation(userId) {
        try {
            // Stop any existing polling
            if (messagePollInterval) {
                clearInterval(messagePollInterval);
                messagePollInterval = null;
            }

            activeConversationUserId = userId;
            // Mark active in UI
            document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
            const activeItem = document.querySelector(`.conversation-item[data-conversation="${userId}"]`);
            if (activeItem) activeItem.classList.add('active');

            // Fetch messages, show chat header info (use cached conversation for user meta)
            const messages = await fetchMessages(userId);

            // Track last message ID for polling
            if (messages && messages.length > 0) {
                lastMessageId = messages[messages.length - 1].message_id;
            } else {
                lastMessageId = null;
            }

            // Render chat messages
            renderMessages(messages);

            // Set chat header info using conversation cache (if available)
            const conv = conversationsMap[userId] || null;
            const other = conv ? conv.name : (messages && messages.length ? messages[0].sender_name : 'Conversation');
            const avatar = conv ? (conv.profile_pic || 'assets/img/default-avatar.png') : (messages && messages.length ? (messages[0].sender_avatar || 'assets/img/default-avatar.png') : 'assets/img/default-avatar.png');
            if (chatAvatar) {
                chatAvatar.src = avatar;
                chatAvatar.onerror = function () { this.onerror = null; this.src = 'assets/img/default-avatar.png'; };
            }
            if (chatUsername) {
                chatUsername.textContent = other;
                chatUsername.style.color = '#222222';
            }

            // Show chat container and hide no conversation message
            if (chatContainer) {
                chatContainer.classList.remove('d-none');
                chatContainer.classList.add('d-flex'); // Ensure flex display
            }
            const noConversationSelected = document.getElementById('noConversationSelected');
            if (noConversationSelected) noConversationSelected.style.display = 'none';
            if (backToConversations) backToConversations.classList.add('d-md-none');

            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;

            // Start polling for new messages every 3 seconds
            messagePollInterval = setInterval(pollForNewMessages, 3000);
        } catch (err) {
            console.error('Open conversation error', err);
        }
    }

    // Function to send a message
    async function sendMessage() {
        const message = messageInput ? messageInput.value.trim() : '';
        if (!message || !activeConversationUserId) {
            if (!activeConversationUserId) {
                showToast('Please select a conversation first.', 'warning');
            }
            return;
        }

        // Disable the submit button to prevent double submissions
        const submitBtn = messageForm ? messageForm.querySelector('button[type="submit"]') : null;
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch('backend/chat/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    receiver_id: activeConversationUserId,
                    content: message
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to send message');
            }

            // Clear input
            if (messageInput) messageInput.value = '';

            // Refresh messages
            const messages = await fetchMessages(activeConversationUserId);

            // Update last message ID
            if (messages && messages.length > 0) {
                lastMessageId = messages[messages.length - 1].message_id;
            }

            renderMessages(messages);
            await fetchConversations();

            // Auto-scroll to bottom
            if (chatMessages) {
                setTimeout(() => {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 100);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            showToast('Failed to send message: ' + (error.message || 'Please try again.'), 'error');
        } finally {
            // Re-enable the submit button
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // Initialize recipient search functionality
    if (recipientInput && userSearchResults) {
        recipientInput.addEventListener('input', debounce(async (e) => {
            const query = e.target.value.trim();
            selectedRecipientId = null; // Reset selection
            userSearchResults.innerHTML = '';
            userSearchResults.style.display = 'none';

            if (query.length < 2) return;

            try {
                const response = await fetch(`backend/api/search_users.php?q=${encodeURIComponent(query)}`, { credentials: 'include' });
                if (!response.ok) throw new Error('Search failed');

                const users = await response.json();
                if (!users || !Array.isArray(users) || users.length === 0) {
                    userSearchResults.innerHTML = '<div class="p-2 text-muted">No users found</div>';
                    userSearchResults.style.display = 'block';
                    return;
                }

                userSearchResults.innerHTML = users.map(user => `
                    <div class="search-result-item" data-user-id="${user.id}">
                        <img src="${user.avatar || 'assets/img/default-avatar.png'}" class="search-result-avatar">
                        <div>
                            <div class="fw-bold">${escapeHtml(user.name || 'User')}</div>
                            <small class="text-muted">${escapeHtml(user.skills || '')}</small>
                        </div>
                    </div>
                `).join('');

                // Add click handlers to search results
                document.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', function () {
                        const userId = this.dataset.userId;
                        const userName = this.querySelector('.fw-bold').textContent;
                        const selectedRecipientInput = document.getElementById('selectedRecipientId');
                        if (selectedRecipientInput) selectedRecipientInput.value = userId;
                        if (recipientInput) recipientInput.value = userName;
                        if (userSearchResults) userSearchResults.innerHTML = '';
                        selectedRecipientId = userId;
                    });
                });

                userSearchResults.style.display = 'block';
            } catch (error) {
                console.error('Search error:', error);
                userSearchResults.innerHTML = '<div class="p-2 text-muted">Error searching for users</div>';
                userSearchResults.style.display = 'block';
            }
        }, 300));
    }

    // Focus recipient input when modal opens
    const newMessageModal = document.getElementById('newMessageModal');
    if (newMessageModal) {
        newMessageModal.addEventListener('shown.bs.modal', () => {
            if (recipientInput) {
                recipientInput.value = '';
                recipientInput.focus();
            }
            if (userSearchResults) {
                userSearchResults.innerHTML = '';
                userSearchResults.style.display = 'none';
            }
            const messageText = document.getElementById('messageText');
            if (messageText) messageText.value = '';
            selectedRecipientId = null;
        });
    }

    function getCurrentUserId() {
        const u = (window.Auth && window.Auth.getCurrentUser && window.Auth.getCurrentUser()) || null;
        return u ? (u.id || u.user_id || u.userId || null) : null;
    }

    function renderMessages(messages) {
        chatMessages.innerHTML = '';
        if (!messages || messages.length === 0) {
            chatMessages.innerHTML = '<div class="text-center text-muted small">No messages yet. Say hi!</div>';
            return;
        }
        messages.forEach(m => {
            const el = document.createElement('div');
            const currentUserId = getCurrentUserId();
            el.className = `message ${m.sender_id === currentUserId ? 'message-sent' : 'message-received'}`;
            const isSent = m.sender_id === currentUserId;
            el.innerHTML = `
                <div class="message-content">
                    <p class="mb-0" style="color: ${isSent ? '#FFFFFF' : '#222222'} !important;">${escapeHtml(m.content)}</p>
                    <div class="message-time" style="color: ${isSent ? 'rgba(255, 255, 255, 0.9)' : '#666666'} !important;">${new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            `;
            chatMessages.appendChild(el);
        });
    }

    // Handle message form submission (sending messages in active conversation)
    if (messageForm) {
        messageForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            e.stopPropagation();
            await sendMessage();
            return false;
        });
    }

    // Handle new message form submission
    if (newMessageForm) {
        newMessageForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const messageText = document.getElementById('messageText');
            const message = messageText ? messageText.value.trim() : '';

            // Validate input
            if (!selectedRecipientId) {
                showToast('Please select a recipient', 'warning');
                return;
            }

            if (!message) {
                showToast('Please enter a message', 'warning');
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';

                const response = await fetch('backend/chat/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        receiver_id: selectedRecipientId,
                        content: message
                    })
                });

                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.error || 'Failed to send message');
                }

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('newMessageModal'));
                if (modal) modal.hide();

                // Open the conversation
                openConversation(selectedRecipientId);

                // Refresh conversations list
                await fetchConversations();

            } catch (error) {
                console.error('Error sending message:', error);
                showToast('Failed to send message: ' + (error.message || 'Please try again later.'), 'error');
            } finally {
                // Reset button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    }

    // Handle new message button click
    document.querySelectorAll('[data-bs-target="#newMessageModal"]').forEach(button => {
        button.addEventListener('click', function () {
            const modal = new bootstrap.Modal(document.getElementById('newMessageModal'));
            if (recipientInput) recipientInput.value = '';
            if (userSearchResults) userSearchResults.innerHTML = '';
            const messageText = document.getElementById('messageText');
            if (messageText) messageText.value = '';
            modal.show();
        });
    });

    // Recipient search is already handled in the earlier event listener

    // Conversation search/filter
    const searchInput = document.getElementById('messageSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.conversation-item').forEach(it => {
                const label = it.querySelector('.conversation-name') && it.querySelector('.conversation-name').textContent.toLowerCase();
                it.style.display = (!q || (label && label.includes(q))) ? '' : 'none';
            });
        });
    }

    // Cleanup polling when page is hidden (save resources)
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            // Page is hidden - stop polling
            if (messagePollInterval) {
                clearInterval(messagePollInterval);
                messagePollInterval = null;
            }
        } else {
            // Page is visible - restart polling if conversation is open
            if (activeConversationUserId && !messagePollInterval) {
                messagePollInterval = setInterval(pollForNewMessages, 3000);
            }
            // Also refresh conversations when page becomes visible
            fetchConversations();
        }
    });

    // Stop polling when navigating away
    window.addEventListener('beforeunload', function () {
        if (messagePollInterval) {
            clearInterval(messagePollInterval);
        }
    });

    // Enhanced openConversation to handle users not yet in conversation list
    const originalOpenConversationFunc = openConversation;
    openConversation = async function (userId) {
        // If user is not in conversationsMap, fetch their info
        if (!conversationsMap[userId]) {
            try {
                // Try to fetch user info from API
                const userRes = await fetch(`backend/api/user_profile.php?id=${userId}`, { credentials: 'include' });
                if (userRes.ok) {
                    const userData = await userRes.json();
                    if (userData && userData.ok && userData.user) {
                        // Add to conversationsMap temporarily
                        conversationsMap[userId] = {
                            user_id: userId,
                            other_user_id: userId,
                            name: userData.user.name,
                            profile_pic: userData.user.profile_pic || 'assets/img/default-avatar.png',
                            last_message: '',
                            last_message_time: null,
                            unread_count: 0
                        };
                        // Add to conversation list if not already there
                        const existingItem = document.querySelector(`[data-conversation="${userId}"]`);
                        if (!existingItem) {
                            const searchBox = conversationList.querySelector('.conversation-search');
                            const el = document.createElement('div');
                            el.className = 'conversation-item';
                            el.dataset.conversation = userId;
                            const user = conversationsMap[userId];
                            let avatar = user.profile_pic || 'assets/img/default-avatar.png';
                            if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/') && !avatar.startsWith('assets/')) {
                                avatar = './' + avatar;
                            }
                            el.innerHTML = `
                                <img src="${avatar}" alt="${escapeHtml(user.name)}" class="conversation-avatar" onerror="this.onerror=null; this.src='assets/img/default-avatar.png';">
                                <div class="conversation-content">
                                    <div class="conversation-header">
                                        <h6 class="conversation-name">${escapeHtml(user.name)}</h6>
                                        <span class="conversation-time"></span>
                                    </div>
                                    <p class="conversation-preview">No messages yet</p>
                                </div>
                            `;
                            el.addEventListener('click', function () {
                                openConversation(userId);
                            });
                            if (searchBox && searchBox.nextSibling) {
                                conversationList.insertBefore(el, searchBox.nextSibling);
                            } else {
                                conversationList.appendChild(el);
                            }
                        }
                    }
                }
            } catch (err) {
                console.error('Error fetching user info:', err);
            }
        }
        // Call original function
        return originalOpenConversationFunc(userId);
    };

    // Check for user_id in URL parameters to auto-open conversation
    function checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const userIdParam = urlParams.get('user_id');
        if (userIdParam) {
            const userId = parseInt(userIdParam);
            if (userId && !isNaN(userId)) {
                // Open conversation after conversations are loaded
                setTimeout(() => {
                    openConversation(userId);
                    // Clean URL after opening
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 500);
            }
        }
    }

    // Initialize
    fetchConversations();
    // Check URL params after a short delay
    setTimeout(checkUrlParams, 200);
    // Refresh conversation list periodically (every 10 seconds)
    setInterval(fetchConversations, 10000);
});
