<?php

declare(strict_types=1);

namespace LAL\Src;

use LAL\Src\Cloudflare\Config;
use LAL\Src\Cloudflare\LoginGuard;
use LAL\Src\Cloudflare\CheckoutGuard;
use LAL\Src\Cloudflare\RouteGuard;
use LAL\Src\Includes\Settings;

final class Kernel
{
	/** Edit these if you ever need to change coverage. */
	private const PROTECTED_PATHS = [
		'/my-account/',
		'/checkout/',
		// Note: /wp-admin/ is NOT listed here intentionally.
		// The login page is protected by LoginGuard; once authenticated, wp-admin has no extra CF checks.
	];

	public static function boot(): void
	{
		\LAL\Src\Includes\Settings::boot();

		$config = \LAL\Src\Cloudflare\Config::load();
		if ($config === null) return;

		// Protect the login page (wp-login.php) only.
		\LAL\Src\Cloudflare\LoginGuard::init($config);

		// Front-end routes only (not wp-admin).
		$paths = apply_filters('lal_cloudflare_protected_paths', self::PROTECTED_PATHS);
		\LAL\Src\Cloudflare\RouteGuard::init($config, is_array($paths) ? $paths : self::PROTECTED_PATHS);

		// Protect just the checkout template
		\LAL\Src\Cloudflare\CheckoutGuard::init($config);

	}
}
