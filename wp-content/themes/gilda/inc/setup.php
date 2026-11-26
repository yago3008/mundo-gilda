<?php
/**
 * Theme setup, assets, block styles and accessibility helpers.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports.
 */
function gilda_setup() {
	load_theme_textdomain( 'gilda', GILDA_DIR . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );

	// WooCommerce (Epics 3–6).
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );     // 5.1 zoom.
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );   // 5.1 "Next item".

	add_editor_style( 'assets/css/theme.css' );
}
add_action( 'after_setup_theme', 'gilda_setup' );

/**
 * Front-end assets.
 */
function gilda_enqueue_assets() {
	wp_enqueue_style( 'gilda-theme', GILDA_URI . '/assets/css/theme.css', array(), filemtime( GILDA_DIR . '/assets/css/theme.css' ) );

	wp_enqueue_script(
		'gilda-theme',
		GILDA_URI . '/assets/js/gilda.js',
		array(),
		GILDA_VERSION . '.' . filemtime( GILDA_DIR . '/assets/js/gilda.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// Clique nos cards de categoria: a tela "entra" no nome do card (pattern gilda/categories).
	wp_enqueue_script(
		'gilda-category-zoom',
		GILDA_URI . '/assets/js/category-zoom.js',
		array(),
		GILDA_VERSION . '.' . filemtime( GILDA_DIR . '/assets/js/category-zoom.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$wc = function_exists( 'WC' );
	$account_images = array();
	// Fotos do carrossel do login/cadastro: as versões em alta de data/site-images.json (account_carousel).
	// Sem elas importadas, usa a foto principal dos produtos.
	if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'gilda_image' ) ) {
		foreach ( (array) gilda_image( 'account_carousel' ) as $carousel_id ) {
			$image_url = wp_get_attachment_image_url( $carousel_id, 'full' );
			if ( $image_url ) {
				$account_images[] = esc_url_raw( $image_url );
			}
		}
	}
	if ( ! $account_images && $wc && function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'wc_get_products' ) ) {
		$account_products = wc_get_products(
			array(
				'limit'   => 8,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'status'  => 'publish',
			)
		);
		foreach ( $account_products as $account_product ) {
			if ( ! $account_product instanceof WC_Product || ! $account_product->get_image_id() ) {
				continue;
			}
			$image_url = wp_get_attachment_image_url( $account_product->get_image_id(), 'large' );
			if ( $image_url ) {
				$account_images[] = esc_url_raw( $image_url );
			}
			if ( count( $account_images ) >= 4 ) {
				break;
			}
		}
	}

	wp_localize_script(
		'gilda-theme',
		'gildaSettings',
		array(
			'storeApi'    => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'storeNonce'  => $wc ? wp_create_nonce( 'wc_store_api' ) : '',
			'shippingApi' => esc_url_raw( rest_url( 'gilda/v1/shipping' ) ),
			'restNonce'   => wp_create_nonce( 'wp_rest' ),
			'cartUrl'     => $wc ? wc_get_cart_url() : '',
			'checkoutUrl' => $wc ? wc_get_checkout_url() : '',
			'homeUrl'     => esc_url_raw( home_url( '/' ) ),
			'accountImages' => $account_images,
			'colors'      => gilda_product_colors(),
			'i18n'        => array(
				'prev'            => __( 'Produto anterior', 'gilda' ),
				'next'            => __( 'Próximo produto', 'gilda' ),
				'added'           => __( 'Produto adicionado ao carrinho', 'gilda' ),
				'viewCart'        => __( 'ver carrinho', 'gilda' ),
				'selectBoth'      => __( 'Selecione a cor e o tamanho antes de continuar.', 'gilda' ),
				'selectColor'     => __( 'Selecione uma cor.', 'gilda' ),
				'selectSize'      => __( 'Selecione um tamanho.', 'gilda' ),
				'selectOption'    => __( 'Selecione as opções do produto.', 'gilda' ),
				'genericError'    => __( 'Não foi possível adicionar ao carrinho. Tente novamente.', 'gilda' ),
				'cepInvalid'      => __( 'CEP inválido. Digite os 8 números (ex.: 82015-384).', 'gilda' ),
				'cepError'        => __( 'Não conseguimos calcular o frete agora. Tente novamente em instantes.', 'gilda' ),
				'cepEmpty'        => __( 'Nenhuma opção de entrega para este CEP.', 'gilda' ),
				'calculating'     => __( 'Calculando…', 'gilda' ),
				'decrease'        => __( 'Remover 1', 'gilda' ),
				'increase'        => __( 'Adicionar 1', 'gilda' ),
				'addToCart'       => __( 'Adicionar ao carrinho', 'gilda' ),
				'soldOut'         => __( 'Esgotado', 'gilda' ),
				'filter'          => __( 'Filtrar', 'gilda' ),
				/* translators: %d: number of items in the cart. */
				'cartLabel'       => __( 'Carrinho com %d itens', 'gilda' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gilda_enqueue_assets' );

/**
 * Segunda metade da transição dos cards de categoria: inline no começo do <body>, para a tela
 * creme com o nome já sair na primeira pintura da página de destino (assets/js/category-zoom-arrive.js).
 * Não faz nada se a página não foi aberta por um card.
 */
function gilda_category_zoom_arrive() {
	$file = GILDA_DIR . '/assets/js/category-zoom-arrive.js';
	if ( is_admin() || ! is_readable( $file ) ) {
		return;
	}
	echo '<script id="gilda-category-zoom-arrive">' . file_get_contents( $file ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions
}
add_action( 'wp_body_open', 'gilda_category_zoom_arrive', 1 );

/**
 * Product colour swatches (docs/design/01-colors.md → Product colors).
 * Keys are attribute term slugs of `pa_cor`.
 *
 * @return array<string,string>
 */
function gilda_product_colors() {
	// Reserva: as cores do site Wix. A cor de verdade vem de cada termo de Cor (term meta
	// "gilda_hex", gravado pelo import e editável no painel), então cor nova não fica bege.
	$colors = array(
		'off-white'   => '#e9e9d4',
		'marrom'      => '#b4522d',
		'azul-claro'  => '#d4edff',
		'branco'      => '#ffffff',
		'laranja'     => '#eb6d18',
		'verde-musgo' => '#496148',
		'preto'       => '#000000',
	);
	if ( taxonomy_exists( 'pa_cor' ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'pa_cor',
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$hex = (string) get_term_meta( $term->term_id, 'gilda_hex', true );
			if ( preg_match( '/^#[0-9a-f]{6}$/i', $hex ) ) {
				$colors[ $term->slug ] = strtolower( $hex );
			}
		}
	}
	return apply_filters( 'gilda_product_colors', $colors );
}

/**
 * Body class: marks pages whose template opens with the hero, so the header
 * can overlay it transparently and fade to solid on scroll.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function gilda_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'gilda-has-hero';
	}
	return $classes;
}
add_filter( 'body_class', 'gilda_body_classes' );

/**
 * Block styles.
 */
function gilda_register_block_styles() {
	register_block_style(
		'core/button',
		array(
			'name'  => 'pill',
			'label' => __( 'Pill (disponível)', 'gilda' ),
		)
	);
	register_block_style(
		'core/button',
		array(
			'name'  => 'editorial-link',
			'label' => __( 'Link editorial', 'gilda' ),
		)
	);
	register_block_style(
		'core/heading',
		array(
			'name'  => 'editorial',
			'label' => __( 'Editorial itálico', 'gilda' ),
		)
	);
	register_block_style(
		'core/heading',
		array(
			'name'  => 'headline',
			'label' => __( 'Manchete', 'gilda' ),
		)
	);
	register_block_style(
		'core/separator',
		array(
			'name'  => 'semicolon',
			'label' => __( 'Ponto e vírgula', 'gilda' ),
		)
	);
}
add_action( 'init', 'gilda_register_block_styles' );

/**
 * Pattern categories.
 */
function gilda_register_pattern_categories() {
	register_block_pattern_category( 'gilda', array( 'label' => __( 'GILDA;', 'gilda' ) ) );
	register_block_pattern_category( 'gilda-pages', array( 'label' => __( 'GILDA; — páginas', 'gilda' ) ) );
}
add_action( 'init', 'gilda_register_pattern_categories' );

/**
 * Decorative text (task 3.3): any block with the `gilda-decorative` class
 * is hidden from assistive technology.
 *
 * @param string $content Block HTML.
 * @return string
 */
function gilda_decorative_aria_hidden( $content ) {
	if ( false === strpos( $content, 'gilda-decorative' ) ) {
		return $content;
	}
	$tags = new WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag( array( 'class_name' => 'gilda-decorative' ) ) ) {
		$tags->set_attribute( 'aria-hidden', 'true' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block', 'gilda_decorative_aria_hidden', 20 );

/**
 * Skip link copy (task 1.7). Core adds the link automatically for block
 * templates that contain a <main> element; only the label changes here.
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @param string $domain      Text domain.
 * @return string
 */
function gilda_skip_link_text( $translation, $text, $domain ) {
	if ( 'default' === $domain && 'Skip to content' === $text ) {
		return __( 'Ir para o conteúdo principal', 'gilda' );
	}
	return $translation;
}
add_filter( 'gettext', 'gilda_skip_link_text', 10, 3 );
