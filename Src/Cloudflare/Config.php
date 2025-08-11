<?php
declare(strict_types=1);

namespace LAL\Src\Cloudflare\Turnstile;

final class Config
{
	public function __construct(
		public readonly string $siteKey,
		public readonly string $secretKey,
		public readonly string $theme = 'auto',   // auto|light|dark
		public readonly string $size  = 'normal'  // normal|compact|flexible
	) {}

	public static function load(): ?self
	{
		$group  = (array) get_option('lal_turnstile', []);
		$site   = is_string($group['site_key']   ?? null) ? trim($group['site_key'])   : '';
		$secret = is_string($group['secret_key'] ?? null) ? trim($group['secret_key']) : '';
		$theme  = is_string($group['theme'] ?? null) ? $group['theme'] : 'auto';
		$size   = is_string($group['size']  ?? null) ? $group['size']  : 'normal';

		// Legacy fallbacks (individual options)
		if ($site === '')   $site   = (string) get_option('lal_turnstile_sitekey', '');
		if ($secret === '') $secret = (string) get_option('lal_turnstile_secret', '');

		// Constant fallbacks
		if ($site === ''   && defined('LAL_TURNSTILE_SITE'))   $site   = (string) LAL_TURNSTILE_SITE;
		if ($secret === '' && defined('LAL_TURNSTILE_SECRET')) $secret = (string) LAL_TURNSTILE_SECRET;
		if ($site === ''   && defined('TURNSTILE_SITEKEY'))    $site   = (string) TURNSTILE_SITEKEY;
		if ($secret === '' && defined('TURNSTILE_SECRET'))     $secret = (string) TURNSTILE_SECRET;

		if (!in_array($size, ['normal','compact','flexible'], true)) $size = 'normal';
		if ($site === '' || $secret === '') return null;

		return new self($site, $secret, $theme, $size);
	}
}
