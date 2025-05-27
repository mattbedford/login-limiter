<?php


namespace LAL;

class Helpers {
    public static function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    //Remove login error helpers
    public static function remove_all_login_errors( $error ) {
        $message = __("Something went wrong with your login. Please check your credentials and try again. Too many attempts will result in a temporary ban.", "lal");
        return $message;
    }
}