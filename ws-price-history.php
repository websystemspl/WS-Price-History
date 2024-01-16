<?php

/*
 * Plugin Name:       WS Price History
 * Text Domain:       ws-price-history
 * Description:       Add price history for products
 * Version:           1.0
 * Requires at least: 6.0
 * Author:            Web Systems
 * Author URI:        https://www.web-systems.pl/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 */

if (!defined('WPINC')) {
  die;
}

require __DIR__ . '/vendor/autoload.php';

class WsPriceHistory
{
  public function __construct()
  {
    load_plugin_textdomain('ws-price-history', false, dirname(plugin_basename(__FILE__)) . '/languages');
    if (!defined('WS_PRICE_HISTORY_PLUGIN_DIR_PATH')) {
      define('WS_PRICE_HISTORY_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
    }

    if (!defined('WS_PRICE_HISTORY_PLUGIN_DIR_URL')) {
      define('WS_PRICE_HISTORY_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
    }
    register_activation_hook(__FILE__, [$this, 'activate']);
    register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    $pluginManager = new WsPriceHistory\App\PluginManager;
    $pluginManager->run();
  }

  public static function activate()
  {
  }

  public static function deactivate()
  {
  }
}
new WsPriceHistory();
