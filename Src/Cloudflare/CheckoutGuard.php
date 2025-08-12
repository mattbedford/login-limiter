<?php
declare(strict_types=1);

namespace LAL\Src\Cloudflare;

final class CheckoutGuard
{
	public function __construct(private readonly Config $config) {}

	public static function init(Config $config): void
	{
		$self = new self($config);

		// Only hook when we're actually on checkout (and logged out)
		add_action('wp', static function () use ($self): void {
			if (!function_exists('is_checkout') || !is_checkout()) return;
			if (is_user_logged_in()) return;

			// Inject the widget into the checkout form
			add_action('wp_enqueue_scripts', [$self, 'enqueueApi'], 9);
			add_action('woocommerce_review_order_before_submit', [$self, 'injectHolder'], 9);
			add_action('wp_footer', [$self, 'renderScript'], 99);

			// Verify server-side during Woo validation (works for wc-ajax=checkout too)
			add_action('woocommerce_checkout_process', [$self, 'verifyOnProcess'], 5);
		}, 1);
	}

	public function enqueueApi(): void
	{
		wp_enqueue_script(
			'lal-cf-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			[],
			null,
			false
		);
	}

	/** Places the placeholder inside the Woo checkout form, right above the submit button. */
	public function injectHolder(): void
	{
		echo '<div id="lal-turnstile-checkout" class="cf-turnstile" style="margin:12px 0"></div>';
	}

	/** Explicitly renders the widget; re-renders after Woo updates the order review fragment. */
	public function renderScript(): void
	{
		$siteKey = esc_js($this->config->siteKey);
		$theme   = esc_js($this->config->theme);
		$size    = esc_js($this->config->size);
		?>
		<script>
            (function () {
                function render() {
                    var el = document.getElementById('lal-turnstile-checkout');
                    if (!el) return;
                    if (!window.turnstile) { setTimeout(render, 150); return; }
                    if (el.getAttribute('data-rendered') === '1') return;

                    window.turnstile.render(el, {
                        sitekey: "<?php echo $siteKey; ?>",
                        appearance: "always", // keep visible for now; we can relax later
                        theme: "<?php echo $theme; ?>",
                        size: "<?php echo $size; ?>",
                        retry: "auto",
                        "retry-interval": 8000,
                        "refresh-expired": "auto",
                        "refresh-timeout": "auto"
                    });
                    el.setAttribute('data-rendered', '1');
                }

                render();
                // Woo fires 'updated_checkout' whenever the order review fragment refreshes
                document.addEventListener('updated_checkout', render);
                if (window.jQuery) jQuery(document.body).on('updated_checkout', render);
            })();
		</script>
		<?php
	}

	/** Fail-closed during Woo validation; blocks before payment processing. */
	public function verifyOnProcess(): void
	{
		if (is_user_logged_in()) return; // front door only

		$token  = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
			? $_POST['cf-turnstile-response'] : '';
		$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		if (!$this->verify($token, $remote)) {
			if (function_exists('wc_add_notice')) {
				wc_add_notice(__('Please complete the human verification challenge and try again.', 'lal'), 'error');
			} else {
				wp_die(
					esc_html__('Please complete the human verification challenge and try again.', 'lal'),
					esc_html__('Verification required', 'lal'),
					['response' => 403]
				);
			}
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
}
