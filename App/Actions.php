<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Database\DatabaseOperation;

class Actions
{
    public function __construct()
    {
        \add_action('woocommerce_before_add_to_cart_form', [$this, 'showTheLowestPrice'], 10, 0);
        add_action('wp_ajax_index_all_prices', [$this, 'index_all_prices']);
        add_action('wp_ajax_remove_old_prices', [$this, 'remove_old_prices']);
        add_action('save_post', [$this, 'writePriceHistoryOnSaveProduct'], 10, 2);
    }

    public function showTheLowestPrice()
    {
        global $product;
        if (is_a($product, 'WC_Product') && $product->is_on_sale()) {
            $price = DatabaseOperation::readOnePrice($product->id);
            if(!is_null($price)){
                $html = "<p class='the-lowest-price'>";
                $html .= __("The lowest price from 30 days: ", "ws_price_history") . wc_price($price);
                $html .= "</p>";
                echo $html;
            }           
        }
    }

    public function writePriceHistoryOnSaveProduct($postID, $post)
    {
        $postType = $post->post_type;
        if ($postType === 'product') {
            $postID = $_POST['post_ID'];
            $product = \wc_get_product($postID);
            $productID = $product->get_id();
            $price = $product->get_price();
            $currentDate = date('Y-m-d');
            $previousPrice = DatabaseOperation::getPreviousPrice($postID);
            if ($previousPrice != $price) {
                $record = new Record('', $productID, $price, date_create_from_format('Y-m-d', $currentDate));
                $record->setId(DatabaseOperation::write($record));
            }
        }
    }

    public function index_all_prices()
    {
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'ws-price-history-nonce')){
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
            );
            $loop = new \WP_Query($args);
            $products = $loop->posts;
            $currentDate = date('Y-m-d');
            foreach ($products as $product) {
                $product = \wc_get_product($product->ID);
                $price = $product->get_price();
                $record = new Record('', $product->id, $price, date_create_from_format('Y-m-d', $currentDate));
                $record->setId(DatabaseOperation::write($record));
            }
            json_encode("done");
        } else {
            json_encode("error");
        }
    }

    public function remove_old_prices()
    {
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'ws-price-history-nonce')){
            DatabaseOperation::removeOldPrices();
            json_encode("done");
        } else {
            json_encode("error");
        }
    }
}
