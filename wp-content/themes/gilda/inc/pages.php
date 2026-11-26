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
 * Permalink of a product by slug (falls back to the /product-page/ base).
 *
 * @param string $slug Product slug.
 * @return string
 */
function gilda_product_url( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'product' );
	return $post ? get_permalink( $post ) : home_url( '/product-page/' . $slug . '/' );
}
