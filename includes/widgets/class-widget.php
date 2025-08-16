<?php
namespace DomGats\ProductFilter\Widgets;

use \WP_Query;
use \Elementor\Controls_Manager;
use \Elementor\Group_Control_Border;
use \Elementor\Group_Control_Box_Shadow;
use \Elementor\Group_Control_Typography;
use \Elementor\Repeater;
use \Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Widget extends Widget_Base {

    public function get_name() {
        return 'dgcpf_filtered_loop';
    }

    public function get_title() {
        return esc_html__( 'DomGats Filtered Loop', 'custom-product-filters' );
    }

    public function get_icon() {
        return 'eicon-loop';
    }

    public function get_categories() {
        return [ 'domgats-widgets' ];
    }

    public function get_script_depends() {
        return [
            'dgcpf-frontend-js',
            'flickity-js',
            'imagesloaded',
        ];
    }

    public function get_style_depends() {
        return [
            'dgcpf-frontend-css',
            'flickity-css',
        ];
    }

    private function _is_acf_active() {
        return class_exists('ACF');
    }

    

    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    protected function register_content_controls() {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__( 'Layout', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'template_id',
            [
                'label'   => esc_html__( 'Choose a Template', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_loop_templates(),
                'default' => '',
                'frontend_available' => true,
            ]
        );

        $this->add_control(
            'layout_preset',
            [
                'label'   => esc_html__( 'Load Layout Preset', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_layout_presets_options(),
                'default' => 'custom',
                'frontend_available' => true,
            ]
        );

        $this->add_responsive_control(
            'layout_type',
            [
                'label' => esc_html__( 'Render As', 'custom-product-filters' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'grid' => [ 'title' => esc_html__( 'Grid', 'custom-product-filters' ), 'icon' => 'eicon-thumbnails-grid' ],
                    'carousel' => [ 'title' => esc_html__( 'Carousel', 'custom-product-filters' ), 'icon' => 'eicon-post-slider' ],
                ],
                'default' => 'grid',
                'toggle' => false,
                'frontend_available' => true,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__( 'Columns (Grid)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'default' => 3,
                'condition' => [ 'layout_type' => 'grid' ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);' ],
                'frontend_available' => true,
            ]
        );

        $this->add_responsive_control(
            'columns_carousel',
            [
                'label' => esc_html__( 'Columns (Carousel)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'default' => 3,
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-carousel .elementor-loop-item' => 'width: calc(100% / {{VALUE}});'
                ],
            ]
        );

        $this->add_responsive_control(
            'horizontal_gap',
            [
                'label' => esc_html__( 'Horizontal Gap', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
                'default' => [ 'unit' => 'px', 'size' => 20 ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-carousel .elementor-loop-item' => 'padding-left: calc({{SIZE}}{{UNIT}} / 2); padding-right: calc({{SIZE}}{{UNIT}} / 2);',
                ],
                'frontend_available' => true,
            ]
        );

        $this->add_responsive_control(
            'vertical_gap',
            [
                'label' => esc_html__( 'Vertical Gap', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
                'default' => [ 'unit' => 'px', 'size' => 20 ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'row-gap: {{SIZE}}{{UNIT}};' ],
                'condition' => [ 'layout_type' => 'grid' ],
                'frontend_available' => true,
            ]
        );

        $this->add_responsive_control(
            'equal_height_columns',
            [
                'label' => esc_html__( 'Equal Height Columns', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'stretch',
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'align-items: {{VALUE}};',
                ],
                'condition' => [ 'template_id!' => '' ],
                'frontend_available' => true,
            ]
        );

        $this->add_control(
            'posts_per_page_initial',
            [
                'label' => esc_html__( 'Initial Items Per Page', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1, 'default' => 9,
                'separator' => 'before',
                'frontend_available' => true,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__( 'Query', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            '_is_acf_active',
            [
                'label' => esc_html__( 'Is ACF Active?', 'custom-product-filters' ),
                'type' => Controls_Manager::HIDDEN,
                'default' => $this->_is_acf_active() ? '1' : '0',
                'frontend_available' => true,
            ]
        );

        $this->add_control(
            'post_type',
            [
                'label'   => esc_html__( 'Post Type', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_post_types(),
                'default' => 'product',
                'frontend_available' => true,
            ]
        );

        if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
            $this->add_control(
                'posts_include_by_ids',
                [
                    'label' => esc_html__( 'Include Posts by ID', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_POST,
                        'query'  => [
                            'post_type' => 'any'
                        ],
                    ],
                    'frontend_available' => true,
                ]
            );
            $this->add_control(
                'posts_exclude_by_ids',
                [
                    'label' => esc_html__( 'Exclude Posts by ID', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_POST,
                        'query'  => [
                            'post_type' => 'any'
                        ],
                    ],
                    'frontend_available' => true,
                ]
            );
            $this->add_control(
                'terms_include',
                [
                    'label' => esc_html__( 'Include Terms', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_TAX,
                        'query' => [
                            'post_type' => '{{post_type}}',
                        ],
                    ],
                    'frontend_available' => true,
                ]
            );
            $this->add_control(
                'terms_exclude',
                [
                    'label' => esc_html__( 'Exclude Terms', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_TAX,
                        'query' => [
                            'post_type' => '{{post_type}}',
                        ],
                    ],
                    'frontend_available' => true,
                ]
            );
            $this->add_control(
                'product_categories_query',
                [
                    'label' => esc_html__( 'Product Categories', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_TAX,
                        'query'  => [
                            'taxonomy' => 'product_cat'
                        ],
                    ],
                    'condition' => [
                        'post_type' => 'product'
                    ],
                    'frontend_available' => true,
                ]
            );
            $this->add_control(
                'product_tags_query',
                [
                    'label' => esc_html__( 'Product Tags', 'custom-product-filters' ),
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'label_block' => true,
                    'multiple' => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_TAX,
                        'query'  => [
                            'taxonomy' => 'product_tag'
                        ],
                    ],
                    'condition' => [
                        'post_type' => 'product'
                    ],
                    'frontend_available' => true,
                ]
            );
        } else {
            $this->add_control(
                'elementor_pro_query_control_notice',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => esc_html__( 'Elementor Pro\'s Query Control is not active or fully loaded. Advanced query options are unavailable.', 'custom-product-filters' ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ]
            );
        }

        $acf_repeater = new Repeater();
        $acf_repeater->add_control(
            'acf_field_label',
            [
                'type' => Controls_Manager::HIDDEN,
                'default' => ''
            ]
        );
        $acf_repeater->add_control(
            'acf_meta_key',
            [
                'label'   => esc_html__( 'ACF Field', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_acf_choice_field_keys(),
                'frontend_available' => true,
            ]
        );
        $acf_repeater->add_control(
            'acf_meta_value',
            [
                'label' => esc_html__( 'Field Value', 'custom-product-filters' ),
                'type' => Controls_Manager::SELECT,
                'options' => [], // Will be dynamically populated
                'condition' => [ 'acf_meta_key!' => '' ],
                'frontend_available' => true,
                'render_type' => 'ui', // Important for dynamic updates in editor
                'ajax' => [
                    'event' => 'select2:select',
                    'selector' => 'select[data-setting="acf_meta_key"]',
                    'action' => 'dgcpf_get_acf_field_choices',
                    'data' => [
                        'field_key' => 'acf_meta_key',
                        'post_type' => 'post_type',
                    ],
                ],
            ]
        );
        $acf_repeater->add_control(
            'acf_meta_compare',
            [
                'label'   => esc_html__( 'Comparison', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    '=' => esc_html__( 'Equal to', 'custom-product-filters' ), '!=' => esc_html__( 'Not Equal to', 'custom-product-filters' ),
                    '>' => esc_html__( 'Greater than', 'custom-product-filters' ), '>=' => esc_html__( 'Greater than or Equal to', 'custom-product-filters' ),
                    '<' => esc_html__( 'Less than', 'custom-product-filters' ), '<=' => esc_html__( 'Less than or Equal to', 'custom-product-filters' ),
                    'LIKE' => esc_html__( 'Contains', 'custom-product-filters' ), 'NOT LIKE' => esc_html__( 'Does Not Contain', 'custom-product-filters' ),
                    'IN' => esc_html__( 'In Array', 'custom-product-filters' ), 'NOT IN' => esc_html__( 'Not In Array', 'custom-product-filters' ),
                    'BETWEEN' => esc_html__( 'Between', 'custom-product-filters' ), 'NOT BETWEEN' => esc_html__( 'Not Between', 'custom-product-filters' ),
                    'EXISTS' => esc_html__( 'Exists', 'custom-product-filters' ), 'NOT EXISTS' => esc_html__( 'Does Not Exist', 'custom-product-filters' ),
                ],
                'default' => '=',
                'condition' => [ 'acf_meta_key!' => '' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'acf_meta_query_repeater',
            [
                'label'   => esc_html__( 'ACF Meta Queries', 'custom-product-filters' ),
                'type'    => Controls_Manager::REPEATER,
                'fields'  => $acf_repeater->get_controls(),
                'title_field' => '.',
                'separator' => 'before',
                'condition' => [ '_is_acf_active' => '1' ],
                'frontend_available' => true,
            ]
        );

        $this->add_control(
            'post_status',
            [
                'label'   => esc_html__( 'Post Status', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT2, 'multiple' => true,
                'options' => [
                    'publish' => esc_html__( 'Publish', 'custom-product-filters' ), 'pending' => esc_html__( 'Pending', 'custom-product-filters' ),
                    'draft'   => esc_html__( 'Draft', 'custom-product-filters' ), 'future'  => esc_html__( 'Future', 'custom-product-filters' ),
                    'private' => esc_html__( 'Private', 'custom-product-filters' ), 'any'     => esc_html__( 'Any', 'custom-product-filters' ),
                ],
                'default' => 'publish',
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'orderby',
            [
                'label'   => esc_html__( 'Order By', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'date' => esc_html__( 'Date', 'custom-product-filters' ), 'ID' => esc_html__( 'Post ID', 'custom-product-filters' ),
                    'author' => esc_html__( 'Author', 'custom-product-filters' ), 'title' => esc_html__( 'Title', 'custom-product-filters' ),
                    'name' => esc_html__( 'Post Name (Slug)', 'custom-product-filters' ), 'modified' => esc_html__( 'Last Modified Date', 'custom-product-filters' ),
                    'parent' => esc_html__( 'Parent ID', 'custom-product-filters' ), 'rand' => esc_html__( 'Random', 'custom-product-filters' ),
                    'comment_count' => esc_html__( 'Comment Count', 'custom-product-filters' ), 'menu_order' => esc_html__( 'Menu Order', 'custom-product-filters' ),
                ],
                'default' => 'date',
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'order',
            [
                'label'   => esc_html__( 'Order', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [ 'DESC' => esc_html__( 'Descending', 'custom-product-filters' ), 'ASC'  => esc_html__( 'Ascending', 'custom-product-filters' ) ],
                'default' => 'DESC',
                'frontend_available' => true,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_filters',
            [
                'label' => esc_html__( 'Filters', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();
        $repeater->add_control(
            'filter_type',
            [
                'label'   => esc_html__( 'Filter By', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [ 'taxonomy' => esc_html__( 'Taxonomy', 'custom-product-filters' ), 'acf' => esc_html__( 'Custom Field (ACF)', 'custom-product-filters' ) ],
                'default' => 'taxonomy',
                'frontend_available' => true,
            ]
        );
        $repeater->add_control(
            'taxonomy_name',
            [
                'label'   => esc_html__( 'Taxonomy', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_taxonomies(),
                'condition' => [ 'filter_type' => 'taxonomy' ],
                'frontend_available' => true,
            ]
        );
        $repeater->add_control(
            'acf_field_key',
            [
                'label'     => esc_html__( 'ACF Field', 'custom-product-filters' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => $this->_get_all_acf_field_keys(),
                'condition' => [ 'filter_type' => 'acf' ],
                'frontend_available' => true,
            ]
        );
        $repeater->add_control(
            'display_as',
            [
                'label'   => esc_html__( 'Display As', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'dropdown' => esc_html__( 'Dropdown', 'custom-product-filters' ), 'checkbox' => esc_html__( 'Checkboxes', 'custom-product-filters' ),
                    'radio'    => esc_html__( 'Radio Buttons', 'custom-product-filters' ),
                ],
                'default' => 'dropdown',
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'filters_repeater',
            [
                'label'   => esc_html__( 'Filters', 'custom-product-filters' ),
                'type'    => Controls_Manager::REPEATER,
                'fields'  => $repeater->get_controls(),
                'title_field' => '{{{ filter_type }}} - {{{ taxonomy_name || acf_field_key }}}',
                'default' => [ [ 'filter_type' => 'taxonomy', 'taxonomy_name' => 'product_tag', 'display_as' => 'dropdown' ] ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'filter_logic',
            [
                'label'   => esc_html__( 'Filter Logic', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [ 'AND' => esc_html__( 'AND', 'custom-product-filters' ), 'OR'  => esc_html__( 'OR', 'custom-product-filters' ) ],
                'default' => 'AND',
                'frontend_available' => true,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_pagination_carousel',
            [
                'label' => esc_html__( 'Pagination & Carousel', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'enable_load_more',
            [
                'label' => esc_html__( 'Enable Load More', 'custom-product-filters' ),
                'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
                'condition' => [ 'layout_type' => 'grid' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__( 'Posts Per Page (Load More)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1, 'default' => 9,
                'condition' => [ 'enable_load_more' => 'yes', 'layout_type' => 'grid' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'load_more_button_text',
            [
                'label' => esc_html__( 'Load More Button Text', 'custom-product-filters' ),
                'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Load More', 'custom-product-filters' ),
                'condition' => [ 'enable_load_more' => 'yes', 'layout_type' => 'grid' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'no_more_products_text',
            [
                'label' => esc_html__( 'No More Products Text', 'custom-product-filters' ),
                'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'No More Products', 'custom-product-filters' ),
                'condition' => [ 'enable_load_more' => 'yes', 'layout_type' => 'grid' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'enable_history_api',
            [
                'label' => esc_html__( 'Enable History API (URL Update)', 'custom-product-filters' ),
                'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no',
                'frontend_available' => true,
            ]
        );
        
        $this->add_control(
            'carousel_options_heading',
            [
                'label' => esc_html__( 'Carousel Options', 'custom-product-filters' ),
                'type' => Controls_Manager::HEADING, 'separator' => 'before',
                'condition' => [ 'layout_type' => 'carousel' ],
            ]
        );
        $this->add_control(
            'carousel_autoplay',
            [
                'label' => esc_html__( 'Autoplay', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_autoplay_interval',
            [
                'label' => esc_html__( 'Autoplay Interval (ms)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1000, 'step' => 500, 'default' => 3000,
                'condition' => [ 'layout_type' => 'carousel', 'carousel_autoplay' => 'yes' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_nav_buttons',
            [
                'label' => esc_html__( 'Navigation Arrows', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_prev_arrow_icon',
            [
                'label' => esc_html__( 'Previous Arrow Icon', 'custom-product-filters' ),
                'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false,
                'condition' => [ 'layout_type' => 'carousel', 'carousel_nav_buttons' => 'yes' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_next_arrow_icon',
            [
                'label' => esc_html__( 'Next Arrow Icon', 'custom-product-filters' ),
                'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false,
                'condition' => [ 'layout_type' => 'carousel', 'carousel_nav_buttons' => 'yes' ],
                'frontend_available' => true,
            ]
        );
        $this->add_responsive_control(
            'carousel_slides_to_move',
            [
                'label' => esc_html__( 'Slides to Move', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER, 'min' => 1, 'default' => 1,
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_page_dots',
            [
                'label' => esc_html__( 'Pagination Dots', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_wrap_around',
            [
                'label' => esc_html__( 'Wrap Around', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_draggable',
            [
                'label' => esc_html__( 'Draggable', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_adaptive_height',
            [
                'label' => esc_html__( 'Adaptive Height', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );
        $this->add_control(
            'carousel_cell_align',
            [
                'label' => esc_html__( 'Cell Align', 'custom-product-filters' ),
                'type' => Controls_Manager::SELECT,
                'options' => [ 'left' => esc_html__( 'Left', 'custom-product-filters' ), 'center' => esc_html__( 'Center', 'custom-product-filters' ), 'right'  => esc_html__( 'Right', 'custom-product-filters' ) ],
                'default' => 'left',
                'condition' => [ 'layout_type' => 'carousel' ],
                'frontend_available' => true,
            ]
        );

        $this->end_controls_section();
    }
    
    protected function register_style_controls() {
        $this->start_controls_section(
            'section_filter_bar_style',
            [
                'label' => esc_html__( 'Filter Bar', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'filter_bar_spacing',
            [
                'label' => esc_html__( 'Spacing', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-filters-wrapper' => 'gap: {{SIZE}}{{UNIT}};' ],
            ]
        );
        $this->add_control(
            'filter_label_heading',
            [
                'label' => esc_html__( 'Filter Label', 'custom-product-filters' ),
                'type' => Controls_Manager::HEADING, 'separator' => 'before',
            ]
        );
        $this->add_control(
            'filter_label_color',
            [
                'label' => esc_html__( 'Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-label' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'filter_label_typography',
                'selector' => '{{WRAPPER}} .dgcpf-filter-label',
            ]
        );
        $this->add_control(
            'filter_dropdown_heading',
            [
                'label' => esc_html__( 'Dropdown/Input Fields', 'custom-product-filters' ),
                'type' => Controls_Manager::HEADING, 'separator' => 'before',
            ]
        );
        $this->add_control(
            'filter_input_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'filter_input_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'background-color: {{VALUE}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'filter_input_border',
                'selector' => '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input',
            ]
        );
        $this->add_control(
            'filter_input_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'filter_input_box_shadow',
                'selector' => '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input',
            ]
        );
        $this->add_control(
            'filter_input_padding',
            [
                'label' => esc_html__( 'Padding', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_control(
            'filter_input_disabled_color',
            [
                'label' => esc_html__( 'Disabled Item Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-dropdown option:disabled, {{WRAPPER}} .dgcpf-filter-checkboxes label.disabled, {{WRAPPER}} .dgcpf-filter-radio-buttons label.disabled' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'filter_input_disabled_background_color',
            [
                'label' => esc_html__( 'Disabled Item Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-checkboxes label.disabled, {{WRAPPER}} .dgcpf-filter-radio-buttons label.disabled' => 'background-color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'filter_input_active_text_color',
            [
                'label' => esc_html__( 'Active Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-filter-checkbox:checked + span, {{WRAPPER}} .dgcpf-filter-radio:checked + span' => 'color: {{VALUE}};' ],
                'condition' => [ 'display_as' => ['checkbox', 'radio'] ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_grid_carousel_style',
            [
                'label' => esc_html__( 'Grid/Carousel', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'grid_carousel_min_height',
            [
                'label' => esc_html__( 'Minimum Height', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [ 'px' => [ 'min' => 100, 'max' => 1000 ] ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-loop-container' => 'min-height: {{SIZE}}{{UNIT}};' ],
            ]
        );
        $this->add_control(
            'loading_spinner_color',
            [
                'label' => esc_html__( 'Loading Spinner Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-loop-container.loading::after' => 'border-top-color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'loading_overlay_color',
            [
                'label' => esc_html__( 'Loading Overlay Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-loop-container.loading::before' => 'background-color: {{VALUE}};' ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_load_more_style',
            [
                'label' => esc_html__( 'Load More Button', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
                'condition' => [ 'enable_load_more' => 'yes', 'layout_type' => 'grid' ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'load_more_typography',
                'selector' => '{{WRAPPER}} .dgcpf-load-more-button',
            ]
        );
        $this->start_controls_tabs( 'tabs_load_more_button_style' );
        $this->start_controls_tab( 'tab_load_more_button_normal', [ 'label' => esc_html__( 'Normal', 'custom-product-filters' ) ] );
        $this->add_control(
            'load_more_button_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'load_more_button_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button' => 'background-color: {{VALUE}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'load_more_button_border',
                'selector' => '{{WRAPPER}} .dgcpf-load-more-button',
            ]
        );
        $this->add_control(
            'load_more_button_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'load_more_button_box_shadow',
                'selector' => '{{WRAPPER}} .dgcpf-load-more-button',
            ]
        );
        $this->add_control(
            'load_more_button_padding',
            [
                'label' => esc_html__( 'Padding', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab( 'tab_load_more_button_hover', [ 'label' => esc_html__( 'Hover', 'custom-product-filters' ) ] );
        $this->add_control(
            'load_more_button_hover_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'load_more_button_hover_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'background-color: {{VALUE}};' ],
            ]
        );
        $this->add_control(
            'load_more_button_hover_border_color',
            [
                'label' => esc_html__( 'Border Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'border-color: {{VALUE}};' ],
            ]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section(
            'section_clear_all_style',
            [
                'label' => esc_html__( 'Clear All Button', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'clear_all_typography',
                'selector' => '{{WRAPPER}} .dgcpf-clear-all-filters-button',
            ]
        );

        $this->start_controls_tabs( 'tabs_clear_all_button_style' );

        $this->start_controls_tab(
            'tab_clear_all_button_normal',
            [
                'label' => esc_html__( 'Normal', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'clear_all_button_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_button_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'clear_all_button_border',
                'selector' => '{{WRAPPER}} .dgcpf-clear-all-filters-button',
            ]
        );

        $this->add_control(
            'clear_all_button_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'clear_all_button_box_shadow',
                'selector' => '{{WRAPPER}} .dgcpf-clear-all-filters-button',
            ]
        );

        $this->add_control(
            'clear_all_button_padding',
            [
                'label' => esc_html__( 'Padding', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_clear_all_button_hover',
            [
                'label' => esc_html__( 'Hover', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'clear_all_button_hover_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_button_hover_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_button_hover_border_color',
            [
                'label' => esc_html__( 'Border Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-clear-all-filters-button:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        $this->start_controls_section(
            'section_no_products_style',
            [
                'label' => esc_html__( '"No Products Found" Message', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'no_products_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .no-products-found' => 'color: {{VALUE}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'no_products_typography',
                'selector' => '{{WRAPPER}} .no-products-found',
            ]
        );
        $this->add_control(
            'no_products_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .no-products-found' => 'background-color: {{VALUE}};' ],
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'no_products_border',
                'selector' => '{{WRAPPER}} .no-products-found',
            ]
        );
        $this->add_control(
            'no_products_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ],
                'selectors' => [ '{{WRAPPER}} .no-products-found' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ]
        );
        $this->add_control(
            'no_products_padding',
            [
                'label' => esc_html__( 'Padding', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [ '{{WRAPPER}} .no-products-found' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ]
        );

        $this->end_controls_section();
    }

    private function _get_loop_templates() {
        $templates = get_posts( [
            'post_type' => 'elementor_library', 'posts_per_page' => -1,
            'meta_key' => '_elementor_template_type', 'meta_value' => 'loop-item',
            'fields' => 'ids',
        ] );
        $options = [ '' => esc_html__( 'Select a template', 'custom-product-filters' ) ];
        if ( $templates ) {
            foreach ( $templates as $template_id ) {
                $options[ $template_id ] = get_the_title( $template_id );
            }
        }
        return $options;
    }

    private function _get_all_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $options = [ '' => esc_html__( 'Select Post Type', 'custom-product-filters' ) ];
        foreach ( $post_types as $post_type ) {
            $options[ $post_type->name ] = $post_type->labels->singular_name;
        }
        return $options;
    }

    private function _get_all_taxonomies() {
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        $options = [ '' => esc_html__( 'Select a taxonomy', 'custom-product-filters' ) ];
        if ( $taxonomies ) {
            foreach ( $taxonomies as $taxonomy ) {
                $label = $taxonomy->labels->singular_name;
                $object_types = $taxonomy->object_type;
                if ( ! empty( $object_types ) ) {
                    $post_type_labels = [];
                    foreach ( $object_types as $post_type_slug ) {
                        $post_type_obj = get_post_type_object( $post_type_slug );
                        if ( $post_type_obj ) {
                            $post_type_labels[] = $post_type_obj->labels->singular_name;
                        }
                    }
                    if ( ! empty( $post_type_labels ) ) {
                        $label .= ' (' . implode(', ', $post_type_labels) . ')';
                    }
                }
                $options[ $taxonomy->name ] = $label;
            }
        }
        return $options;
    }

    private function _get_all_acf_choice_field_keys() {
        static $options = null;

        if ( $options !== null ) {
            return $options;
        }

        if ( !$this->_is_acf_active() ) {
            $options = ['' => esc_html__('ACF plugin not active', 'custom-product-filters')];
            return $options;
        }
        $options = [ '' => esc_html__( 'Select an ACF field', 'custom-product-filters' ) ];
        $field_groups = acf_get_field_groups();
        foreach ( $field_groups as $group ) {
            $fields = acf_get_fields( $group['key'] );
            foreach ( $fields as $field ) {
                if ( in_array( $field['type'], ['select', 'checkbox', 'radio', 'true_false'] ) ) {
                    $options[ $field['key'] ] = $field['label'];
                }
            }
        }
        return $options;
    }

    private function _get_all_acf_field_keys() {
        static $options = null;

        if ( $options !== null ) {
            return $options;
        }

        if ( !$this->_is_acf_active() ) {
            $options = ['' => esc_html__('ACF plugin not active', 'custom-product-filters')];
            return $options;
        }
        $options = [ '' => esc_html__( 'Select an ACF field', 'custom-product-filters' ) ];
        $field_groups = acf_get_field_groups();
        foreach ( $field_groups as $group ) {
            $fields = acf_get_fields( $group['key'] );
            foreach ( $fields as $field ) {
                if ( in_array( $field['type'], ['text', 'number', 'select', 'checkbox', 'radio', 'true_false'] ) ) {
                    $options[ $field['key'] ] = $field['label'];
                }
            }
        }
        return $options;
    }

    public function _get_layout_presets() {
        return [
            'default_grid' => [
                'label' => esc_html__( 'Default Grid (3 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid', 'columns' => 3, 'columns_tablet' => 2, 'columns_mobile' => 1,
                    'horizontal_gap' => ['size' => 20, 'unit' => 'px'], 'vertical_gap' => ['size' => 20, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'enable_load_more' => 'yes', 'posts_per_page_initial' => 9, 'posts_per_page' => 9,
                ],
            ],
            'compact_grid' => [
                'label' => esc_html__( 'Compact Grid (4 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid', 'columns' => 4, 'columns_tablet' => 3, 'columns_mobile' => 2,
                    'horizontal_gap' => ['size' => 15, 'unit' => 'px'], 'vertical_gap' => ['size' => 15, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'enable_load_more' => 'yes', 'posts_per_page_initial' => 12, 'posts_per_page' => 12,
                ],
            ],
            'single_column_grid' => [
                'label' => esc_html__( 'Single Column Grid (Mobile-friendly)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid', 'columns' => 1, 'columns_tablet' => 1, 'columns_mobile' => 1,
                    'horizontal_gap' => ['size' => 0, 'unit' => 'px'], 'vertical_g_gap' => ['size' => 20, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'enable_load_more' => 'yes', 'posts_per_page_initial' => 5, 'posts_per_page' => 5,
                ],
            ],
            'autoplay_carousel' => [
                'label' => esc_html__( 'Autoplay Carousel (3 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel', 'columns_carousel' => 3, 'columns_carousel_tablet' => 2, 'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 20, 'unit' => 'px'], 'vertical_gap' => ['size' => 0, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'carousel_autoplay' => 'yes', 'carousel_autoplay_interval' => 3000,
                    'carousel_nav_buttons' => 'yes', 'carousel_page_dots' => 'no', 'carousel_wrap_around' => 'yes',
                    'carousel_draggable' => 'yes', 'carousel_adaptive_height' => 'no', 'carousel_cell_align' => 'left', 'carousel_slides_to_move' => 1,
                ],
            ],
            'minimal_carousel' => [
                'label' => esc_html__( 'Minimal Carousel (2 Columns, No Autoplay)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel', 'columns_carousel' => 2, 'columns_carousel_tablet' => 1, 'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 30, 'unit' => 'px'], 'vertical_gap' => ['size' => 0, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'carousel_autoplay' => 'no', 'carousel_nav_buttons' => 'yes',
                    'carousel_page_dots' => 'yes', 'carousel_wrap_around' => 'no', 'carousel_draggable' => 'yes',
                    'carousel_adaptive_height' => 'yes', 'carousel_cell_align' => 'center', 'carousel_slides_to_move' => 1,
                ],
            ],
            'single_slide_carousel' => [
                'label' => esc_html__( 'Single Slide Carousel (Mobile-friendly)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel', 'columns_carousel' => 1, 'columns_carousel_tablet' => 1, 'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 0, 'unit' => 'px'], 'vertical_gap' => ['size' => 0, 'unit' => 'px'],
                    'equal_height_columns' => 'no', 'carousel_autoplay' => 'no', 'carousel_nav_buttons' => 'yes',
                    'carousel_page_dots' => 'yes', 'carousel_wrap_around' => 'no', 'carousel_draggable' => 'yes',
                    'carousel_adaptive_height' => 'yes', 'carousel_cell_align' => 'center', 'carousel_slides_to_move' => 1,
                ],
            ],
        ];
    }

    private function _get_layout_presets_options() {
        $presets = $this->_get_layout_presets();
        $options = [ 'custom' => esc_html__( 'Custom', 'custom-product-filters' ) ];
        foreach ( $presets as $key => $preset ) {
            $options[ $key ] = $preset['label'];
        }
        return $options;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $template_id = (int) $settings['template_id'];

        if ( empty( $template_id ) ) {
            echo '<div class="dgcpf-filtered-loop-widget-container"><h3>' . esc_html__( 'DomGats Filtered Loop Widget', 'custom-product-filters' ) . '</h3><p>' . esc_html__( 'Please select a Loop Item template to display content.', 'custom-product-filters' ) . '</p></div>';
            return;
        }

        // Build initial query args
        $args = [
            'post_type'      => $settings['post_type'] ?? 'product',
            'post_status'    => $settings['post_status'] ?? ['publish'],
            'posts_per_page' => $settings['posts_per_page_initial'] ?? 9,
            'paged'          => 1,
            'orderby'        => $settings['orderby'] ?? 'date',
            'order'          => $settings['order'] ?? 'DESC',
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
        ];

        if (!empty($settings['posts_include_by_ids'])) $args['post__in'] = $settings['posts_include_by_ids'];
        if (!empty($settings['posts_exclude_by_ids'])) $args['post__not_in'] = $settings['posts_exclude_by_ids'];
        if (!empty($settings['terms_include'])) $args['tax_query'][] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $settings['terms_include']];
        if (!empty($settings['terms_exclude'])) $args['tax_query'][] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $settings['terms_exclude'], 'operator' => 'NOT IN'];
        if (!empty($settings['product_categories_query'])) $args['tax_query'][] = ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $settings['product_categories_query']];
        if (!empty($settings['product_tags_query'])) $args['tax_query'][] = ['taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => $settings['product_tags_query']];

        if (!empty($settings['acf_meta_query_repeater']) && function_exists('get_field_object')) {
            foreach ($settings['acf_meta_query_repeater'] as $item) {
                if (!empty($item['acf_meta_key']) && isset($item['acf_meta_value'])) {
                    $args['meta_query'][] = [
                        'key'     => $item['acf_meta_key'],
                        'value'   => $item['acf_meta_value'],
                        'compare' => $item['acf_meta_compare'] ?? '=',
                    ];
                }
            }
        }

        $query = new \WP_Query($args);
        
        $settings['max_num_pages'] = $query->max_num_pages;

        $this->add_render_attribute( 'widget_container', 'class', 'dgcpf-filtered-loop-widget-container' );
        $this->add_render_attribute( 'widget_container', 'data-settings', wp_json_encode( $settings ) );
        $this->add_render_attribute( 'widget_container', 'data-widget-id', $this->get_id() );
        $this->add_render_attribute( 'widget_container', 'data-nonce', wp_create_nonce( 'dgcpf_filter_posts_' . $this->get_id() ) );
        $this->add_render_attribute( 'widget_container', 'data-template-id', $template_id );

        $layout_type_class = 'dgcpf-' . $settings['layout_type'];
        $this->add_render_attribute( 'loop_container', 'class', [ 'dgcpf-loop-container', $layout_type_class ] );
        $this->add_render_attribute( 'loop_container', 'aria-live', 'polite' );

        if ( 'carousel' === $settings['layout_type'] ) {
            $this->add_render_attribute( 'loop_container', 'class', 'flickity-enabled' );
            $this->add_render_attribute( 'loop_container', 'data-columns-desktop', $settings['columns_carousel'] );
            $this->add_render_attribute( 'loop_container', 'data-columns-tablet', $settings['columns_carousel_tablet'] ?? $settings['columns_carousel'] );
            $this->add_render_attribute( 'loop_container', 'data-columns-mobile', $settings['columns_carousel_mobile'] ?? $settings['columns_carousel'] );
        }
        
        echo '<div ' . $this->get_render_attribute_string( 'widget_container' ) . '>';
        echo '<div class="dgcpf-filters-wrapper">';
        if ( ! empty( $settings['filters_repeater'] ) ) {
            foreach ( $settings['filters_repeater'] as $index => $filter_item ) {
                $filter_type = $filter_item['filter_type'];
                $display_as = $filter_item['display_as'];
                $widget_id = $this->get_id();

                echo '<div class="dgcpf-filter-group dgcpf-filter-type-' . esc_attr( $filter_type ) . '">';

                if ( 'taxonomy' === $filter_type && ! empty( $filter_item['taxonomy_name'] ) ) {
                    $taxonomy_name = $filter_item['taxonomy_name'];
                    $terms = get_terms( [ 'taxonomy' => $taxonomy_name, 'hide_empty' => false ] );

                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        echo '<span class="dgcpf-filter-label" id="label_' . esc_attr($widget_id . '_' . $taxonomy_name) . '">' . esc_html( get_taxonomy( $taxonomy_name )->labels->singular_name ) . ':</span>';
                        echo '<div data-taxonomy="' . esc_attr( $taxonomy_name ) . '" data-display-as="' . esc_attr( $display_as ) . '">';

                        if ( 'dropdown' === $display_as ) {
                            echo '<select class="dgcpf-filter-dropdown" aria-labelledby="label_' . esc_attr($widget_id . '_' . $taxonomy_name) . '"><option value="">' . esc_html__( 'All', 'custom-product-filters' ) . '</option>';
                            foreach ( $terms as $term ) echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . ' (0)</option>';
                            echo '</select>';
                        } elseif ( 'checkbox' === $display_as ) {
                            echo '<div class="dgcpf-filter-checkboxes" role="group" aria-labelledby="label_' . esc_attr($widget_id . '_' . $taxonomy_name) . '">';
                            foreach ( $terms as $term_index => $term ) {
                                $input_id = 'tax_' . esc_attr($widget_id . '_' . $taxonomy_name . '_' . $term->slug);
                                echo '<label for="' . $input_id . '"><input id="' . $input_id . '" type="checkbox" class="dgcpf-filter-checkbox" value="' . esc_attr( $term->slug ) . '"> <span>' . esc_html( $term->name ) . ' (0)</span></label>';
                            }
                            echo '</div>';
                        } elseif ( 'radio' === $display_as ) {
                            echo '<div class="dgcpf-filter-radio-buttons" role="radiogroup" aria-labelledby="label_' . esc_attr($widget_id . '_' . $taxonomy_name) . '">';
                            $input_id_all = 'tax_' . esc_attr($widget_id . '_' . $taxonomy_name . '_all');
                            echo '<label for="' . $input_id_all . '"><input id="' . $input_id_all . '" type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_' . esc_attr( $widget_id . '_' . $taxonomy_name ) . '" value="" checked> <span>' . esc_html__( 'All', 'custom-product-filters' ) . ' (0)</span></label>';
                            foreach ( $terms as $term_index => $term ) {
                                $input_id = 'tax_' . esc_attr($widget_id . '_' . $taxonomy_name . '_' . $term->slug);
                                echo '<label for="' . $input_id . '"><input id="' . $input_id . '" type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_' . esc_attr( $widget_id . '_' . $taxonomy_name ) . '" value="' . esc_attr( $term->slug ) . '"> <span>' . esc_html( $term->name ) . ' (0)</span></label>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } elseif ( 'acf' === $filter_type && ! empty( $filter_item['acf_field_key'] ) && $this->_is_acf_active() ) {
                    $acf_field_key = $filter_item['acf_field_key'];
                    $field_object = get_field_object( $acf_field_key );
                    if ( $field_object ) {
                        echo '<span class="dgcpf-filter-label" id="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '">' . esc_html( $field_object['label'] ) . ':</span>';
                        echo '<div data-acf-field-key="' . esc_attr( $acf_field_key ) . '" data-display-as="' . esc_attr( $display_as ) . '" data-acf-field-type="' . esc_attr( $field_object['type'] ) . '">';

                        if ( in_array( $display_as, ['dropdown', 'checkbox', 'radio'] ) && in_array( $field_object['type'], ['select', 'radio', 'checkbox', 'true_false'] ) ) {
                            $choices = $field_object['choices'] ?? [];
                            if ('true_false' === $field_object['type']) $choices = ['1' => esc_html__('Yes', 'custom-product-filters'), '0' => esc_html__('No', 'custom-product-filters')];

                            if ( 'dropdown' === $display_as ) {
                                echo '<select class="dgcpf-filter-dropdown" aria-labelledby="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '"><option value="">' . esc_html__( 'All', 'custom-product-filters' ) . '</option>';
                                foreach ( $choices as $value => $label ) echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . ' (0)</option>';
                                echo '</select>';
                            } elseif ( 'checkbox' === $display_as ) {
                                echo '<div class="dgcpf-filter-checkboxes" role="group" aria-labelledby="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '">';
                                foreach ( $choices as $value => $label ) {
                                    $input_id = 'acf_' . esc_attr($widget_id . '_' . $acf_field_key . '_' . $value);
                                    echo '<label for="' . $input_id . '"><input id="' . $input_id . '" type="checkbox" class="dgcpf-filter-checkbox" value="' . esc_attr( $value ) . '"> <span>' . esc_html( $label ) . ' (0)</span></label>';
                                }
                                echo '</div>';
                            } elseif ( 'radio' === $display_as ) {
                                echo '<div class="dgcpf-filter-radio-buttons" role="radiogroup" aria-labelledby="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '">';
                                $input_id_all = 'acf_' . esc_attr($widget_id . '_' . $acf_field_key . '_all');
                                echo '<label for="' . $input_id_all . '"><input id="' . $input_id_all . '" type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $widget_id . '_' . $acf_field_key ) . '" value="" checked> <span>' . esc_html__( 'All', 'custom-product-filters' ) . ' (0)</span></label>';
                                foreach ( $choices as $value => $label ) {
                                    $input_id = 'acf_' . esc_attr($widget_id . '_' . $acf_field_key . '_' . $value);
                                    echo '<label for="' . $input_id . '"><input id="' . $input_id . '" type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $widget_id . '_' . $acf_field_key ) . '" value="' . esc_attr( $value ) . '"> <span>' . esc_html( $label ) . ' (0)</span></label>';
                                }
                                echo '</div>';
                            }
                        } elseif ( 'text' === $display_as && 'text' === $field_object['type'] ) {
                            echo '<input type="text" class="dgcpf-filter-text-input" placeholder="' . esc_attr( $field_object['label'] ) . '" aria-labelledby="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '">';
                        } elseif ( 'number' === $display_as && 'number' === $field_object['type'] ) {
                            echo '<input type="number" class="dgcpf-filter-number-input" placeholder="' . esc_attr( $field_object['label'] ) . '" aria-labelledby="label_' . esc_attr($widget_id . '_' . $acf_field_key) . '">';
                        }
                        echo '</div>';
                    }
                }
                echo '</div>';
            }
        }
        echo '<button class="dgcpf-clear-all-filters-button elementor-button" style="display:none;">' . esc_html__( 'Clear All Filters', 'custom-product-filters' ) . '</button>';
        echo '</div>';

        echo '<div ' . $this->get_render_attribute_string( 'loop_container' ) . '>';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (class_exists('\Elementor\Plugin')) {
                    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                }
            }
        } else {
            echo '<p class="no-products-found">' . esc_html__('No products found.', 'custom-product-filters') . '</p>';
        }
        echo '</div>';
        
        echo '<div class="dgcpf-load-more-container">';
        if ( 'yes' === $settings['enable_load_more'] ) {
            echo '<button class="dgcpf-load-more-button elementor-button">' . esc_html( $settings['load_more_button_text'] ) . '</button>';
        }
        echo '</div>';
        
        echo '</div>';

        wp_reset_postdata();
    }
}