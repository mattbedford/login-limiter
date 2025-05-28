<?php
/*
Plugin Name: Login Attempt Limiter
Description: Limits login attempts with IP and username management
Version: 1.5.2
Author: Matt Bedford
Author uri: https://mattbedford.com
Text domain: lal;
*/

namespace LAL;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/DBHandler.php';
require_once __DIR__ . '/src/IPBanManager.php';
require_once __DIR__ . '/src/LoginLimiter.php';
require_once __DIR__ . '/src/SettingsPage.php';
require_once __DIR__ . '/src/RestAPI.php';
require_once __DIR__ . '/src/Helpers.php';


// On activate, trigger table creation for ip addresses
register_activation_hook( __FILE__, static function() {
    require_once plugin_dir_path( __FILE__ ) . '/src/DBHandler.php';
    DBHandler::create_table();
} );


add_action('plugins_loaded', function () {
    new LoginLimiter();
    new IPBanManager();
    new SettingsPage();
    new RestAPI();
});


