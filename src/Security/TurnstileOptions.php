<?php declare(strict_types=1);

namespace LAL\Security;

final class TurnstileOptions
{
    /** @var string[] */
    private const THEMES = ['auto', 'light', 'dark'];

    /** @var string[] */
    private const SIZES  = ['normal', 'compact', 'flexible'];

    public string $siteKey;
    public string $secretKey;
    public string $theme;
    public string $size;

    public function __construct(
        string $siteKey,
        string $secretKey,
        string $theme = 'auto',
        string $size  = 'normal'
    ) {
        $this->siteKey   = $siteKey;
        $this->secretKey = $secretKey;
        $this->theme     = $this->sanitizeTheme($theme);
        $this->size      = $this->sanitizeSize($size);
    }

    private function sanitizeTheme(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, self::THEMES, true) ? $v : 'auto';
    }

    private function sanitizeSize(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, self::SIZES, true) ? $v : 'normal';
    }
}