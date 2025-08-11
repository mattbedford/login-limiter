<?php declare(strict_types=1);

namespace LAL\Security;

final class Turnstile
{
	public function __construct(private TurnstileOptions $opts) {}

	public function attachToLogin(): void
	{
		// 1) Load CF API in <head> and our renderer (explicit, visible)
		add_action('login_enqueue_scripts', function (): void {
			wp_enqueue_script(
				'lal-cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
				[],
				null,
				false // head on login; no reliable footer
			);

			// Our explicit renderer for the login page
			wp_register_script(
				'lal-login-turnstile',
				plugins_url('assets/login-turnstile.js', \LAL\src\LAL_PLUGIN_FILE ?? LAL_PLUGIN_FILE),
				[],
				'1.0',
				true
			);

			// Visible, robust settings
			$cfg = [
				'siteKey' => $this->opts->siteKey,
				'opts'    => [
					'appearance'        => 'always',           // force visible while we validate
					'theme'             => $this->opts->theme,
					'retry'             => 'auto',
					'retry-interval'    => 8000,
					'refresh-expired'   => 'auto',
					'refresh-timeout'   => 'auto',
				],
			];

			wp_add_inline_script(
				'lal-login-turnstile',
				'window.LAL_TURNSTILE = ' . wp_json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';',
				'before'
			);
			wp_enqueue_script('lal-login-turnstile');
		}, 9);

		// 2) Add placeholder inside the login form
		add_action('login_form', static function (): void {
			echo '<div id="lal-turnstile" class="cf-turnstile" style="margin:12px 0;"></div>';
		}, 20);

		// 3) Server-side verification: fail-closed before WP auth runs
		add_filter('authenticate', function ($user, string $username, string $password) {
			if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
				return $user;
			}
			// Only the core login form (wp-login.php)
			if (!isset($_POST['log'], $_POST['pwd'])) {
				return $user;
			}

			$token  = is_string($_POST['cf-turnstile-response'] ?? null) ? (string) $_POST['cf-turnstile-response'] : '';
			$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';

			$result = $this->verify($token, $remote); // returns VerificationResult

			if (!$result->ok) {
				return new \WP_Error(
					'lal_turnstile_failed',
					__('Please complete the human verification challenge and try again.', 'lal')
				);
			}
			return $user;
		}, 5, 3);
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
