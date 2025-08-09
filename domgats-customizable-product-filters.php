<?php
/**
 * Plugin Name:       DomGats Customizable Product Filters
 * Plugin URI:        https://example.com/
 * Description:       A custom product filter for WooCommerce and more to come.
 * Version:           1.3.14
 * Author:            Radovan Gataric DomGat
 * Author URI:        https://radovangataric.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-product-filters
 * Domain Path:       /languages
 *
 * @package           DomGats_Customizable_Product_Filters
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'DGCPF_VERSION', '1.3.14' );
define( 'DGCPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGCPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( DGCPF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once DGCPF_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p><strong>DomGats Product Filters:</strong> Composer autoloader not found. Please run `composer install` in the plugin directory.</p></div>';
	});
	return;
}

function dgcpf_activate_plugin() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dgcpf_activate_plugin' );

function dgcpf_enqueue_assets() {
}
add_action( 'wp_enqueue_scripts', 'dgcpf_enqueue_assets', 30 );

function dgcpf_enqueue_editor_assets() {
    wp_register_script(
        'dgcpf-filtered-loop-widget-editor-js',
        DGCPF_PLUGIN_URL . 'assets/js/filtered-loop-widget-editor.js',
        [ 'elementor-editor' ],
        DGCPF_VERSION,
        true
    );

    if ( class_exists('\DomGats\ProductFilter\Elementor\Widgets\Filtered_Loop_Widget') ) {
        $widget = new \DomGats\ProductFilter\Elementor\Widgets\Filtered_Loop_Widget();
        $presets = $widget->_get_layout_presets();

        wp_localize_script(
            'dgcpf-filtered-loop-widget-editor-js',
            'DgcpfEditorData',
            [
                'presets' => $presets
            ]
        );
    }

    wp_enqueue_script( 'dgcpf-filtered-loop-widget-editor-js' );
}
add_action( 'elementor/editor/after_enqueue_scripts', 'dgcpf_enqueue_editor_assets' );

function dgcpf_initialize_plugin() {
	new \DomGats\ProductFilter\DGCPF_Ajax();

	if ( is_admin() ) {
		new \DomGats\ProductFilter\DGCPF_Admin();
	}
}
add_action( 'plugins_loaded', 'dgcpf_initialize_plugin' );

add_action( 'init', 'dgcpf_init_elementor_widgets' );
function dgcpf_init_elementor_widgets() {
    if ( defined( 'ELEMENTOR_PATH' ) && file_exists( DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php' ) ) {
        require_once DGCPF_PLUGIN_DIR . 'includes/elementor/class-dgcpf-elementor-widgets.php';
    }
}
