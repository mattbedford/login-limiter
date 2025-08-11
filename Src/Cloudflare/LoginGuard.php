<?php
declare(strict_types=1);

namespace LAL\Src\Cloudflare;

final class LoginGuard
{
	public function __construct(private readonly Config $cfg) {}

	public static function init(Config $cfg): void
	{
		$self = new self($cfg);
		$self->hook();
	}

	private function hook(): void
	{
		// Load CF API into <head> (login page has no reliable footer)
		add_action('login_enqueue_scripts', function (): void {
			wp_enqueue_script(
				'lal-cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
				[],
				null,
				false
			);
		}, 9);

		// Add placeholder inside the login form
		add_action('login_form', static function (): void {
			echo '<div id="lal-turnstile" class="cf-turnstile" style="margin:12px 0;"></div>';
		}, 20);

		// Explicit render — visible (debug), robust
		add_action('login_footer', function (): void {
			?>
			<script>
                (function () {
                    var el = document.getElementById('lal-turnstile');
                    if (!el) return;
                    function render(){
                        if (!window.turnstile) { setTimeout(render, 150); return; }
                        window.turnstile.render(el, {
                            sitekey: "<?php echo esc_js($this->cfg->siteKey); ?>",
                            appearance: "always",
                            theme: "<?php echo esc_js($this->cfg->theme); ?>",
                            size: "<?php echo esc_js($this->cfg->size); ?>",
                            retry: "auto",
                            "retry-interval": 8000,
                            "refresh-expired": "auto",
                            "refresh-timeout": "auto"
                        });
                    }
                    render();
                })();
			</script>
			<?php
		}, 99);

		// Server-side verification: fail-closed before WP auth
		add_filter('authenticate', function ($user, string $username, string $password) {
			if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return $user;
			if (!isset($_POST['log'], $_POST['pwd']))        return $user; // core login only

			$token  = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
				? $_POST['cf-turnstile-response'] : '';
			$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';

			if (!$this->verify($token, $remote)) {
				return new \WP_Error('lal_turnstile_failed',
					__('Please complete the human verification challenge and try again.', 'lal'));
			}
			return $user;
		}, 5, 3);
	}

	private function verify(string $token, string $remoteIp): bool
	{
		if ($token === '') return false;

		$resp = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			[
				'timeout' => 8,
				'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
				'body'    => [
					'secret'   => $this->cfg->secretKey,
					'response' => $token,
					'remoteip' => $remoteIp,
				],
			]
		);
		if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return false;

		$json = json_decode((string) wp_remote_retrieve_body($resp), true);
		return is_array($json) ? (bool) ($json['success'] ?? false) : false;
	}
}
