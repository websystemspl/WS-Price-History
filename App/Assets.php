<?php

namespace WsPriceHistory\App;

class Assets
{
  public function __construct()
  {
    \add_action('wp_enqueue_scripts', [$this, "addStylesAndScripts"], 10);
    \add_action('admin_enqueue_scripts', [$this, "addAdminStylesAndScripts"], 10);
  }

  public function addStylesAndScripts()
  {
    /* Styles */


    /* Scripts */
  }

  public function addAdminStylesAndScripts($hook)
  {
    wp_enqueue_script('ws-price-history-admin-js', WSPH_PRICE_HISTORY_PLUGIN_DIR_URL . 'assets/js/admin/indexAllPrices.js', array('jquery'));
    wp_localize_script('ws-price-history-admin-js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('ws-price-history-nonce')));
  }
}
