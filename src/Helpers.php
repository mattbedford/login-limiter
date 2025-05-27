<?php


namespace LAL;

class Helpers {
    public static function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    //Remove login error helpers
    public static function remove_all_login_errors( $error ) {
        $message = __("Qualcosa non ha funzionato. Controlla i dati inseriti e riprovare.", "lal");
        return $message;
    }
}