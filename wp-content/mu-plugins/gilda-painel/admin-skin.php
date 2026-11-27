<?php
/**
 * Visual GILDA; do painel.
 *
 * - Para todos: as telas do painel (Início, Dados da loja, Capítulos, Nova peça) usam assets/painel.css.
 * - Só para a Dona da loja (gilda_dona): o wp-admin inteiro ganha a cara do protótipo
 *   (painel-proto/*.dc.html): menu terracota com "GILDA;" e "Ver o site ↗ / nome · Sair", fundo creme,
 *   cartões brancos com borda areia, sem a barra de cima no computador, sem rodapé do WordPress,
 *   ajuda, opções de tela e avisos de atualização/plugins. O administrador continua com o WordPress padrão.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** CSS das telas do painel (e as fontes do tema). */
function gilda_painel_enqueue_ui(): void {
	wp_enqueue_style( 'gilda-painel-ui', GILDA_PAINEL_URL . 'assets/painel.css', array(), gilda_painel_ver( 'assets/painel.css' ) );
	wp_add_inline_style( 'gilda-painel-ui', gilda_painel_fonts_css() );
}

add_filter(
	'admin_body_class',
	static fn( $classes ) => gilda_is_owner() ? $classes . ' gilda-owner' : $classes
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( ! gilda_is_owner() ) {
			return;
		}
		gilda_painel_enqueue_ui();
		wp_enqueue_style( 'gilda-owner', GILDA_PAINEL_URL . 'assets/owner.css', array( 'gilda-painel-ui' ), gilda_painel_ver( 'assets/owner.css' ) );
	}
);

/** "Ver o site ↗" e "nome · Sair" no pé do menu. */
add_action(
	'adminmenu',
	static function () {
		if ( ! gilda_is_owner() ) {
			return;
		}
		$user = wp_get_current_user();
		printf(
			'<div class="gilda-menu-foot"><a href="%1$s" target="_blank" rel="noopener">Ver o site ↗</a><span>%2$s · <a href="%3$s">Sair</a></span></div>',
			esc_url( home_url( '/' ) ),
			esc_html( $user->first_name ? $user->first_name : $user->display_name ),
			esc_url( wp_logout_url( wp_login_url() ) )
		);
	}
);

add_filter( 'admin_footer_text', static fn( $text ) => gilda_is_owner() ? '' : $text, 99 );
add_filter( 'update_footer', static fn( $text ) => gilda_is_owner() ? '' : $text, 99 );
add_filter( 'screen_options_show_screen', static fn( $show ) => gilda_is_owner() ? false : $show, 99 );

add_action(
	'current_screen',
	static function ( $screen ) {
		if ( gilda_is_owner() && $screen ) {
			$screen->remove_help_tabs();
			$screen->set_help_sidebar( '' );
		}
	},
	99
);

/**
 * Avisos: para a dona, só os do próprio painel (arquivos de gilda-painel/). Some tudo o que é
 * atualização, propaganda de plugin e aviso técnico do WooCommerce. As mensagens de "salvo" das telas
 * do WordPress não passam por aqui e continuam aparecendo.
 */
add_action(
	'in_admin_header',
	static function () {
		if ( ! gilda_is_owner() ) {
			return;
		}
		global $wp_filter;
		foreach ( array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $callback ) {
					if ( ! gilda_is_painel_callback( $callback['function'] ) ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	},
	PHP_INT_MAX
);

/**
 * A função foi escrita neste plugin?
 *
 * @param callable $fn Callback.
 * @return bool
 */
function gilda_is_painel_callback( $fn ): bool {
	try {
		if ( is_string( $fn ) && function_exists( $fn ) ) {
			$ref = new ReflectionFunction( $fn );
		} elseif ( $fn instanceof Closure ) {
			$ref = new ReflectionFunction( $fn );
		} elseif ( is_array( $fn ) && 2 === count( $fn ) ) {
			$ref = new ReflectionMethod( $fn[0], $fn[1] );
		} else {
			return false;
		}
		return str_starts_with( wp_normalize_path( (string) $ref->getFileName() ), wp_normalize_path( GILDA_PAINEL_DIR ) );
	} catch ( ReflectionException $e ) {
		return false;
	}
}

/** Lista de peças da dona: sem colunas técnicas (SKU, marcas, tags, data). */
add_filter(
	'hidden_columns',
	static function ( $hidden, $screen ) {
		if ( gilda_is_owner() && $screen && 'edit-product' === $screen->id ) {
			$hidden = array_unique( array_merge( (array) $hidden, array( 'sku', 'product_brand', 'taxonomy-product_brand', 'product_tag', 'date' ) ) );
		}
		return $hidden;
	},
	10,
	2
);
