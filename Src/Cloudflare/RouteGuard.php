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
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') return;
		if (!$this->isProtectedPath($this->currentPath())) return;

		// 1) Load API early to avoid race conditions
		add_action('wp_enqueue_scripts', function (): void {
			wp_enqueue_script(
				'lal-cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
				[],
				null,
				false // head
			);
		}, 9);

		// 2) Inject placeholders + explicit render for every form (idempotent)
		add_action('wp_footer', function (): void {
			$siteKey = esc_js($this->config->siteKey);
			$theme   = esc_js($this->config->theme);
			$size    = esc_js($this->config->size);
			?>
			<script>
                (function () {
                    function ensureWidgets() {
                        var forms = document.querySelectorAll('form'); // TODO: target forms better on specific page basis
                        for (var i = 0; i < forms.length; i++) {
                            var f = forms[i];
                            if (f.querySelector('.cf-turnstile')) continue;
                            var holder = document.createElement('div');
                            holder.className = 'cf-turnstile';
                            holder.style.margin = '12px 0';
                            f.appendChild(holder);
                        }
                    }
                    function render() {
                        if (!window.turnstile) { setTimeout(render, 150); return; }
                        document.querySelectorAll('form .cf-turnstile').forEach(function (el) {
                            // If already rendered, turnstile ignores gracefully.
                            window.turnstile.render(el, {
                                sitekey: "<?php echo $siteKey; ?>",
                                appearance: "always", // visible for now (debug); we can relax later
                                theme: "<?php echo $theme; ?>",
                                size: "<?php echo $size; ?>",
                                retry: "auto",
                                "retry-interval": 8000,
                                "refresh-expired": "auto",
                                "refresh-timeout": "auto"
                            });
                        });
                    }
                    ensureWidgets();
                    render();
                })();
			</script>
			<?php
		}, 99);
	}

	public function verifyOnPost(): void
	{
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
		if (!$this->isProtectedPath($this->currentPath())) return;

		$token  = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
			? $_POST['cf-turnstile-response'] : '';
		$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		if (!$this->verify($token, $remote)) {
			wp_die(
				esc_html__('Please tick the "I am not a robot" box and try again.', 'lal'),
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