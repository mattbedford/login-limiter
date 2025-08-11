<?php
declare(strict_types=1);

namespace LAL\src\Security;

final class RouteGuard
{
	public function __construct(
		private readonly TurnstileOptions $options,
		private readonly Turnstile        $turnstile
	) {}

	public static function init(TurnstileOptions $options, Turnstile $turnstile): void
	{
		$self = new self($options, $turnstile);
		add_action('init', [$self, 'maybeEnforceEarly']);
		add_action('wp',   [$self, 'maybeInjectAssets']); // runs after WP query resolved (front-end)
	}

	/** Verifies on POST before Woo/handlers run. */
	public function maybeEnforceEarly(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}
		$path = $this->currentPath();
		if (!$this->isProtectedPath($path)) {
			return;
		}

		$token   = $_POST['cf-turnstile-response'] ?? '';
		$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
		$result  = $this->turnstile->verify($token, $remote);

		if (!$result->success) {
			// Hard fail. Keep it simple; you can theme this later.
			wp_die(
				esc_html__('Please complete the human verification challenge and try again.', 'lal'),
				esc_html__('Verification required', 'lal'),
				['response' => 403]
			);
		}
	}

	/** Enqueue API + inject a widget on matching GET pages. */
	public function maybeInjectAssets(): void
	{
		if (is_admin() || $_SERVER['REQUEST_METHOD'] === 'POST') {
			return;
		}
		$path = $this->currentPath();
		if (!$this->isProtectedPath($path)) {
			return;
		}

		// 1) API
		add_action('wp_enqueue_scripts', static function (): void {
			wp_enqueue_script(
				'lal-turnstile-api',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				[],
				null,
				false
			);
		}, 9);

		// 2) Minimal inline script: append a managed placeholder inside forms (idempotent).
		add_action('wp_footer', function (): void {
			$cfg = $this->options->getConfig();
			if ($cfg === null) {
				return;
			}
			$site = esc_js($cfg->siteKey);
			?>
			<script>
                (function () {
                    if (!document || !window || typeof window.turnstile === 'undefined') {
                        // The managed widget will self-init when API finishes; we still add placeholders now.
                    }
                    var forms = document.querySelectorAll('form');
                    if (!forms.length) return;

                    forms.forEach(function (f) {
                        if (f.querySelector('.cf-turnstile')) return; // idempotent
                        var holder = document.createElement('div');
                        holder.className = 'cf-turnstile';
                        holder.setAttribute('data-sitekey', '<?php echo $site; ?>');
                        holder.setAttribute('data-appearance', 'interaction-only'); // managed; only shows when needed
                        f.appendChild(holder);
                    });
                })();
			</script>
			<?php
		}, 99);
	}

	/** Prefix match against configured routes. */
	private function isProtectedPath(string $path): bool
	{
		$cfg = $this->options->getRaw();
		$enabled = !empty($cfg['routes_enabled']);
		if (!$enabled) {
			return false;
		}
		$routes = is_array($cfg['routes'] ?? null) ? $cfg['routes'] : [];
		foreach ($routes as $prefix) {
			if (is_string($prefix) && $prefix !== '' && str_starts_with($path, $prefix)) {
				return true;
			}
		}
		return false;
	}

	private function currentPath(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		// strip scheme/host if any; keep path only
		$qpos = strpos($uri, '?');
		$path = $qpos === false ? $uri : substr($uri, 0, $qpos);
		$path = '/' . ltrim($path, '/');
		return rtrim($path) === '' ? '/' : rtrim($path);
	}
}
