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
 * Render the Quem Cria page directly when a database template cannot resolve
 * the theme pattern reference.
 *
 * @return string
 */
function gilda_about_page_shortcode() {
	ob_start();
	include GILDA_DIR . '/patterns/about.php';
	return (string) ob_get_clean();
}
add_shortcode( 'gilda_about_page', 'gilda_about_page_shortcode' );

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

/**
 * URL of the product catalog filtered by a product category.
 *
 * @param string $slug Product category slug.
 * @return string
 */
function gilda_product_type_url( $slug ) {
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
	if ( ! $shop_url ) {
		$shop_url = home_url( '/produtos/' );
	}
	return add_query_arg( 'tipo', sanitize_title( $slug ), $shop_url );
}

/**
 * URL of the catalog filtered by a chapter.
 *
 * @param string $chapter Chapter slug.
 * @return string
 */
function gilda_chapter_shop_url( $chapter ) {
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
	if ( ! $shop_url ) {
		$shop_url = home_url( '/loja/' );
	}
	return add_query_arg( 'capitulo', sanitize_title( $chapter ), $shop_url );
}

/* -------------------------------------------------------------------------
 * Páginas de atendimento (cuidados, trocas, dúvidas): layout próprio
 * (templates/page-info.html). O texto continua sendo o da página, editado no painel; o tema só
 * desenha em volta e estiliza os blocos comuns (títulos, listas, parágrafos).
 * ---------------------------------------------------------------------- */

/**
 * Páginas de atendimento, na ordem do menu: slug => nome curto.
 *
 * @return array<string,string>
 */
function gilda_info_pages() {
	return array(
		'cuidados-com-as-pecas' => __( 'Cuidados com as peças', 'gilda' ),
		'trocas-e-devolucoes'   => __( 'Trocas e devoluções', 'gilda' ),
		'duvidas-frequentes'    => __( 'Dúvidas frequentes', 'gilda' ),
	);
}

/**
 * Slug da página de atendimento aberta, ou ''.
 *
 * @return string
 */
function gilda_current_info_page() {
	if ( ! is_page() ) {
		return '';
	}
	$post = get_queried_object();
	return ( $post instanceof WP_Post && isset( gilda_info_pages()[ $post->post_name ] ) ) ? $post->post_name : '';
}

/**
 * Usa templates/page-info.html nas páginas de atendimento (se a página não escolheu outro modelo).
 *
 * @param string[] $templates Hierarquia de modelos.
 * @return string[]
 */
function gilda_info_page_template( $templates ) {
	if ( gilda_current_info_page() ) {
		$custom = (string) get_page_template_slug( get_queried_object_id() );
		$at     = $custom ? 1 : 0; // modelo escolhido no editor continua mandando
		array_splice( $templates, $at, 0, array( 'page-info.php' ) );
	}
	return $templates;
}
add_filter( 'page_template_hierarchy', 'gilda_info_page_template' );

/**
 * Classe no <body> por página de atendimento (o CSS de cada uma).
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gilda_info_body_class( $classes ) {
	$slug = gilda_current_info_page();
	if ( $slug ) {
		$classes[] = 'gilda-info-page';
		$classes[] = 'gilda-info-page--' . $slug;
	}
	return $classes;
}
add_filter( 'body_class', 'gilda_info_body_class' );

/**
 * [gilda_info_nav] — abas entre as páginas de atendimento publicadas.
 *
 * @return string
 */
function gilda_shortcode_info_nav() {
	$current = gilda_current_info_page();
	$items   = '';
	foreach ( gilda_info_pages() as $slug => $label ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || ( 'publish' !== $page->post_status && $slug !== $current ) ) {
			continue; // rascunho não aparece no menu (daria 404 para a cliente)
		}
		$items .= sprintf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( get_permalink( $page ) ),
			$slug === $current ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}
	return $items ? '<nav class="gilda-info__nav" aria-label="' . esc_attr__( 'Atendimento', 'gilda' ) . '"><ul>' . $items . '</ul></nav>' : '';
}
add_shortcode( 'gilda_info_nav', 'gilda_shortcode_info_nav' );

