<?php

namespace LAL\src;

class TurnstileHandler {

    public static function init() {
        // Render widgets
        add_action('login_form', [self::class, 'render_widget']);
        add_action('woocommerce_login_form', [self::class, 'render_widget']);
        //add_action('woocommerce_register_form', [self::class, 'render_widget']);
        //add_action('woocommerce_checkout_before_customer_details', [self::class, 'render_widget']);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_script']);
        // Validate on login
        add_filter('authenticate', [self::class, 'validate_token'], 21, 3);

        // Validate on WooCommerce forms (if needed, can hook into woocommerce_process_* actions)
    }

    public static function render_widget() {
        $sitekey = defined('TURNSTILE_SITEKEY') ? TURNSTILE_SITEKEY : 'your-sitekey-here';
        echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($sitekey) . '" data-theme="light"></div>';
    }

    public static function validate_token($user, $username, $password) {
        if (isset($_POST['cf-turnstile-response'])) {
            $token = sanitize_text_field($_POST['cf-turnstile-response']);
            if (!self::verify_token($token)) {
                return new \WP_Error('turnstile_failed', __('Turnstile verification failed.', 'lal'));
            }
        } else {
            return new \WP_Error('turnstile_missing', __('Human verification is required.', 'lal'));
        }

        return $user;
    }

    protected static function verify_token($token) {
        $secret = get_option('lal_turnstile_secret', '');
        $remoteip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (empty($token) || empty($secret)) {
            return false;
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $remoteip,
            ],
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            error_log('[Turnstile] WP_Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['success']) || $data['success'] !== true) {
            error_log('[Turnstile] Validation failed: ' . $body);
            return false;
        }

        return true;
    }

    public static function maybe_enqueue_script() {
        if (!is_page('checkout')) return;

        wp_localize_script('lal-turnstile', 'lal_turnstile', [
            'sitekey' => get_option('lal_turnstile_sitekey', '')
        ]);

        wp_enqueue_script(
            'lal-turnstile',
            plugin_dir_url(__FILE__) . '../assets/turnstile-inject.js',
            [],
            null,
            true
        );
    }

}
