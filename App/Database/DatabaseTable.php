<?php

namespace WsPriceHistory\App\Database;

class DatabaseTable
{
  private static $tableName = "ws_price_history";
  private static $idColumnName = "id";
  private static $postIdColumnName = "post_id";
  private static $dateColumnName = "date";
  private static $priceColumnName = "price";

  public static function getTableName()
  {
    return self::$tableName;
  }

  public static function getIdColumnName()
  {
    return self::$idColumnName;
  }

  public static function getPostIdColumnName()
  {
    return self::$postIdColumnName;
  }

  public static function getPriceColumnName()
  {
    return self::$priceColumnName;
  }

  public static function getDateColumnName()
  {
    return self::$dateColumnName;
  }

  public function createTable()
  {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $tableNameWithPrefix = $wpdb->prefix . self::getTableName();
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $tableNameWithPrefix);
    if ($wpdb->get_var($query) != $tableNameWithPrefix) {
      $sql_query = "CREATE TABLE IF NOT EXISTS `$tableNameWithPrefix` (
        " . self::getIdColumnName() . " bigint(50) NOT NULL AUTO_INCREMENT,
        " . self::getPostIdColumnName() . " bigint(50) NOT NULL,
        " . self::getPriceColumnName() . " TEXT NOT NULL,
        " . self::getDateColumnName() . " DATE NOT NULL,
        PRIMARY KEY (" . self::getIdColumnName() . ")) $charset_collate;";
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql_query);
      $is_error = empty($wpdb->last_error);
      return $is_error;
    }
  }
}