/**
 * Telefone brasileiro legível: 41991008303 → (41) 99100-8303. Outro formato fica como veio.
 *
 * @param string $value Número.
 * @return string
 */
function gilda_info_format_phone( $value ) {
	$digits = preg_replace( '/\D+/', '', (string) $value );
	if ( 13 === strlen( $digits ) && str_starts_with( $digits, '55' ) ) {
		$digits = substr( $digits, 2 );
	}
	if ( 11 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 5 ), substr( $digits, 7 ) );
	}
	if ( 10 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 4 ), substr( $digits, 6 ) );
	}
	return trim( (string) $value );
}

/**
 * [gilda_info_contact] — cartão "Fale com a gente" (WhatsApp, e-mail e Instagram dos Dados da loja).
 *
 * @return string
 */
function gilda_shortcode_info_contact() {
	$whatsapp  = function_exists( 'gilda_store_whatsapp_url' ) ? gilda_store_whatsapp_url() : 'https://wa.me/5541991008303';
	$phone     = gilda_info_format_phone( gilda_store_value( 'whatsapp', '41991008303' ) );
	$email     = gilda_store_value( 'email', 'mundogilda@gmail.com' );
	$instagram = function_exists( 'gilda_store_instagram_url' ) ? gilda_store_instagram_url() : 'https://www.instagram.com/gildapontoevirgula/';
	$handle    = function_exists( 'gilda_store_instagram_handle' ) ? gilda_store_instagram_handle( gilda_store_value( 'instagram' ) ) : 'gildapontoevirgula';

	$rows = '';
	if ( $email ) {
		$rows .= sprintf( '<li><span>%1$s</span><a href="%2$s">%3$s</a></li>', esc_html__( 'E-mail', 'gilda' ), esc_url( 'mailto:' . $email, array( 'mailto' ) ), esc_html( $email ) );
	}
	if ( $instagram && $handle ) {
		$rows .= sprintf( '<li><span>%1$s</span><a href="%2$s" target="_blank" rel="noreferrer noopener">@%3$s</a></li>', esc_html__( 'Instagram', 'gilda' ), esc_url( $instagram ), esc_html( $handle ) );
	}
	$button = $whatsapp ? sprintf(
		'<a class="gilda-info__whatsapp" href="%1$s" target="_blank" rel="noreferrer noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"></path><path d="M9 9.5c.3 2 2.5 4.2 4.5 4.5l1-1.2 1.8.8c-.2 1-1.1 1.6-2.1 1.5-3.2-.4-5.8-3-6.2-6.2-.1-1 .5-1.9 1.5-2.1l.8 1.8L9 9.5z"></path></svg><span>%2$s</span></a>%3$s',
		esc_url( $whatsapp ),
		esc_html__( 'Chamar no WhatsApp', 'gilda' ),
		$phone ? '<p class="gilda-info__phone">' . esc_html( $phone ) . '</p>' : ''
	) : '';

	return '<aside class="gilda-info__contact" aria-labelledby="gilda-info-contact-title">'
		. '<p class="gilda-info__contact-kicker">' . esc_html__( 'Ficou alguma dúvida?', 'gilda' ) . '</p>'
		. '<h2 id="gilda-info-contact-title" class="gilda-info__contact-title">' . esc_html__( 'Fale com a gente', 'gilda' ) . '</h2>'
		. '<p class="gilda-info__contact-text">' . esc_html__( 'Ajudamos com tamanho, cuidados, trocas e o seu pedido.', 'gilda' ) . '</p>'
		. $button
		. ( $rows ? '<ul class="gilda-info__contact-list">' . $rows . '</ul>' : '' )
		. '</aside>';
}
add_shortcode( 'gilda_info_contact', 'gilda_shortcode_info_contact' );
