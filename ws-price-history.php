<?php

/*
 * Plugin Name:       WS Price History
 * Text Domain:       ws-price-history
 * Description:       Price history for products.
 * Version:           1.0
 * Requires at least: 6.0
 * Author:            Web Systems
 * Author URI:        https://www.k4.pl/
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Tested up to:      6.0
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
    if (!defined('WSPH_PRICE_HISTORY_PLUGIN_DIR_PATH')) {
      define('WSPH_PRICE_HISTORY_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
    }

    if (!defined('WSPH_PRICE_HISTORY_PLUGIN_DIR_URL')) {
      define('WSPH_PRICE_HISTORY_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
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
