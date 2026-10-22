<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Database\DatabaseOperation;

class Actions
{
    const PERISHABLE_META = '_wsph_perishable';

    public function __construct()
    {
        // Display next to the price on the single product page (art. 4 ust. 2: "obok informacji
        // o obniżonej cenie"). Themes can move it with the wsph_display_hook / wsph_display_priority filters.
        add_action('init', [$this, 'registerDisplayHooks']);
        add_filter('woocommerce_available_variation', [$this, 'addLowestPriceToVariation'], 10, 3);
        add_shortcode('ws_price_history', [$this, 'shortcode']);

        // Record every price change made through WooCommerce CRUD: admin edit, quick/bulk edit,
        // REST API, imports, and scheduled sales started/ended by the WooCommerce cron.
        add_action('woocommerce_new_product', [$this, 'onProductSaved'], 20, 2);
        add_action('woocommerce_update_product', [$this, 'onProductSaved'], 20, 2);
        add_action('woocommerce_new_product_variation', [$this, 'onProductSaved'], 20, 2);
        add_action('woocommerce_update_product_variation', [$this, 'onProductSaved'], 20, 2);
        add_action('woocommerce_scheduled_sales', [$this, 'onScheduledSales'], 20);

        // Perishable goods flag (art. 4 ust. 4).
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderPerishableField']);
        add_action('woocommerce_admin_process_product_object', [$this, 'savePerishableField']);

        add_action('wp_ajax_index_all_prices', [$this, 'index_all_prices']);
        add_action('wp_ajax_remove_old_prices', [$this, 'remove_old_prices']);
    }

    public function registerDisplayHooks()
    {
        $hook = apply_filters('wsph_display_hook', 'woocommerce_single_product_summary');
        $priority = (int) apply_filters('wsph_display_priority', 11);
        if ($hook) {
            add_action($hook, [$this, 'showTheLowestPrice'], $priority, 0);
        }
    }

    /**
     * Current price state of a product or variation, or null when it has no own price
     * (variable or grouped parent, empty price).
     */
    public static function productState($product)
    {
        if (!is_a($product, 'WC_Product') || $product->is_type(['variable', 'grouped'])) {
            return null;
        }
        $regular = $product->get_regular_price('edit');
        $sale = $product->get_sale_price('edit');
        $onSale = $product->is_on_sale('edit');
        $price = $onSale ? $sale : $regular;
        if ($price === '' || $price === null) {
            $price = $product->get_price('edit');
        }
        if ($price === '' || $price === null) {
            return null;
        }
        $from = $product->get_date_on_sale_from('edit');
        $to = $product->get_date_on_sale_to('edit');
        return [
            'price' => (float) $price,
            'regular' => $regular === '' ? null : (float) $regular,
            'sale' => $sale,
            'on_sale' => (bool) $onSale,
            'sale_from' => $from ? $from->getTimestamp() : null,
            'sale_to' => $to ? $to->getTimestamp() : null,
        ];
    }

    /**
     * Stores the current price state when it differs from the last stored one.
     */
    public static function recordProduct($product)
    {
        $state = self::productState($product);
        if ($state === null) {
            return false;
        }
        $id = $product->get_id();
        $last = DatabaseOperation::getLastRow($id);
        $row = LowestPriceCalculator::makeRow(0, $state['price'], $state['regular'], $state['on_sale']);
        if (!LowestPriceCalculator::shouldRecord($last, $row)) {
            return false;
        }
        $now = time();
        $time = $now;
        // The WooCommerce cron applies scheduled sales late (once a day); in that context the
        // sale actually started/ended at the scheduled date.
        if (doing_action('woocommerce_scheduled_sales') || wp_doing_cron()) {
            $time = LowestPriceCalculator::effectiveTime($now, $last ? $last['time'] : null, $state['on_sale'], $state['sale_from'], $state['sale_to']);
        }
        DatabaseOperation::insertSnapshot($id, $state['price'], $state['regular'], $state['sale'], $state['on_sale'], $time);
        return true;
    }

    public function onProductSaved($productId, $product = null)
    {
        if (!is_a($product, 'WC_Product')) {
            $product = wc_get_product($productId);
        }
        if ($product) {
            self::recordProduct($product);
        }
    }

