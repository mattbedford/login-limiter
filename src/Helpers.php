<?php


namespace LAL;

class Helpers {
    public static function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}