<?php
/**
 * Plugin Name:       DomGats Customizable Product Filters
 * Plugin URI:        https://example.com/
 * Description:       A custom product filter for WooCommerce and more to come.
 * Version:           1.3.21
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

define( 'DGCPF_VERSION', '1.3.21' );
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

function dgcpf_register_assets() {
    wp_register_script(
        'dgcpf-filtered-loop-widget-js',
        DGCPF_PLUGIN_URL . 'assets/js/filtered-loop-widget.js',
        [ 'jquery', 'elementor-frontend' ],
        DGCPF_VERSION,
        true
    );

    wp_localize_script(
        'dgcpf-filtered-loop-widget-js',
        'ahh_maa_filter_params',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'product_filter_nonce' ),
        ]
    );

    wp_register_style(
        'dgcpf-filtered-loop-widget-css',
        DGCPF_PLUGIN_URL . 'assets/css/filtered-loop-widget.css',
        [],
        DGCPF_VERSION
    );

    wp_register_script(
        'flickity-js',
        'https://unpkg.com/flickity@2/dist/flickity.pkgd.min.js',
        [ 'jquery' ],
        '2.3.0',
        true
    );

    wp_register_style(
        'flickity-css',
        'https://unpkg.com/flickity@2/dist/flickity.min.css',
        [],
        '2.3.0'
    );
}
add_action( 'wp_enqueue_scripts', 'dgcpf_register_assets' );
add_action( 'elementor/frontend/after_register_scripts', 'dgcpf_register_assets' );

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

function dgcpf_enqueue_preview_assets() {
    wp_enqueue_style('dgcpf-filtered-loop-widget-css');
    wp_enqueue_script('dgcpf-filtered-loop-widget-js');
}
add_action('elementor/preview/enqueue_styles', 'dgcpf_enqueue_preview_assets');
add_action('elementor/preview/enqueue_scripts', 'dgcpf_enqueue_preview_assets');


function dgcpf_check_required_plugins() {
    if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) && ! is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>DomGats Product Filters:</strong> The Advanced Custom Fields (ACF) plugin is not active. ACF-related features will be unavailable.</p></div>';
        });
    }
}
add_action( 'admin_init', 'dgcpf_check_required_plugins' );


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
