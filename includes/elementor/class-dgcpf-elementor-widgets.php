<?php
namespace DomGats\ProductFilter\Elementor;

use \Elementor\Widgets_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DGCPF_Elementor_Widgets {

    public function __construct() {
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        
        // Hooks to enqueue scripts and styles for Elementor editor preview.
        add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_preview_scripts' ] );
        add_action( 'elementor/preview/enqueue_styles', [ $this, 'enqueue_preview_styles' ] );
    }

    public function enqueue_preview_scripts() {
        // Enqueue scripts needed for the widget's functionality in the editor.
        wp_enqueue_script('flickity-js');
        wp_enqueue_script('dgcpf-filtered-loop-widget-js');
    }

    public function enqueue_preview_styles() {
        // Enqueue styles needed for the widget's appearance in the editor.
        wp_enqueue_style('flickity-css');
        wp_enqueue_style('dgcpf-filtered-loop-widget-css');
    }

    public function register_widget_categories( $elements_manager ) {
        $elements_manager->add_category(
            'domgats-widgets',
            [
                'title' => esc_html__( 'DomGats Widgets', 'custom-product-filters' ),
                'icon'  => 'fa fa-filter',
            ]
        );
    }

    public function register_widgets( Widgets_Manager $widgets_manager ) {
        // Include and register the new widget.
        require_once DGCPF_PLUGIN_DIR . 'includes/elementor/widgets/dgcpf-filtered-loop-widget/class-filtered-loop-widget.php';
        $widgets_manager->register( new Widgets\Filtered_Loop_Widget() );
    }
}

new DGCPF_Elementor_Widgets();
