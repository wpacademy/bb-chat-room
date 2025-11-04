<?php
/**
 * Chat Room Template
 *
 * @package BuddyBossLiveChat
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bb-chat-container">
    <div class="bb-chat-header">
        <h3>Live Chat Room</h3>
        <div class="bb-header-actions">
            <button id="bb-sound-toggle" class="bb-sound-btn" data-sound="on" title="Toggle sound notifications">
                <span class="bb-sound-icon">🔔</span>
            </button>
        </div>
    </div>
    <div class="bb-chat-main">
        <div class="bb-chat-sidebar">
            <div class="bb-sidebar-title">Active Members</div>
            <ul class="bb-active-users" id="bb-active-users">
                <li class="bb-loading">Loading...</li>
            </ul>
        </div>
        <div class="bb-chat-content">
            <div class="bb-chat-messages" id="bb-chat-messages">
                <div class="bb-loading">Loading messages...</div>
            </div>
            <div class="bb-chat-input-area">
                <button id="bb-emoji-btn" class="bb-emoji-trigger" title="Add emoji">😊</button>
                <div class="bb-emoji-picker" id="bb-emoji-picker" style="display: none;">
                    <div class="bb-emoji-grid"></div>
                </div>
                <input type="text" id="bb-chat-input" placeholder="Type your message..." maxlength="500">
                <button id="bb-chat-send">Send</button>
            </div>
        </div>
    </div>
</div>