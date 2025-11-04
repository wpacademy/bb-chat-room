<?php
namespace BuddyBossLiveChat\Core;

class Database {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_messages = $wpdb->prefix . 'bb_chat_messages';
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            message TEXT NOT NULL,
            timestamp DATETIME NOT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        $table_activity = $wpdb->prefix . 'bb_chat_activity';
        $sql_activity = "CREATE TABLE IF NOT EXISTS $table_activity (
            user_id BIGINT(20) NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (user_id),
            KEY last_seen (last_seen)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $result1 = dbDelta($sql_messages);
        $result2 = dbDelta($sql_activity);
        
        wp_cache_delete('bb_chat_table_exists');
        wp_cache_delete('bb_chat_activity_table_exists');
        
        return !empty($result1) && !empty($result2);
    }

    public function clear_transients() {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            'bb_chat_rate_%'
        ));
        wp_cache_flush();
        return $result !== false;
    }
}