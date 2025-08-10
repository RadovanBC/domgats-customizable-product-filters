<?php
/**
 * Plugin Name:       DomGats Customizable Product Filters
 * Plugin URI:        https://example.com/
 * Description:       A custom product filter for WooCommerce and more to come.
 * Version:           1.3.24
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

// Define plugin constants
define( 'DGCPF_VERSION', '1.3.24' );
define( 'DGCPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGCPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the Composer autoloader
if ( file_exists( DGCPF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once DGCPF_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'DomGats Product Filters: Composer autoloader not found. Please run `composer install` in the plugin directory.', 'custom-product-filters' ) . '</p></div>';
	});
	return;
}

/**
 * Flush rewrite rules on plugin activation.
 */
function dgcpf_activate_plugin() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dgcpf_activate_plugin' );

/**
 * Register plugin assets.
 */
function dgcpf_register_assets() {
    wp_register_script(
        'dgcpf-frontend-js',
        DGCPF_PLUGIN_URL . 'assets/js/frontend.js',
        [ 'jquery', 'elementor-frontend', 'imagesloaded' ],
        DGCPF_VERSION,
        true
    );

    wp_localize_script(
        'dgcpf-frontend-js',
        'dgcpf_params',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'product_filter_nonce' ),
        ]
    );

    wp_register_style(
        'dgcpf-frontend-css',
        DGCPF_PLUGIN_URL . 'assets/css/frontend.css',
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

    wp_register_script(
        'imagesloaded',
        'https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/5.0.0/imagesloaded.pkgd.min.js',
        [ 'jquery' ],
        '5.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'dgcpf_register_assets' );
add_action( 'elementor/frontend/after_register_scripts', 'dgcpf_register_assets' );

/**
 * Enqueue assets for the Elementor editor.
 */
function dgcpf_enqueue_editor_assets() {
    wp_register_script(
        'dgcpf-editor-js',
        DGCPF_PLUGIN_URL . 'assets/js/editor.js',
        [ 'elementor-editor' ],
        DGCPF_VERSION,
        true
    );

    if ( class_exists('\DomGats\ProductFilter\Widgets\Widget') ) {
        $widget = new \DomGats\ProductFilter\Widgets\Widget();
        $presets = $widget->_get_layout_presets();

        wp_localize_script(
            'dgcpf-editor-js',
            'DgcpfEditorData',
            [
                'presets' => $presets
            ]
        );
    }

    wp_enqueue_script( 'dgcpf-editor-js' );
    wp_enqueue_script( 'dgcpf-frontend-js' );
}
add_action( 'elementor/editor/after_enqueue_scripts', 'dgcpf_enqueue_editor_assets' );

/**
 * Register Elementor widget category.
 */
function dgcpf_register_widget_categories( $elements_manager ) {
    $elements_manager->add_category(
        'domgats-widgets',
        [
            'title' => esc_html__( 'DomGats Widgets', 'custom-product-filters' ),
            'icon'  => 'fa fa-filter',
        ]
    );
}
add_action( 'elementor/elements/categories_registered', 'dgcpf_register_widget_categories' );

/**
 * Register Elementor widgets.
 */
function dgcpf_register_elementor_widgets( $widgets_manager ) {
    require_once DGCPF_PLUGIN_DIR . 'includes/widgets/class-widget.php';
    $widgets_manager->register( new \DomGats\ProductFilter\Widgets\Widget() );
}
add_action( 'elementor/widgets/register', 'dgcpf_register_elementor_widgets' );


/**
 * Check for required plugins.
 */
function dgcpf_check_required_plugins() {
    if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) && ! is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'DomGats Product Filters: The Advanced Custom Fields (ACF) plugin is not active. ACF-related features will be unavailable.', 'custom-product-filters' ) . '</p></div>';
        });
    }
}
add_action( 'admin_init', 'dgcpf_check_required_plugins' );

/**
 * Initialize the plugin.
 */
function dgcpf_initialize_plugin() {
	require_once DGCPF_PLUGIN_DIR . 'includes/class-ajax.php';
	new \DomGats\ProductFilter\Ajax();

	if ( is_admin() ) {
		require_once DGCPF_PLUGIN_DIR . 'includes/class-admin.php';
		new \DomGats\ProductFilter\Admin();
	}
}
add_action( 'plugins_loaded', 'dgcpf_initialize_plugin' );