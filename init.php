<?php
/*
Plugin Name: Login Limiter
Description: Limits login attempts with IP and username management
Version: 2.2.3
Author: Matt Bedford
Author uri: https://mattbedford.com
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


// On activate, trigger table creation for ip addresses
register_activation_hook( __FILE__, static function() {
    require_once plugin_dir_path( __FILE__ ) . '/src/DBHandler.php';
    \Lal\src\DBHandler::create_table();
} );


add_action('plugins_loaded', function () {
    \Lal\src\StopUserEnumeration::init();
    new \Lal\src\LoginLimiter();
    new \Lal\src\IPBanManager();
    new \Lal\src\SettingsPage();
    new \Lal\src\RestAPI();
});


