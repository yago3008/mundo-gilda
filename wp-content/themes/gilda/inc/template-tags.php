<?php
/**
 * Small render helpers.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline stroke icon (decorative: aria-hidden). Label the parent control.
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 * @return string
 */
function gilda_icon( $name, $size = 22 ) {
	$paths = array(
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
		'bag'       => '<path d="M5 8h14l-1 13H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
		'eye'       => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'close'     => '<path d="M6 6l12 12M18 6L6 18"/>',
		'chevron-l' => '<path d="M15 5l-7 7 7 7"/>',
		'chevron-r' => '<path d="M9 5l7 7-7 7"/>',
		'filter'    => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'whatsapp'  => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"/>',
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
		'x'         => '<path d="M4 4l16 16M20 4L4 20"/>',
		'pinterest' => '<circle cx="12" cy="12" r="9"/><path d="M11 8c3-1 5 1 4 4s-3 3-4 2l-2 7"/>',
		'truck'     => '<path d="M2 6h12v10H2z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
		'lock'      => '<rect x="5" y="11" width="14" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="gilda-icon gilda-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		(int) $size,
		$paths[ $name ] // Static, trusted markup.
	);
}

/**
 * Number of items in the WooCommerce cart (0 without WooCommerce).
 *
 * @return int
 */
function gilda_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * Header cart link markup (task 1.5). Also used as a WooCommerce fragment.
 *
 * @return string
 */
function gilda_cart_link_html() {
	$count = gilda_cart_count();
	$url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrinho/' );
	/* translators: %d: number of items in the cart. */
	$label = sprintf( _n( 'Carrinho com %d item', 'Carrinho com %d itens', $count, 'gilda' ), $count );

	return sprintf(
		'<a class="gilda-cart-link" href="%1$s" aria-label="%2$s" data-count="%3$d">%4$s<span class="gilda-cart-count" aria-hidden="true"%5$s>%3$d</span></a>',
		esc_url( $url ),
		esc_attr( $label ),
		$count,
		gilda_icon( 'bag' ),
		$count > 0 ? '' : ' hidden'
	);
}
