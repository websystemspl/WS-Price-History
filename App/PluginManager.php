<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Assets;
use WsPriceHistory\App\Actions;
use WsPriceHistory\App\Database\DatabaseOperation;
use WsPriceHistory\App\Database\DatabaseTable;


class PluginManager
{
  public function run()
  {
    new Assets;
    new Actions;
    $databaseTable = new DatabaseTable;
    $databaseTable->createTable();
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

      if($salePrice === ""){
        $price = $regularPrice;
      }else{
        if($regularPrice < $salePrice){
          $price = $regularPrice;
        }else {
          if($salePriceDatesTo != ""){
            if ($salePriceDatesTo < $currentDate) {
              $price = $regularPrice;
            } else {
              $price = $salePrice;
            }
          }else{
            $price = $salePrice;
          }
      }     
    }
      $previousPrice = DatabaseOperation::getPreviousPrice($_POST['post_ID']);
      if($previousPrice != $price){
        $record = new Record('', $postID, $price, date_create_from_format('Y-m-d', $currentDate));
        $record->setId(DatabaseOperation::write($record));
      }
    }
  }
}
