<?php
/**
 * Plugin Name:       BCM - Elementor Staging License Fix
 * Plugin URI:        https://blackchalkmarketing.com.au/
 * Description:       Ensures the Elementor Pro license remains active on Kinsta staging environments by reporting the live domain during license checks.
 * Version:           1.0
 * Author:            Black Chalk Marketing
 * Author URI:        https://blackchalkmarketing.com.au/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * * IMPORTANT: This plugin should ONLY be used on a staging or development site.
 * Deactivate and remove it before pushing the database to your live site.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Filters the site URL that Elementor uses for license validation.
 *
 * This function intercepts Elementor's license check and replaces the
 * current staging site URL with the live production URL. This makes
 * Elementor's servers believe the check is coming from the licensed domain,
 * keeping the Pro features active on staging.
 *
 * @param string $site_url The original site URL that Elementor is checking.
 * @return string The modified site URL to report to Elementor's servers.
 */
add_filter( 'elementor/license/get_site_url', function( $site_url ) {
    
    // ====================================================================
    // IMPORTANT: REPLACE THIS URL WITH YOUR ACTUAL LIVE WEBSITE DOMAIN
    // Make sure to include https:// and do NOT include a slash at the end.
    // ====================================================================
    
    return 'https://ahhmaa.com.au/';

} );
