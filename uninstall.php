<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

function wsph_drop_table()
{
  global $wpdb;
  $tableNameWithPrefix = esc_sql($wpdb->prefix . 'ws_price_history');
  // Identifiers cannot be passed through $wpdb->prepare() placeholders (they would be quoted as strings).
  $wpdb->query("DROP TABLE IF EXISTS `$tableNameWithPrefix`");
  delete_option('wsph_db_version');
  delete_post_meta_by_key('_wsph_perishable');
}

wsph_drop_table();
