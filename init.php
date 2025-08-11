<?php
/*
Plugin Name: Login Limiter
Description: Limits login attempts with IP and username management
Version: 2.2.3
Author: Matt Bedford
Author uri: https://mattbedford.com\
Text domain: lal;
*/


if (!defined('ABSPATH')) exit;

if (!defined('LAL_PLUGIN_FILE')) {
    define('LAL_PLUGIN_FILE', __FILE__);
}

/**
 * Minimal PSR-4-style autoloader:
 * Maps "LAL\src\..." to "<plugin>/src/..." (and "LAL\Anything\..." to "<plugin>/Anything/...").
 */
spl_autoload_register(static function (string $class): void {
	$prefix = 'LAL\\';
	$len = strlen($prefix);
	if (strncmp($class, $prefix, $len) !== 0) {
		return;
	}
	$relative = substr($class, $len);            // e.g. "src\Security\Config"
	$path     = LAL_PLUGIN_DIR . '/' . str_replace('\\', '/', $relative) . '.php';
	if (is_file($path)) {
		require $path;
	}
});


require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/DBHandler.php';
require_once __DIR__ . '/src/IPBanManager.php';
require_once __DIR__ . '/src/LoginLimiter.php';
require_once __DIR__ . '/src/SettingsPage.php';
require_once __DIR__ . '/src/RestAPI.php';
require_once __DIR__ . '/src/StopUserEnumeration.php';
require_once __DIR__ . '/src/Security/Turnstile.php';
require_once __DIR__ . '/src/Security/TurnstileOptions.php';
require_once __DIR__ . '/src/TurnstileHandler.php';



add_action('plugins_loaded', function () {
	\LAL\src\StopUserEnumeration::init();
	new \LAL\src\LoginLimiter();
	new \LAL\src\SettingsPage();
	new \LAL\src\RestAPI();
	\LAL\src\TurnstileHandler::init();
});


