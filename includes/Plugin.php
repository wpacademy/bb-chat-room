<?php
namespace BuddyBossLiveChat;

use BuddyBossLiveChat\Core\Database;
use BuddyBossLiveChat\Core\Security;
use BuddyBossLiveChat\Ajax\MessageHandler;
use BuddyBossLiveChat\Ajax\ActivityHandler;

class Plugin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Security headers
        add_action('send_headers', array(Security::get_instance(), 'add_security_headers'));
        
        // Initialize hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('buddyboss_chat', array($this, 'render_chat_room'));
        
        // Initialize AJAX handlers
        MessageHandler::get_instance();
        ActivityHandler::get_instance();

        // Ensure DB tables exist at runtime if activation wasn't run
        if (get_option('bb_chat_tables_created') !== '1') {
            $result = Database::get_instance()->create_tables();
            if ($result !== false) {
                update_option('bb_chat_tables_created', '1', false);
            }
        }
    }

    public static function activate() {
        Database::get_instance()->create_tables();
    }

    public static function deactivate() {
        Database::get_instance()->clear_transients();
    }

    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_style('bb-chat-style', $plugin_url . 'assets/css/style.css', array(), BBCHAT_VERSION);
        wp_enqueue_script('bb-chat-script', $plugin_url . 'assets/js/script.js', array('jquery'), BBCHAT_VERSION, true);
        wp_enqueue_script('bb-chat-sounds', $plugin_url . 'assets/js/sounds.js', array('jquery'), BBCHAT_VERSION, true);
        
        wp_localize_script('bb-chat-script', 'bbChat', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bb_chat_nonce'),
            'user_id' => get_current_user_id(),
            'is_admin' => current_user_can('manage_options')
        ));
    }

    public function render_chat_room($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to access the chat room.', 'buddyboss-live-chat') . '</p>';
        }
        
        $template_path = dirname(__FILE__) . '/Templates/chat-room.php';
        if (!file_exists($template_path)) {
            return '<p>' . esc_html__('Chat template not found.', 'buddyboss-live-chat') . '</p>';
        }
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}