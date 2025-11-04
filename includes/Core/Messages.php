<?php
namespace BuddyBossLiveChat\Core;

class Messages {
    private static $instance = null;
    private $max_messages = 0; // 0 = no limit, any positive number = limit
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load max_messages from option, default to 0 (no limit)
        $this->max_messages = absint(get_option('bb_chat_max_messages', 0));
    }

    /**
     * Set maximum messages limit
     * 
     * @param int $limit 0 for no limit, positive number for limit
     */
    public function set_max_messages($limit) {
        $this->max_messages = absint($limit);
        update_option('bb_chat_max_messages', $this->max_messages, false);
    }

    /**
     * Get current message limit
     * 
     * @return int 0 means no limit
     */
    public function get_max_messages() {
        return $this->max_messages;
    }

    public function read_messages($last_id = 0, $limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $last_id = intval($last_id);
        $limit = min(intval($limit), 100); // Cap at 100 per request for performance
        
        $cache_key = 'bb_chat_messages_' . $last_id . '_' . $limit;
        $results = wp_cache_get($cache_key);
        
        if (false === $results) {
            if ($last_id > 0) {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT m.*, 
                            u.display_name as username,
                            UNIX_TIMESTAMP(m.timestamp) as timestamp
                        FROM %i m 
                        JOIN {$wpdb->users} u ON m.user_id = u.ID 
                        WHERE m.id > %d
                        ORDER BY m.id DESC 
                        LIMIT %d",
                        $table,
                        $last_id,
                        $limit
                    ),
                    ARRAY_A
                );
            } else {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT m.*, 
                            u.display_name as username,
                            UNIX_TIMESTAMP(m.timestamp) as timestamp
                        FROM %i m 
                        JOIN {$wpdb->users} u ON m.user_id = u.ID 
                        ORDER BY m.id DESC 
                        LIMIT %d",
                        $table,
                        $limit
                    ),
                    ARRAY_A
                );
            }
            wp_cache_set($cache_key, $results, '', 10);
        }
        
        if (!$results) {
            return array();
        }

        $messages = array();
        foreach ($results as $row) {
            $messages[] = array(
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => esc_html($row['username']),
                'avatar' => esc_url(get_avatar_url($row['user_id'], array('size' => 32))),
                'profile_url' => esc_url(
                    function_exists('bp_core_get_user_domain') 
                        ? bp_core_get_user_domain($row['user_id']) 
                        : get_author_posts_url($row['user_id'])
                ),
                'message' => Encryption::get_instance()->decrypt($row['message']),
                'timestamp' => $row['timestamp'],
                'is_admin' => (bool)$row['is_admin']
            );
        }

        return array_reverse($messages);
    }

    public function write_message($message_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        if (!isset($message_data['user_id']) || !isset($message_data['message'])) {
            return false;
        }
        
        $user_id = intval($message_data['user_id']);
        if ($user_id <= 0) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'message' => Encryption::get_instance()->encrypt($message_data['message']),
                'timestamp' => current_time('mysql'),
                'is_admin' => !empty($message_data['is_admin']) ? 1 : 0
            ),
            array('%d', '%s', '%s', '%d')
        );

        if ($result) {
            wp_cache_delete('bb_chat_message_count');
            wp_cache_flush();
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }
            
            return array(
                'id' => $wpdb->insert_id,
                'user_id' => $user_id,
                'username' => esc_html($user->display_name),
                'avatar' => esc_url(get_avatar_url($user_id, array('size' => 32))),
                'profile_url' => esc_url(
                    function_exists('bp_core_get_user_domain') 
                        ? bp_core_get_user_domain($user_id) 
                        : get_author_posts_url($user_id)
                ),
                'message' => esc_html($message_data['message']),
                'timestamp' => current_time('timestamp'),
                'is_admin' => !empty($message_data['is_admin'])
            );
        }
        
        return false;
    }

    /**
     * Clear old messages if limit is enabled
     * Only keeps the most recent messages up to max_messages
     * 
     * @return bool|int False on failure, number of deleted rows on success, 0 if no limit
     */
    public function clear_old_messages() {
        // If max_messages is 0, no limit is set - don't delete anything
        if ($this->max_messages <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i 
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM %i ORDER BY id DESC LIMIT %d
                    ) temp
                )",
                $table,
                $table,
                $this->max_messages
            )
        );
        
        wp_cache_delete('bb_chat_message_count');
        return $result !== false ? $result : false;
    }

    /**
     * Clear all messages from the database
     * Admin-only function
     *
     * @return bool True on success, false on failure
     */
    public function clear_all_messages() {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $result = $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $table));
        wp_cache_delete('bb_chat_message_count');
        return $result !== false;
    }

    /**
     * Get total message count
     * Useful for admin statistics
     *
     * @return int Total number of messages
     */
    public function get_message_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $cache_key = 'bb_chat_message_count';
        $count = wp_cache_get($cache_key);
        if (false === $count) {
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table));
            wp_cache_set($cache_key, $count, '', 60);
        }
        return intval($count);
    }

    /**
     * Delete messages older than specified days
     * Optional maintenance function for very high-traffic sites
     *
     * @param int $days Number of days to keep
     * @return bool|int Number of deleted rows or false on failure
     */
    public function delete_old_messages($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $days = absint($days);
        if ($days <= 0) {
            return false;
        }
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $table,
                $days
            )
        );
        
        wp_cache_delete('bb_chat_message_count');
        return $result;
    }

    /**
     * Get messages by date range
     * Useful for reporting or archives
     *
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @param int $limit Maximum number of messages to retrieve
     * @return array Array of messages
     */
    public function get_messages_by_date($start_date, $end_date, $limit = 1000) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_messages';
        
        $limit = min(intval($limit), 1000);
        
        $cache_key = 'bb_chat_messages_date_' . md5($start_date . $end_date . $limit);
        $results = wp_cache_get($cache_key);
        
        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*, 
                        u.display_name as username,
                        UNIX_TIMESTAMP(m.timestamp) as timestamp
                    FROM %i m 
                    JOIN {$wpdb->users} u ON m.user_id = u.ID 
                    WHERE DATE(m.timestamp) BETWEEN %s AND %s
                    ORDER BY m.id DESC 
                    LIMIT %d",
                    $table,
                    $start_date,
                    $end_date,
                    $limit
                ),
                ARRAY_A
            );
            wp_cache_set($cache_key, $results, '', 300);
        }
        
        if (!$results) {
            return array();
        }

        $messages = array();
        foreach ($results as $row) {
            $messages[] = array(
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => esc_html($row['username']),
                'avatar' => esc_url(get_avatar_url($row['user_id'], array('size' => 32))),
                'profile_url' => esc_url(
                    function_exists('bp_core_get_user_domain') 
                        ? bp_core_get_user_domain($row['user_id']) 
                        : get_author_posts_url($row['user_id'])
                ),
                'message' => Encryption::get_instance()->decrypt($row['message']),
                'timestamp' => $row['timestamp'],
                'is_admin' => (bool)$row['is_admin']
            );
        }

        return array_reverse($messages);
    }
}