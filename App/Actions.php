<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Database\DatabaseOperation;

class Actions {
    public function __construct()
    {
        \add_action( 'woocommerce_before_add_to_cart_form', [$this, 'showTheLowestPrice'], 10, 0 );
    }

    public function showTheLowestPrice() {
        // Get the global product object
        global $product;
        
        // Is a WC product
        if ( is_a( $product, 'WC_Product' ) && $product->is_on_sale() ) {
            $price = DatabaseOperation::readOnePrice($product->id);
            echo "<p class='the-lowest-price'>";
            echo __("The lowest price from 30 days: ", "ws_price_history") . wc_price($price);
            echo "</p>";
        }
    }
}


