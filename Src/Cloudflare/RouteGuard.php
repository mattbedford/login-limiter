<?php
declare(strict_types=1);

namespace LAL\Src\Cloudflare;

final class RouteGuard
{
	public function __construct(
		private readonly Config $config,
		private readonly array $protectedPaths
	) {}

	public static function init(Config $config, array $protectedPaths): void
	{
		$self = new self($config, $protectedPaths);
		add_action('init', [$self, 'verifyOnPost'], 1);
		add_action('wp',   [$self, 'injectOnGet'], 1);
	}

	public function injectOnGet(): void
	{
		if (is_admin()) return;
		if (is_user_logged_in()) return;
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') return;

		$path = $this->currentPath();

		// Target ONLY the Woo login form on /my-account/
		if (!str_starts_with($path, '/my-account/')) return;

		// Load CF API in head
		add_action('wp_enqueue_scripts', function (): void {
			wp_enqueue_script(
				'lal-cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
				[],
				null,
				false
			);
		}, 9);

		// Render into: form.woocommerce-form.woocommerce-form-login.login
		add_action('wp_footer', function (): void {
			$siteKey = esc_js($this->config->siteKey);
			$theme   = esc_js($this->config->theme);
			$size    = esc_js($this->config->size);
			?>
            <script>
                (function () {
                    function render() {
                        var form = document.querySelector('form.woocommerce-form.woocommerce-form-login.login');
                        if (!form) return;
                        // Ensure single placeholder near the submit button
                        var id = 'lal-turnstile-myaccount';
                        var holder = document.getElementById(id);
                        if (!holder) {
                            holder = document.createElement('div');
                            holder.id = id;
                            holder.className = 'cf-turnstile';
                            holder.style.margin = '12px 0';
                            // Try to place just before the submit
                            var submit = form.querySelector('button, input[type="submit"]');
                            if (submit && submit.parentNode) {
                                submit.parentNode.insertBefore(holder, submit);
                            } else {
                                form.appendChild(holder);
                            }
                        }
                        if (!window.turnstile) { setTimeout(render, 150); return; }
                        window.turnstile.render(holder, {
                            sitekey: "<?php echo $siteKey; ?>",
                            appearance: "always", // visible for now
                            theme: "<?php echo $theme; ?>",
                            size: "<?php echo $size; ?>",
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
	}

	public function verifyOnPost(): void
	{
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
		if (is_user_logged_in()) return;

		$path = $this->currentPath();

		// Only handle Woo login form submits on /my-account/
		if (!str_starts_with($path, '/my-account/')) return;

		// Heuristic: Woo login has these posted
		$isWooLogin = isset($_POST['username'], $_POST['password'], $_POST['woocommerce-login-nonce']);
		if (!$isWooLogin) return;

		$token  = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
			? $_POST['cf-turnstile-response'] : '';
		$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		if (!$this->verify($token, $remote)) {
			wp_die(
				esc_html__('Please complete the human verification challenge and try again.', 'lal'),
				esc_html__('Verification required', 'lal'),
				['response' => 403]
			);
		}
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
					'secret'   => $this->config->secretKey,
					'response' => $token,
					'remoteip' => $remoteIp,
				],
			]
		);
		if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return false;

		$json = json_decode((string) wp_remote_retrieve_body($resp), true);
		return is_array($json) ? (bool) ($json['success'] ?? false) : false;
	}

	private function currentPath(): string
	{
		$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
		$q   = strpos($uri, '?');
		$p   = $q === false ? $uri : substr($uri, 0, $q);
		$p   = '/' . ltrim($p, '/');
		return $p === '' ? '/' : rtrim($p);
	}

	private function isProtectedPath(string $path): bool
	{
		foreach ($this->protectedPaths as $prefix) {
			if (is_string($prefix) && $prefix !== '' && str_starts_with($path, $prefix)) {
				return true;
			}
		}
		return false;
	}
}