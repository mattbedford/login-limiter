<?php

namespace LAL\src;

class SettingsPage {
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
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

    public function register_settings() {
        register_setting('lal_settings_group', 'lal_settings');

        add_settings_section('lal_main', 'Login Limiter Settings', null, 'login-attempt-limiter');

        add_settings_field('max_attempts', 'Max Attempts Before Lockout', function () {
            $settings = get_option('lal_settings');
            echo "<input type='number' name='lal_settings[max_attempts]' value='" . esc_attr($settings['max_attempts'] ?? 5) . "' min='1' />";
        }, 'login-attempt-limiter', 'lal_main');

        add_settings_field('timeout_minutes', 'Lockout Duration (minutes)', function () {
            $settings = get_option('lal_settings');
            echo "<input type='number' name='lal_settings[timeout_minutes]' value='" . esc_attr($settings['timeout_minutes'] ?? 10) . "' min='1' />";
        }, 'login-attempt-limiter', 'lal_main');

        add_settings_field(
            'lal_turnstile_sitekey',
            'Cloudflare Turnstile Site Key',
            [$this, 'text_field'],
            'login-attempt-limiter',
            'lal_main',
            ['label_for' => 'lal_turnstile_sitekey']
        );

        add_settings_field(
            'lal_turnstile_secret',
            'Cloudflare Turnstile Secret Key',
            [$this, 'text_field'],
            'login-attempt-limiter',
            'lal_main',
            ['label_for' => 'lal_turnstile_secret']
        );
    }

    public function text_field($args) {
        $name = $args['label_for'];
        $value = esc_attr(get_option($name, ''));
        echo "<input type='text' id='{$name}' name='{$name}' value='{$value}' class='regular-text'>";
    }

    public function page() {
        $bans = get_option('lal_ban_list', ['ips' => [], 'users' => []]);
        $whitelist = get_option('lal_whitelist', []);
        ?>
        <div class="wrap">
            <h1>Login Attempt Limiter</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#settings" class="nav-tab nav-tab-active">Settings</a>
                <a href="#lists" class="nav-tab">Ban/Whitelist</a>
                <a href="#logs" class="nav-tab">Logs</a>
            </h2>

            <div id="settings" class="tab-content" style="display:block">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('lal_settings_group');
                    do_settings_sections('login-attempt-limiter');
                    submit_button();
                    ?>
                </form>
            </div>

            <div id="lists" class="tab-content" style="display:none">
                <form id="lal-banlist-form">
                    <h3>IP Whitelist</h3>
                    <textarea name="whitelist" rows="4" style="width: 100%;"><?php echo esc_textarea(implode("\n", $whitelist)); ?></textarea>

                    <h3>Ban List</h3>
                    <p><strong>IPs:</strong></p>
                    <textarea name="ban_ips" rows="4" style="width: 100%;"><?php echo esc_textarea(implode("\n", $bans['ips'])); ?></textarea>

                    <p><strong>Usernames:</strong></p>
                    <textarea name="ban_users" rows="4" style="width: 100%;"><?php echo esc_textarea(implode("\n", $bans['users'])); ?></textarea>

                    <p><button class="button button-primary">Save Lists</button></p>
                </form>
            </div>

            <div id="logs" class="tab-content" style="display:none">
                <label>Filter by Date (YYYY-MM-DD): <input type="date" id="lal-log-filter" /></label>
                <div id="lal-log-output" style="margin-top:1em;white-space:pre;background:#fff;border:1px solid #ccc;padding:1em;"></div>
                <button id="lal-clear-logs" class="button button-secondary">Clear Logs</button>
            </div>
        </div>
        <script>
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.addEventListener('click', e => {
                    e.preventDefault();
                    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                    tab.classList.add('nav-tab-active');
                    document.querySelector(tab.getAttribute('href')).style.display = 'block';
                });
            });
        </script>
        <?php
    }
}
