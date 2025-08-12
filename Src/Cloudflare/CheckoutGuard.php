<?php
declare(strict_types=1);

namespace LAL\Src\Cloudflare;

final class CheckoutGuard
{
	public function __construct(private readonly Config $config) {}

	public static function init(Config $config): void
	{
		$self = new self($config);

		add_action('wp', static function () use ($self): void {
			if (!function_exists('is_checkout') || !is_checkout()) return;
			if (is_user_logged_in()) return; // front door only

			add_action('wp_enqueue_scripts', [$self, 'enqueueApi'], 9);
			add_action('wp_footer',          [$self, 'renderIntoForm'], 99);

			// Classic checkout server-side validation
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

	/** Injects a placeholder into form.checkout.woocommerce-checkout and renders explicitly. */
	public function renderIntoForm(): void
	{
		$siteKey = esc_js($this->config->siteKey);
		$theme   = esc_js($this->config->theme);
		$size    = esc_js($this->config->size);
		?>
        <script>
            (function () {
                function ensureHolder(form) {
                    if (!form) return null;
                    var id = 'lal-turnstile-checkout';
                    var holder = document.getElementById(id);
                    if (!holder) {
                        holder = document.createElement('div');
                        holder.id = id;
                        holder.className = 'cf-turnstile';
                        holder.style.margin = '12px 0';

                        // Prefer right above "Place order" (#place_order). Fallback: end of form.
                        var place = form.querySelector('#place_order');
                        if (place && place.parentNode) {
                            place.parentNode.insertBefore(holder, place);
                        } else {
                            form.appendChild(holder);
                        }
                    }
                    return holder;
                }

                function render() {
                    var form = document.querySelector('form.checkout.woocommerce-checkout');
                    if (!form) return;
                    var holder = ensureHolder(form);
                    if (!holder) return;

                    if (!window.turnstile) { setTimeout(render, 150); return; }
                    if (holder.getAttribute('data-rendered') === '1') return;

                    window.turnstile.render(holder, {
                        sitekey: "<?php echo $siteKey; ?>",
                        appearance: "always", // keep visible while we verify UX; can relax later
                        theme: "<?php echo $theme; ?>",
                        size: "<?php echo $size; ?>",
                        retry: "auto",
                        "retry-interval": 8000,
                        "refresh-expired": "auto",
                        "refresh-timeout": "auto"
                    });
                    holder.setAttribute('data-rendered', '1');
                }

                // Initial render
                render();

                // Re-render when Woo updates checkout fragments
                document.addEventListener('updated_checkout', render);
                if (window.jQuery) jQuery(document.body).on('updated_checkout', render);

                // Safety: observe DOM mutations in case themes replace the form markup
                var mo = new MutationObserver(function () { render(); });
                mo.observe(document.body, { childList: true, subtree: true });
            })();
        </script>
		<?php
	}

	/** Blocks before payment processing in classic checkout. */
	public function verifyOnProcess(): void
	{
		if (is_user_logged_in()) return;

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
