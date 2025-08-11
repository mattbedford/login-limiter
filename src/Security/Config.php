<?php
declare(strict_types=1);

namespace LAL\src\Security;

final class Config
{
	public function __construct(
		public readonly string $siteKey,
		public readonly string $secretKey,
		public readonly string $mode = 'managed',   // managed | invisible | non-interactive
		public readonly string $size = 'normal',    // normal | compact | flexible
		public readonly string $theme = 'auto'      // auto | light | dark
	) {}

	public static function fromOptions(): self
	{
		$opts = get_option('lal_turnstile', []);
		$site  = is_string($opts['site_key'] ?? '') ? $opts['site_key'] : '';
		$secret= is_string($opts['secret_key'] ?? '') ? $opts['secret_key'] : '';

		if ($site === '' || $secret === '') {
			throw new \RuntimeException('Turnstile keys are missing.');
		}

		$mode  = in_array(($opts['mode'] ?? 'managed'), ['managed','invisible','non-interactive'], true)
			? $opts['mode'] : 'managed';

		$size  = in_array(($opts['size'] ?? 'normal'), ['normal','compact','flexible'], true)
			? $opts['size'] : 'normal';

		$theme = in_array(($opts['theme'] ?? 'auto'), ['auto','light','dark'], true)
			? $opts['theme'] : 'auto';

		return new self($site, $secret, $mode, $size, $theme);
	}
}
