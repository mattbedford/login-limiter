<?php declare(strict_types=1);

namespace LAL\old\src;

use LAL\old\src\Security\Config;
use LAL\old\src\Security\RouteGuard;
use LAL\old\src\Security\Turnstile;
use LAL\old\src\Security\TurnstileOptions;

final class TurnstileHandler
{
	public static function init(): void
	{
		add_action('init', static function (): void {
			// 1) Build shared Config once
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
				return; // keys missing — bail cleanly
			}

			// 2) Instantiate once; share everywhere
			$options   = new TurnstileOptions($config);
			$turnstile = new Turnstile($config);

			// 3) Login screen: attach visible widget + server-side check
			$turnstile->attachToLogin($options);

			// 4) Front-end routes (/checkout, /my-account, ...)
			RouteGuard::init($options, $turnstile);
		}, 1);
	}
}
