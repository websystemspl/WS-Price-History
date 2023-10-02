<?php

use WsPriceHistory\App\Database\DatabaseTable;

if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

DatabaseTable::dropTable();
