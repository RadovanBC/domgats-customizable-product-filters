<?php
/**
 * Plugin Name:       DomGats Customizable Product Filters
 * Plugin URI:        https://example.com/
 * Description:       A custom product filter for WooCommerce and more to come.
 * Version:           1.3.13
 * Author:            Radovan Gataric DomGat
 * Author URI:        https://radovangataric.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-product-filters
 * Domain Path:       /languages
 *
 * @package           DomGats_Customizable_Product_Filters
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define Constants
 */
define( 'DGCPF_VERSION', '1.3.13' );
define( 'DGCPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGCPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the Composer autoloader
if ( file_exists( DGCPF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once DGCPF_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p><strong>DomGats Product Filters:</strong> Composer autoloader not found. Please run `composer install` in the plugin directory.</p></div>';
	});
	return;
}


/**
 * The function that runs during plugin activation.
 */
function dgcpf_activate_plugin() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dgcpf_activate_plugin' );


/**
 * Enqueues scripts and styles for the front end.
 */
function dgcpf_enqueue_assets() {
    // Register frontend script
    wp_register_script(
        'dgcpf-filtered-loop-widget-js',
        DGCPF_PLUGIN_URL . 'assets/js/filtered-loop-widget.js',
        [ 'jquery', 'elementor-frontend' ],
        DGCPF_VERSION,
        true
    );

    // Register frontend style
    wp_register_style(
        'dgcpf-filtered-loop-widget-css',
        DGCPF_PLUGIN_URL . 'assets/css/filtered-loop-widget.css',
        [],
        DGCPF_VERSION
    );

    // Register Flickity JS (will be enqueued by widget if needed)
    wp_register_script(
        'flickity-js',
        'https://unpkg.com/flickity@2/dist/flickity.pkgd.min.js',
        [],
        '2.3.0',
        true
    );

    // Register Flickity CSS (will be enqueued by widget if needed)
    wp_register_style(
        'flickity-css',
        'https://unpkg.com/flickity@2/dist/flickity.min.css',
        [],
        '2.3.0'
    );
}
add_action( 'wp_enqueue_scripts', 'dgcpf_enqueue_assets', 30 );

/**
 * Initializes the plugin's classes.
 */
function dgcpf_initialize_plugin() {
	new \DomGats\ProductFilter\DGCPF_Ajax();

	if ( is_admin() ) {
		new \DomGats\ProductFilter\DGCPF_Admin();
	}
}
add_action( 'plugins_loaded', 'dgcpf_initialize_plugin' );

/**
 * Elementor Widget Integration
 */
add_action( 'init', 'dgcpf_init_elementor_widgets' );
function dgcpf_init_elementor_widgets() {
    if ( defined( 'ELEMENTOR_PATH' ) && file_exists( DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php' ) ) {
        require_once DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php';
    }
}
