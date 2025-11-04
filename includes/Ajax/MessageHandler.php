<?php
namespace BuddyBossLiveChat\Ajax;

use BuddyBossLiveChat\Core\Messages;
use BuddyBossLiveChat\Core\Security;

class MessageHandler {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_bb_send_message', array($this, 'send_message'));
        add_action('wp_ajax_nopriv_bb_send_message', array($this, 'handle_unauthorized'));
        add_action('wp_ajax_bb_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_nopriv_bb_get_messages', array($this, 'handle_unauthorized'));
        add_action('wp_ajax_bb_clear_chat', array($this, 'clear_chat'));
        add_action('wp_ajax_nopriv_bb_clear_chat', array($this, 'handle_unauthorized'));
        add_action('wp_ajax_bb_get_message_count', array($this, 'get_message_count'));
        add_action('wp_ajax_nopriv_bb_get_message_count', array($this, 'handle_unauthorized'));
    }
    
    public function handle_unauthorized() {
        wp_send_json_error(
            __('Authentication required', 'buddyboss-live-chat'), 
            401
        );
    }

    public function send_message() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(
                __('Invalid nonce', 'buddyboss-live-chat'), 
                403
            );
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(
                __('Authentication required', 'buddyboss-live-chat'), 
                401
            );
        }

        $user_id = get_current_user_id();
        $security = Security::get_instance();
        
        if (!$security->check_rate_limit($user_id)) {
            wp_send_json_error(
                __('Rate limit exceeded. Please wait a moment before sending more messages.', 'buddyboss-live-chat'), 
                429
            );
        }
        
        if (!isset($_POST['message'])) {
            wp_send_json_error(
                __('Message is required', 'buddyboss-live-chat'), 
                400
            );
        }
        
        // Use sanitize_textarea_field for better multi-line support
        $message = sanitize_textarea_field(wp_unslash($_POST['message']));
        $validation = $security->validate_message($message);
        
        if (!$validation['valid']) {
            wp_send_json_error($validation['error'], 400);
        }
        
        $message_data = array(
            'user_id' => $user_id,
            'message' => $message,
            'is_admin' => current_user_can('manage_options')
        );
        
        $new_message = Messages::get_instance()->write_message($message_data);
        
        if (!$new_message) {
            wp_send_json_error(
                __('Failed to save message', 'buddyboss-live-chat'), 
                500
            );
        }
        
        // Cleanup old messages periodically if limit is enabled
        // Only runs if max_messages > 0
        if (wp_rand(1, 10) === 1) {
            Messages::get_instance()->clear_old_messages();
        }
        
        wp_send_json_success($new_message);
    }

    public function get_messages() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(
                __('Invalid nonce', 'buddyboss-live-chat'), 
                403
            );
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(
                __('Authentication required', 'buddyboss-live-chat'), 
                401
            );
        }
        
        $last_id = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
        $messages = Messages::get_instance()->read_messages($last_id);
        
        if ($last_id > 0) {
            $new_messages = array();
            foreach (array_reverse($messages) as $msg) {
                if (intval($msg['id']) === $last_id) {
                    break;
                }
                $new_messages[] = $msg;
            }
            $messages = array_reverse($new_messages);
        }
        
        wp_send_json_success($messages);
    }

    public function clear_chat() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(
                __('Invalid nonce', 'buddyboss-live-chat'), 
                403
            );
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                __('Permission denied', 'buddyboss-live-chat'), 
                403
            );
        }
        
        $result = Messages::get_instance()->clear_all_messages();
        if ($result) {
            wp_send_json_success(
                __('Chat cleared successfully', 'buddyboss-live-chat')
            );
        } else {
            wp_send_json_error(
                __('Failed to clear chat', 'buddyboss-live-chat'), 
                500
            );
        }
    }

    /**
     * Get total message count (admin only)
     * Useful for statistics
     */
    public function get_message_count() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(
                __('Invalid nonce', 'buddyboss-live-chat'), 
                403
            );
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                __('Permission denied', 'buddyboss-live-chat'), 
                403
            );
        }
        
        $count = Messages::get_instance()->get_message_count();
        $max = Messages::get_instance()->get_max_messages();
        
        wp_send_json_success(array(
            'count' => $count,
            'formatted' => number_format_i18n($count),
            'limit' => $max,
            'has_limit' => $max > 0,
            'limit_text' => $max > 0 
                /* translators: 1: current message count, 2: maximum message limit */
                ? sprintf(__('%1$s of %2$s messages', 'buddyboss-live-chat'), number_format_i18n($count), number_format_i18n($max))
                /* translators: %s: current message count */
                : sprintf(__('%s messages (no limit)', 'buddyboss-live-chat'), number_format_i18n($count))
        ));
    }
}