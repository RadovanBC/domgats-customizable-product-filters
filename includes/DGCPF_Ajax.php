<?php
namespace DomGats\ProductFilter;

use \WP_Query;
use \Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class DGCPF_Ajax {

    public function __construct() {
        add_action('wp_ajax_dgcpf_filter_posts', [$this, 'filter_posts_handler']);
        add_action('wp_ajax_nopriv_dgcpf_filter_posts', [$this, 'filter_posts_handler']);
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

        if (!empty($settings['selected_terms_by_taxonomy'])) {
            foreach ($settings['selected_terms_by_taxonomy'] as $taxonomy => $terms) {
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

        if (!empty($settings['selected_acf_fields']) && function_exists('get_field_object')) {
            foreach ($settings['selected_acf_fields'] as $field_key => $field_value) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'product_filter_nonce')) {
            wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'custom-product-filters')]);
            return;
        }

        $settings = $this->_sanitize_input($_POST);
        
        // Override sanitization for specific fields
        if (isset($settings['posts_include_by_ids'])) $settings['posts_include_by_ids'] = array_map('intval', (array) $settings['posts_include_by_ids']);
        if (isset($settings['posts_exclude_by_ids'])) $settings['posts_exclude_by_ids'] = array_map('intval', (array) $settings['posts_exclude_by_ids']);
        if (isset($settings['terms_include'])) $settings['terms_include'] = array_map('intval', (array) $settings['terms_include']);
        if (isset($settings['terms_exclude'])) $settings['terms_exclude'] = array_map('intval', (array) $settings['terms_exclude']);
        if (isset($settings['product_categories_query'])) $settings['product_categories_query'] = array_map('intval', (array) $settings['product_categories_query']);
        if (isset($settings['product_tags_query'])) $settings['product_tags_query'] = array_map('intval', (array) $settings['product_tags_query']);
        if (isset($settings['page'])) $settings['page'] = intval($settings['page']);
        if (isset($settings['posts_per_page'])) $settings['posts_per_page'] = intval($settings['posts_per_page']);
        if (isset($settings['template_id'])) $settings['template_id'] = intval($settings['template_id']);


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
        $filters_config = $settings['filters_data'] ?? [];

        foreach ($filters_config as $filter_config) {
            $filter_type = $filter_config['filter_type'];
            $filter_key = '';
            if ($filter_type === 'taxonomy') {
                $filter_key = $filter_config['taxonomy_name'];
            } elseif ($filter_type === 'acf') {
                $filter_key = $filter_config['acf_field_key'];
            }
            if (empty($filter_key)) {
                continue;
            }

            $temp_query_args = $this->_build_query_args($settings, $filter_key, $filter_type);
            $temp_query_args['fields'] = 'ids';
            $temp_query_args['posts_per_page'] = -1;
            $temp_query_args['no_found_rows'] = true;

            $temp_query = new \WP_Query($temp_query_args);
            $post_ids = $temp_query->posts;

            if ($filter_type === 'taxonomy') {
                $taxonomy_name = $filter_config['taxonomy_name'];
                $all_terms = get_terms(['taxonomy' => $taxonomy_name, 'hide_empty' => false]);
                if (is_wp_error($all_terms)) continue;

                $available_options[$taxonomy_name] = [];
                foreach ($all_terms as $term) {
                    $available_options[$taxonomy_name][$term->slug] = ['name' => $term->name, 'count' => 0];
                }

                if (!empty($post_ids)) {
                    global $wpdb;
                    $query = $wpdb->prepare(
                        "SELECT t.slug FROM {$wpdb->terms} AS t
                         INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
                         INNER JOIN {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                         WHERE tt.taxonomy = %s AND tr.object_id IN (" . implode(',', array_map('intval', $post_ids)) . ")",
                        $taxonomy_name
                    );
                    $terms_for_posts = $wpdb->get_col($query);

                    if (!empty($terms_for_posts)) {
                        $term_counts = array_count_values($terms_for_posts);
                        foreach ($term_counts as $slug => $count) {
                            if (isset($available_options[$taxonomy_name][$slug])) {
                                $available_options[$taxonomy_name][$slug]['count'] = $count;
                            }
                        }
                    }
                }
            } elseif ($filter_type === 'acf' && function_exists('get_field_object')) {
                $acf_field_key = $filter_config['acf_field_key'];
                if (empty($acf_field_key)) continue;

                $field_object = get_field_object($acf_field_key);
                if (!$field_object) continue;

                $available_options[$acf_field_key] = ['type' => $field_object['type'], 'values' => []];
                $choices = $field_object['choices'] ?? [];

                if (in_array($field_object['type'], ['select', 'radio', 'checkbox', 'true_false'])) {
                     if ('true_false' === $field_object['type']) {
                        $choices = ['1' => esc_html__('Yes', 'custom-product-filters'), '0' => esc_html__('No', 'custom-product-filters')];
                    }

                    foreach ($choices as $value => $label) {
                        $available_options[$acf_field_key]['values'][$value] = ['name' => $label, 'count' => 0];
                    }

                    if (!empty($post_ids)) {
                        global $wpdb;
                        $meta_values = $wpdb->get_col($wpdb->prepare(
                            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN (" . implode(',', array_map('intval', $post_ids)) . ")",
                            $acf_field_key
                        ));

                        $all_values = [];
                        foreach ($meta_values as $meta_value) {
                            $unserialized_values = maybe_unserialize($meta_value);
                            if (is_array($unserialized_values)) {
                                $all_values = array_merge($all_values, $unserialized_values);
                            } else {
                                $all_values[] = $meta_value;
                            }
                        }

                        $value_counts = array_count_values($all_values);

                        foreach ($value_counts as $value => $count) {
                            if (isset($available_options[$acf_field_key]['values'][$value])) {
                                $available_options[$acf_field_key]['values'][$value]['count'] = $count;
                            }
                        }
                    }
                }
            }
        }

        return $available_options;
    }
}

new DGCPF_Ajax();