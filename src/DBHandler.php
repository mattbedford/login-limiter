<?php

namespace LAL\src;

global $wpdb;

abstract class DBHandler {

    public const TABLE = 'login_attempts';

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id INT NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(60),
            attempts INT NOT NULL DEFAULT 1,
            last_attempt DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_unique (ip_address)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_attempt($ip) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ip_address = %s", $ip));
    }

    public static function record_failed_attempt($ip, $username) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $existing = self::get_attempt($ip);

        if ($existing) {
            $wpdb->update($table, [
                'attempts' => $existing->attempts + 1,
                'last_attempt' => current_time('mysql')
            ], ['ip_address' => $ip]);
        } else {
            $wpdb->insert($table, [
                'ip_address' => $ip,
                'username' => sanitize_user($username),
                'attempts' => 1,
                'last_attempt' => current_time('mysql')
            ]);
        }
    }

    public static function clear_attempts($ip) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->delete($table, ['ip_address' => $ip]);
    }

    public static function get_logs($offset = 0, $limit = 20, $after = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $where = '';

        if ($after) {
            $where = $wpdb->prepare("WHERE last_attempt >= %s", $after);
        }

        return $wpdb->get_results("SELECT * FROM $table $where ORDER BY last_attempt DESC LIMIT $offset, $limit");
    }

    public static function clear_all_logs() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->query("TRUNCATE TABLE $table");
    }
}
