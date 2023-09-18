<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Database\DatabaseOperation;

class Actions {
    public function __construct()
    {
        \add_action( 'woocommerce_before_add_to_cart_form', [$this, 'showTheLowestPrice'], 10, 0 );
        add_action( 'wp_ajax_nopriv_index_all_prices', [$this, 'index_all_prices']);
        add_action( 'wp_ajax_index_all_prices', [$this, 'index_all_prices']);
        add_action('save_post', [$this, 'writePriceHistoryOnSaveProduct']);
    }

    public function showTheLowestPrice() {
        global $product;
        if ( is_a( $product, 'WC_Product' ) && $product->is_on_sale() ) {
            $price = DatabaseOperation::readOnePrice($product->id);
            echo "<p class='the-lowest-price'>";
            echo __("The lowest price from 30 days: ", "ws_price_history") . wc_price($price);
            echo "</p>";
        }
    }

    public function writePriceHistoryOnSaveProduct(){
    $postType = $_POST['post_type'];
    if ($postType === 'product') {
      $postID = $_POST['post_ID'];
      $product = \wc_get_product( $postID );
      $price = $product->get_price();
      $currentDate = date('Y-m-d');
      $previousPrice = DatabaseOperation::getPreviousPrice($_POST['post_ID']);
      if($previousPrice != $price){
        $record = new Record('', $product->ID, $price, date_create_from_format('Y-m-d', $currentDate));
        $record->setId(DatabaseOperation::write($record));
      }
    }
  }

    public function index_all_prices(){
        if($_POST['action'] = 'indexAllPrices'){
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
            );
            $loop = new \WP_Query( $args );
            $products = $loop->posts;
            $currentDate = date('Y-m-d');
            foreach ($products as $product){
                $product = \wc_get_product( $product->ID );
                $price = $product->get_price();
                $record = new Record('', $product->id, $price, date_create_from_format('Y-m-d', $currentDate));
                $record->setId(DatabaseOperation::write($record));
            }
            json_encode("done");
        }
    }
}


