<?php

namespace LAL\old\src;

class StopUserEnumeration {

    public static function init() {
        // Disable XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');

        // Remove REST /users endpoint
        add_filter('rest_endpoints', [self::class, 'disable_users_endpoint']);

        // Block unauthenticated ?author=X queries in REST
        add_filter('rest_post_query', [self::class, 'block_rest_author_queries'], 10, 2);

        // Block author archives and ?author=1
        add_action('template_redirect', [self::class, 'block_author_archives']);

    }

    public static function disable_users_endpoint($endpoints) {
        unset($endpoints['/wp/v2/users']);
        return $endpoints;
    }

    public static function block_rest_author_queries($args, $request) {
        if (!is_user_logged_in() && isset($request['author'])) {
            $args['author'] = -1;
        }
        return $args;
    }

    public static function block_author_archives() {
        if (is_author() || (isset($_GET['author']) && is_numeric($_GET['author']))) {
            wp_redirect(home_url(), 301);
            exit;
        }
    }
}
