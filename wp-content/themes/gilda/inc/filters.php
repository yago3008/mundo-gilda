<?php
/**
 * Catalog sidebar filters (tasks 4.1–4.5).
 *
 * Plain GET form, so it works without JavaScript; gilda.js upgrades it to
 * update the gallery in place. Query params:
 *   tipo[]     product_cat slugs        (4.2)
 *   min_price  / max_price (WooCommerce native)  (4.3)
 *   cor[]      pa_cor term slugs         (4.4)
 *   tamanho[]  pa_tamanho term slugs     (4.5)
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a list of slugs from the query string.
 *
 * @param string $key Param.
 * @return string[]
 */
function gilda_filter_param( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only filter.
	$raw = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : array();
	$raw = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
	return array_values( array_filter( array_map( 'sanitize_title', $raw ) ) );
}

/**
 * Apply tipo / cor / tamanho to the main product query.
 *
 * @param WP_Query $q Main product query.
 */
function gilda_apply_product_filters( $q ) {
	$tax_query = (array) $q->get( 'tax_query' );
	$map       = array(
		'tipo'    => 'product_cat',
		'cor'     => 'pa_cor',
		'tamanho' => 'pa_tamanho',
	);
	foreach ( $map as $param => $taxonomy ) {
		$slugs = gilda_filter_param( $param );
		if ( $slugs && taxonomy_exists( $taxonomy ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}
	}
	if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
		$tax_query['relation'] = 'AND';
	}
	$q->set( 'tax_query', $tax_query );

	$chapter_params = gilda_filter_param( 'capitulo' );
	if ( $chapter_params ) {
		// Peças do capítulo (campo "Capítulo" da peça, no painel). Capítulo fora do site: loja vazia.
		$product_ids = gilda_chapter_product_ids( $chapter_params[0] );
		$q->set( 'post__in', $product_ids ? $product_ids : array( 0 ) );
	}
}
add_action( 'woocommerce_product_query', 'gilda_apply_product_filters' );

/**
 * Price bounds of the catalogue (fallback: 97–569 from the backlog).
 *
 * @return int[] [min, max]
 */
function gilda_price_bounds() {
	$bounds = wp_cache_get( 'gilda_price_bounds', 'gilda' );
	if ( false === $bounds ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cached aggregate over the WooCommerce lookup table.
		$row    = $wpdb->get_row( "SELECT MIN(min_price) AS lo, MAX(max_price) AS hi FROM {$wpdb->wc_product_meta_lookup} WHERE max_price > 0" );
		$bounds = ( $row && $row->hi ) ? array( (int) floor( $row->lo ), (int) ceil( $row->hi ) ) : array( 97, 569 );
		wp_cache_set( 'gilda_price_bounds', $bounds, 'gilda', HOUR_IN_SECONDS );
	}
	return $bounds;
}

/**
 * URL of the current catalog view without filter params.
 *
 * @return string
 */
function gilda_filters_base_url() {
	if ( is_product_category() ) {
		return get_term_link( get_queried_object() );
	}
	return wc_get_page_permalink( 'shop' );
}

/**
 * Render a collapsible filter section.
 *
 * @param string $title Section title.
 * @param string $body  Inner HTML.
 * @param string $id    Section id.
 * @return string
 */
function gilda_filter_section( $title, $body, $id ) {
	return sprintf(
		'<details class="gilda-filter" open><summary class="gilda-filter__title" id="%1$s">%2$s</summary><div class="gilda-filter__body" role="group" aria-labelledby="%1$s">%3$s</div></details>',
		esc_attr( $id ),
		esc_html( $title ),
		$body
	);
}

/**
 * [gilda_product_filters]
 *
 * @return string
 */
