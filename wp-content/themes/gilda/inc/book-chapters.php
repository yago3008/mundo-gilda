<?php
/**
 * Capítulos do Livro GILDA;: vêm do painel (Capítulos, mu-plugin gilda-painel), não do tema.
 * Usado pela história contínua [gilda_book_story] (inc/transitions.php), pelo pattern gilda/book,
 * pelos cards da home (patterns/shop-section.php) e pelo filtro "Capítulo" da loja (inc/filters.php).
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capítulos publicados, do 1º ao último.
 *
 * Cada capítulo: id, number, title ("CAPÍTULO N"), anchor ("capitulo-N"), name, text[] (parágrafos,
 * <em> permitido), zoom (IDs: principal + 6 da colagem), story (IDs da narrativa), gallery (IDs),
 * products (IDs), links ([rótulo, url] por peça).
 *
 * @return array[]
 */
function gilda_book_chapters() {
	return function_exists( 'gilda_chapters' ) ? gilda_chapters() : array();
}

/**
 * IDs das peças de um capítulo pela âncora / valor do filtro ("capitulo-3").
 *
 * @param string $anchor Âncora.
 * @return int[]
 */
function gilda_chapter_product_ids( $anchor ) {
	$chapter = function_exists( 'gilda_chapter_by_anchor' ) ? gilda_chapter_by_anchor( (string) $anchor ) : null;
	return $chapter ? $chapter['products'] : array();
}

/**
 * [gilda_product_chapter] — "Esta peça é do Capítulo 3 — Ritual Brasileiro" na página da peça.
 *
 * @return string
 */
function gilda_product_chapter_shortcode() {
	if ( ! function_exists( 'gilda_product_chapter' ) || ! is_singular( 'product' ) ) {
		return '';
	}
	$chapter = gilda_product_chapter( (int) get_queried_object_id() );
	if ( ! $chapter ) {
		return '';
	}
	return sprintf(
		'<p class="gilda-pdp-chapter">%1$s <a href="%2$s">%3$s</a></p>',
		esc_html__( 'Esta peça é do', 'gilda' ),
		esc_url( home_url( '/capitulos/#' . $chapter['anchor'] ) ),
		/* translators: 1: chapter number, 2: chapter name */
		esc_html( sprintf( __( 'Capítulo %1$d — %2$s', 'gilda' ), $chapter['number'], $chapter['name'] ) )
	);
}
add_shortcode( 'gilda_product_chapter', 'gilda_product_chapter_shortcode' );
