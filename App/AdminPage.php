<?php

namespace WsPriceHistory\App;

class AdminPage
{
	private $capability = 'manage_options';
	private const WS_PRICE_HISTORY_SETTINGS_KEY = "ws_price_history_plugin_options";

	private $generalFields = [];

	private $fields = [];

	public function __construct()
	{
		add_action('admin_init', [$this, 'settings_init']);
		add_action('admin_menu', [$this, 'options_page']);
		add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 20);
	}

	public function saveDefaultData()
	{
	}

	public function admin_enqueue_scripts($screen)
	{
		if ('toplevel_page_ws-price-history-settings' !== $screen) return;
		wp_enqueue_script('ws-price-history-settings-js', WSPH_PRICE_HISTORY_PLUGIN_DIR_URL . 'assets/js/admin/sections.js', array(), null, true);
		wp_enqueue_style('ws-price-history-settings-style', WSPH_PRICE_HISTORY_PLUGIN_DIR_URL . 'assets/css/admin/sections.css');
	}

	public function settings_init(): void
	{
		register_setting('ws-price-history-settings', self::WS_PRICE_HISTORY_SETTINGS_KEY);

		add_settings_section(
			'ws-price-history-general-section',
			__('General', 'ws-price-history'),
			[$this, 'render_section'],
			'ws-price-history-settings'
		);
		// add_settings_section(
		// 	'ws-price-history-button-section',
		// 	__('Button', 'ws-price-history-settings'),
		// 	[$this, 'render_section'],
		// 	'ws-price-history-settings'
		// );
		add_settings_section(
			'ws-licence-section',
			__('Pro Version', 'ws-price-history'),
			[$this, 'renderLicenceSection'],
			'ws-price-history-settings'
		);

		$this->addSettingFields($this->generalFields, [$this, 'render_field'], 'ws-price-history-settings', 'ws-price-history-general-section');
		$this->addSettingFields($this->fields, [$this, 'render_field'], 'ws-price-history-settings', 'ws-price-history-button-section');
	}

	public function addSettingFields($fields, $callback, $menuPage, $section)
	{
		foreach ($fields as $field) {
			add_settings_field(
				$field['id'],
				$field['label'],
				$callback,
				$menuPage,
				$section,
				[
					'label_for' => $field['id'],
					'class' => 'wporg_row',
					'field' => $field,
				]
			);
		}
	}

	public function options_page(): void
	{
		add_menu_page(
			'Settings',
			__('WS Price History Settings', 'ws-price-history'),
			$this->capability,
			'ws-price-history-settings',
			[$this, 'render_options_page'],
			'dashicons-money-alt',
			'80',
		);
	}

	public function render_options_page(): void
	{
		if (!current_user_can($this->capability)) {
			return;
		}

		if (isset($_GET['settings-updated'])) {
			add_settings_error('ws_messages', 'ws_message', __('Settings Saved', 'ws-price-history'), 'updated');
		}

		global $wp_settings_sections;
		if (isset($_GET['page']) && sanitize_text_field($_GET['page']) === "ws-price-history-settings") {
			$page = sanitize_text_field($_GET['page']);
			$sections = $wp_settings_sections[$page];
		} else {
			return;
		}
?>
		<div id="settings-container" class="wrap">
			<div class="messages-box"><?php settings_errors('ws_messages'); ?></div>
			<div class="information-container">
				<div class="first-row">
					<a href="<?php echo "https://k4.pl/en/" ?>"><img width="100px" height="100px" src="<?php echo esc_attr(WSPH_PRICE_HISTORY_PLUGIN_DIR_URL . "assets/src/img/K4-logo.png"); ?>"></img></a>
					<p><?php echo esc_html__(' WS Price History', 'ws-price-history'); ?></p>
				</div>
				<div class="description">
					<p><?php echo esc_html__('Price history for products.', 'ws-price-history'); ?></p>
				</div>
			</div>
			<div class="settings-tabs">
				<?php
				foreach ($sections as $section) {
				?>
					<a href="<?php echo "#" . esc_attr($section["id"]); ?>"><?php echo esc_html($section["title"]); ?></a>
				<?php
				}
				?>
			</div>
			<form action="options.php" method="post">
				<?php
				settings_fields('ws-price-history-settings');
				?>
				<div id="ws-price-history-general-section" class="active">
					<div class="index-all-prices">
						<p><?php echo esc_html__("Press to index all prices: ", 'ws-price-history'); ?></p>
						<a id="bulk-index-all-prices" href="#" class="button-primary"><?php echo esc_html__("Index all prices", 'ws-price-history'); ?></a>
						<p class="index-success"><?php echo esc_html__("Success", 'ws-price-history'); ?></p>
					</div>
					<div class="remove-all-old-prices">
						<p><?php echo esc_html__("Press to remove prices older than 30 days: ", 'ws-price-history'); ?></p>
						<a id="bulk-remove-all-old-prices" href="#" class="button-primary"><?php echo esc_html__("Remove prices older than 30 days", 'ws-price-history'); ?></a>
						<p class="remove-success"><?php echo esc_html__("Success", 'ws-price-history'); ?></p>
					</div>
					<?php do_settings_fields('ws-price-history-settings', 'ws-price-history-general-section'); ?>
				</div>
				<!-- <div id="ws-price-history-button-section"><?php do_settings_fields('ws-price-history-settings',  'ws-price-history-button-section'); ?></div> -->
				<div id="ws-licence-section"><?php echo esc_html($this->renderLicenceSection()); ?></div>
				<?php
				//submit_button('Save Settings');
				?>
			</form>
		</div>
		<?php
	}

	public function render_field(array $args): void
	{
		$field = $args['field'];
		$options = get_option(self::WS_PRICE_HISTORY_SETTINGS_KEY);
		switch ($field['type']) {
			case "text": {
		?>
					<input type="text" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "checkbox": {
				?>
					<input type="checkbox" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="1" <?php echo isset($options[$field['id']]) ? (checked($options[$field['id']], 1, false)) : (''); ?>>
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "textarea": {
				?>
					<textarea id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]"><?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?></textarea>
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "select": {
				?>
					<select id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]">
						<?php foreach ($field['options'] as $key => $option) { ?>
							<option value="<?php echo esc_html($key); ?>" <?php echo isset($options[$field['id']]) ? (selected($options[$field['id']], $key, false)) : (''); ?>>
								<?php echo esc_html($option); ?>
							</option>
						<?php } ?>
					</select>
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "password": {
				?>
					<input type="password" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "wysiwyg": {
					wp_editor(
						isset($options[$field['id']]) ? $options[$field['id']] : '',
						$field['id'],
						array(
							'textarea_name' => self::WS_PRICE_HISTORY_SETTINGS_KEY[$field["id"]],
							'textarea_rows' => 5,
						)
					);
					break;
				}
			case "email": {
				?>
					<input type="email" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "url": {
				?>
					<input type="url" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "color": {
				?>
					<input type="color" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "date": {
				?>
					<input type="date" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
				<?php
					break;
				}
			case "number": {
				?>
					<input type="number" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr(self::WS_PRICE_HISTORY_SETTINGS_KEY); ?>[<?php echo esc_attr($field['id']); ?>]" value="<?php echo isset($options[$field['id']]) ? esc_attr($options[$field['id']]) : ''; ?>" min="0" max="100">
					<p class="description">
						<?php echo esc_html($field['description']); ?>
					</p>
		<?php
					break;
				}
		}
	}
	public function render_section(array $args): void
	{
		?>
		<div class="<?php echo esc_attr($args['id']); ?>">
			<p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('', 'ws-price-history-settings'); ?></p>
		</div>

	<?php
	}
	public function renderLicenceSection()
	{
	?>
		<h2><?php echo esc_html__("Pro version features", "ws-price-history"); ?></h2>
		<ul class="pro-version-fatures-list">
			<li class="feature"><?php echo esc_html__("More customisation options", "ws-price-history"); ?></li>
			<li class="feature"><?php echo esc_html__("Automatic database table optimization", "ws-price-history"); ?></li>
			<li class="feature"><?php echo esc_html__("Advanced support", "ws-price-history"); ?></li>
		</ul>
		<p class="premium-version"><?php echo esc_html__("To get premium version visit this link:", "ws-price-history") . " "; ?><a href="https://k4.pl/en/shop"><?php echo esc_html__("K4-shop", "ws-price-history-settings"); ?></a></p>
<?php
	}
}
