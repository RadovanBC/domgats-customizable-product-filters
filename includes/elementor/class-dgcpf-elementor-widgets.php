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
