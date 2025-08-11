<?php
/*
Plugin Name: Login Limiter
Description: Lean login protection (Cloudflare Turnstile + rate limiting + hardening).
Version: 0.0.1
Author: Matt Bedford
Author uri: https://mattbedford.com
*/

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

if (!defined('LAL_PLUGIN_FILE')) define('LAL_PLUGIN_FILE', __FILE__);
if (!defined('LAL_PLUGIN_DIR'))  define('LAL_PLUGIN_DIR',  __DIR__);

// PSR-4 autoloader mapping "LAL\*" → "<plugin>/*"
spl_autoload_register(static function (string $class): void {
	$prefix = 'LAL\\';
	if (strpos($class, $prefix) !== 0) return;
	$relative = substr($class, strlen($prefix));                 // e.g. "Src\Cloudflare\Turnstile\Config"
	$path     = LAL_PLUGIN_DIR . '/' . str_replace('\\','/',$relative) . '.php';
	if (is_file($path)) require $path;
});

add_action('plugins_loaded', static function (): void {
	\LAL\Src\Kernel::boot();
});