=== WS Price History ===    
Contributors:      websystems2006  
Tags:              Prices, History  
Requires at least: 6.0  
Tested up to:      6.4  
Requires PHP:      7.1  
Stable tag:        1.1.0  
License:           GPLv3  
License URI:       https://www.gnu.org/licenses/gpl-3.0.html  

This plugin allow you to show the lowest price from 30 days on sale products.

== Description ==
  
Shows, next to a reduced price, the lowest price of the product from the 30 days before the reduction was introduced (EU Omnibus Directive, Polish Act on informing about prices of goods and services, art. 4 ust. 2-5).

* Regular and sale prices are stored separately with the time they started to apply.
* The lowest price is calculated from the 30 days before the start of the current reduction, the reduction itself excluded.
* Products offered for less than 30 days: the lowest price since the product was first offered.
* Perishable goods (checkbox on the product): the price from before the first reduction.
* Scheduled sales (WooCommerce sale dates) are recorded with their scheduled start and end date.
* Variable products: the price history is kept and shown per variation.

== Installation ==
  
1. Upload the plugin folder to your /wp-content/plugins/ folder.
2. Go to the Plugins page and activate the plugin.
3. Index prices on the settings page.
  
== Frequently Asked Questions ==
  
= How do I use this plugin? =  
To use this plugin, follow these steps:  
Set up the plugin on the settings page.
  
= How do I uninstall the plugin? =  

To uninstall the plugin: 
Deactivate the plugin. 
Delete the plugin. 
  
== Screenshots ==  

1. screenshot-1.png  
2. screenshot-2.png  
3. screenshot-3.png  
  
== Changelog ==

= 1.1.0 =
* Fix: the lowest price is now calculated from the 30 days before the reduction started, not from the last 30 days (the sale price itself is no longer shown as the "lowest price").
* Fix: regular and sale price are stored separately, with date and time.
* Fix: scheduled sales started or ended by the WooCommerce cron are recorded.
* Fix: variable products are supported (history and notice per variation).
* Fix: products offered for less than 30 days use the history since they were first offered.
* New: perishable goods option (price before the first reduction).
* New: [ws_price_history id="123"] shortcode; filters wsph_display_hook, wsph_display_priority, wsph_calculation_options, wsph_lowest_price, wsph_lowest_price_html, wsph_is_perishable, wsph_show_regular_price_fallback, wsph_history_retention_days.
* Fix: "Remove old prices" no longer deletes the price that still applied at the start of the 30-day window; it removes history older than one year and keeps the last known price of every product.
* Fix: the price table is migrated once per version instead of being checked on every request; existing data is kept.
* Fix: uninstall now drops the table.

= 1.0 =
* Plugin released. 

==  Upgrade Notice ==  

= 1.1.0 =  
* Corrects the Omnibus lowest price calculation. After updating, press "Index all prices" once on the settings page.

= 1.0 =  
* Plugin released. 