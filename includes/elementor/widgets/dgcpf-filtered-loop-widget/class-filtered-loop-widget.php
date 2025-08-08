<?php
namespace DomGats\ProductFilter\Elementor\Widgets;

use \WP_Query;
use \Elementor\Controls_Manager;
use \Elementor\Group_Control_Border;
use \Elementor\Group_Control_Box_Shadow;
use \Elementor\Group_Control_Typography;
use \Elementor\Repeater;

// No direct 'use' for Query or QueryControlModule here,
// as we will check for class/constant existence before using them.

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Filtered_Loop_Widget extends Widget_Base {

    /**
     * Get widget name.
     */
    public function get_name() {
        return 'dgcpf_filtered_loop';
    }

    /**
     * Get widget title.
     */
    public function get_title() {
        return esc_html__( 'DomGats Filtered Loop', 'custom-product-filters' );
    }

    /**
     * Get widget icon.
     */
    public function get_icon() {
        return 'eicon-loop';
    }

    /**
     * Get widget categories.
     */
    public function get_categories() {
        return [ 'domgats-widgets' ];
    }

    /**
     * Get widget scripts.
     * Enqueue the main JavaScript file for the widget.
     */
    public function get_script_depends() {
        return [
            'dgcpf-filtered-loop-widget-js', // Our new JS file
            'flickity-js', // Flickity for carousel, if not already enqueued globally
        ];
    }

    /**
     * Get widget styles.
     * Enqueue the main CSS file for the widget.
     */
    public function get_style_depends() {
        return [
            'dgcpf-filtered-loop-widget-css', // Our new CSS file
            'flickity-css', // Flickity CSS, if not already enqueued globally
        ];
    }

    /**
     * Helper method to safely get Elementor Pro Query Control ID.
     * Prevents fatal errors if the constant is not yet defined.
     *
     * @return string The Query Control ID or a fallback text control ID.
     */
    private function _get_query_control_type() {
        // Check if the class and its constant are defined before attempting to use.
        if ( defined( '\ElementorPro\Modules\QueryControl\Controls\Query::CONTROL_ID' ) ) {
            return \ElementorPro\Modules\QueryControl\Controls\Query::CONTROL_ID;
        }
        // Fallback to a standard text control if Elementor Pro Query Control is not fully loaded.
        // This prevents fatal errors and allows the editor to load, though functionality will be limited.
        return Controls_Manager::TEXT;
    }

    /**
     * Helper method to safely get Elementor Pro Query Control Module for autocomplete objects.
     *
     * @return string The QueryControl Module class name or empty string.
     */
    private function _get_query_control_module_class() {
        if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
            return \ElementorPro\Modules\QueryControl\Module::class;
        }
        return '';
    }

    /**
     * Register controls for the widget.
     * This method adds all the controls to the widget in the Elementor editor.
     * It's part of Phase 2 of our project plan.
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content tab controls.
     */
    protected function register_content_controls() {
        // --- Start of Layout Tab ---
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
                'description' => esc_html__( 'Select an Elementor Loop Item template for the product card design.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'layout_preset',
            [
                'label'   => esc_html__( 'Load Layout Preset', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_layout_presets_options(),
                'default' => '',
                'description' => esc_html__( 'Choose a predefined layout and carousel setting. This will override current settings.', 'custom-product-filters' ),
                'render_type' => 'none', // This control is for editor-side logic, not frontend rendering.
                'frontend_available' => false,
                'separator' => 'after',
            ]
        );

        $this->add_responsive_control(
            'layout_type',
            [
                'label' => esc_html__( 'Render As', 'custom-product-filters' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'grid' => [
                        'title' => esc_html__( 'Grid', 'custom-product-filters' ),
                        'icon' => 'eicon-thumbnails-grid',
                    ],
                    'carousel' => [
                        'title' => esc_html__( 'Carousel', 'custom-product-filters' ),
                        'icon' => 'eicon-post-slider',
                    ],
                ],
                'default' => 'grid',
                'toggle' => false,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__( 'Columns (Grid)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'default' => 3,
                'condition' => [
                    'layout_type' => 'grid',
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'columns_carousel',
            [
                'label' => esc_html__( 'Columns (Carousel)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'default' => 3,
                'condition' => [
                    'layout_type' => 'carousel',
                ],
                // Note: Carousel columns are handled by Flickity JS, passed via data attribute.
                'frontend_available' => true, // Make available for JS.
            ]
        );

        $this->add_responsive_control(
            'horizontal_gap',
            [
                'label' => esc_html__( 'Horizontal Gap', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-carousel .elementor-loop-item' => 'padding-left: calc({{SIZE}}{{UNIT}} / 2); padding-right: calc({{SIZE}}{{UNIT}} / 2);',
                ],
            ]
        );

        $this->add_responsive_control(
            'vertical_gap',
            [
                'label' => esc_html__( 'Vertical Gap', 'custom-product-filters' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
                    // Vertical gap for carousel items is less common, but can be applied if needed.
                ],
                'condition' => [
                    'layout_type' => 'grid', // Only relevant for grid layout mostly
                ],
            ]
        );

        $this->add_responsive_control(
            'equal_height_columns',
            [
                'label' => esc_html__( 'Equal Height Columns', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'no',
                'selectors_dictionary' => [
                    'yes' => 'grid-auto-rows: 1fr;',
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-grid' => '{{VALUE}}',
                    '{{WRAPPER}} .dgcpf-loop-container.dgcpf-carousel .elementor-loop-item' => 'height: 100%;', // For carousel, Flickity adaptiveHeight is separate.
                ],
                'condition' => [
                    'template_id!' => '',
                ],
            ]
        );

        $this->add_control(
            'posts_per_page_initial',
            [
                'label' => esc_html__( 'Initial Items Per Page', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 9,
                'description' => esc_html__( 'Number of items to load on the first page. For "Load More" pagination.', 'custom-product-filters' ),
                'condition' => [
                    'layout_type' => 'grid',
                    'enable_load_more' => 'yes',
                ],
                'separator' => 'before',
            ]
        );


        $this->end_controls_section();
        // --- End of Layout Tab ---

        // --- Start of Query Tab ---
        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__( 'Query', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'post_type',
            [
                'label'   => esc_html__( 'Post Type', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_post_types(), // Dynamic post types
                'default' => 'product',
            ]
        );

        // Check if Elementor Pro's Query Control is available before adding these controls
        $query_control_type = $this->_get_query_control_type();
        $query_control_module_class = $this->_get_query_control_module_class();

        if ( $query_control_type !== Controls_Manager::TEXT && ! empty( $query_control_module_class ) ) {
            $this->add_control(
                'posts_include_by_ids',
                [
                    'label'       => esc_html__( 'Include Posts by ID', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_POST',
                        'query'  => [ 'post_type' => 'any' ], // Will be dynamically updated by JS if needed
                    ],
                    'description' => esc_html__( 'Select specific posts to include.', 'custom-product-filters' ),
                ]
            );

            $this->add_control(
                'posts_exclude_by_ids',
                [
                    'label'       => esc_html__( 'Exclude Posts by ID', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_POST',
                        'query'  => [ 'post_type' => 'any' ], // Will be dynamically updated by JS if needed
                    ],
                    'description' => esc_html__( 'Select specific posts to exclude.', 'custom-product-filters' ),
                ]
            );

            $this->add_control(
                'terms_include',
                [
                    'label'       => esc_html__( 'Include Terms', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_TAX',
                        'query'  => [ 'taxonomy' => 'category' ], // Default, will be dynamically updated
                    ],
                    'description' => esc_html__( 'Select terms (categories, tags, etc.) to include.', 'custom-product-filters' ),
                ]
            );

            $this->add_control(
                'terms_exclude',
                [
                    'label'       => esc_html__( 'Exclude Terms', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_TAX',
                        'query'  => [ 'taxonomy' => 'category' ], // Default, will be dynamically updated
                    ],
                    'description' => esc_html__( 'Select terms (categories, tags, etc.) to exclude.', 'custom-product-filters' ),
                ]
            );

            // New: Separate Select2 for Product Categories
            $this->add_control(
                'product_categories_query',
                [
                    'label'       => esc_html__( 'Product Categories', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_TAX',
                        'query'  => [ 'taxonomy' => 'product_cat' ],
                    ],
                    'condition' => [
                        'post_type' => 'product',
                    ],
                    'description' => esc_html__( 'Filter by specific product categories.', 'custom-product-filters' ),
                ]
            );

            // New: Separate Select2 for Product Tags
            $this->add_control(
                'product_tags_query',
                [
                    'label'       => esc_html__( 'Product Tags', 'custom-product-filters' ),
                    'type'        => $query_control_type,
                    'label_block' => true,
                    'multiple'    => true,
                    'autocomplete' => [
                        'object' => $query_control_module_class . '::' . 'QUERY_OBJECT_TAX',
                        'query'  => [ 'taxonomy' => 'product_tag' ],
                    ],
                    'condition' => [
                        'post_type' => 'product',
                    ],
                    'description' => esc_html__( 'Filter by specific product tags.', 'custom-product-filters' ),
                ]
            );
        } else {
            $this->add_control(
                'elementor_pro_query_control_notice',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => esc_html__( 'Elementor Pro\'s Query Control is not active or fully loaded. Advanced query options are unavailable. Please ensure Elementor Pro is installed and active.', 'custom-product-filters' ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ]
            );
        }


        // New: ACF Meta Query Repeater
        $acf_repeater = new Repeater();

        $acf_repeater->add_control(
            'acf_meta_key',
            [
                'label'   => esc_html__( 'ACF Field', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_acf_field_keys(), // Dynamically populate ACF fields
                'description' => esc_html__( 'Select the ACF field to query by.', 'custom-product-filters' ),
            ]
        );

        $acf_repeater->add_control(
            'acf_meta_value',
            [
                'label'       => esc_html__( 'Field Value', 'custom-product-filters' ),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'Enter value', 'custom-product-filters' ),
                'description' => esc_html__( 'Value to compare against the ACF field. For multiple values (e.g., checkbox), use comma-separated values.', 'custom-product-filters' ),
                'condition' => [
                    'acf_meta_key!' => '',
                ],
            ]
        );

        $acf_repeater->add_control(
            'acf_meta_compare',
            [
                'label'   => esc_html__( 'Comparison', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    '='         => esc_html__( 'Equal to', 'custom-product-filters' ),
                    '!='        => esc_html__( 'Not Equal to', 'custom-product-filters' ),
                    '>'         => esc_html__( 'Greater than', 'custom-product-filters' ),
                    '>='        => esc_html__( 'Greater than or Equal to', 'custom-product-filters' ),
                    '<'         => esc_html__( 'Less than', 'custom-product-filters' ),
                    '<='        => esc_html__( 'Less than or Equal to', 'custom-product-filters' ),
                    'LIKE'      => esc_html__( 'Contains', 'custom-product-filters' ),
                    'NOT LIKE'  => esc_html__( 'Does Not Contain', 'custom-product-filters' ),
                    'IN'        => esc_html__( 'In Array', 'custom-product-filters' ),
                    'NOT IN'    => esc_html__( 'Not In Array', 'custom-product-filters' ),
                    'BETWEEN'   => esc_html__( 'Between', 'custom-product-filters' ),
                    'NOT BETWEEN' => esc_html__( 'Not Between', 'custom-product-filters' ),
                    'EXISTS'    => esc_html__( 'Exists', 'custom-product-filters' ),
                    'NOT EXISTS' => esc_html__( 'Does Not Exist', 'custom-product-filters' ),
                    'REGEXP'    => esc_html__( 'Regex', 'custom-product-filters' ),
                    'NOT REGEXP' => esc_html__( 'Not Regex', 'custom-product-filters' ),
                ],
                'default' => '=',
                'condition' => [
                    'acf_meta_key!' => '',
                ],
            ]
        );

        $this->add_control(
            'acf_meta_query_repeater',
            [
                'label'   => esc_html__( 'ACF Meta Queries', 'custom-product-filters' ),
                'type'    => Controls_Manager::REPEATER,
                'fields'  => $acf_repeater->get_controls(),
                'title_field' => '{{{ acf_meta_key }}}',
                'description' => esc_html__( 'Add custom field queries. Use field name for Meta Key. For multiple values, use comma-separated.', 'custom-product-filters' ),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'post_status',
            [
                'label'   => esc_html__( 'Post Status', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'publish' => esc_html__( 'Publish', 'custom-product-filters' ),
                    'pending' => esc_html__( 'Pending', 'custom-product-filters' ),
                    'draft'   => esc_html__( 'Draft', 'custom-product-filters' ),
                    'future'  => esc_html__( 'Future', 'custom-product-filters' ),
                    'private' => esc_html__( 'Private', 'custom-product-filters' ),
                    'any'     => esc_html__( 'Any', 'custom-product-filters' ),
                ],
                'default' => 'publish',
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'   => esc_html__( 'Order By', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'date'          => esc_html__( 'Date', 'custom-product-filters' ),
                    'ID'            => esc_html__( 'Post ID', 'custom-product-filters' ),
                    'author'        => esc_html__( 'Author', 'custom-product-filters' ),
                    'title'         => esc_html__( 'Title', 'custom-product-filters' ),
                    'name'          => esc_html__( 'Post Name (Slug)', 'custom-product-filters' ),
                    'modified'      => esc_html__( 'Last Modified Date', 'custom-product-filters' ),
                    'parent'        => esc_html__( 'Parent ID', 'custom-product-filters' ),
                    'rand'          => esc_html__( 'Random', 'custom-product-filters' ),
                    'comment_count' => esc_html__( 'Comment Count', 'custom-product-filters' ),
                    'menu_order'    => esc_html__( 'Menu Order', 'custom-product-filters' ),
                ],
                'default' => 'date',
            ]
        );

        $this->add_control(
            'order',
            [
                'label'   => esc_html__( 'Order', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'DESC' => esc_html__( 'Descending', 'custom-product-filters' ),
                    'ASC'  => esc_html__( 'Ascending', 'custom-product-filters' ),
                ],
                'default' => 'DESC',
            ]
        );

        $this->end_controls_section();
        // --- End of Query Tab ---

        // --- Start of Filters Tab ---
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
                'options' => [
                    'taxonomy' => esc_html__( 'Taxonomy', 'custom-product-filters' ),
                    'acf'      => esc_html__( 'Custom Field (ACF)', 'custom-product-filters' ),
                ],
                'default' => 'taxonomy',
            ]
        );

        $repeater->add_control(
            'taxonomy_name',
            [
                'label'   => esc_html__( 'Taxonomy', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->_get_all_taxonomies(), // Dynamically populate taxonomies
                'condition' => [
                    'filter_type' => 'taxonomy',
                ],
            ]
        );

        $repeater->add_control(
            'acf_field_key',
            [
                'label'     => esc_html__( 'ACF Field', 'custom-product-filters' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => $this->_get_all_acf_field_keys(), // Dynamically populate ACF fields
                'condition' => [
                    'filter_type' => 'acf',
                ],
            ]
        );
        
        $repeater->add_control(
            'display_as',
            [
                'label'   => esc_html__( 'Display As', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'dropdown' => esc_html__( 'Dropdown', 'custom-product-filters' ),
                    'checkbox' => esc_html__( 'Checkboxes', 'custom-product-filters' ),
                    'radio'    => esc_html__( 'Radio Buttons', 'custom-product-filters' ),
                    'text'     => esc_html__( 'Text Input', 'custom-product-filters' ), // For ACF text/number
                    'number'   => esc_html__( 'Number Input', 'custom-product-filters' ), // For ACF number
                ],
                'default' => 'dropdown',
            ]
        );

        $this->add_control(
            'filters_repeater',
            [
                'label'   => esc_html__( 'Filters', 'custom-product-filters' ),
                'type'    => Controls_Manager::REPEATER,
                'fields'  => $repeater->get_controls(),
                'title_field' => '{{{ filter_type }}} - {{{ taxonomy_name || acf_field_key }}}', // Better title for repeater items
                'default' => [
                    [
                        'filter_type'   => 'taxonomy',
                        'taxonomy_name' => 'product_tag', // Default to product_tag
                        'display_as'    => 'dropdown',
                    ],
                ],
            ]
        );

        $this->add_control(
            'filter_logic',
            [
                'label'   => esc_html__( 'Filter Logic', 'custom-product-filters' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'AND' => esc_html__( 'AND', 'custom-product-filters' ),
                    'OR'  => esc_html__( 'OR', 'custom-product-filters' ),
                ],
                'default'     => 'AND',
                'description' => esc_html__( 'Determines the relationship between different active filters.', 'custom-product-filters' ),
            ]
        );

        $this->end_controls_section();
        // --- End of Filters Tab ---

        // --- Start of Carousel & Load More Tab ---
        $this->start_controls_section(
            'section_pagination_carousel',
            [
                'label' => esc_html__( 'Pagination & Carousel', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
                'conditions' => [
                    'relation' => 'or',
                    'terms' => [
                        [
                            'name'     => 'layout_type',
                            'operator' => '==',
                            'value'    => 'grid',
                        ],
                        [
                            'name'     => 'layout_type',
                            'operator' => '==',
                            'value'    => 'carousel',
                        ]
                    ]
                ]
            ]
        );

        $this->add_control(
            'enable_load_more',
            [
                'label' => esc_html__( 'Enable Load More', 'custom-product-filters' ),
                'type'  => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'layout_type' => 'grid',
                ],
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__( 'Posts Per Page (Load More)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 9,
                'condition' => [
                    'enable_load_more' => 'yes',
                    'layout_type' => 'grid',
                ],
                'description' => esc_html__( 'Number of items to load with each "Load More" click.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'load_more_button_text',
            [
                'label' => esc_html__( 'Load More Button Text', 'custom-product-filters' ),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__( 'Load More', 'custom-product-filters' ),
                'placeholder' => esc_html__( 'Load More', 'custom-product-filters' ),
                'condition' => [
                    'enable_load_more' => 'yes',
                    'layout_type' => 'grid',
                ],
            ]
        );

        $this->add_control(
            'no_more_products_text',
            [
                'label' => esc_html__( 'No More Products Text', 'custom-product-filters' ),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__( 'No More Products', 'custom-product-filters' ),
                'placeholder' => esc_html__( 'No More Products', 'custom-product-filters' ),
                'condition' => [
                    'enable_load_more' => 'yes',
                    'layout_type' => 'grid',
                ],
                'description' => esc_html__( 'Text to display when all products have been loaded.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'enable_history_api',
            [
                'label' => esc_html__( 'Enable History API (URL Update)', 'custom-product-filters' ),
                'type'  => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => esc_html__( 'If enabled, filter selections will update the browser URL, allowing sharing and back/forward navigation.', 'custom-product-filters' ),
            ]
        );
        
        // --- Carousel Specific Controls ---
        $this->add_control(
            'carousel_options_heading',
            [
                'label' => esc_html__( 'Carousel Options', 'custom-product-filters' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
            ]
        );

        $this->add_control(
            'carousel_autoplay',
            [
                'label' => esc_html__( 'Autoplay', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
            ]
        );

        $this->add_control(
            'carousel_autoplay_interval',
            [
                'label' => esc_html__( 'Autoplay Interval (ms)', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1000,
                'step' => 500,
                'default' => 3000,
                'condition' => [
                    'layout_type' => 'carousel',
                    'carousel_autoplay' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'carousel_nav_buttons',
            [
                'label' => esc_html__( 'Navigation Arrows', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'custom-product-filters' ),
                'label_off' => esc_html__( 'Hide', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
            ]
        );

        $this->add_control(
            'carousel_prev_arrow_icon',
            [
                'label' => esc_html__( 'Previous Arrow Icon', 'custom-product-filters' ),
                'type' => Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'fa4compatibility' => 'icon',
                'condition' => [
                    'layout_type' => 'carousel',
                    'carousel_nav_buttons' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'carousel_next_arrow_icon',
            [
                'label' => esc_html__( 'Next Arrow Icon', 'custom-product-filters' ),
                'type' => Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'fa4compatibility' => 'icon',
                'condition' => [
                    'layout_type' => 'carousel',
                    'carousel_nav_buttons' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'carousel_slides_to_move',
            [
                'label' => esc_html__( 'Slides to Move', 'custom-product-filters' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 1,
                'condition' => [
                    'layout_type' => 'carousel',
                ],
                'description' => esc_html__( 'Number of slides to move with each navigation click.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'carousel_page_dots',
            [
                'label' => esc_html__( 'Pagination Dots', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'custom-product-filters' ),
                'label_off' => esc_html__( 'Hide', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
            ]
        );

        $this->add_control(
            'carousel_wrap_around',
            [
                'label' => esc_html__( 'Wrap Around', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
                'description' => esc_html__( 'Enable infinite scrolling.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'carousel_draggable',
            [
                'label' => esc_html__( 'Draggable', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
                'description' => esc_html__( 'Allow dragging cells to navigate.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'carousel_adaptive_height',
            [
                'label' => esc_html__( 'Adaptive Height', 'custom-product-filters' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'custom-product-filters' ),
                'label_off' => esc_html__( 'No', 'custom-product-filters' ),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
                'description' => esc_html__( 'Height of carousel changes to fit selected cell.', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'carousel_cell_align',
            [
                'label' => esc_html__( 'Cell Align', 'custom-product-filters' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'left'   => esc_html__( 'Left', 'custom-product-filters' ),
                    'center' => esc_html__( 'Center', 'custom-product-filters' ),
                    'right'  => esc_html__( 'Right', 'custom-product-filters' ),
                ],
                'default' => 'left',
                'condition' => [
                    'layout_type' => 'carousel',
                ],
            ]
        );


        $this->end_controls_section();
        // --- End of Carousel & Load More Tab ---
    }
    
    /**
     * Register style tab controls.
     */
    protected function register_style_controls() {
        // --- Start of Filter Bar Styling ---
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
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filters-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'filter_label_heading',
            [
                'label' => esc_html__( 'Filter Label', 'custom-product-filters' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'filter_label_color',
            [
                'label' => esc_html__( 'Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-label' => 'color: {{VALUE}};',
                ],
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
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'filter_input_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_input_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'background-color: {{VALUE}};',
                ],
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
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown, {{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label, {{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .dgcpf-filter-checkboxes label, {{WRAPPER}} .dgcpf-filter-radio-buttons label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .dgcpf-filter-text-input, {{WRAPPER}} .dgcpf-filter-number-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'filter_input_disabled_color',
            [
                'label' => esc_html__( 'Disabled Item Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-dropdown option:disabled, {{WRAPPER}} .dgcpf-filter-checkboxes label.disabled, {{WRAPPER}} .dgcpf-filter-radio-buttons label.disabled' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_input_disabled_background_color',
            [
                'label' => esc_html__( 'Disabled Item Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-checkboxes label.disabled, {{WRAPPER}} .dgcpf-filter-radio-buttons label.disabled' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        // Active state color for checkboxes/radios
        $this->add_control(
            'filter_input_active_text_color',
            [
                'label' => esc_html__( 'Active Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-filter-checkbox:checked + span, {{WRAPPER}} .dgcpf-filter-radio:checked + span' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'display_as' => ['checkbox', 'radio'],
                ],
            ]
        );

        $this->end_controls_section();
        // --- End of Filter Bar Styling ---

        // --- Start of Grid/Carousel Styling ---
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
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 1000,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container' => 'min-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'loading_spinner_color',
            [
                'label' => esc_html__( 'Loading Spinner Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.loading::after' => 'border-top-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'loading_overlay_color',
            [
                'label' => esc_html__( 'Loading Overlay Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-loop-container.loading::before' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
        // --- End of Grid/Carousel Styling ---

        // --- Start of Pagination (Load More) Styling ---
        $this->start_controls_section(
            'section_load_more_style',
            [
                'label' => esc_html__( 'Load More Button', 'custom-product-filters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'enable_load_more' => 'yes',
                    'layout_type' => 'grid', // Only for grid layout
                ],
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

        $this->start_controls_tab(
            'tab_load_more_button_normal',
            [
                'label' => esc_html__( 'Normal', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'load_more_button_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_button_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button' => 'background-color: {{VALUE}};',
                ],
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
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_load_more_button_hover',
            [
                'label' => esc_html__( 'Hover', 'custom-product-filters' ),
            ]
        );

        $this->add_control(
            'load_more_button_hover_text_color',
            [
                'label' => esc_html__( 'Text Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_button_hover_background_color',
            [
                'label' => esc_html__( 'Background Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'load_more_button_hover_border_color',
            [
                'label' => esc_html__( 'Border Color', 'custom-product-filters' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dgcpf-load-more-button:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
        // --- End of Pagination (Load More) Styling ---

        // --- Start of No Products Found Message Styling ---
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
                'selectors' => [
                    '{{WRAPPER}} .no-products-found' => 'color: {{VALUE}};',
                ],
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
                'selectors' => [
                    '{{WRAPPER}} .no-products-found' => 'background-color: {{VALUE}};',
                ],
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
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .no-products-found' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'no_products_padding',
            [
                'label' => esc_html__( 'Padding', 'custom-product-filters' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .no-products-found' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
        // --- End of No Products Found Message Styling ---
    }

    /**
     * Get a list of all Elementor Loop Item templates.
     *
     * @return array
     */
    private function _get_loop_templates() {
        $templates = get_posts( [
            'post_type'      => 'elementor_library',
            'posts_per_page' => -1,
            'meta_key'       => '_elementor_template_type',
            'meta_value'     => 'loop-item',
            'fields'         => 'ids', // Only get IDs for performance
        ] );

        $options = [
            '' => esc_html__( 'Select a template', 'custom-product-filters' ),
        ];

        if ( $templates ) {
            foreach ( $templates as $template_id ) {
                $options[ $template_id ] = get_the_title( $template_id );
            }
        }

        return $options;
    }

    /**
     * Get a list of all public post types.
     *
     * @return array
     */
    private function _get_all_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $options = [
            '' => esc_html__( 'Select Post Type', 'custom-product-filters' ),
        ];
        foreach ( $post_types as $post_type ) {
            $options[ $post_type->name ] = $post_type->labels->singular_name;
        }
        return $options;
    }

    /**
     * Get a list of all public taxonomies.
     *
     * @return array
     */
    private function _get_all_taxonomies() {
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        $options = [
            '' => esc_html__( 'Select a taxonomy', 'custom-product-filters' ),
        ];

        if ( $taxonomies ) {
            foreach ( $taxonomies as $taxonomy ) {
                $options[ $taxonomy->name ] = $taxonomy->labels->singular_name;
            }
        }
        return $options;
    }

    /**
     * Get a list of all ACF field keys.
     *
     * @return array
     */
    private function _get_all_acf_field_keys() {
        $options = [
            '' => esc_html__( 'Select an ACF field', 'custom-product-filters' ),
        ];

        if ( function_exists( 'acf_get_field_groups' ) ) {
            $field_groups = acf_get_field_groups();
            foreach ( $field_groups as $group ) {
                $fields = acf_get_fields( $group['key'] );
                foreach ( $fields as $field ) {
                    // Only include common filterable field types
                    if ( in_array( $field['type'], ['text', 'number', 'select', 'checkbox', 'radio', 'true_false'] ) ) {
                        // Use field name as key, and append type for clarity
                        $options[ $field['name'] ] = $field['label'] . ' (' . $field['type'] . ')';
                    }
                }
            }
        } else {
            $options['acf_not_active'] = esc_html__( 'ACF plugin not active', 'custom-product-filters' );
        }

        return $options;
    }

    /**
     * Define and retrieve layout presets.
     *
     * @return array
     */
    private function _get_layout_presets() {
        return [
            'default_grid' => [
                'label' => esc_html__( 'Default Grid (3 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid',
                    'columns' => 3,
                    'columns_tablet' => 2,
                    'columns_mobile' => 1,
                    'horizontal_gap' => ['size' => 20, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 20, 'unit' => 'px'],
                    'equal_height_columns' => 'no',
                    'enable_load_more' => 'yes',
                    'posts_per_page_initial' => 9,
                    'posts_per_page' => 9,
                ],
            ],
            'compact_grid' => [
                'label' => esc_html__( 'Compact Grid (4 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid',
                    'columns' => 4,
                    'columns_tablet' => 3,
                    'columns_mobile' => 2,
                    'horizontal_gap' => ['size' => 15, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 15, 'unit' => 'px'],
                    'equal_height_columns' => 'no',
                    'enable_load_more' => 'yes',
                    'posts_per_page_initial' => 12,
                    'posts_per_page' => 12,
                ],
            ],
            'single_column_grid' => [
                'label' => esc_html__( 'Single Column Grid (Mobile-friendly)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'grid',
                    'columns' => 1,
                    'columns_tablet' => 1,
                    'columns_mobile' => 1,
                    'horizontal_gap' => ['size' => 0, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 20, 'unit' => 'px'],
                    'equal_height_columns' => 'no',
                    'enable_load_more' => 'yes',
                    'posts_per_page_initial' => 5,
                    'posts_per_page' => 5,
                ],
            ],
            'autoplay_carousel' => [
                'label' => esc_html__( 'Autoplay Carousel (3 Columns)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel',
                    'columns_carousel' => 3,
                    'columns_carousel_tablet' => 2,
                    'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 20, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 0, 'unit' => 'px'], // Not typically used for carousel
                    'equal_height_columns' => 'no', // Adaptive height is separate for carousel
                    'carousel_autoplay' => 'yes',
                    'carousel_autoplay_interval' => 3000,
                    'carousel_nav_buttons' => 'yes',
                    'carousel_page_dots' => 'no',
                    'carousel_wrap_around' => 'yes',
                    'carousel_draggable' => 'yes',
                    'carousel_adaptive_height' => 'no',
                    'carousel_cell_align' => 'left',
                    'carousel_slides_to_move' => 1,
                ],
            ],
            'minimal_carousel' => [
                'label' => esc_html__( 'Minimal Carousel (2 Columns, No Autoplay)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel',
                    'columns_carousel' => 2,
                    'columns_carousel_tablet' => 1,
                    'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 30, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 0, 'unit' => 'px'], // Not typically used for carousel
                    'equal_height_columns' => 'no',
                    'carousel_autoplay' => 'no',
                    'carousel_nav_buttons' => 'yes',
                    'carousel_page_dots' => 'yes',
                    'carousel_wrap_around' => 'no',
                    'carousel_draggable' => 'yes',
                    'carousel_adaptive_height' => 'yes',
                    'carousel_cell_align' => 'center',
                    'carousel_slides_to_move' => 1,
                ],
            ],
            'single_slide_carousel' => [
                'label' => esc_html__( 'Single Slide Carousel (Mobile-friendly)', 'custom-product-filters' ),
                'settings' => [
                    'layout_type' => 'carousel',
                    'columns_carousel' => 1,
                    'columns_carousel_tablet' => 1,
                    'columns_carousel_mobile' => 1,
                    'horizontal_gap' => ['size' => 0, 'unit' => 'px'],
                    'vertical_gap' => ['size' => 0, 'unit' => 'px'],
                    'equal_height_columns' => 'no',
                    'carousel_autoplay' => 'no',
                    'carousel_nav_buttons' => 'yes',
                    'carousel_page_dots' => 'yes',
                    'carousel_wrap_around' => 'no',
                    'carousel_draggable' => 'yes',
                    'carousel_adaptive_height' => 'yes',
                    'carousel_cell_align' => 'center',
                    'carousel_slides_to_move' => 1,
                ],
            ],
        ];
    }

    /**
     * Get layout presets formatted for Elementor Select control.
     *
     * @return array
     */
    private function _get_layout_presets_options() {
        $presets = $this->_get_layout_presets();
        $options = [
            '' => esc_html__( '— Select Preset —', 'custom-product-filters' ),
        ];
        foreach ( $presets as $key => $preset ) {
            $options[ $key ] = $preset['label'];
        }
        return $options;
    }

    /**
     * Render the widget output on the frontend.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $template_id = (int) $settings['template_id'];

        if ( empty( $template_id ) ) {
            echo '<div class="dgcpf-filtered-loop-widget-container">';
            echo '<h3>' . esc_html__( 'DomGats Filtered Loop Widget', 'custom-product-filters' ) . '</h3>';
            echo '<p>' . esc_html__( 'Please select a Loop Item template to display content.', 'custom-product-filters' ) . '</p>';
            echo '</div>';
            return;
        }

        // Prepare initial query arguments based on widget settings.
        $args = [
            'post_type'      => $settings['post_type'],
            'post_status'    => !empty($settings['post_status']) ? $settings['post_status'] : 'publish',
            'posts_per_page' => $settings['posts_per_page_initial'] ?? 9, // Use initial items loaded
            'paged'          => 1, // Always start on page 1 for initial render
            'orderby'        => $settings['orderby'] ?? 'date',
            'order'          => $settings['order'] ?? 'DESC',
        ];

        // Handle include/exclude posts
        if ( ! empty( $settings['posts_include_by_ids'] ) ) {
            $args['post__in'] = $settings['posts_include_by_ids']; // Already array from Query control
        }
        if ( ! empty( $settings['posts_exclude_by_ids'] ) ) {
            $args['post__not_in'] = $settings['posts_exclude_by_ids']; // Already array from Query control
        }

        // Handle include/exclude terms (initial query) and new product categories/tags
        $tax_query_main = [];
        if ( ! empty( $settings['terms_include'] ) ) {
            $tax_query_main[] = [
                'taxonomy' => 'category', // Default, will be refined by AJAX handler
                'field'    => 'term_id', // Query control returns IDs
                'terms'    => $settings['terms_include'],
                'operator' => 'IN',
            ];
        }
        if ( ! empty( $settings['terms_exclude'] ) ) {
            $tax_query_main[] = [
                'taxonomy' => 'category', // Default, will be refined by AJAX handler
                'field'    => 'term_id', // Query control returns IDs
                'terms'    => $settings['terms_exclude'],
                'operator' => 'NOT IN',
            ];
        }
        if ( ! empty( $settings['product_categories_query'] ) && 'product' === $settings['post_type'] ) {
            $tax_query_main[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $settings['product_categories_query'],
                'operator' => 'IN',
            ];
        }
        if ( ! empty( $settings['product_tags_query'] ) && 'product' === $settings['post_type'] ) {
            $tax_query_main[] = [
                'taxonomy' => 'product_tag',
                'field'    => 'term_id',
                'terms'    => $settings['product_tags_query'],
                'operator' => 'IN',
            ];
        }
        if ( ! empty( $tax_query_main ) ) {
            $args['tax_query'] = [ 'relation' => 'AND' ];
            $args['tax_query'] = array_merge( $args['tax_query'], $tax_query_main );
        }

        // Handle ACF Meta Queries
        $meta_query_main = [];
        if ( ! empty( $settings['acf_meta_query_repeater'] ) && function_exists( 'get_field_object' ) ) {
            $meta_query_main['relation'] = 'AND'; // Default relation for multiple meta queries in initial render
            foreach ( $settings['acf_meta_query_repeater'] as $meta_item ) {
                if ( ! empty( $meta_item['acf_meta_key'] ) && ! empty( $meta_item['acf_meta_value'] ) ) {
                    $field_object = get_field_object( $meta_item['acf_meta_key'] );
                    if ( $field_object ) {
                        $meta_value = $meta_item['acf_meta_value'];
                        $compare_operator = $meta_item['acf_meta_compare'];
                        $meta_type = 'CHAR'; // Default

                        // Adjust meta_value and type based on field type and comparison
                        if ( in_array( $field_object['type'], ['number'] ) ) {
                            $meta_type = 'NUMERIC';
                            // For BETWEEN/NOT BETWEEN, value should be an array
                            if ( in_array( $compare_operator, ['BETWEEN', 'NOT BETWEEN'] ) ) {
                                $meta_value = array_map( 'floatval', explode( ',', $meta_value ) );
                            } else {
                                $meta_value = floatval( $meta_value );
                            }
                        } elseif ( in_array( $field_object['type'], ['checkbox', 'select'] ) && in_array( $compare_operator, ['LIKE', 'NOT LIKE'] ) ) {
                            // ACF stores checkbox/select multiple values as serialized arrays
                            $meta_value = '%' . serialize( strval( $meta_value ) ) . '%';
                        } elseif ( in_array( $compare_operator, ['IN', 'NOT IN'] ) ) {
                            $meta_value = array_map( 'trim', explode( ',', $meta_value ) );
                        }

                        $meta_query_main[] = [
                            'key'     => $meta_item['acf_meta_key'],
                            'value'   => $meta_value,
                            'compare' => $compare_operator,
                            'type'    => $meta_type,
                        ];
                    }
                }
            }
        }
        if ( ! empty( $meta_query_main ) ) {
            $args['meta_query'] = $meta_query_main;
        }

        $query = new WP_Query( $args );

        // Add data attributes for JavaScript to pick up widget settings.
        $this->add_render_attribute( 'widget_container', 'class', 'dgcpf-filtered-loop-widget-container' );
        $this->add_render_attribute( 'widget_container', 'data-settings', wp_json_encode( $settings ) );
        $this->add_render_attribute( 'widget_container', 'data-widget-id', $this->get_id() );
        $this->add_render_attribute( 'widget_container', 'data-template-id', $template_id );

        // Determine layout class
        $layout_type_class = 'dgcpf-' . $settings['layout_type'];
        $this->add_render_attribute( 'loop_container', 'class', [ 'dgcpf-loop-container', $layout_type_class ] );
        if ( 'carousel' === $settings['layout_type'] ) {
            $this->add_render_attribute( 'loop_container', 'class', 'flickity-enabled' ); // Add Flickity class for initial styling
            // Pass responsive columns for carousel to JS
            $this->add_render_attribute( 'loop_container', 'data-columns-desktop', $settings['columns_carousel'] );
            $this->add_render_attribute( 'loop_container', 'data-columns-tablet', $settings['columns_carousel_tablet'] ?? $settings['columns_carousel'] );
            $this->add_render_attribute( 'loop_container', 'data-columns-mobile', $settings['columns_carousel_mobile'] ?? $settings['columns_carousel'] );
        }
        
        // Output the main widget container with data attributes.
        echo '<div ' . $this->get_render_attribute_string( 'widget_container' ) . '>';

        echo '<div class="dgcpf-filters-wrapper">';
        // Render the filter UI based on the repeater controls.
        if ( ! empty( $settings['filters_repeater'] ) ) {
            foreach ( $settings['filters_repeater'] as $filter_item ) {
                $filter_type   = $filter_item['filter_type'];
                $display_as    = $filter_item['display_as'];
                $filter_name_for_url = ''; // Initialize

                echo '<div class="dgcpf-filter-group dgcpf-filter-type-' . esc_attr( $filter_type ) . '">';

                if ( 'taxonomy' === $filter_type && ! empty( $filter_item['taxonomy_name'] ) ) {
                    $taxonomy_name = $filter_item['taxonomy_name'];
                    $terms = get_terms( [
                        'taxonomy'   => $taxonomy_name,
                        'hide_empty' => false, // Get all terms initially, JS will disable/count
                    ] );

                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        $filter_name_for_url = 'dgcpf_tax_' . sanitize_key( $taxonomy_name );
                        echo '<span class="dgcpf-filter-label">' . esc_html( get_taxonomy( $taxonomy_name )->labels->singular_name ) . ':</span>';
                        echo '<div data-taxonomy="' . esc_attr( $taxonomy_name ) . '" data-display-as="' . esc_attr( $display_as ) . '" data-filter-name="' . esc_attr( $filter_name_for_url ) . '">';

                        if ( 'dropdown' === $display_as ) {
                            echo '<select class="dgcpf-filter-dropdown" aria-label="' . esc_attr( get_taxonomy( $taxonomy_name )->labels->singular_name ) . '">';
                            echo '<option value="">' . esc_html__( 'All', 'custom-product-filters' ) . '</option>';
                            foreach ( $terms as $term ) {
                                echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                            }
                            echo '</select>';
                        } elseif ( 'checkbox' === $display_as ) {
                            echo '<div class="dgcpf-filter-checkboxes" role="group" aria-labelledby="filter-label-' . esc_attr( $taxonomy_name ) . '">';
                            echo '<span id="filter-label-' . esc_attr( $taxonomy_name ) . '" class="screen-reader-text">' . esc_html( get_taxonomy( $taxonomy_name )->labels->singular_name ) . ' filter options</span>'; // Hidden label for accessibility
                            foreach ( $terms as $term ) {
                                echo '<label><input type="checkbox" class="dgcpf-filter-checkbox" value="' . esc_attr( $term->slug ) . '" aria-label="' . esc_attr( $term->name ) . '"> <span>' . esc_html( $term->name ) . '</span></label>';
                            }
                            echo '</div>';
                        } elseif ( 'radio' === $display_as ) {
                            echo '<div class="dgcpf-filter-radio-buttons" role="radiogroup" aria-labelledby="filter-label-' . esc_attr( $taxonomy_name ) . '">';
                            echo '<span id="filter-label-' . esc_attr( $taxonomy_name ) . '" class="screen-reader-text">' . esc_html( get_taxonomy( $taxonomy_name )->labels->singular_name ) . ' filter options</span>'; // Hidden label for accessibility
                            echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_' . esc_attr( $taxonomy_name ) . '" value="" checked aria-label="' . esc_html__( 'All', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'All', 'custom-product-filters' ) . '</span></label>';
                            foreach ( $terms as $term ) {
                                echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_' . esc_attr( $taxonomy_name ) . '" value="' . esc_attr( $term->slug ) . '" aria-label="' . esc_attr( $term->name ) . '"> <span>' . esc_html( $term->name ) . '</span></label>';
                            }
                            echo '</div>';
                        }
                        echo '</div>'; // data-taxonomy container
                    }
                } elseif ( 'acf' === $filter_type && ! empty( $filter_item['acf_field_key'] ) && function_exists( 'get_field_object' ) ) {
                    $acf_field_key = $filter_item['acf_field_key'];
                    $field_object = get_field_object( $acf_field_key ); // Use field name directly as per decision
                    if ( ! $field_object ) {
                        // Fallback if field name doesn't work, try with 'field_' prefix for key
                        $field_object = get_field_object( 'field_' . $acf_field_key );
                    }

                    if ( $field_object ) {
                        $filter_name_for_url = 'dgcpf_acf_' . sanitize_key( $acf_field_key );
                        echo '<span class="dgcpf-filter-label">' . esc_html( $field_object['label'] ) . ':</span>';
                        echo '<div data-acf-field-key="' . esc_attr( $acf_field_key ) . '" data-display-as="' . esc_attr( $display_as ) . '" data-filter-name="' . esc_attr( $filter_name_for_url ) . '" data-acf-field-type="' . esc_attr( $field_object['type'] ) . '">';

                        if ( 'dropdown' === $display_as && in_array( $field_object['type'], ['select', 'radio', 'checkbox', 'true_false'] ) ) {
                            echo '<select class="dgcpf-filter-dropdown" aria-label="' . esc_attr( $field_object['label'] ) . '">';
                            echo '<option value="">' . esc_html__( 'All', 'custom-product-filters' ) . '</option>';
                            if ( ! empty( $field_object['choices'] ) ) {
                                foreach ( $field_object['choices'] as $value => $label ) {
                                    echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
                                }
                            } elseif ( 'true_false' === $field_object['type'] ) {
                                echo '<option value="1">' . esc_html__( 'Yes', 'custom-product-filters' ) . '</option>';
                                echo '<option value="0">' . esc_html__( 'No', 'custom-product-filters' ) . '</option>';
                            }
                            echo '</select>';
                        } elseif ( 'checkbox' === $display_as && in_array( $field_object['type'], ['checkbox', 'select', 'true_false'] ) ) {
                            echo '<div class="dgcpf-filter-checkboxes" role="group" aria-labelledby="filter-label-acf-' . esc_attr( $acf_field_key ) . '">';
                            echo '<span id="filter-label-acf-' . esc_attr( $acf_field_key ) . '" class="screen-reader-text">' . esc_html( $field_object['label'] ) . ' filter options</span>'; // Hidden label for accessibility
                            if ( ! empty( $field_object['choices'] ) ) {
                                foreach ( $field_object['choices'] as $value => $label ) {
                                    echo '<label><input type="checkbox" class="dgcpf-filter-checkbox" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $label ) . '"> <span>' . esc_html( $label ) . '</span></label>';
                                }
                            } elseif ( 'true_false' === $field_object['type'] ) {
                                echo '<label><input type="checkbox" class="dgcpf-filter-checkbox" value="1" aria-label="' . esc_html__( 'Yes', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'Yes', 'custom-product-filters' ) . '</span></label>';
                                echo '<label><input type="checkbox" class="dgcpf-filter-checkbox" value="0" aria-label="' . esc_html__( 'No', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'No', 'custom-product-filters' ) . '</span></label>';
                            }
                            echo '</div>';
                        } elseif ( 'radio' === $display_as && in_array( $field_object['type'], ['radio', 'select', 'true_false'] ) ) {
                            echo '<div class="dgcpf-filter-radio-buttons" role="radiogroup" aria-labelledby="filter-label-acf-' . esc_attr( $acf_field_key ) . '">';
                            echo '<span id="filter-label-acf-' . esc_attr( $acf_field_key ) . '" class="screen-reader-text">' . esc_html( $field_object['label'] ) . ' filter options</span>'; // Hidden label for accessibility
                            echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $acf_field_key ) . '" value="" checked aria-label="' . esc_html__( 'All', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'All', 'custom-product-filters' ) . '</span></label>';
                            if ( ! empty( $field_object['choices'] ) ) {
                                foreach ( $field_object['choices'] as $value => $label ) {
                                    echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $acf_field_key ) . '" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $label ) . '"> <span>' . esc_html( $label ) . '</span></label>';
                                }
                            } elseif ( 'true_false' === $field_object['type'] ) {
                                echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $acf_field_key ) . '" value="1" aria-label="' . esc_html__( 'Yes', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'Yes', 'custom-product-filters' ) . '</span></label>';
                                echo '<label><input type="radio" class="dgcpf-filter-radio" name="dgcpf_filter_acf_' . esc_attr( $acf_field_key ) . '" value="0" aria-label="' . esc_html__( 'No', 'custom-product-filters' ) . '"> <span>' . esc_html__( 'No', 'custom-product-filters' ) . '</span></label>';
                            }
                            echo '</div>';
                        } elseif ( 'text' === $display_as && 'text' === $field_object['type'] ) {
                            echo '<input type="text" class="dgcpf-filter-text-input" placeholder="' . esc_attr( $field_object['label'] ) . '" aria-label="' . esc_attr( $field_object['label'] ) . '">';
                        } elseif ( 'number' === $display_as && 'number' === $field_object['type'] ) {
                            echo '<input type="number" class="dgcpf-filter-number-input" placeholder="' . esc_attr( $field_object['label'] ) . '" aria-label="' . esc_attr( $field_object['label'] ) . '">';
                        } else {
                            // Fallback for unsupported ACF display_as combinations
                            echo '<p class="dgcpf-filter-error">' . esc_html__( 'Unsupported ACF field type or display option.', 'custom-product-filters' ) . '</p>';
                        }
                        echo '</div>'; // data-acf-field-key container
                    }
                }
                echo '</div>'; // .dgcpf-filter-group
            }
        }
        // Add Clear All Filters button
        echo '<button class="dgcpf-clear-all-filters-button elementor-button" style="display:none;">' . esc_html__( 'Clear All Filters', 'custom-product-filters' ) . '</button>';
        echo '</div>'; // .dgcpf-filters-wrapper

        // Output the loop container.
        echo '<div ' . $this->get_render_attribute_string( 'loop_container' ) . '>';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                // Add class for individual loop items for Flickity.
                echo '<div class="elementor-loop-item">';
                echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id );
                echo '</div>';
            }
        } else {
            // Retrieve "No products found" message from admin settings.
            $options = get_option('dgcpf_options', []);
            $no_products_text = isset($options['no_products_text']) ? $options['no_products_text'] : 'There are no products with that combination of tags.';
            echo '<p class="no-products-found">' . esc_html($no_products_text) . '</p>';
        }

        echo '</div>'; // .dgcpf-loop-container
        
        // Add a placeholder for the "Load More" button.
        echo '<div class="dgcpf-load-more-container">';
        if ( $settings['enable_load_more'] === 'yes' && $query->max_num_pages > 1 ) {
            echo '<button class="dgcpf-load-more-button elementor-button" data-max-pages="' . esc_attr( $query->max_num_pages ) . '">' . esc_html( $settings['load_more_button_text'] ) . '</button>';
        }
        echo '</div>';
        
        echo '</div>'; // .dgcpf-filtered-loop-widget-container

        wp_reset_postdata();
    }
}

