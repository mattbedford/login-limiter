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


