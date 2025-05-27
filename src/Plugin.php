<?php


namespace LAL;

class Plugin {
    public function init() {
        new DBHandler();
        new LoginLimiter();
        new IPBanManager();
        new SettingsPage();
        new RestAPI();
    }
}

// src/Helpers.php
namespace LAL;

class Helpers {
    public static function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}