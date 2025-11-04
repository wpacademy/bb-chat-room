jQuery(document).ready(function($) {
    let lastMessageId = '';
    let pollInterval;
    let activityInterval;
    let soundEnabled = localStorage.getItem('bbChatSound') !== 'off';
    let previousUserIds = null;
    
    // Format timestamp
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp * 1000);
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }
    
    // Emoji list
    const emojis = ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤌','🤏','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤙','💪','🙏','✍️','💅','🤳','👏','👐','🙌','🤲','🤝','🎉','🎊','🎈','🎁','🏆','🥇','🥈','🥉','⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱','🏓','🏸','🏒','🏑','🥍','🏏','🥅','⛳','🪁','🏹','🎣','🤿','🥊','🥋','🎽','🛹','🛼','⛸️','🥌','🎿','⛷️','🏂','🪂','🏋️','🤼','🤸','🤺','⛹️','🤾','🏌️','🏇','🧘','🏊','🚴','🚵','🧗','🤹'];
    
    // Initialize sound button state
    updateSoundButton();
    
    function updateSoundButton() {
        const $btn = $('#bb-sound-toggle');
        if (soundEnabled) {
            $btn.attr('data-sound', 'on').find('.bb-sound-icon').text('🔔');
            $btn.attr('title', 'Sound on - Click to mute');
        } else {
            $btn.attr('data-sound', 'off').find('.bb-sound-icon').text('🔕');
            $btn.attr('title', 'Sound off - Click to unmute');
        }
    }
    
    function playNotificationSound() {
        if (soundEnabled) {
            try {
                notificationSound.currentTime = 0;
                notificationSound.play().catch(function(error) {
                    console.warn('Audio playback failed:', error.message);
                });
            } catch (error) {
                console.warn('Audio initialization failed:', error.message);
            }
        }
    }
    
    function playUserEnterSound() {
        if (soundEnabled) {
            try {
                userEnterSound.currentTime = 0;
                userEnterSound.play().catch(function(error) {
                    console.warn('Audio playback failed:', error.message);
                });
            } catch (error) {
                console.warn('Audio initialization failed:', error.message);
            }
        }
    }
    
    function playUserExitSound() {
        if (soundEnabled) {
            try {
                userExitSound.currentTime = 0;
                userExitSound.play().catch(function(error) {
                    console.warn('Audio playback failed:', error.message);
                });
            } catch (error) {
                console.warn('Audio initialization failed:', error.message);
            }
        }
    }
    
    // Format timestamp
    function formatTime(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Sanitize URL to prevent XSS
    function sanitizeUrl(url) {
        try {
            const urlObj = new URL(url);
            return ['http:', 'https:'].includes(urlObj.protocol) ? url : '#';
        } catch {
            return '#';
        }
    }
    
    // Render a message
    function renderMessage(msg) {
        const isOwnMessage = msg.user_id == bbChat.user_id;
        const messageClass = isOwnMessage ? 'bb-message-own' : 'bb-message-other';
        const adminBadge = msg.is_admin ? '<span class="bb-admin-badge">Admin</span>' : '';
        
        // Escape all user content to prevent XSS
        let messageText = escapeHtml(msg.message || '');
        
        // Convert URLs to clickable links if user is admin (with sanitization)
        if (msg.is_admin) {
            messageText = messageText.replace(
                /(https?:\/\/[^\s<>"']+)/g, 
                function(match) {
                    const sanitizedUrl = sanitizeUrl(match);
                    return `<a href="${sanitizedUrl}" target="_blank" rel="noopener noreferrer">${escapeHtml(match)}</a>`;
                }
            );
        }
        
        return `
            <div class="bb-message ${messageClass}" data-id="${escapeHtml(msg.id || '')}">
                <a href="${sanitizeUrl(msg.profile_url || '#')}" class="bb-avatar-link" target="_blank">
                    <img src="${sanitizeUrl(msg.avatar || '')}" class="bb-avatar" alt="${escapeHtml(msg.username || '')}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNlMGUwZTAiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDEyQzE0LjIwOTEgMTIgMTYgMTAuMjA5MSAxNiA4QzE2IDUuNzkwODYgMTQuMjA5MSA0IDEyIDRDOS43OTA4NiA0IDggNS43OTA4NiA4IDhDOCAxMC4yMDkxIDkuNzkwODYgMTIgMTIgMTJaIiBmaWxsPSIjOTk5Ii8+CjxwYXRoIGQ9Ik0xMiAxNEM5LjMzIDEzLjk5IDcuMDEgMTUuNjIgNiAxOFYyMEgxOFYxOEMxNi45OSAxNS42MiAxNC42NyAxMy45OSAxMiAxNFoiIGZpbGw9IiM5OTkiLz4KPC9zdmc+Cjwvc3ZnPgo='">
                </a>
                <div class="bb-message-content">
                    <div class="bb-message-header">
                        <a href="${sanitizeUrl(msg.profile_url || '#')}" class="bb-username-link" target="_blank">
                            <span class="bb-username">${escapeHtml(msg.username || '')}</span>
                        </a>
                        ${adminBadge}
                        <span class="bb-timestamp">${formatTime(msg.timestamp)}</span>
                    </div>
                    <div class="bb-message-text">${messageText}</div>
                </div>
            </div>
        `;
    }
    
    // Render active users
    function renderActiveUsers(users) {
        const $container = $('#bb-active-users');
        
        if (!Array.isArray(users) || users.length === 0) {
            $container.html('<li class="bb-no-messages" style="list-style: none;">No active members</li>');
            previousUserIds = [];
            return;
        }
        
        const currentUserIds = users.map(u => u.user_id).filter(Boolean);
        
        if (previousUserIds !== null) {
            const newUsers = currentUserIds.filter(id => !previousUserIds.includes(id) && id != bbChat.user_id);
            const leftUsers = previousUserIds.filter(id => !currentUserIds.includes(id) && id != bbChat.user_id);
            
            if (newUsers.length > 0) playUserEnterSound();
            else if (leftUsers.length > 0) playUserExitSound();
        }
        
        previousUserIds = currentUserIds;
        
        $container.empty();
        users.forEach(function(user) {
            if (user && user.name) {
                $container.append(`
                    <li class="bb-active-user">
                        <a href="${sanitizeUrl(user.profile_url || '#')}" target="_blank">
                            <img src="${sanitizeUrl(user.avatar || '')}" alt="${escapeHtml(user.name)}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNlMGUwZTAiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDEyQzE0LjIwOTEgMTIgMTYgMTAuMjA5MSAxNiA4QzE2IDUuNzkwODYgMTQuMjA5MSA0IDEyIDRDOS43OTA4NiA0IDggNS43OTA4NiA4IDhDOCAxMC4yMDkxIDkuNzkwODYgMTIgMTIgMTJaIiBmaWxsPSIjOTk5Ii8+CjxwYXRoIGQ9Ik0xMiAxNEM5LjMzIDEzLjk5IDcuMDEgMTUuNjIgNiAxOFYyMEgxOFYxOEMxNi45OSAxNS42MiAxNC42NyAxMy45OSAxMiAxNFoiIGZpbGw9IiM5OTkiLz4KPC9zdmc+Cjwvc3ZnPgo='">
                        </a>
                        <div class="bb-active-user-info">
                            <a href="${sanitizeUrl(user.profile_url || '#')}" target="_blank" class="bb-active-user-name-link">
                                <span class="bb-active-user-name">${escapeHtml(user.name)}</span>
                            </a>
                            <span class="bb-active-user-status">Online</span>
                        </div>
                    </li>
                `);
            }
        });
    }
    
    // Load active users
    function loadActiveUsers() {
        $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_get_active_users',
                nonce: bbChat.nonce
            },
            success: function(response) {
                try {
                    if (response && response.success) {
                        renderActiveUsers(response.data || []);
                    }
                } catch (error) {
                    console.warn('Error processing active users:', error.message);
                }
            },
            error: function(xhr, status, error) {
                console.warn('Failed to load active users:', error);
            },
            timeout: 10000
        });
    }
    
    // Update user activity
    function updateActivity() {
        $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_update_activity',
                nonce: bbChat.nonce
            },
            error: function(xhr, status, error) {
                console.warn('Failed to update activity:', error);
            },
            timeout: 5000
        });
    }
    
    // Load messages
    function loadMessages(append = false) {
        $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_get_messages',
                nonce: bbChat.nonce,
                last_id: append ? lastMessageId : ''
            },
            success: function(response) {
                try {
                    if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
                        const $container = $('#bb-chat-messages');
                        const wasAtBottom = isScrolledToBottom();
                        let hasNewMessages = false;
                        
                        if (!append) {
                            $container.empty();
                        }
                        
                        // Check for duplicates before adding
                        response.data.forEach(function(msg) {
                            if (msg && msg.id) {
                                // Only add if message doesn't already exist
                                if ($container.find('[data-id="' + escapeHtml(msg.id) + '"]').length === 0) {
                                    $container.append(renderMessage(msg));
                                    // Play sound for new messages from others
                                    if (append && msg.user_id != bbChat.user_id) {
                                        hasNewMessages = true;
                                    }
                                }
                                lastMessageId = msg.id;
                            }
                        });
                        
                        if (hasNewMessages) {
                            playNotificationSound();
                        }
                        
                        if (wasAtBottom || !append) {
                            scrollToBottom();
                        }
                    } else if (!append) {
                        $('#bb-chat-messages').html('<div class="bb-no-messages">No messages yet. Start the conversation!</div>');
                    }
                } catch (error) {
                    console.warn('Error processing messages:', error.message);
                    if (!append) {
                        $('#bb-chat-messages').html('<div class="bb-no-messages">Error loading messages. Please refresh.</div>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.warn('Failed to load messages:', error);
                if (!append) {
                    $('#bb-chat-messages').html('<div class="bb-no-messages">Failed to load messages. Please refresh.</div>');
                }
            },
            timeout: 15000
        });
    }
    
    // Check if scrolled to bottom
    function isScrolledToBottom() {
        const $container = $('#bb-chat-messages');
        const threshold = 100;
        return $container[0].scrollHeight - $container.scrollTop() - $container.outerHeight() < threshold;
    }
    
    // Scroll to bottom
    function scrollToBottom() {
        const $container = $('#bb-chat-messages');
        $container.animate({
            scrollTop: $container[0].scrollHeight
        }, 300);
    }
    
    // Send message
    function sendMessage() {
        const $input = $('#bb-chat-input');
        const $sendBtn = $('#bb-chat-send');
        const message = $input.val().trim();
        
        // Validate input
        if (!message) return;
        if (message.length > 1000) {
            alert('Message is too long. Maximum 1000 characters allowed.');
            return;
        }
        
        // Disable input while sending
        $input.prop('disabled', true);
        $sendBtn.prop('disabled', true).text('Sending...');
        
        $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_send_message',
                nonce: bbChat.nonce,
                message: message
            },
            success: function(response) {
                try {
                    if (response && response.success && response.data) {
                        $input.val('');
                        // Immediately add the message to UI
                        const $container = $('#bb-chat-messages');
                        $container.append(renderMessage(response.data));
                        lastMessageId = response.data.id;
                        scrollToBottom();
                    } else {
                        // Show error message
                        alert(response.data || 'Failed to send message');
                    }
                } catch (error) {
                    console.warn('Error processing send message response:', error.message);
                    alert('Failed to send message. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.warn('Failed to send message:', error);
                alert('Failed to send message. Please try again.');
            },
            complete: function() {
                // Re-enable input
                $input.prop('disabled', false).focus();
                $sendBtn.prop('disabled', false).text('Send');
            },
            timeout: 10000
        });
    }
    
    // Clear chat (admin only)
    $('#bb-clear-chat').on('click', function() {
        if (!confirm('Are you sure you want to clear all chat messages?')) {
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_clear_chat',
                nonce: bbChat.nonce
            },
            success: function(response) {
                try {
                    if (response && response.success) {
                        $('#bb-chat-messages').html('<div class="bb-no-messages">Chat cleared.</div>');
                        lastMessageId = '';
                    } else {
                        alert('Failed to clear chat: ' + (response.data || 'Unknown error'));
                    }
                } catch (error) {
                    console.warn('Error processing clear chat response:', error.message);
                    alert('Failed to clear chat. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.warn('Failed to clear chat:', error);
                alert('Failed to clear chat. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Clear Chat');
            },
            timeout: 10000
        });
    });
    
    // Sound toggle
    $('#bb-sound-toggle').on('click', function() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('bbChatSound', soundEnabled ? 'on' : 'off');
        updateSoundButton();
        
        // Play a test sound when enabling
        if (soundEnabled) {
            playNotificationSound();
        }
    });
    
    // Emoji picker
    function createEmojiPicker() {
        // Remove existing elements to prevent duplicates
        $('#bb-emoji-btn, #bb-emoji-picker').remove();
        
        // Create emoji button with HTML entity to ensure proper rendering
        const $emojiBtn = $('<button>', {
            type: 'button',
            id: 'bb-emoji-btn',
            class: 'bb-emoji-trigger',
            html: '&#128512;' // This is the HTML entity for �
        });
        $('.bb-chat-input-area').prepend($emojiBtn);
        
        // Create picker container
        const $picker = $('<div>', {
            id: 'bb-emoji-picker',
            class: 'bb-emoji-picker',
            style: 'display:none'
        }).append('<div class="bb-emoji-grid"></div>');
        $('.bb-chat-input-area').append($picker);
        
        // Populate emoji grid
        const $grid = $picker.find('.bb-emoji-grid');
        emojis.forEach(function(emoji) {
            $('<span>', {
                class: 'bb-emoji-item',
                text: emoji,
                click: function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    insertEmoji(emoji);
                }
            }).appendTo($grid);
        });
    }
    
    // Function to insert emoji at cursor position
    function insertEmoji(emoji) {
        const $input = $('#bb-chat-input');
        const input = $input[0];
        
        // Simple append if no selection support
        if (typeof input.selectionStart === 'undefined') {
            $input.val($input.val() + emoji);
        } else {
            const pos = input.selectionStart;
            const value = $input.val();
            $input.val(value.slice(0, pos) + emoji + value.slice(pos));
            input.selectionStart = input.selectionEnd = pos + emoji.length;
        }
        
        $input.focus();
        $('#bb-emoji-picker').hide();
    }
    
    // Toggle emoji picker
    $(document).on('click', '#bb-emoji-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#bb-emoji-picker').toggle();
    });
    
    // Close emoji picker when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#bb-emoji-picker, #bb-emoji-btn').length) {
            $('#bb-emoji-picker').hide();
        }
    });
    
    // Insert emoji into input
    $(document).on('click', '.bb-emoji-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const emoji = $(this).text();
        const $input = $('#bb-chat-input');
        
        // Simple append if there's no cursor support
        if (typeof $input[0].selectionStart === 'undefined') {
            $input.val($input.val() + emoji);
        } else {
            // Insert at cursor position
            const pos = $input[0].selectionStart;
            const value = $input.val();
            $input.val(value.slice(0, pos) + emoji + value.slice(pos));
            // Update cursor position
            $input[0].selectionStart = $input[0].selectionEnd = pos + emoji.length;
        }
        
        // Focus input and close picker
        $input.focus();
        $('#bb-emoji-picker').hide();
    });
    
    // Send button click
    $('#bb-chat-send').on('click', sendMessage);
    
    // Enter key to send
    $('#bb-chat-input').on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
        }
    });
    
    // Initial load
    loadMessages();
    loadActiveUsers();
    updateActivity();
    createEmojiPicker();
    
    let pollFailureCount = 0;
    const maxPollFailures = 5;
    
    // Poll for new messages with exponential backoff on failures
    function startPolling() {
        pollInterval = setInterval(function() {
            if (pollFailureCount < maxPollFailures) {
                loadMessages(true);
            } else {
                console.warn('Polling stopped due to repeated failures');
                clearInterval(pollInterval);
            }
        }, 3000);
    }
    
    // Override loadMessages to track failures
    const originalLoadMessages = loadMessages;
    loadMessages = function(append) {
        return $.ajax({
            url: bbChat.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_get_messages',
                nonce: bbChat.nonce,
                last_id: append ? lastMessageId : ''
            },
            success: function(response) {
                pollFailureCount = 0; // Reset on success
                try {
                    if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
                        const $container = $('#bb-chat-messages');
                        const wasAtBottom = isScrolledToBottom();
                        let hasNewMessages = false;
                        
                        if (!append) {
                            $container.empty();
                        }
                        
                        // Check for duplicates before adding
                        response.data.forEach(function(msg) {
                            if (msg && msg.id) {
                                // Only add if message doesn't already exist
                                if ($container.find('[data-id="' + escapeHtml(msg.id) + '"]').length === 0) {
                                    $container.append(renderMessage(msg));
                                    // Play sound for new messages from others
                                    if (append && msg.user_id != bbChat.user_id) {
                                        hasNewMessages = true;
                                    }
                                }
                                lastMessageId = msg.id;
                            }
                        });
                        
                        if (hasNewMessages) {
                            playNotificationSound();
                        }
                        
                        if (wasAtBottom || !append) {
                            scrollToBottom();
                        }
                    } else if (!append) {
                        $('#bb-chat-messages').html('<div class="bb-no-messages">No messages yet. Start the conversation!</div>');
                    }
                } catch (error) {
                    console.warn('Error processing messages:', error.message);
                    if (!append) {
                        $('#bb-chat-messages').html('<div class="bb-no-messages">Error loading messages. Please refresh.</div>');
                    }
                }
            },
            error: function(xhr, status, error) {
                pollFailureCount++;
                console.warn('Failed to load messages (attempt ' + pollFailureCount + '):', error);
                if (!append) {
                    $('#bb-chat-messages').html('<div class="bb-no-messages">Failed to load messages. Please refresh.</div>');
                }
            },
            timeout: 15000
        });
    };
    
    startPolling();
    
    // Update activity and load active users every 10 seconds
    activityInterval = setInterval(function() {
        updateActivity();
        loadActiveUsers();
    }, 10000);
    
    // Clean up on page unload
    $(window).on('beforeunload', function() {
        clearInterval(pollInterval);
        clearInterval(activityInterval);
    });
});