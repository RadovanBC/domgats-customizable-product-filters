<?php
namespace DomGats\ProductFilter;

use \WP_Query;
use \Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax {

    public function __construct() {
        add_action('wp_ajax_dgcpf_filter_posts', [$this, 'filter_posts_handler']);
        add_action('wp_ajax_nopriv_dgcpf_filter_posts', [$this, 'filter_posts_handler']);
        add_action('wp_ajax_dgcpf_get_acf_field_choices', [$this, 'get_acf_field_choices_handler']);
    }

    public function get_acf_field_choices_handler() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'custom-product-filters' ) ] );
            return;
        }

        $field_key = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
        $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

        if ( empty( $field_key ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'ACF field key is missing.', 'custom-product-filters' ) ] );
            return;
        }

        if ( ! function_exists( 'get_field_object' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'ACF is not active.', 'custom-product-filters' ) ] );
            return;
        }

        $field_object = get_field_object( $field_key );
        $options = [];

        if ( $field_object ) {
            // For fields with predefined choices (select, radio, checkbox, true_false)
            if ( in_array( $field_object['type'], [ 'select', 'radio', 'checkbox', 'true_false' ] ) ) {
                $choices = $field_object['choices'] ?? [];
                if ( 'true_false' === $field_object['type'] ) {
                    $choices = [ '1' => esc_html__( 'Yes', 'custom-product-filters' ), '0' => esc_html__( 'No', 'custom-product-filters' ) ];
                }
                foreach ( $choices as $value => $label ) {
                    $options[] = [ 'id' => $value, 'text' => $label ];
                }
            } else {
                // For fields without predefined choices (e.g., text, number)
                global $wpdb;
                $unique_values = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'",
                    $field_key,
                    $post_type
                ) );

                foreach ( $unique_values as $value ) {
                    // Handle serialized data if necessary (e.g., for checkbox fields saved as serialized arrays)
                    $unserialized_value = maybe_unserialize( $value );
                    if ( is_array( $unserialized_value ) ) {
                        foreach ( $unserialized_value as $sub_value ) {
                            $options[] = [ 'id' => $sub_value, 'text' => $sub_value ];
                        }
                    } else {
                        $options[] = [ 'id' => $value, 'text' => $value ];
                    }
                }
                // Sort options alphabetically
                usort($options, function($a, $b) {
                    return strcmp($a['text'], $b['text']);
                });
            }
        } else {
            wp_send_json_error( [ 'message' => esc_html__( 'ACF field not found.', 'custom-product-filters' ) ] );
            return;
        }

        wp_send_json_success( [ 'results' => $options ] );
    }

    private function _sanitize_input($data) {
        if (is_array($data)) {
            return array_map([$this, '_sanitize_input'], $data);
        }
        return sanitize_text_field(wp_unslash($data));
    }

    private function _build_query_args($settings, $exclude_filter_key = null, $exclude_filter_type = null) {
        $args = [
            'post_type'      => $settings['post_type'] ?? 'product',
            'post_status'    => $settings['post_status'] ?? ['publish'],
            'posts_per_page' => $settings['posts_per_page'] ?? 9,
            'paged'          => $settings['page'] ?? 1,
            'orderby'        => $settings['orderby'] ?? 'date',
            'order'          => $settings['order'] ?? 'DESC',
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
        ];

        // Base query conditions from Elementor controls
        if (!empty($settings['posts_include_by_ids'])) $args['post__in'] = $settings['posts_include_by_ids'];
        if (!empty($settings['posts_exclude_by_ids'])) $args['post__not_in'] = $settings['posts_exclude_by_ids'];
        if (!empty($settings['product_categories_query'])) $args['tax_query'][] = ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $settings['product_categories_query']];
        if (!empty($settings['product_tags_query'])) $args['tax_query'][] = ['taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => $settings['product_tags_query']];

        // ACF Meta Query from Elementor controls
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

        // Live filter selections
        $filter_logic = $settings['filter_logic'] ?? 'AND';
        $tax_queries_from_filters = ['relation' => $filter_logic];
        $meta_queries_from_filters = ['relation' => $filter_logic];

        if (!empty($settings['taxonomies'])) {
            foreach ($settings['taxonomies'] as $taxonomy => $terms) {
                if ($exclude_filter_type === 'taxonomy' && $exclude_filter_key === $taxonomy) {
                    continue;
                }
                if (!empty($terms)) {
                    $tax_queries_from_filters[] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    ];
                }
            }
            if (count($tax_queries_from_filters) > 1) {
                $args['tax_query'][] = $tax_queries_from_filters;
            }
        }

        if (!empty($settings['acf_fields']) && function_exists('get_field_object')) {
            foreach ($settings['acf_fields'] as $field_key => $field_value) {
                if ($exclude_filter_type === 'acf' && $exclude_filter_key === $field_key) {
                    continue;
                }
                if ($field_value !== '' && $field_value !== null && !empty($field_value)) {
                    $field_object = get_field_object($field_key);
                    if ($field_object) {
                        $type = 'CHAR';
                        if (in_array($field_object['type'], ['number', 'range'])) {
                            $type = 'NUMERIC';
                        }

                        $compare = '=';
                        if (in_array($field_object['type'], ['text', 'textarea', 'wysiwyg', 'email', 'url', 'password'])) {
                            $compare = 'LIKE';
                        }

                        if (is_array($field_value)) { // Checkbox
                            $checkbox_group = ['relation' => $filter_logic];
                            foreach ($field_value as $value_item) {
                                $checkbox_group[] = ['key' => $field_key, 'value' => $value_item, 'compare' => 'LIKE'];
                            }
                            if(count($checkbox_group) > 1) $meta_queries_from_filters[] = $checkbox_group;
                        } else {
                            $meta_queries_from_filters[] = ['key' => $field_key, 'value' => $field_value, 'compare' => $compare, 'type' => $type];
                        }
                    }
                }
            }
            if (count($meta_queries_from_filters) > 1) {
                $args['meta_query'][] = $meta_queries_from_filters;
            }
        }

        return $args;
    }

    public function filter_posts_handler() {
        $widget_id = isset($_POST['widget_id']) ? sanitize_text_field($_POST['widget_id']) : '';
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dgcpf_filter_posts_' . $widget_id)) {
            wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'custom-product-filters')]);
            return;
        }

        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : [];
        $taxonomies = isset($_POST['taxonomies']) ? $this->_sanitize_input($_POST['taxonomies']) : [];
        $acf_fields = isset($_POST['acf_fields']) ? $this->_sanitize_input($_POST['acf_fields']) : [];
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

        $settings['taxonomies'] = $taxonomies;
        $settings['acf_fields'] = $acf_fields;
        $settings['page'] = $page;
        
        $template_id = $settings['template_id'] ?? 0;

        if (empty($template_id)) {
            wp_send_json_error(['message' => esc_html__('Template ID is missing.', 'custom-product-filters')]);
            return;
        }

        $args = $this->_build_query_args($settings);
        $query = new \WP_Query($args);

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (class_exists('\Elementor\Plugin')) {
                    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                }
            }
        } else {
            echo '<p class="no-products-found">' . esc_html__('No products found matching your selection.', 'custom-product-filters') . '</p>';
        }
        $html = ob_get_clean();
        wp_reset_postdata();

        $available_filter_options = $this->get_available_filter_options($settings);

        wp_send_json_success([
            'html' => $html,
            'max_pages' => $query->max_num_pages,
            'available_filter_options' => $available_filter_options,
        ]);
    }

    private function get_available_filter_options($settings) {
        $available_options = [];
        $filters_config = $settings['filters_repeater'] ?? [];

        foreach ($filters_config as $filter_config) {
            $filter_type = $filter_config['filter_type'];

            if ($filter_type === 'taxonomy') {
                $taxonomy_name = $filter_config['taxonomy_name'];
                if (empty($taxonomy_name)) continue;

                $available_options[$taxonomy_name] = [];
                $terms = get_terms(['taxonomy' => $taxonomy_name, 'hide_empty' => false]);

                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $current_filter_args = $this->_build_query_args($settings, $taxonomy_name, 'taxonomy');
                        $current_filter_args['posts_per_page'] = -1;
                        $current_filter_args['fields'] = 'ids';

                        $temp_args = $current_filter_args;
                        $temp_args['tax_query'][] = ['taxonomy' => $taxonomy_name, 'field' => 'slug', 'terms' => $term->slug];
                        
                        $query = new \WP_Query($temp_args);
                        $available_options[$taxonomy_name][$term->slug] = [
                            'count' => $query->found_posts,
                            'label' => $term->name,
                        ];
                    }
                }
            } elseif ($filter_type === 'acf' && function_exists('get_field_object')) {
                $acf_field_key = $filter_config['acf_field_key'];
                if (empty($acf_field_key)) continue;

                $field_object = get_field_object($acf_field_key);
                if (!$field_object) continue;

                $available_options[$acf_field_key] = [];
                $choices = $field_object['choices'] ?? [];

                if ('true_false' === $field_object['type']) {
                    $choices = ['1' => esc_html__('Yes', 'custom-product-filters'), '0' => esc_html__('No', 'custom-product-filters')];
                }

                if (!empty($choices)) {
                    foreach ($choices as $value => $label) {
                        $current_filter_args = $this->_build_query_args($settings, $acf_field_key, 'acf');
                        $current_filter_args['posts_per_page'] = -1;
                        $current_filter_args['fields'] = 'ids';

                        $temp_args = $current_filter_args;
                        $temp_args['meta_query'][] = ['key' => $acf_field_key, 'value' => $value, 'compare' => '='];
                        
                        $query = new \WP_Query($temp_args);
                        $available_options[$acf_field_key][$value] = [
                            'count' => $query->found_posts,
                            'label' => $label,
                        ];
                    }
                }
            }
        }

        return $available_options;
    }
}
