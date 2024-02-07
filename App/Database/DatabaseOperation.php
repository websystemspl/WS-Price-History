<?php

namespace WsPriceHistory\App\Database;

use DateTime;
use WsPriceHistory\App\Record;
use WsPriceHistory\App\Database\DatabaseTable;

class DatabaseOperation
{
  public static function read(int $id): ?Record
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $tableNameWithPrefix);
    if ($wpdb->get_var($query) === $tableNameWithPrefix) {
      $result = $wpdb->get_row(
        "SELECT " .
          DatabaseTable::getIdColumnName() . ", " .
          DatabaseTable::getPostIdColumnName() . ", " .
          DatabaseTable::getPriceColumnName() . ", " .
          DatabaseTable::getDateColumnName() . ", " .
          " FROM " . $tableNameWithPrefix . " WHERE " . DatabaseTable::getIdColumnName() . " = " . $id . ";",
        "ARRAY_A",
      );
      $id = $result[DatabaseTable::getIdColumnName()];
      $postId = $result[DatabaseTable::getPostIdColumnName()];
      $price = filter_var($result[DatabaseTable::getPriceColumnName()], FILTER_VALIDATE_BOOLEAN);
      $date = DateTime::createFromFormat('Y-m-d H:i:s', $result[DatabaseTable::getDateColumnName()]);
    }
    if ($result != null) {
      $record = new Record($id, $postId, $price, $date);
      return $record;
    } else {
      return null;
    }
  }

  public static function write(Record $record): ?int
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    if ($record != null) {
      $wpdb->insert($tableNameWithPrefix, array(
        DatabaseTable::getPostIdColumnName() => $record->getPostId(),
        DatabaseTable::getPriceColumnName() => floatval($record->getPrice()),
        DatabaseTable::getDateColumnName() => $record->getDate()->format('Y-m-d'),
      ));
      $lastId = $wpdb->insert_id;
      if ($lastId !== null) {
        return $lastId;
      }
    } else {
      return null;
    }
  }

  public static function getPreviousPrice($postId)
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    $query = $wpdb->prepare("SELECT price as lowest_price FROM $tableNameWithPrefix WHERE post_id = %s ORDER BY date DESC LIMIT 1;", [$postId]);
    $result = $wpdb->get_row($query);
    if ($result->lowest_price != null) {
      return $result->lowest_price;
    } else {
      return null;
    }
  }

  public static function readOnePrice($postId)
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    $query = $wpdb->prepare("SELECT MIN(price) AS lowest_price FROM $tableNameWithPrefix WHERE post_id = %s AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY);", [$postId]);
    $result = $wpdb->get_row($query);
    if ($result->lowest_price != null) {
      return $result->lowest_price;
    } else {
      return null;
    }
  }

  public static function readAllPrices()
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    $query = $wpdb->prepare("SELECT %s, MIN(price) AS lowest_price FROM $tableNameWithPrefix WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY post_id;", [DatabaseTable::getPostIdColumnName()]);
    $result = $wpdb->get_results($query, 'ARRAY_N');
    if ($result != null) {
      return $result;
    } else {
      return null;
    }
  }
  public static function removeOldPrices()
  {
    global $wpdb;
    $tableNameWithPrefix = $wpdb->prefix . DatabaseTable::getTableName();
    $query = $wpdb->prepare("DELETE FROM $tableNameWithPrefix WHERE date < DATE_SUB(NOW(), INTERVAL 30 DAY);");
    $result = $wpdb->get_results($query, 'ARRAY_N');
    if ($result != null) {
      return $result;
    } else {
      return null;
    }
  }
}
