<?php

namespace LAL\old\src;

class RestAPI {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('login-limiter/v1', '/update-lists', [
            'methods' => 'POST',
            'callback' => [$this, 'update_lists'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('login-limiter/v1', '/clear-logs', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('login-limiter/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    public function update_lists($request) {
        update_option('lal_ban_list', [
            'ips' => array_map('trim', explode("\n", $request['ban_list']['ips'] ?? '')),
            'users' => array_map('trim', explode("\n", $request['ban_list']['users'] ?? '')),
        ]);

        update_option('lal_whitelist', array_map('trim', explode("\n", $request['whitelist'] ?? '')));
        return ['success' => true];
    }

    public function clear_logs() {
        DBHandler::clear_all_logs();
        return ['success' => true];
    }

    public function get_logs($request) {
        $offset = intval($request['offset'] ?? 0);
        $limit = intval($request['limit'] ?? 20);
        $after = sanitize_text_field($request['after'] ?? null);
        return DBHandler::get_logs($offset, $limit, $after);
    }
}
