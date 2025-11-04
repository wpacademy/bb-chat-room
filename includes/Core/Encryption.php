<?php
namespace BuddyBossLiveChat\Core;

class Encryption {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function encrypt($message) {
        if (!defined('NONCE_KEY') || empty(NONCE_KEY)) {
            return $message;
        }
        
        try {
            $key = hash('sha256', NONCE_KEY);
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $encrypted = openssl_encrypt($message, 'aes-256-cbc', $key, 0, $iv);
            
            if ($encrypted === false) {
                return $message;
            }
            
            return base64_encode($encrypted . '::' . $iv);
        } catch (Exception $e) {
            return $message;
        }
    }

    public function decrypt($encrypted) {
        if (!defined('NONCE_KEY') || empty(NONCE_KEY)) {
            return $encrypted;
        }
        
        try {
            $key = hash('sha256', NONCE_KEY);
            $decoded = base64_decode($encrypted);
            
            if ($decoded === false || strpos($decoded, '::') === false) {
                return $encrypted;
            }
            
            list($encrypted_data, $iv) = explode('::', $decoded, 2);
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
            
            return $decrypted !== false ? $decrypted : $encrypted;
        } catch (Exception $e) {
            return $encrypted;
        }
    }
}