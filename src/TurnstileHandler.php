<?php declare(strict_types=1);

namespace LAL\src;

use LAL\src\Security\Config;
use LAL\src\Security\RouteGuard;
use LAL\Src\Security\Turnstile;
use LAL\Src\Security\TurnstileOptions;

class TurnstileHandler
{
    private static ?Turnstile $svc = null;

    private static function svc(): Turnstile
    {
        if (self::$svc instanceof Turnstile) {
            return self::$svc;
        }

        $siteKey = defined('TURNSTILE_SITEKEY') ? (string) TURNSTILE_SITEKEY : (string) get_option('lal_turnstile_sitekey', '');
        $secret  = defined('TURNSTILE_SECRET')  ? (string) TURNSTILE_SECRET  : (string) get_option('lal_turnstile_secret', '');
        $theme   = (string) get_option('lal_turnstile_theme', 'auto');
        $size = (string) get_option('lal_turnstile_size', 'normal');

        $validSizes = ['normal', 'compact', 'flexible'];
        if (!in_array($size, $validSizes, true)) {
            $size = 'normal';
        }

        self::$svc = new Turnstile(new TurnstileOptions(
            siteKey:  $siteKey,
            secretKey: $secret,
            theme:    $theme,
            size:     $size
        ));
        return self::$svc;
    }

	public static function init(): void
	{
		add_action('init', static function (): void {
			// 1) Build a shared Config (options → fallback to constants)
			$config = null;
			try {
				$config = Config::fromOptions();
			} catch (\Throwable $e) {
				if (defined('LAL_TURNSTILE_SITE') && defined('LAL_TURNSTILE_SECRET')) {
					$config = new Config(
						(string) LAL_TURNSTILE_SITE,
						(string) LAL_TURNSTILE_SECRET,
						'managed',
						'normal',
						'auto'
					);
				}
			}
			if (!$config instanceof Config) {
				return; // keys missing — nothing to do
			}

			// 2) Instantiate once; share everywhere
			$turnstile = new Turnstile($config);
			$options   = new TurnstileOptions($config);

			// 3) Login screen hooks (your existing method)
			if (method_exists(self::class, 'attachToLogin')) {
				self::attachToLogin($turnstile, $options);
			}

			// 4) Route‑based protection (/checkout, /my-account, etc.)
			RouteGuard::init($options, $turnstile);
		}, 1);
	}

    // Back-compat if anything still calls this
    public static function verify_token(string $token): bool
    {
        return self::svc()->verify($token, $_SERVER['REMOTE_ADDR'] ?? '')->ok;
    }
}
