<?php
namespace BuddyBossLiveChat\Core;

class Activity {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function update_user_activity($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_activity';
        
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $cache_key = 'bb_chat_activity_table_exists';
        $table_exists = wp_cache_get($cache_key);
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            wp_cache_set($cache_key, $table_exists, '', 300);
        }
        
        if ($table_exists) {
            $timestamp = current_time('mysql', true);
            $result = $wpdb->replace(
                $table,
                array(
                    'user_id' => $user_id,
                    'last_seen' => $timestamp
                ),
                array('%d', '%s')
            );
            wp_cache_delete('bb_chat_active_users');
            return $result !== false;
        }

        $opt = get_option('bb_chat_activity_store', array());
        if (!is_array($opt)) {
            $opt = array();
        }
        $opt[$user_id] = current_time('timestamp');
        return update_option('bb_chat_activity_store', $opt, false);
    }

    public function get_active_users($timeout = 60) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_activity';
        
        $timeout = 60;
        
        $cache_key = 'bb_chat_activity_table_exists';
        $table_exists = wp_cache_get($cache_key);
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            wp_cache_set($cache_key, $table_exists, '', 300);
        }
        $active_users = array();

        if ($table_exists) {
            $users_cache_key = 'bb_chat_active_users';
            $active_users = wp_cache_get($users_cache_key);
            if (false === $active_users) {
                $active_users = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT a.user_id, u.display_name as name, a.last_seen
                        FROM %i a 
                        JOIN {$wpdb->users} u ON a.user_id = u.ID 
                        WHERE a.last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)",
                        $table,
                        $timeout
                    ),
                    ARRAY_A
                );
            
            if (!$active_users) {
                $active_users = array();
            }
            foreach ($active_users as &$user) {
                $user['name'] = esc_html($user['name']);
                $user['avatar'] = esc_url(get_avatar_url($user['user_id'], array('size' => 32)));
                $user['profile_url'] = esc_url(
                    function_exists('bp_core_get_user_domain') 
                        ? bp_core_get_user_domain($user['user_id']) 
                        : get_author_posts_url($user['user_id'])
                );
            }
            unset($user);

                wp_cache_set($users_cache_key, $active_users, '', 30);
            }

            return $active_users;
        }

        // Option fallback: stored as [ user_id => unix_timestamp ]
        $opt = get_option('bb_chat_activity_store', array());
        if (!is_array($opt)) $opt = array();
        $now = current_time('timestamp');
        foreach ($opt as $uid => $last_seen_ts) {
            if (($now - intval($last_seen_ts)) <= intval($timeout)) {
                $user = get_user_by('id', intval($uid));
                if ($user) {
                    $active_users[] = array(
                        'user_id' => $user->ID,
                        'name' => esc_html($user->display_name),
                        'avatar' => esc_url(get_avatar_url($user->ID, array('size' => 32))),
                        'profile_url' => esc_url(
                            function_exists('bp_core_get_user_domain') 
                                ? bp_core_get_user_domain($user->ID) 
                                : get_author_posts_url($user->ID)
                        ),
                    );
                }
            }
         }
        
        // If list is empty, add current user as fallback
        if (empty($active_users)) {
            $current_user = wp_get_current_user();
            $active_users[] = array(
                'user_id' => $current_user->ID,
                'name' => esc_html($current_user->display_name),
                'avatar' => esc_url(get_avatar_url($current_user->ID, array('size' => 32))),
                'profile_url' => esc_url(
                    function_exists('bp_core_get_user_domain') 
                        ? bp_core_get_user_domain($current_user->ID) 
                        : get_author_posts_url($current_user->ID)
                )
            );
        }

        return $active_users;
    }

    public function cleanup_activity($timeout = 120) {
        global $wpdb;
        $table = $wpdb->prefix . 'bb_chat_activity';
        
        $timeout = intval($timeout);
        if ($timeout <= 0) {
            $timeout = 120;
        }
        
        $cache_key = 'bb_chat_activity_table_exists';
        $table_exists = wp_cache_get($cache_key);
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            wp_cache_set($cache_key, $table_exists, '', 300);
        }
        if ($table_exists) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM %i WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d SECOND)",
                    $table,
                    $timeout
                )
            );
            wp_cache_delete('bb_chat_active_users');
            return $result !== false;
        }

        // Fallback: cleanup option storage
        $opt = get_option('bb_chat_activity_store', array());
        if (!is_array($opt)) return false;
        $now = current_time('timestamp');
        foreach ($opt as $uid => $last_seen_ts) {
            if (($now - intval($last_seen_ts)) > intval($timeout)) {
                unset($opt[$uid]);
            }
        }
        update_option('bb_chat_activity_store', $opt, false);
        return true;
    }
}