<?php
/**
 * Editorial pages the theme templates expect, and URL helpers.
 *
 * Templates matched by slug (page-{slug}.html):
 *   /produtos/   → templates/page-produtos.html   (Epic 3)
 *   /quem-cria/  → templates/page-quem-cria.html  (Epic 8)
 *   /capitulos/  → templates/page-capitulos.html  (Epic 9, "Livro GILDA;")
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

const GILDA_PAGES_VERSION = 1;

/**
 * Create the editorial pages once (idempotent; never overwrites edits).
 */
function gilda_ensure_pages() {
	if ( (int) get_option( 'gilda_pages_version' ) >= GILDA_PAGES_VERSION ) {
		return;
	}
	$pages = array(
		'produtos'  => __( 'Produtos;', 'gilda' ),
		'quem-cria' => __( 'Quem Cria', 'gilda' ),
		'capitulos' => __( 'Livro GILDA;', 'gilda' ),
	);
	foreach ( $pages as $slug => $title ) {
		if ( ! get_page_by_path( $slug ) ) {
			wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => $slug,
				)
			);
		}
	}
	update_option( 'gilda_pages_version', GILDA_PAGES_VERSION );
}
add_action( 'after_switch_theme', 'gilda_ensure_pages' );
add_action( 'init', 'gilda_ensure_pages', 20 );
/**
 * Send the old editorial /produtos/ landing page to the actual WooCommerce catalog.
 */
function gilda_redirect_products_landing_to_catalog() {
	if ( ! is_page( 'produtos' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$shop_url = wc_get_page_permalink( 'shop' );
	if ( $shop_url && untrailingslashit( $shop_url ) !== untrailingslashit( get_permalink() ) ) {
		wp_safe_redirect( $shop_url, 302, 'GILDA' );
		exit;
	}
}
add_action( 'template_redirect', 'gilda_redirect_products_landing_to_catalog', 1 );

/**
 * Permalink of a product by slug (falls back to the /product-page/ base).
 *
 * @param string $slug Product slug.
 * @return string
 */
function gilda_product_url( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'product' );
	return $post ? get_permalink( $post ) : home_url( '/product-page/' . $slug . '/' );
}
