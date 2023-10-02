<?php

use WsPriceHistory\App\Database\DatabaseTable;

if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

function dropTable()
{
  global $wpdb;
  $tableNameWithPrefix = $wpdb->prefix . 'ws_price_history';
  $query = $wpdb->prepare('DROP TABLE IF EXISTS %s', $tableNameWithPrefix);
  $wpdb->query($query);
}

dropTable();
