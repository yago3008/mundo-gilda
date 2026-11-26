<?php
/**
 * GILDA; theme bootstrap.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

define( 'GILDA_VERSION', '1.0.0' );
define( 'GILDA_DIR', get_template_directory() );
define( 'GILDA_URI', get_template_directory_uri() );

require GILDA_DIR . '/inc/setup.php';
require GILDA_DIR . '/inc/template-tags.php';
require GILDA_DIR . '/inc/shortcodes.php';
require GILDA_DIR . '/inc/pages.php';
require GILDA_DIR . '/inc/images.php';
require GILDA_DIR . '/inc/book-chapters.php';
require GILDA_DIR . '/inc/transitions.php';

if ( class_exists( 'WooCommerce' ) ) {
	require GILDA_DIR . '/inc/woocommerce.php';
	require GILDA_DIR . '/inc/shipping.php';
	require GILDA_DIR . '/inc/filters.php';
}
