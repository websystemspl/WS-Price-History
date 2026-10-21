<?php

namespace WsPriceHistory\App\Database;

use WsPriceHistory\App\LowestPriceCalculator;

class DatabaseOperation
{
  private static function table()
  {
    global $wpdb;
    return $wpdb->prefix . DatabaseTable::getTableName();
  }

  /**
   * Stores one price state of a product or a variation.
   *
   * @param int        $postId     product or variation ID
   * @param float      $price      effective (charged) price
   * @param float|null $regular    regular price
   * @param string     $salePrice  sale price as set in WooCommerce ('' when none)
   * @param bool       $onSale     effective price is a reduction
   * @param int        $time       UTC timestamp from which the state applies
   */
  public static function insertSnapshot($postId, $price, $regular, $salePrice, $onSale, $time)
  {
    global $wpdb;
    $wpdb->insert(
      self::table(),
      [
        'post_id' => (int) $postId,
        'price' => (string) (float) $price,
        'date' => gmdate('Y-m-d', $time),
        'regular_price' => $regular === null || $regular === '' ? null : (string) (float) $regular,
        'sale_price' => $salePrice === null || $salePrice === '' ? null : (string) (float) $salePrice,
        'on_sale' => $onSale ? 1 : 0,
        'recorded_at' => gmdate('Y-m-d H:i:s', $time),
      ],
      ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
    );
    return $wpdb->insert_id ? (int) $wpdb->insert_id : null;
  }

  /**
   * History of one product/variation as LowestPriceCalculator rows, oldest first.
   * Rows from 1.0.x have no recorded_at: their DATE is read as midnight UTC.
   */
  public static function getRows($postId, $limit = 1000)
  {
    global $wpdb;
    $table = self::table();
    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM (
           SELECT id, price, regular_price, on_sale, COALESCE(recorded_at, CAST(date AS DATETIME)) AS t
           FROM $table WHERE post_id = %d ORDER BY t DESC, id DESC LIMIT %d
         ) x ORDER BY t ASC, id ASC",
        (int) $postId,
        (int) $limit
      ),
      ARRAY_A
    );
    $rows = [];
    foreach ((array) $results as $r) {
      $rows[] = LowestPriceCalculator::makeRow(
        strtotime($r['t'] . ' UTC'),
        $r['price'],
        $r['regular_price'],
        $r['on_sale'] === null ? null : (bool) (int) $r['on_sale']
      );
    }
    return $rows;
  }

  public static function getLastRow($postId)
  {
    $rows = self::getRows($postId, 1);
    return empty($rows) ? null : $rows[0];
  }

  /**
   * Deletes history older than $days, but keeps for every product the newest row from before the
   * cut-off, because that price still applied after the cut-off and is needed for the 30-day window.
   */
  public static function removeOldPrices($days = 365)
  {
    global $wpdb;
    $table = self::table();
    $cutoff = gmdate('Y-m-d H:i:s', time() - (int) $days * 86400);
    return $wpdb->query(
      $wpdb->prepare(
        "DELETE h FROM $table h
         JOIN (
           SELECT post_id, MAX(COALESCE(recorded_at, CAST(date AS DATETIME))) AS keep_t
           FROM $table WHERE COALESCE(recorded_at, CAST(date AS DATETIME)) < %s
           GROUP BY post_id
         ) k ON k.post_id = h.post_id
         WHERE COALESCE(h.recorded_at, CAST(h.date AS DATETIME)) < k.keep_t",
        $cutoff
      )
    );
  }
}
