<?php
namespace BuddyBossLiveChat\Core;

/**
 * Enhanced Security class with improved validation and sanitization
 *
 * @package BuddyBossLiveChat
 * @subpackage Core
 * @since 1.0.0
 */
class Security {
    private static $instance = null;
    private $rate_limit = 20; // messages per minute
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' blob:; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com; img-src \'self\' data: https://s.w.org https://www.gravatar.com https://*.amazonaws.com; media-src \'self\' data: blob:; worker-src blob:;');
        }
    }

    /**
     * Check rate limit for user with IP fallback
     *
     * @param int $user_id User ID
     * @return bool True if within rate limit
     */
    public function check_rate_limit($user_id) {
        if (!is_numeric($user_id) || $user_id <= 0) {
            return false;
        }
        
        $user_id = absint($user_id);
        $transient_key = 'bb_chat_rate_' . $user_id;
        $rate_data = get_transient($transient_key);
        
        // Also check IP-based rate limiting
        $ip_limit = $this->check_ip_rate_limit();
        if (!$ip_limit) {
            return false;
        }
        
        if (!$rate_data) {
            $rate_data = array(
                'count' => 1,
                'first_message' => time()
            );
        } else {
            $rate_data['count']++;
            
            // Reset if window expired
            if (time() - $rate_data['first_message'] > 60) {
                $rate_data = array(
                    'count' => 1,
                    'first_message' => time()
                );
            } elseif ($rate_data['count'] > $this->rate_limit) {
                return false;
            }
        }
        
        set_transient($transient_key, $rate_data, 60);
        return true;
    }

    /**
     * IP-based rate limiting (stricter)
     *
     * @return bool True if within rate limit
     */
    private function check_ip_rate_limit() {
        $ip = $this->get_user_ip();
        if (empty($ip)) {
            return true; // Can't determine IP, allow
        }
        
        $transient_key = 'bb_chat_ip_rate_' . md5($ip);
        $rate_data = get_transient($transient_key);
        $ip_limit = 30; // Higher limit for IP (multiple users might share IP)
        
        if (!$rate_data) {
            $rate_data = array(
                'count' => 1,
                'first_message' => time()
            );
        } else {
            $rate_data['count']++;
            
            if (time() - $rate_data['first_message'] > 60) {
                $rate_data = array(
                    'count' => 1,
                    'first_message' => time()
                );
            } elseif ($rate_data['count'] > $ip_limit) {
                return false;
            }
        }
        
        set_transient($transient_key, $rate_data, 60);
        return true;
    }

    /**
     * Get user IP address securely
     *
     * @return string Sanitized IP address
     */
    private function get_user_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        // Validate IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        return $ip ? $ip : '';
    }

    /**
     * Enhanced message validation with better sanitization
     *
     * @param string $message Message to validate
     * @return array Validation result
     */
    public function validate_message($message) {
        if (empty($message)) {
            return array(
                'valid' => false, 
                'error' => __('Empty message', 'buddyboss-live-chat')
            );
        }
        
        // Use sanitize_textarea_field for multi-line support
        $message = sanitize_textarea_field($message);
        
        if (strlen($message) > 500) {
            return array(
                'valid' => false, 
                'error' => __('Message too long (max 500 characters)', 'buddyboss-live-chat')
            );
        }
        
        // Check for spam patterns
        if ($this->is_spam($message)) {
            return array(
                'valid' => false, 
                'error' => __('Message detected as spam', 'buddyboss-live-chat')
            );
        }
        
        // Only administrators can send links
        if (!current_user_can('manage_options')) {
            if (preg_match('/(https?:\/\/|www\.|[a-zA-Z0-9\-]+\.(com|net|org|io|co|uk|edu|gov|info|biz))/i', $message)) {
                return array(
                    'valid' => false, 
                    'error' => __('Only administrators can send links in the chat.', 'buddyboss-live-chat')
                );
            }
        }
        
        return array('valid' => true, 'message' => $message);
    }

    /**
     * Basic spam detection
     *
     * @param string $message Message to check
     * @return bool True if likely spam
     */
    private function is_spam($message) {
        // Check for excessive repetition
        if (preg_match('/(.)\1{10,}/', $message)) {
            return true;
        }
        
        // Check for excessive caps
        $caps_ratio = strlen(preg_replace('/[^A-Z]/', '', $message)) / max(strlen($message), 1);
        if ($caps_ratio > 0.7 && strlen($message) > 20) {
            return true;
        }
        
        return false;
    }

    /**
     * Sanitize and validate URL
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    public function sanitize_url($url) {
        $url = esc_url_raw($url);
        
        // Only allow http and https
        if (!preg_match('/^https?:\/\//', $url)) {
            return '';
        }
        
        return $url;
    }

    /**
     * Escape table name for SQL
     *
     * @param string $table_name Table name 
     * @return string Escaped table name
     */
    public function escape_table_name($table_name) {
        // Remove any existing backticks
        $table_name = str_replace('`', '', $table_name);
        
        // Validate table name characters
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
            return '';
        }
        
        return '`' . esc_sql($table_name) . '`';
    }
}