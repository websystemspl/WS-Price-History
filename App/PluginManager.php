<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Assets;
use WsPriceHistory\App\Database\DatabaseOperation;
use WsPriceHistory\App\Database\DatabaseTable;


class PluginManager
{
  public function run()
  {
    new Assets;
    $databaseTable = new DatabaseTable;
    $databaseTable->createTable();
    DatabaseOperation::readOnePrice('10581');
    add_action('save_post', [$this, 'writePriceHistoryOnSaveProduct']);
  }

  public function writePriceHistoryOnSaveProduct()
  {
    $postType = $_POST['post_type'];
    if ($postType === 'product') {
      $postID = $_POST['post_ID'];
      $price = '';
      $regularPrice = $_POST['_regular_price'];
      $salePrice = $_POST['_sale_price'];
      $salePriceDatesTo = $_POST['_sale_price_dates_to'];
      $currentDate = date('Y-m-d');
      if ($regularPrice < $salePrice) {
        $price = $regularPrice;
      } else {
        if ($salePriceDatesTo < $currentDate) {
          $price = $regularPrice;
        } else {
          $price = $salePrice;
        }
      }

      $record = new Record('', $postID, $price, date_create_from_format('Y-m-d', $currentDate));
      $record->setId(DatabaseOperation::write($record));
    }
  }
}
