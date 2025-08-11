<?php
declare(strict_types=1);

namespace LAL\Src;

use LAL\Src\Cloudflare\Config;
use LAL\Src\Cloudflare\LoginGuard;
use LAL\Src\Cloudflare\RouteGuard;
use LAL\Src\Includes\Settings;

final class Kernel
{
	public static function boot(): void
	{
		// Admin UI
		Settings::boot();

		// Runtime guards
		$config = Config::load();
		if ($config === null) {
			return; // no keys yet; UI still available
		}

		// wp-login.php
		LoginGuard::init($config);

		// Front-end guarded paths
		RouteGuard::init($config, ['/my-account/', '/checkout/']);
	}
}