function gilda_shortcode_product_filters() {
	$base     = gilda_filters_base_url();
	$tipos    = gilda_filter_param( 'tipo' );
	$cores    = gilda_filter_param( 'cor' );
	$tamanhos = gilda_filter_param( 'tamanho' );
	$capitulo = gilda_filter_param( 'capitulo' );
	list( $lo, $hi ) = gilda_price_bounds();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$min = isset( $_GET['min_price'] ) ? max( $lo, absint( $_GET['min_price'] ) ) : $lo;
	$max = isset( $_GET['max_price'] ) ? min( $hi, absint( $_GET['max_price'] ) ) : $hi;
	// phpcs:enable

	$categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	$categories = is_wp_error( $categories ) ? array() : $categories;
	$current    = is_product_category() ? get_queried_object_id() : 0;

	// 4.1 Buscar por.
	$nav  = '<ul class="gilda-filter-cats">';
	$nav .= sprintf(
		'<li><a href="%1$s"%2$s>%3$s</a></li>',
		esc_url( wc_get_page_permalink( 'shop' ) ),
		$current ? '' : ' aria-current="page"',
		esc_html__( 'Todos os produtos', 'gilda' )
	);
	foreach ( $categories as $cat ) {
		$nav .= sprintf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( get_term_link( $cat ) ),
			$current === $cat->term_id ? ' aria-current="page"' : '',
			esc_html( $cat->name )
		);
	}
	$nav .= '</ul>';

	// Chapter selector.
	$chapter_html = sprintf(
		'<label class="screen-reader-text" for="gilda-filter-chapter">%1$s</label><select class="gilda-filter-chapter" id="gilda-filter-chapter" name="capitulo"><option value="">%2$s</option>',
		esc_html__( 'Capítulo', 'gilda' ),
		esc_html__( 'Todos os capítulos', 'gilda' )
	);
	foreach ( gilda_book_chapters() as $chapter ) {
		/* translators: 1: chapter number, 2: chapter name */
		$chapter_label = sprintf( __( 'Capítulo %1$d — %2$s', 'gilda' ), $chapter['number'], $chapter['name'] );
		$chapter_html .= sprintf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $chapter['anchor'] ),
			selected( in_array( $chapter['anchor'], $capitulo, true ), true, false ),
			esc_html( $chapter_label )
		);
	}
	$chapter_html .= '</select>';

	// 4.2 Tipo de produto.
	$tipo_html = '';
	foreach ( $categories as $cat ) {
		$tipo_html .= sprintf(
			'<label class="gilda-check"><input type="checkbox" name="tipo[]" value="%1$s"%2$s%3$s /><span>%4$s</span></label>',
			esc_attr( $cat->slug ),
			checked( in_array( $cat->slug, $tipos, true ), true, false ),
			disabled( 0 === (int) $cat->count, true, false ),
			esc_html( $cat->name )
		);
	}

	// 4.3 Preço.
	$price_html = sprintf(
		'<div class="gilda-range" data-min="%1$d" data-max="%2$d">
			<div class="gilda-range__track"><span class="gilda-range__fill"></span></div>
			<label class="screen-reader-text" for="gilda-min-price">%5$s</label>
			<input id="gilda-min-price" type="range" name="min_price" min="%1$d" max="%2$d" step="1" value="%3$d" />
			<label class="screen-reader-text" for="gilda-max-price">%6$s</label>
			<input id="gilda-max-price" type="range" name="max_price" min="%1$d" max="%2$d" step="1" value="%4$d" />
		</div>
		<p class="gilda-range__values"><output for="gilda-min-price">R$ %3$d</output><output for="gilda-max-price">R$ %4$d</output></p>',
		$lo,
		$hi,
		$min,
		$max,
		esc_html__( 'Preço mínimo', 'gilda' ),
		esc_html__( 'Preço máximo', 'gilda' )
	);

	// 4.4 Cor.
	$colors    = gilda_product_colors();
	$cor_html  = '';
	$cor_terms = taxonomy_exists( 'pa_cor' ) ? get_terms( array( 'taxonomy' => 'pa_cor', 'hide_empty' => false ) ) : array();
	foreach ( is_wp_error( $cor_terms ) ? array() : $cor_terms as $term ) {
		$cor_html .= sprintf(
			'<label class="gilda-check gilda-check--color"><input type="checkbox" name="cor[]" value="%1$s"%2$s%3$s /><span class="gilda-swatch" style="--swatch:%4$s" aria-hidden="true"></span><span>%5$s</span></label>',
			esc_attr( $term->slug ),
			checked( in_array( $term->slug, $cores, true ), true, false ),
			disabled( 0 === (int) $term->count, true, false ),
			esc_attr( $colors[ $term->slug ] ?? '#D9D3C2' ),
			esc_html( $term->name )
		);
	}

	// 4.5 Tamanho.
	$tam_html  = '';
	$tam_terms = taxonomy_exists( 'pa_tamanho' ) ? get_terms( array( 'taxonomy' => 'pa_tamanho', 'hide_empty' => false, 'orderby' => 'menu_order' ) ) : array();
	foreach ( is_wp_error( $tam_terms ) ? array() : $tam_terms as $term ) {
		$tam_html .= sprintf(
			'<label class="gilda-size"><input type="checkbox" name="tamanho[]" value="%1$s"%2$s%3$s /><span>%4$s</span></label>',
			esc_attr( $term->slug ),
			checked( in_array( $term->slug, $tamanhos, true ), true, false ),
			disabled( 0 === (int) $term->count, true, false ),
			esc_html( $term->name )
		);
	}

	$sections  = gilda_filter_section( __( 'Capítulo', 'gilda' ), $chapter_html, 'gilda-f-capitulo' );
	$sections .= gilda_filter_section( __( 'Tipo de produto', 'gilda' ), $tipo_html, 'gilda-f-tipo' );
	$sections .= gilda_filter_section( __( 'Preço', 'gilda' ), $price_html, 'gilda-f-preco' );
	if ( $cor_html ) {
		$sections .= gilda_filter_section( __( 'Cor', 'gilda' ), $cor_html, 'gilda-f-cor' );
	}
	if ( $tam_html ) {
		$sections .= gilda_filter_section( __( 'Tamanho', 'gilda' ), '<div class="gilda-sizes">' . $tam_html . '</div>', 'gilda-f-tamanho' );
	}

	return sprintf(
		'<aside class="gilda-filters" id="gilda-filters" aria-label="%1$s">
			<div class="gilda-filters__head"><h2 class="gilda-filters__heading">%2$s</h2><button type="button" class="gilda-filters__close" aria-label="%3$s">%4$s</button></div>
			<h2 class="gilda-filters__by">%5$s</h2>
			%6$s
			<form class="gilda-filters__form" method="get" action="%7$s">
				%8$s
				<div class="gilda-filters__actions">
					<button type="submit" class="gilda-btn gilda-btn--primary">%9$s</button>
					<a class="gilda-filters__clear" href="%7$s">%10$s</a>
				</div>
			</form>
		</aside>',
		esc_attr__( 'Filtros', 'gilda' ),
		esc_html__( 'Filtrar', 'gilda' ),
		esc_attr__( 'Fechar filtros', 'gilda' ),
		gilda_icon( 'close' ),
		esc_html__( 'Buscar por', 'gilda' ),
		$nav,
		esc_url( $base ),
		$sections,
		esc_html__( 'Aplicar filtros', 'gilda' ),
		esc_html__( 'Limpar filtros', 'gilda' )
	);
}
add_shortcode( 'gilda_product_filters', 'gilda_shortcode_product_filters' );
