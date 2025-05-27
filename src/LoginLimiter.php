<?php

// src/LoginLimiter.php
namespace LAL;

class LoginLimiter {
    private $settings;

    public function __construct() {
        $this->settings = get_option('lal_settings', [
            'max_attempts' => 5,
            'timeout_minutes' => 10,
        ]);

        add_filter('authenticate', [$this, 'check_login'], 30, 3);
        add_action('wp_login_failed', [$this, 'handle_failed_login']);
        add_action('wp_login', [$this, 'handle_successful_login'], 10, 2);
    }

    public function check_login($user, $username, $password) {
        $ip = Helpers::get_client_ip();

        if (IPBanManager::is_whitelisted($ip)) return $user;
        if (IPBanManager::is_banned($ip, $username)) {
            return new \WP_Error('banned', __('Access denied: IP or user banned.'));
        }

        $record = DBHandler::get_attempt($ip);

        if ($record && $record->attempts >= $this->settings['max_attempts']) {
            $lock_until = strtotime($record->last_attempt) + ($this->settings['timeout_minutes'] * 60);
            if (time() < $lock_until) {
                return new \WP_Error('locked_out', sprintf(__('Too many login attempts. Try again in %s.'), human_time_diff(time(), $lock_until)));
            }
        }

        return $user;
    }

    public function handle_failed_login($username) {
        $ip = Helpers::get_client_ip();
        DBHandler::record_failed_attempt($ip, $username);
    }

    public function handle_successful_login($username, $user) {
        $ip = Helpers::get_client_ip();
        DBHandler::clear_attempts($ip);
    }
}
