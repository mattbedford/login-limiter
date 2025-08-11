<?php
declare(strict_types=1);

namespace LAL\Src;

use LAL\Src\Cloudflare\Config;
use LAL\Src\Cloudflare\LoginGuard;
use LAL\Src\Cloudflare\RouteGuard;

final class Kernel
{
	public static function boot(): void
	{
		$config = Config::load();
		if ($config === null) return;

		// wp-login.php
		LoginGuard::init($config);

		// Front-end guarded paths
		RouteGuard::init($config, ['/my-account/', '/checkout/']);
	}
}