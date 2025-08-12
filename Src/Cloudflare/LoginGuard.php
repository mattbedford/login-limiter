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
                    var form   = document.getElementById('loginform');            // core wp-login form
                    var holder = document.getElementById('lal-turnstile');        // our placeholder inside the form
                    if (!form || !holder) return;

                    var widgetId = null;
                    var submitting = false;

                    function ensureHiddenField() {
                        var input = form.querySelector('input[name="cf-turnstile-response"]');
                        if (!input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'cf-turnstile-response';
                            form.appendChild(input);
                        }
                        return input;
                    }

                    function render() {
                        if (!window.turnstile) { setTimeout(render, 120); return; }
                        if (widgetId !== null) return;

                        widgetId = window.turnstile.render(holder, {
                            sitekey: "<?php echo esc_js($this->config->siteKey); ?>",
                            appearance: "always",                               // keep visible for now
                            theme: "<?php echo esc_js($this->config->theme); ?>",
                            size: "<?php echo esc_js($this->config->size); ?>",
                            execution: "execute",                               // ← token only when we execute
                            "refresh-expired": "auto",
                            "refresh-timeout": "auto",
                            callback: function (token) {
                                ensureHiddenField().value = token;                // guarantee POST contains the token
                                submitting = false;
                                form.submit();                                    // proceed only after we have a token
                            },
                            "error-callback": function () {
                                submitting = false;
                                alert("Verification failed. Please try again.");
                            }
                        });

                        // Gate the submit: if no token yet, execute first
                        form.addEventListener('submit', function (e) {
                            var token = window.turnstile.getResponse(widgetId);
                            if (token && token.length > 0) {
                                ensureHiddenField().value = token;
                                return true;                                      // already have a token
                            }
                            if (submitting) { e.preventDefault(); return false; }
                            e.preventDefault();
                            submitting = true;
                            window.turnstile.execute(widgetId);                 // this will trigger callback() above
                            return false;
                        }, { passive: false });
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
