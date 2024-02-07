<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

function wsph_drop_table()
{
  global $wpdb;
  $tableNameWithPrefix = $wpdb->prefix . 'ws_price_history';
  $query = $wpdb->prepare('DROP TABLE IF EXISTS %s', $tableNameWithPrefix);
  $wpdb->query($query);
}

wsph_drop_table();
