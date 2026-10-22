<?php

namespace WsPriceHistory\App\Database;

class DatabaseTable
{
  const DB_VERSION = '1.1.0';
  const DB_VERSION_OPTION = 'wsph_db_version';

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

  /**
   * Creates the table or migrates it to the current schema. Runs only when the stored schema
   * version differs, not on every request.
   *
   * 1.1.0 adds regular_price, sale_price, on_sale and recorded_at (UTC, with time). The new
   * columns are nullable, so rows written by 1.0.x stay valid and are read as "legacy" rows.
   */
  public function createTable()
  {
    if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
      return true;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . self::getTableName();

    // dbDelta format: one column per line, two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE $table (
  id bigint(50) NOT NULL AUTO_INCREMENT,
  post_id bigint(50) NOT NULL,
  price TEXT NOT NULL,
  date DATE NOT NULL,
  regular_price varchar(32) NULL DEFAULT NULL,
  sale_price varchar(32) NULL DEFAULT NULL,
  on_sale tinyint(1) NULL DEFAULT NULL,
  recorded_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY post_id_recorded (post_id,recorded_at)
) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (empty($wpdb->last_error)) {
      update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
      return true;
    }
    return false;
  }
}
