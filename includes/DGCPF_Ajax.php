<?php
namespace DomGats\ProductFilter;

use \WP_Query;
use \Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class DGCPF_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_filter_products_by_tag', [$this, 'filter_products_handler']);
        add_action('wp_ajax_nopriv_filter_products_by_tag', [$this, 'filter_products_handler']);
    }

    public function filter_products_handler()
    {
        check_ajax_referer('product_filter_nonce', 'nonce');

        $template_id   = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $post_type     = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'product';
        $filters_data  = isset($_POST['filters_data']) && is_array($_POST['filters_data']) ? wp_unslash($_POST['filters_data']) : [];
        $filter_logic  = isset($_POST['filter_logic']) ? sanitize_text_field($_POST['filter_logic']) : 'AND';
        
        $posts_include_by_ids = isset($_POST['posts_include_by_ids']) ? array_map('intval', $_POST['posts_include_by_ids']) : [];
        $posts_exclude_by_ids = isset($_POST['posts_exclude_by_ids']) ? array_map('intval', $_POST['posts_exclude_by_ids']) : [];
        $terms_include        = isset($_POST['terms_include']) ? array_map('intval', $_POST['terms_include']) : [];
        $terms_exclude        = isset($_POST['terms_exclude']) ? array_map('intval', $_POST['terms_exclude']) : [];
        $product_categories_query = isset($_POST['product_categories_query']) ? array_map('intval', $_POST['product_categories_query']) : [];
        $product_tags_query = isset($_POST['product_tags_query']) ? array_map('intval', $_POST['product_tags_query']) : [];
        $acf_meta_query_repeater = isset($_POST['acf_meta_query_repeater']) && is_array($_POST['acf_meta_query_repeater']) ? wp_unslash($_POST['acf_meta_query_repeater']) : [];
        
        $post_status          = isset($_POST['post_status']) && is_array($_POST['post_status']) ? array_map('sanitize_text_field', $_POST['post_status']) : ['publish'];
        $orderby              = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
        $order                = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        $selected_terms_by_taxonomy = isset($_POST['selected_terms_by_taxonomy']) && is_array($_POST['selected_terms_by_taxonomy']) ? wp_unslash($_POST['selected_terms_by_taxonomy']) : [];
        $selected_acf_fields        = isset($_POST['selected_acf_fields']) && is_array($_POST['selected_acf_fields']) ? wp_unslash($_POST['selected_acf_fields']) : [];

        $page          = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 9;

        if (empty($template_id)) {
            wp_send_json_error(['message' => 'Template ID is missing.']);
        }

        $args = [
            'post_type' => $post_type, 'post_status' => $post_status,
            'posts_per_page' => $posts_per_page, 'paged' => $page,
            'orderby' => $orderby, 'order' => $order,
            'tax_query' => ['relation' => 'AND'], 'meta_query' => ['relation' => 'AND'],
        ];

        if ( ! empty( $posts_include_by_ids ) ) $args['post__in'] = $posts_include_by_ids;
        if ( ! empty( $posts_exclude_by_ids ) ) $args['post__not_in'] = $posts_exclude_by_ids;

        if ( ! empty( $terms_include ) ) $args['tax_query'][] = [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $terms_include, 'operator' => 'IN' ];
        if ( ! empty( $terms_exclude ) ) $args['tax_query'][] = [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $terms_exclude, 'operator' => 'NOT IN' ];
        if ( ! empty( $product_categories_query ) ) $args['tax_query'][] = [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $product_categories_query, 'operator' => 'IN' ];
        if ( ! empty( $product_tags_query ) ) $args['tax_query'][] = [ 'taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => $product_tags_query, 'operator' => 'IN' ];

        if ( ! empty( $acf_meta_query_repeater ) && function_exists( 'get_field_object' ) ) {
            foreach ( $acf_meta_query_repeater as $meta_item ) {
                if ( ! empty( $meta_item['acf_meta_key'] ) && ! empty( $meta_item['acf_meta_value'] ) ) {
                    $args['meta_query'][] = [ 'key' => $meta_item['acf_meta_key'], 'value' => $meta_item['acf_meta_value'], 'compare' => $meta_item['acf_meta_compare'] ];
                }
            }
        }

        $tax_queries_from_filters = [];
        $meta_queries_from_filters = [];

        if (!empty($selected_terms_by_taxonomy)) {
            $tax_queries_from_filters['relation'] = $filter_logic;
            foreach ($selected_terms_by_taxonomy as $taxonomy => $terms) {
                if (!empty($terms)) {
                    $tax_queries_from_filters[] = [ 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN' ];
                }
            }
            if(count($tax_queries_from_filters) > 1) $args['tax_query'][] = $tax_queries_from_filters;
        }

        if (!empty($selected_acf_fields)) {
            $meta_queries_from_filters['relation'] = $filter_logic;
            foreach ($selected_acf_fields as $field_key => $field_value) {
                if ($field_value !== '' && $field_value !== null) {
                    $field_object = function_exists('get_field_object') ? get_field_object($field_key) : false;
                    if ($field_object) {
                        $compare = 'LIKE';
                        $type = 'CHAR';
                        if ($field_object['type'] === 'number') {
                            $compare = '=';
                            $type = 'NUMERIC';
                        }
                        if (is_array($field_value)) { // Checkbox
                            foreach($field_value as $value_item) {
                                $meta_queries_from_filters[] = [ 'key' => $field_key, 'value' => '"' . $value_item . '"', 'compare' => 'LIKE' ];
                            }
                        } else {
                            $meta_queries_from_filters[] = [ 'key' => $field_key, 'value' => $field_value, 'compare' => $compare, 'type' => $type ];
                        }
                    }
                }
            }
            if(count($meta_queries_from_filters) > 1) $args['meta_query'][] = $meta_queries_from_filters;
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
            echo '<p class="no-products-found">' . esc_html__('No products found.', 'custom-product-filters') . '</p>';
        }
        $html = ob_get_clean();
        wp_reset_postdata();

        $available_filter_options = [];

        wp_send_json_success([
            'html' => $html,
            'max_pages' => $query->max_num_pages,
            'available_filter_options' => $available_filter_options,
        ]);
    }
}
