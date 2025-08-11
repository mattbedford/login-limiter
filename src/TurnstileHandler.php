<?php declare(strict_types=1);

namespace LAL\src;

use \LAL\Security\Turnstile;
use \LAL\Security\TurnstileOptions;

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
        if (!get_option('lal_turnstile_enabled')) {
            return;
        }
        self::svc()->attachToLogin(); // login only for now
    }

    // Back-compat if anything still calls this
    public static function verify_token(string $token): bool
    {
        return self::svc()->verify($token, $_SERVER['REMOTE_ADDR'] ?? '')->ok;
    }
}
