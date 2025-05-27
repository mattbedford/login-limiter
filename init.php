<?php
/*
Plugin Name: Login Attempt Limiter
Description: Limits login attempts with ban/whitelist, REST API management, and admin settings/log viewer.
Version: 1.0
Author: Matt Bedford
Author uri: https://mattbedford.com
*/

if (!defined('ABSPATH')) exit;

// Manual includes
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/DBHandler.php';
require_once __DIR__ . '/src/IPBanManager.php';
require_once __DIR__ . '/src/LoginLimiter.php';
require_once __DIR__ . '/src/SettingsPage.php';
require_once __DIR__ . '/src/RestAPI.php';
require_once __DIR__ . '/src/Plugin.php';

add_action('plugins_loaded', function () {
    $plugin = new LAL\Plugin();
    $plugin->init();
});