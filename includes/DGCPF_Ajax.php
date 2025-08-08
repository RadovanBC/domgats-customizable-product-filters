<?php
namespace DomGats\ProductFilter;

use \WP_Query;
use \Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class DGCPF_Ajax
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('wp_ajax_filter_products_by_tag', [$this, 'filter_products_handler']);
        add_action('wp_ajax_nopriv_filter_products_by_tag', [$this, 'filter_products_handler']);
    }

    /**
     * The main AJAX handler for filtering products based on widget settings.
     * This function now accepts a more comprehensive set of parameters from the
     * new Elementor widget, including the template ID, filter repeater data,
     * and query logic.
     */
    public function filter_products_handler()
    {
        // Verify nonce for security.
        check_ajax_referer('product_filter_nonce', 'nonce');

        // Sanitize all POST data received from the frontend.
        $template_id   = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $post_type     = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'product';
        $filters_data  = isset($_POST['filters_data']) && is_array($_POST['filters_data']) ? wp_unslash($_POST['filters_data']) : []; // Unslash to handle escaped quotes
        $filter_logic  = isset($_POST['filter_logic']) ? sanitize_text_field($_POST['filter_logic']) : 'AND';
        
        // New query parameters
        $posts_include_by_ids = isset($_POST['posts_include_by_ids']) ? array_map('intval', explode(',', sanitize_text_field($_POST['posts_include_by_ids']))) : [];
        $posts_exclude_by_ids = isset($_POST['posts_exclude_by_ids']) ? array_map('intval', explode(',', sanitize_text_field($_POST['posts_exclude_by_ids']))) : [];
        $terms_include        = isset($_POST['terms_include']) ? array_map('sanitize_title', explode(',', sanitize_text_field($_POST['terms_include']))) : [];
        $terms_exclude        = isset($_POST['terms_exclude']) ? array_map('sanitize_title', explode(',', sanitize_text_field($_POST['terms_exclude']))) : [];
        $post_status          = isset($_POST['post_status']) && is_array($_POST['post_status']) ? array_map('sanitize_text_field', $_POST['post_status']) : ['publish'];
        $orderby              = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
        $order                = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        // Filter selections from frontend
        $selected_terms_by_taxonomy = isset($_POST['selected_terms_by_taxonomy']) && is_array($_POST['selected_terms_by_taxonomy']) ? wp_unslash($_POST['selected_terms_by_taxonomy']) : [];
        $selected_acf_fields        = isset($_POST['selected_acf_fields']) && is_array($_POST['selected_acf_fields']) ? wp_unslash($_POST['selected_acf_fields']) : [];

        $page          = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 9;

        if (empty($template_id)) {
            wp_send_json_error(['message' => 'Template ID is missing.']);
        }

        // Prepare WP_Query arguments.
        $args = [
            'post_type'      => $post_type,
            'post_status'    => $post_status,
            'posts_per_page' => $posts_per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
            'tax_query'      => [], // Initialize tax_query
            'meta_query'     => [], // Initialize meta_query for ACF
        ];

        // Apply include/exclude posts
        if ( ! empty( $posts_include_by_ids ) ) {
            $args['post__in'] = $posts_include_by_ids;
        }
        if ( ! empty( $posts_exclude_by_ids ) ) {
            $args['post__not_in'] = $posts_exclude_by_ids;
        }

        // Apply include/exclude terms (from query controls)
        if ( ! empty( $terms_include ) || ! empty( $terms_exclude ) ) {
            $args['tax_query']['relation'] = 'AND'; // Default relation for these static terms
            $taxonomies = get_object_taxonomies( $post_type, 'names' );

            if ( ! empty( $terms_include ) ) {
                if ( ! empty( $taxonomies ) ) {
                    $args['tax_query'][] = [
                        'taxonomy' => $taxonomies[0], // Use the first available taxonomy as a fallback
                        'field'    => 'slug',
                        'terms'    => $terms_include,
                        'operator' => 'IN',
                    ];
                }
            }
            if ( ! empty( $terms_exclude ) ) {
                if ( ! empty( $taxonomies ) ) {
                    $args['tax_query'][] = [
                        'taxonomy' => $taxonomies[0], // Use the first available taxonomy as a fallback
                        'field'    => 'slug',
                        'terms'    => $terms_exclude,
                        'operator' => 'NOT IN',
                    ];
                }
            }
        }


        // Apply filter logic to the main tax_query relation if filters exist.
        if (!empty($filters_data)) {
            $tax_queries_from_filters = [];
            $meta_queries_from_filters = [];

            foreach ($filters_data as $filter_config) {
                $filter_type = sanitize_text_field($filter_config['filter_type']);
                
                if ('taxonomy' === $filter_type) {
                    $taxonomy_name = sanitize_text_field($filter_config['taxonomy_name']);
                    
                    if (!empty($taxonomy_name) && isset($selected_terms_by_taxonomy[$taxonomy_name]) && !empty($selected_terms_by_taxonomy[$taxonomy_name])) {
                        $tax_queries_from_filters[] = [
                            'taxonomy' => $taxonomy_name,
                            'field'    => 'slug',
                            'terms'    => $selected_terms_by_taxonomy[$taxonomy_name],
                            'operator' => 'IN',
                        ];
                    }
                } elseif ('acf' === $filter_type) {
                    $acf_field_key = sanitize_text_field($filter_config['acf_field_key']);
                    if (!empty($acf_field_key) && isset($selected_acf_fields[$acf_field_key]) && !empty($selected_acf_fields[$acf_field_key])) {
                        $field_value = $selected_acf_fields[$acf_field_key];
                        $field_object = function_exists('get_field_object') ? get_field_object($acf_field_key) : false;

                        if ($field_object) {
                            $meta_query_item = [
                                'key' => $acf_field_key,
                            ];

                            // Handle different ACF field types and their comparisons
                            switch ($field_object['type']) {
                                case 'text':
                                    $meta_query_item['value'] = $field_value;
                                    $meta_query_item['compare'] = 'LIKE';
                                    break;
                                case 'number':
                                    $meta_query_item['value'] = floatval($field_value);
                                    $meta_query_item['type'] = 'NUMERIC';
                                    $meta_query_item['compare'] = '=';
                                    break;
                                case 'select':
                                case 'radio':
                                case 'true_false':
                                    $meta_query_item['value'] = $field_value;
                                    $meta_query_item['compare'] = '=';
                                    break;
                                case 'checkbox':
                                    if ( is_array( $field_value ) ) {
                                        $meta_query_item['relation'] = 'AND';
                                        foreach ( $field_value as $value ) {
                                            $meta_query_item[] = [
                                                'key'     => $acf_field_key,
                                                'value'   => '"' . $value . '"',
                                                'compare' => 'LIKE',
                                            ];
                                        }
                                    } else {
                                        $meta_query_item['value']   = '"' . $field_value . '"';
                                        $meta_query_item['compare'] = 'LIKE';
                                    }
                                    break;
                                default:
                                    $meta_query_item['value'] = $field_value;
                                    $meta_query_item['compare'] = '=';
                                    break;
                            }
                            $meta_queries_from_filters[] = $meta_query_item;
                        }
                    }
                }
            }

            // Combine tax queries from controls and filters
            if (!empty($tax_queries_from_filters)) {
                if (!isset($args['tax_query']['relation'])) {
                    $args['tax_query']['relation'] = 'AND';
                }
                $args['tax_query'] = array_merge($args['tax_query'], $tax_queries_from_filters);
            }

            // Combine meta queries from filters
            if (!empty($meta_queries_from_filters)) {
                $args['meta_query']['relation'] = $filter_logic;
                $args['meta_query'] = array_merge($args['meta_query'], $meta_queries_from_filters);
            }
        }

        add_filter('elementor/frontend/builder_content_data/should_print_css', '__return_false');

        $query = new WP_Query($args);

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (class_exists('\Elementor\Plugin')) {
                    echo Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                }
            }
        } else {
            $options = get_option('dgcpf_options', []);
            $no_products_text = isset($options['no_products_text']) ? $options['no_products_text'] : 'There are no products with that combination of tags.';
            echo '<p class="no-products-found">' . esc_html($no_products_text) . '</p>';
        }
        $html = ob_get_clean();
        wp_reset_postdata();

        $available_filter_options = $this->get_available_filter_options_for_query($args, $filters_data);

        wp_send_json_success([
            'html'             => $html,
            'max_pages'        => $query->max_num_pages,
            'available_filter_options'  => $available_filter_options,
        ]);
    }

    /**
     * Helper function to get available terms and ACF field values for filtering
     * based on the current query.
     * This is crucial for dynamic filter counts and filter dependencies.
     *
     * @param array $current_query_args The arguments used for the current product query.
     * @param array $filters_config     The configuration of filters from the widget settings.
     * @return array An associative array of taxonomies/ACF fields and their available terms/values.
     */
    private function get_available_filter_options_for_query($current_query_args, $filters_config) {
        $available_options = [];

        // Generate a unique transient key based on the current query arguments and filter configuration.
        // This ensures different filter combinations have different cached results.
        $cache_key_data = [
            'query_args' => $current_query_args,
            'filters_config' => $filters_config,
        ];
        $transient_key = 'dgcpf_available_filters_' . md5( serialize( $cache_key_data ) );

        // Try to get data from transient cache.
        $cached_data = get_transient( $transient_key );
        if ( false !== $cached_data ) {
            return $cached_data;
        }

        // Temporarily remove pagination and set fields to 'ids' for efficiency.
        $temp_query_args = $current_query_args;
        $temp_query_args['fields'] = 'ids';
        unset($temp_query_args['paged']);
        unset($temp_query_args['posts_per_page']);
        $temp_query_args['no_found_rows'] = true; // Optimize for counting, don't calculate max_num_pages

        // Loop through each filter configured in the widget.
        foreach ($filters_config as $filter_config) {
            $filter_type = sanitize_text_field($filter_config['filter_type']);
            
            if ('taxonomy' === $filter_type) {
                $taxonomy_name = sanitize_text_field($filter_config['taxonomy_name']);
                if (empty($taxonomy_name) || !taxonomy_exists($taxonomy_name)) {
                    continue;
                }

                // Get all terms for this taxonomy (even empty ones, to show all options)
                $all_terms = get_terms( [
                    'taxonomy'   => $taxonomy_name,
                    'hide_empty' => false, // Get all terms to display, then filter by count
                ] );

                if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {
                    $available_options[$taxonomy_name] = [];
                    foreach ( $all_terms as $term ) {
                        $count_args = $current_query_args; // Start with the full current query
                        $count_args['fields'] = 'ids';
                        unset($count_args['paged']);
                        unset($count_args['posts_per_page']);
                        $count_args['no_found_rows'] = true; // Optimize for counting

                        // Ensure tax_query exists and has a relation
                        if (!isset($count_args['tax_query']) || !isset($count_args['tax_query']['relation'])) {
                            $count_args['tax_query']['relation'] = 'AND';
                        }
                        
                        // Add the current term to the tax_query for counting
                        $count_args['tax_query'][] = [
                            'taxonomy' => $taxonomy_name,
                            'field'    => 'slug',
                            'terms'    => [$term->slug],
                            'operator' => 'IN',
                        ];
                        
                        $count_query = new WP_Query($count_args);
                        $term_count = $count_query->found_posts;
                        wp_reset_postdata(); // Reset postdata after each count query to free memory

                        $available_options[$taxonomy_name][$term->slug] = [
                            'name'  => $term->name,
                            'count' => $term_count,
                        ];
                    }
                }

            } elseif ('acf' === $filter_type && function_exists('get_field_object')) {
                $acf_field_key = sanitize_text_field($filter_config['acf_field_key']);
                if (empty($acf_field_key)) {
                    continue;
                }

                $field_object = get_field_object($acf_field_key);
                if (!$field_object) {
                    continue;
                }

                $available_options[$acf_field_key] = [
                    'type' => $field_object['type'],
                    'values' => [],
                ];

                // For select, radio, checkbox, true_false fields, get possible choices/values
                if (in_array($field_object['type'], ['select', 'radio', 'checkbox', 'true_false'])) {
                    $choices = [];
                    if (!empty($field_object['choices'])) {
                        $choices = $field_object['choices'];
                    } elseif ('true_false' === $field_object['type']) {
                        $choices = ['1' => esc_html__('Yes', 'custom-product-filters'), '0' => esc_html__('No', 'custom-product-filters')];
                    }

                    foreach ($choices as $value => $label) {
                        $count_args = $current_query_args; // Start with the full current query
                        $count_args['fields'] = 'ids';
                        unset($count_args['paged']);
                        unset($count_args['posts_per_page']);
                        $count_args['no_found_rows'] = true; // Optimize for counting

                        if (!isset($count_args['meta_query']) || !isset($count_args['meta_query']['relation'])) {
                            $count_args['meta_query']['relation'] = 'AND';
                        }
                        
                        $meta_compare_op = '=';
                        // ACF checkbox values can be stored as serialized arrays in meta_value
                        if ('checkbox' === $field_object['type'] && is_array($value)) { // Check if value itself is an array (multi-select ACF)
                            $meta_compare_op = 'LIKE';
                            $value_for_query = serialize(strval($value[0])); // Assuming we're checking for existence of one selected value
                        } elseif ( 'checkbox' === $field_object['type'] && !is_array($value) ) {
                            $meta_compare_op = 'LIKE';
                            $value_for_query = serialize(strval($value));
                        } else {
                            $value_for_query = $value;
                        }

                        $count_args['meta_query'][] = [
                            'key'     => $acf_field_key,
                            'value'   => $value_for_query,
                            'compare' => $meta_compare_op,
                            'type'    => ('number' === $field_object['type']) ? 'NUMERIC' : 'CHAR',
                        ];
                        
                        $count_query = new WP_Query($count_args);
                        $value_count = $count_query->found_posts;
                        wp_reset_postdata(); // Reset postdata after each count query to free memory

                        $available_options[$acf_field_key]['values'][$value] = [
                            'name' => $label,
                            'count' => $value_count,
                        ];
                    }
                }
                // Text and number inputs don't have discrete options to enable/disable or count in this way.
            }
        }

        // Set the calculated data to transient cache.
        set_transient( $transient_key, $available_options, HOUR_IN_SECONDS ); // Cache for 1 hour

        return $available_options;
    }
}
