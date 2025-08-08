<?php
/**
 * Template for displaying the filter UI.
 *
 * Available variables:
 * @var array $tags List of WP_Term objects.
 * @var string $filter_type The type of filter ('products' or 'addons').
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Get the custom text from the new settings page.
$options        = get_option( 'dgcpf_options', [] );
$filter_by_text = isset( $options['filter_by_text'] ) ? $options['filter_by_text'] : 'Filter by:';

?>
<div id="<?php echo esc_attr( $filter_type ); ?>-filter-container" class="ahh-maa-filters" data-filter-type="<?php echo esc_attr( $filter_type ); ?>">
    <div class="selected-tags-area"></div>
    <div class="filter-button-area">
        <a href="#" class="clear-all-filters" style="display: none;">Clear All</a>
        <a href="#" class="filter-toggle-button elementor-button">
            Filters
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </a>
    </div>
    <div class="filter-dropdown" style="display: none;">
        <p class="filter-by-label"><?php echo esc_html( $filter_by_text ); ?></p>
        <ul class="filter-tag-list">
            <?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
                <?php foreach ( $tags as $tag ) : ?>
                    <li data-slug="<?php echo esc_attr( $tag->slug ); ?>">
                        <?php echo esc_html( $tag->name ); ?>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li>No tags found.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
