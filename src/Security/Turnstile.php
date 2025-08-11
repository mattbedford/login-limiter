<?php declare(strict_types=1);

namespace LAL\Security;

final class Turnstile
{
    public function __construct(private TurnstileOptions $opts) {}

    public function attachToLogin(): void
    {
        // Load scripts only on wp-login.php
        add_action('login_enqueue_scripts', function (): void {
            wp_enqueue_script(
                'lal-cf-turnstile',
                'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
                [],
                null,
                true
            );

            // Require a plugin file constant pointing at your main plugin file
            // e.g., define('LAL_PLUGIN_FILE', __FILE__) in your bootstrap if you haven't already.
            wp_enqueue_script(
                'lal-login-turnstile',
                plugins_url('assets/login-turnstile.js', defined('LAL_PLUGIN_FILE') ? LAL_PLUGIN_FILE : __FILE__),
                ['lal-cf-turnstile'],
                null,
                true
            );

            $config = [
                'siteKey' => $this->opts->siteKey,
                'opts'    => [
                    'appearance'        => 'always',
                    'retry'             => 'auto',
                    'retry-interval'    => 8000,
                    'refresh-expired'   => 'auto',
                    'refresh-timeout'   => 'auto',
                    'theme'             => $this->opts->theme,
                    'size'              => $this->opts->size,
                ],
            ];
            wp_add_inline_script(
                'lal-login-turnstile',
                'window.LAL_TURNSTILE = ' . wp_json_encode($config, JSON_UNESCAPED_SLASHES) . ';',
                'before'
            );
        });

        // Placeholder div inside the login form
        add_action('login_form', static function (): void {
                echo '<div id="lal-turnstile"></div>';
            }, 20);

        // Server-side verification for login
        add_filter('authenticate', function ($user, string $username, string $password) {
            if (is_wp_error($user)) {
                return $user;
            }

            $token = $_POST['cf-turnstile-response'] ?? '';
            if ($token === '') {
                return new \WP_Error('turnstile_missing', __('Please verify you are human.', 'lal'));
            }

            $result = $this->verify($token, $_SERVER['REMOTE_ADDR'] ?? '');
            if (!$result->ok) {
                return new \WP_Error(
                    'turnstile_failed',
                    sprintf(
                        __('Human verification failed. (%s)', 'lal'),
                        esc_html($result->errorSummary() ?: 'token invalid/expired')
                    )
                );
            }

            return $user;
        }, 20, 3);
    }

    public function verify(string $token, string $remoteIp = ''): VerificationResult
    {
        $body = [
            'secret'   => $this->opts->secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== '') {
            $body['remoteip'] = $remoteIp;
        }

        $resp = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            ['timeout' => 8, 'body' => $body]
        );

        if (is_wp_error($resp)) {
            return new VerificationResult(false, ['http_error']);
        }

        $code = wp_remote_retrieve_response_code($resp);
        $json = json_decode((string) wp_remote_retrieve_body($resp), true);

        if ($code !== 200 || !is_array($json)) {
            return new VerificationResult(false, ['bad_response']);
        }

        $ok     = (bool)($json['success'] ?? false);
        $errors = isset($json['error-codes']) && is_array($json['error-codes']) ? $json['error-codes'] : [];

        return new VerificationResult(
            $ok,
            $errors,
            $json['hostname'] ?? null,
            $json['action']   ?? null,
            $json['cdata']    ?? null
        );
    }
}
