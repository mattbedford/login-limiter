<?php
declare(strict_types=1);

namespace LAL\Src\Includes;

final class Settings
{
	private const MENU_SLUG      = 'lal-settings';
	private const TAB_CLOUDFLARE = 'cloudflare';
	private const TAB_LIMITER    = 'limiter';

	public static function boot(): void
	{
		add_action('admin_menu', [self::class, 'addMenu']);
		add_action('admin_init', [self::class, 'register']);
	}

	public static function addMenu(): void
	{
		add_options_page(
			__('Login Limiter', 'lal'),
			__('Login Limiter', 'lal'),
			'manage_options',
			self::MENU_SLUG,
			[self::class, 'renderPage']
		);
	}

	public static function register(): void
	{
		// ===== Cloudflare Turnstile (grouped option) =====
		register_setting('lal_cloudflare_group', 'lal_turnstile', [
			'type'              => 'array',
			'sanitize_callback' => [self::class, 'sanitizeTurnstile'],
			'default'           => [],
		]);

		add_settings_section(
			'lal_turnstile_section',
			__('Cloudflare Turnstile', 'lal'),
			function (): void {
				echo '<p>' . esc_html__('Enter your site and secret keys. Choose theme and size.', 'lal') . '</p>';
			},
			self::pageId(self::TAB_CLOUDFLARE)
		);

		add_settings_field(
			'lal_turnstile_site_key',
			__('Site key', 'lal'),
			[self::class, 'fieldSiteKey'],
			self::pageId(self::TAB_CLOUDFLARE),
			'lal_turnstile_section'
		);

		add_settings_field(
			'lal_turnstile_secret_key',
			__('Secret key', 'lal'),
			[self::class, 'fieldSecretKey'],
			self::pageId(self::TAB_CLOUDFLARE),
			'lal_turnstile_section'
		);

		add_settings_field(
			'lal_turnstile_theme',
			__('Theme', 'lal'),
			[self::class, 'fieldTheme'],
			self::pageId(self::TAB_CLOUDFLARE),
			'lal_turnstile_section'
		);

		add_settings_field(
			'lal_turnstile_size',
			__('Size', 'lal'),
			[self::class, 'fieldSize'],
			self::pageId(self::TAB_CLOUDFLARE),
			'lal_turnstile_section'
		);

		// ===== Login Limits (placeholder for now) =====
		add_settings_section(
			'lal_limits_section',
			__('Login Limits', 'lal'),
			function (): void {
				echo '<p>' . esc_html__('Configure IP/username throttling and lockouts (coming next).', 'lal') . '</p>';
			},
			self::pageId(self::TAB_LIMITER)
		);
		// We’ll register the limiter settings group and fields in the next step.
	}

	// ---------- Rendering ----------

	public static function renderPage(): void
	{
		if (!current_user_can('manage_options')) return;

		$activeTab = self::currentTab();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Login Limiter', 'lal') . '</h1>';

		// Tabs
		$baseUrl = admin_url('options-general.php?page=' . self::MENU_SLUG);
		echo '<h2 class="nav-tab-wrapper" style="margin-top:1rem;">';
		self::tabLink($baseUrl, self::TAB_CLOUDFLARE, __('Cloudflare', 'lal'), $activeTab);
		self::tabLink($baseUrl, self::TAB_LIMITER,   __('Login Limits', 'lal'), $activeTab);
		echo '</h2>';

		// Page content
		if ($activeTab === self::TAB_CLOUDFLARE) {
			echo '<form method="post" action="options.php">';
			settings_fields('lal_cloudflare_group');
			do_settings_sections(self::pageId(self::TAB_CLOUDFLARE));
			submit_button(__('Save Changes', 'lal'));
			echo '</form>';
		} else {
			echo '<div style="max-width:780px;">';
			do_settings_sections(self::pageId(self::TAB_LIMITER));
			echo '<p><em>' . esc_html__('We’ll add controls here in the next step.', 'lal') . '</em></p>';
			echo '</div>';
		}

		echo '</div>';
	}

	private static function tabLink(string $baseUrl, string $tab, string $label, string $activeTab): void
	{
		$url  = esc_url($baseUrl . '&tab=' . $tab);
		$cls  = 'nav-tab' . ($activeTab === $tab ? ' nav-tab-active' : '');
		echo '<a href="' . $url . '" class="' . $cls . '">' . esc_html($label) . '</a>';
	}

	private static function currentTab(): string
	{
		$tab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : self::TAB_CLOUDFLARE;
		return in_array($tab, [self::TAB_CLOUDFLARE, self::TAB_LIMITER], true) ? $tab : self::TAB_CLOUDFLARE;
	}

	private static function pageId(string $tab): string
	{
		return self::MENU_SLUG . '-' . $tab;
	}

	// ---------- Sanitization ----------

	/** @return array{site_key:string,secret_key:string,theme:string,size:string} */
	public static function sanitizeTurnstile(mixed $raw): array
	{
		$options = is_array($raw) ? $raw : [];

		$siteKey   = isset($options['site_key'])   && is_string($options['site_key'])   ? trim($options['site_key'])   : '';
		$secretKey = isset($options['secret_key']) && is_string($options['secret_key']) ? trim($options['secret_key']) : '';

		$theme = isset($options['theme']) && is_string($options['theme']) ? $options['theme'] : 'auto';
		$size  = isset($options['size'])  && is_string($options['size'])  ? $options['size']  : 'normal';

		$allowedThemes = ['auto', 'light', 'dark'];
		$allowedSizes  = ['normal', 'compact', 'flexible'];

		return [
			'site_key'   => $siteKey,
			'secret_key' => $secretKey,
			'theme'      => in_array($theme, $allowedThemes, true) ? $theme : 'auto',
			'size'       => in_array($size,  $allowedSizes,  true) ? $size  : 'normal',
		];
	}

	// ---------- Field renderers ----------

	public static function fieldSiteKey(): void
	{
		$options = (array) get_option('lal_turnstile', []);
		$value   = is_string($options['site_key'] ?? null) ? $options['site_key'] : '';
		echo '<input type="text" class="regular-text" name="lal_turnstile[site_key]" value="' . esc_attr($value) . '">';
	}

	public static function fieldSecretKey(): void
	{
		$options = (array) get_option('lal_turnstile', []);
		$value   = is_string($options['secret_key'] ?? null) ? $options['secret_key'] : '';
		echo '<input type="password" class="regular-text" name="lal_turnstile[secret_key]" value="' . esc_attr($value) . '" autocomplete="new-password">';
	}

	public static function fieldTheme(): void
	{
		$options = (array) get_option('lal_turnstile', []);
		$value   = is_string($options['theme'] ?? null) ? $options['theme'] : 'auto';
		$choices = ['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark'];
		echo '<select name="lal_turnstile[theme]">';
		foreach ($choices as $k => $label) {
			echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}

	public static function fieldSize(): void
	{
		$options = (array) get_option('lal_turnstile', []);
		$value   = is_string($options['size'] ?? null) ? $options['size'] : 'normal';
		$choices = ['normal' => 'Normal', 'compact' => 'Compact', 'flexible' => 'Flexible'];
		echo '<select name="lal_turnstile[size]">';
		foreach ($choices as $k => $label) {
			echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}
}
