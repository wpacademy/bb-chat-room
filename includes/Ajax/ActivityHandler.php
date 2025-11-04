<?php
namespace BuddyBossLiveChat\Ajax;

use BuddyBossLiveChat\Core\Activity;

class ActivityHandler {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_bb_get_active_users', array($this, 'get_active_users'));
        add_action('wp_ajax_nopriv_bb_get_active_users', array($this, 'handle_unauthorized'));
        add_action('wp_ajax_bb_update_activity', array($this, 'update_activity'));
        add_action('wp_ajax_nopriv_bb_update_activity', array($this, 'handle_unauthorized'));
        add_action('wp_ajax_bb_debug_activity', array($this, 'debug_activity'));
        add_action('wp_ajax_nopriv_bb_debug_activity', array($this, 'handle_unauthorized'));
    }
    
    public function handle_unauthorized() {
        wp_send_json_error(__('Authentication required', 'buddyboss-live-chat'), 401);
    }

    public function get_active_users() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid nonce', 'buddyboss-live-chat'), 403);
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Authentication required', 'buddyboss-live-chat'), 401);
        }
        
        $active_users = Activity::get_instance()->get_active_users(60);
        $current_user_id = get_current_user_id();
        $found_current = false;
        
        foreach ($active_users as $user) {
            if (intval($user['user_id']) === $current_user_id) {
                $found_current = true;
                break;
            }
        }
        
        if (!$found_current) {
            $current_user = wp_get_current_user();
            $active_users[] = array(
                'user_id' => $current_user_id,
                'name' => esc_html($current_user->display_name),
                'avatar' => get_avatar_url($current_user_id, array('size' => 32)),
                'profile_url' => function_exists('bp_core_get_user_domain') 
                    ? bp_core_get_user_domain($current_user_id) 
                    : get_author_posts_url($current_user_id)
            );
        }
        
        wp_send_json_success($active_users);
    }

    public function update_activity() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(__('Authentication required', 'buddyboss-live-chat'), 401);
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Authentication required', 'buddyboss-live-chat'), 401);
        }
        
        $user_id = get_current_user_id();
        $result = Activity::get_instance()->update_user_activity($user_id);
        
        if (!$result) {
            wp_send_json_error(__('Failed to update activity', 'buddyboss-live-chat'), 500);

        }
        
        // Cleanup old activity records periodically with secure random
        if (wp_rand(1, 10) === 1) {
            Activity::get_instance()->cleanup_activity(120);
        }
        
        wp_send_json_success();
    }

    /**
     * Admin-only debug endpoint to inspect activity storage.
     * Returns JSON with table_exists, table_row_count (if available), and option store.
     */
    public function debug_activity() {
        if (!check_ajax_referer('bb_chat_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid nonce', 'buddyboss-live-chat'), 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'buddyboss-live-chat'), 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $cache_key = 'bb_chat_table_exists';
        $table_exists = wp_cache_get($cache_key);
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            wp_cache_set($cache_key, $table_exists, '', 300);
        }

        $row_count = null;
        if ($table_exists) {
            $count_cache_key = 'bb_chat_message_count';
            $row_count = wp_cache_get($count_cache_key);
            if (false === $row_count) {
                $row_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table)));
                wp_cache_set($count_cache_key, $row_count, '', 60);
            }
        }

        $opt = get_option('bb_chat_activity_store', array());
        if (!is_array($opt)) {
            $opt = array();
        }

        wp_send_json_success(array(
            'table_exists' => $table_exists,
            'table_row_count' => $row_count,
            'option_store' => $opt,
        ));
    }
}