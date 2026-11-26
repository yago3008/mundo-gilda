<?php
/**
 * Plugin Name: GILDA; Cache do catálogo
 * Description: Guarda por alguns dias o HTML do catálogo (loja e filtros: tipo, capítulo, cor, tamanho) para quem não está logado e está com o carrinho vazio. O catálogo abre na hora, inclusive depois do zoom dos cards de categoria da home. Limpa sozinho quando produto, estoque ou categoria muda.
 *
 * Configuração (variáveis de ambiente):
 *   GILDA_CATALOG_CACHE=0       desliga o cache.
 *   GILDA_CATALOG_CACHE_DAYS    quantos dias guardar (padrão 3).
 *
 * Cuidados:
 * - Só visitante sem login e sem itens no carrinho (o cabeçalho mostra o carrinho; logado vê a conta).
 * - Nonces: a página guardada leva nonces de quem a gerou. Na hora de servir, trocamos pelos nonces do
 *   visitante atual (Store API, REST e frete), então adicionar ao carrinho e calcular frete continuam valendo.
 * - Só guarda páginas geradas por um visitante "limpo" (sem sessão do WooCommerce): nada de evento de
 *   pixel na fila ou aviso de outra pessoa entra no HTML guardado.
 * - Parâmetros de anúncio (utm_*, fbclid, gclid) não mudam a página; qualquer outro parâmetro desconhecido
 *   fica sem cache.
 * - Cabeçalho X-Gilda-Cache: HIT / MISS, para conferir.
 * Limpar à mão: wp eval 'gilda_catalog_cache_flush();'
 */

defined( 'ABSPATH' ) || exit;

const GILDA_CATALOG_CACHE_VERSION_OPTION = 'gilda_catalog_cache_ver';

/** Parâmetros que mudam o catálogo (filtros do tema, ordenação, página). */
function gilda_catalog_cache_allowed_args(): array {
	return array( 'tipo', 'capitulo', 'cor', 'tamanho', 'orderby', 'paged', 'product-page', 'preco_min', 'preco_max', 'min_price', 'max_price' );
}

/** Ações dos nonces impressos no catálogo, trocados na hora de servir. */
function gilda_catalog_cache_nonce_actions(): array {
	return (array) apply_filters( 'gilda_catalog_cache_nonce_actions', array( 'wc_store_api', 'wp_rest', 'infixs_correios_automatico_nonce' ) );
}

function gilda_catalog_cache_enabled(): bool {
	return '0' !== (string) getenv( 'GILDA_CATALOG_CACHE' );
}

/** Esvazia o cache (troca a versão; as cópias antigas expiram sozinhas). */
function gilda_catalog_cache_flush(): void {
	update_option( GILDA_CATALOG_CACHE_VERSION_OPTION, (string) time(), false );
}

/**
 * Chave da página atual, ou null se ela não pode usar o cache.
 */
function gilda_catalog_cache_key(): ?string {
	if ( ! gilda_catalog_cache_enabled() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return null;
	}
	if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) || is_user_logged_in() || is_preview() ) {
		return null;
	}
	if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) {
		return null;
	}
	if ( function_exists( 'wc_notice_count' ) && WC()->session && wc_notice_count() > 0 ) {
		return null; // aviso pendente (ex.: "produto adicionado"): página única
	}

	$args = array();
	foreach ( wp_unslash( $_GET ) as $name => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
		$name = (string) $name;
		if ( preg_match( '/^(utm_[a-z_]+|fbclid|gclid|msclkid)$/i', $name ) ) {
			continue;
		}
		if ( ! in_array( $name, gilda_catalog_cache_allowed_args(), true ) || ! is_scalar( $value ) ) {
			return null;
		}
		$args[ $name ] = sanitize_text_field( (string) $value );
	}
	ksort( $args );

	$path = strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), '?' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	// O HTML aponta para os arquivos do tema: um deploy novo do tema também invalida.
	$theme = '';
	foreach ( array( 'assets/js/gilda.js', 'assets/css/theme.css' ) as $file ) {
		$theme .= (string) @filemtime( get_theme_file_path( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$version = (string) get_option( GILDA_CATALOG_CACHE_VERSION_OPTION, '1' );
	return 'gilda_cc_' . md5( $version . '|' . $theme . '|' . $path . '|' . wp_json_encode( $args ) );
}

add_action(
	'template_redirect',
	static function () {
		$key = gilda_catalog_cache_key();
		if ( ! $key ) {
			return;
		}

		$cached = get_transient( $key );
		if ( is_array( $cached ) && ! empty( $cached['html'] ) ) {
			$html = (string) $cached['html'];
			foreach ( (array) ( $cached['nonces'] ?? array() ) as $action => $old ) {
				$html = str_replace( $old, wp_create_nonce( $action ), $html );
			}
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			header( 'X-Gilda-Cache: HIT' );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML gerado pelo próprio site
			exit;
		}

		header( 'X-Gilda-Cache: MISS' );
		// Só guarda o que um visitante sem sessão gerou (nada da fila de pixel/avisos de alguém).
		$clean = ! preg_grep( '/^wp_woocommerce_session_/', array_keys( $_COOKIE ) );
		if ( ! $clean ) {
			return;
		}
		$nonces = array();
		foreach ( gilda_catalog_cache_nonce_actions() as $action ) {
			$nonces[ $action ] = wp_create_nonce( $action );
		}
		ob_start(
			static function ( $html ) use ( $key, $nonces ) {
				$ok = 200 === http_response_code()
					&& strlen( $html ) > 1000
					&& false !== stripos( $html, '</html>' )
					&& false === strpos( $html, 'woocommerce-error' );
				if ( $ok ) {
					$days = max( 1, (int) ( getenv( 'GILDA_CATALOG_CACHE_DAYS' ) ?: 3 ) );
					set_transient( $key, array( 'html' => $html, 'nonces' => array_filter( $nonces ) ), $days * DAY_IN_SECONDS );
				}
				return $html;
			}
		);
	},
	0
);

// O que muda o catálogo: produto, estoque, preço, categorias/atributos, capítulos, tema.
foreach (
	array(
		'save_post_product',
		'deleted_post',
		'trashed_post',
		'woocommerce_update_product',
		'woocommerce_new_product',
		'woocommerce_update_product_variation',
		'woocommerce_product_set_stock',
		'woocommerce_variation_set_stock',
		'woocommerce_product_set_stock_status',
		'woocommerce_variation_set_stock_status',
		'switch_theme',
		'customize_save_after',
	) as $gilda_cc_hook
) {
	add_action( $gilda_cc_hook, 'gilda_catalog_cache_flush' );
}
foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $gilda_cc_hook ) {
	add_action(
		$gilda_cc_hook,
		static function ( $term_id, $tt_id = 0, $taxonomy = '' ) {
			if ( 'product_cat' === $taxonomy || 'product_tag' === $taxonomy || str_starts_with( (string) $taxonomy, 'pa_' ) ) {
				gilda_catalog_cache_flush();
			}
		},
		10,
		3
	);
}
unset( $gilda_cc_hook );
