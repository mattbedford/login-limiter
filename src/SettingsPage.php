<?php

// src/SettingsPage.php
namespace LAL;

class SettingsPage {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu() {
        add_options_page('Login Limiter', 'Login Limiter', 'manage_options', 'login-limiter', [$this, 'page']);
    }

    public function enqueue() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'login-limiter') return;
        wp_enqueue_script('lal-admin', plugin_dir_url(__FILE__) . '../assets/admin.js', ['wp-api'], '1.0', true);
        wp_localize_script('lal-admin', 'lal_vars', [
            'nonce' => wp_create_nonce('wp_rest'),
            'rest_url' => rest_url('login-limiter/v1/')
        ]);
    }

    public function page() {
        ?>
        <div class="wrap">
            <h1>Login Attempt Limiter</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lal_settings_group');
                do_settings_sections('login-attempt-limiter');
                submit_button();
                ?>
            </form>
            <hr>
            <h2>Logs</h2>
            <label>Filter by Date: <input type="date" id="lal-log-filter" value="<?php date('Y-m-d'); ?>"/></label>
            <div id="lal-log-output" style="margin-top:1em;white-space:pre;background:#fff;border:1px solid #ccc;padding:1em;"></div>
            <button id="lal-clear-logs" class="button button-secondary">Clear Logs</button>
        </div>
        <?php
    }
}
