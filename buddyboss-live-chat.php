<?php
/**
 * Plugin Name: BuddyBoss Live Chat Room
 * Plugin URI: https://wpacademy.pk
 * Description: A simple live chat room for BuddyBoss/BuddyPress community websites
 * Version: 1.0.1
 * Author: Mian Shahzad Raza
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BBCHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BBCHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBCHAT_VERSION', '1.0.0');

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Only autoload our plugin classes
    if (strpos($class, 'BuddyBossLiveChat\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $class = str_replace('BuddyBossLiveChat\\', '', $class);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = BBCHAT_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize the plugin
use BuddyBossLiveChat\Plugin;

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('BuddyBossLiveChat\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('BuddyBossLiveChat\Plugin', 'deactivate'));

// Initialize the plugin
add_action('plugins_loaded', function() {
    Plugin::get_instance();
});