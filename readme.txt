=== DomGats Customizable Product Filters ===
Contributors: Radovan Gataric DomGat
Tags: woocommerce, products, filter, ajax, elementor
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom AJAX-powered product filter for WooCommerce and Elementor Loop Grids.

== Description ==
 
This plugin provides a simple, yet powerful tag-based filtering system for your WooCommerce products. It is designed to work seamlessly with Elementor's Loop Grid widget, allowing for dynamic content loading without page reloads.

Features:
* AJAX-powered filtering (no page reloads)
* Supports multiple filter instances on the same page (e.g., one for main products, one for add-ons)
* Works with Elementor Pro's Loop Grid
* "Load More" functionality for mobile and carousel for desktop
* Easy to use via shortcodes: `[product_tag_filter]` and `[add_ons_tag_filter]`

== Installation ==

1.  Upload the `domgats-customizable-product-filters` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Place the shortcode `[product_tag_filter]` or `[add_ons_tag_filter]` above your Elementor Loop Grid widget.
4.  Ensure your Loop Grid has the CSS class `ahh-maa-product-grid` and a data attribute `data-filter-type="products"` or `data-filter-type="addons"` to match the shortcode.

== Changelog ==

= 1.0.0 =
* Initial release. Migrated from theme to a standalone plugin.

