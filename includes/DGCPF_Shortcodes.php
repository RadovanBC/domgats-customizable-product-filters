<?php
namespace DomGats\ProductFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DGCPF_Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'product_tag_filter', [ $this, 'render_product_tag_filter' ] );
		add_shortcode( 'add_ons_tag_filter', [ $this, 'render_add_ons_tag_filter' ] );
	}

	/**
	 * Renders the main product tag filter.
	 */
	public function render_product_tag_filter() {
		// --- PERFORMANCE: Use Transients to cache the tag query ---
		$transient_key = 'dgcpf_product_tags_cache';
		$all_tags      = get_transient( $transient_key );

		if ( false === $all_tags ) {
			// Data is not in the cache, so run the database query.
			$valid_product_ids = get_posts( [
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [
					[
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => 'add-ons',
						'operator' => 'NOT IN',
					],
				],
			] );

			$all_tags = get_terms( [
				'taxonomy'   => 'product_tag',
				'hide_empty' => true,
				'object_ids' => ! empty( $valid_product_ids ) ? $valid_product_ids : [ 0 ],
			] );

			// Save the query result to the cache for 1 hour.
			set_transient( $transient_key, $all_tags, HOUR_IN_SECONDS );
		}
		// --- End of Performance Improvement ---\

		return $this->render_filter_html( $all_tags, 'products' );
	}

	/**
	 * Renders the add-ons tag filter.
	 */
	public function render_add_ons_tag_filter() {
		// --- PERFORMANCE: Use Transients to cache the tag query ---
		$transient_key = 'dgcpf_addons_tags_cache';
		$all_tags      = get_transient( $transient_key );

		if ( false === $all_tags ) {
			// Data is not in the cache, so run the database query.
			$valid_product_ids = get_posts( [
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [
					[
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => 'add-ons',
						'operator' => 'IN',
					],
				],
			] );

			$all_tags = get_terms( [
				'taxonomy'   => 'product_tag',
				'hide_empty' => true,
				'object_ids' => ! empty( $valid_product_ids ) ? $valid_product_ids : [ 0 ],
			] );

			// Save the query result to the cache for 1 hour.
			set_transient( $transient_key, $all_tags, HOUR_IN_SECONDS );
		}
		// --- End of Performance Improvement ---

		return $this->render_filter_html( $all_tags, 'addons' );
	}

	/**
	 * Renders the filter HTML by loading a template file.
	 */
	private function render_filter_html( $tags, $filter_type ) {
		ob_start();
		include DGCPF_PLUGIN_DIR . 'templates/filter-ui.php';
		return ob_get_clean();
	}
}
