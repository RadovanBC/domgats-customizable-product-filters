<?php
/**
 * Plugin Name:       DomGats Customizable Product Filters
 * Plugin URI:        https://example.com/
 * Description:       A custom product filter for WooCommerce and more to come.
 * Version:           1.3.12
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
define( 'DGCPF_VERSION', '1.3.12' ); // Version updated to 1.3.12
define( 'DGCPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGCPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the Composer autoloader
if ( file_exists( DGCPF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once DGCPF_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Optional: Add a notice for the admin if composer is not installed.
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p><strong>DomGats Product Filters:</strong> Composer autoloader not found. Please run `composer install` in the plugin directory.</p></div>';
	});
	return; // Stop the plugin from loading if the autoloader is missing.
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
 * Removed global enqueues for Flickity and main.js/main.css as they are now handled by the Elementor widget.
 */
function dgcpf_enqueue_assets() {
	// The Elementor widget will handle enqueuing its specific CSS and JS,
	// including Flickity if the carousel layout is used.
	// No global enqueues needed here for the widget's main functionality.
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

// Elementor Widget Integration
/**
 * Check if Elementor is active and load and register the custom widgets.
 * Changed hook from 'plugins_loaded' to 'init' for more reliable class loading.
 */
add_action( 'init', 'dgcpf_init_elementor_widgets' ); // Changed hook to 'init'
function dgcpf_init_elementor_widgets() {
    if ( defined( 'ELEMENTOR_PATH' ) && file_exists( DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php' ) ) {
        require_once DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php';
    }
}