    /**
     * Safety net after the WooCommerce scheduled-sales cron: snapshot products whose sale
     * started or ended within the last two days (WooCommerce saves them itself, which already
     * triggers onProductSaved; this catches sales applied without a save).
     */
    public function onScheduledSales()
    {
        $now = time();
        $ids = get_posts([
            'post_type' => ['product', 'product_variation'],
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_sale_price_dates_from', 'value' => [$now - 2 * DAY_IN_SECONDS, $now], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'],
                ['key' => '_sale_price_dates_to', 'value' => [$now - 2 * DAY_IN_SECONDS, $now], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'],
            ],
        ]);
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                self::recordProduct($product);
            }
        }
    }

    public static function isPerishable($product)
    {
        $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $perishable = get_post_meta($id, self::PERISHABLE_META, true) === 'yes';
        return (bool) apply_filters('wsph_is_perishable', $perishable, $product);
    }

    /**
     * @return array|null ['price', 'reference', 'source'] see LowestPriceCalculator::lowestPrice()
     */
    public static function getLowestPrice($product)
    {
        $state = self::productState($product);
        if ($state === null || !$state['on_sale']) {
            return null;
        }
        $options = apply_filters('wsph_calculation_options', [
            'days' => 30,
            'perishable' => self::isPerishable($product),
            'first_reduction' => false,
        ], $product);
        $result = LowestPriceCalculator::lowestPrice(DatabaseOperation::getRows($product->get_id()), $state, time(), $options);
        if (
            $result !== null && $result['source'] === LowestPriceCalculator::SOURCE_FALLBACK_REGULAR
            && !apply_filters('wsph_show_regular_price_fallback', true, $product)
        ) {
            $result = null;
        }
        return apply_filters('wsph_lowest_price', $result, $product);
    }

    public static function getLowestPriceHtml($product)
    {
        $result = self::getLowestPrice($product);
        if ($result === null) {
            return '';
        }
        $price = wc_price(wc_get_price_to_display($product, ['price' => $result['price']]));
        if ($result['source'] === LowestPriceCalculator::SOURCE_PERISHABLE) {
            /* translators: %s: price */
            $text = sprintf(__('Price before the first reduction: %s', 'ws-price-history'), $price);
        } else {
            /* translators: %s: price */
            $text = sprintf(__('Lowest price in the 30 days before the reduction: %s', 'ws-price-history'), $price);
        }
        $html = "<p class='the-lowest-price'>" . $text . "</p>";
        return wp_kses_post(apply_filters('wsph_lowest_price_html', $html, $result, $product));
    }

    public function showTheLowestPrice()
    {
        global $product;
        if (!is_a($product, 'WC_Product') || $product->is_type('variable')) {
            return; // variations are shown through woocommerce_available_variation
        }
        echo self::getLowestPriceHtml($product); // escaped in getLowestPriceHtml()
    }

    public function addLowestPriceToVariation($data, $parent, $variation)
    {
        $html = self::getLowestPriceHtml($variation);
        if ($html !== '') {
            $data['price_html'] = (isset($data['price_html']) ? $data['price_html'] : '') . $html;
        }
        return $data;
    }

    public function shortcode($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'ws_price_history');
        $product = $atts['id'] ? wc_get_product((int) $atts['id']) : (isset($GLOBALS['product']) ? $GLOBALS['product'] : null);
        return is_a($product, 'WC_Product') ? self::getLowestPriceHtml($product) : '';
    }

    public function renderPerishableField()
    {
        woocommerce_wp_checkbox([
            'id' => self::PERISHABLE_META,
            'label' => __('Perishable goods', 'ws-price-history'),
            'description' => __('Short shelf life: show the price from before the first reduction instead of the lowest price from 30 days.', 'ws-price-history'),
        ]);
    }

    public function savePerishableField($product)
    {
        $product->update_meta_data(self::PERISHABLE_META, isset($_POST[self::PERISHABLE_META]) ? 'yes' : 'no');
    }

    private function verifyAjax()
    {
        if (
            !isset($_POST['nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ws-price-history-nonce')
            || !current_user_can('manage_woocommerce')
        ) {
            wp_send_json_error('forbidden', 403);
        }
    }

    public function index_all_prices()
    {
        $this->verifyAjax();
        $ids = get_posts([
            'post_type' => ['product', 'product_variation'],
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);
        $written = 0;
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product && self::recordProduct($product)) {
                $written++;
            }
        }
        wp_send_json_success(['written' => $written]);
    }

    public function remove_old_prices()
    {
        $this->verifyAjax();
        DatabaseOperation::removeOldPrices((int) apply_filters('wsph_history_retention_days', 365));
        wp_send_json_success('done');
    }
}
