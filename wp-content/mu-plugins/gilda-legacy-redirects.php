<?php
/**
 * Plugin Name: GILDA; Redirecionamentos do site antigo (Wix)
 * Description: 301 das URLs do Wix para as novas, preservando SEO e links já compartilhados.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() ) {
			return;
		}

		$path = rawurldecode( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = '/' . trim( $path, '/' );

		// Páginas fixas do Wix → páginas novas (ajustar quando as páginas institucionais existirem).
		$map = array(
			'/category/all-products' => '/loja/',
			'/contact-1'             => '/loja/',  // "PRODUTOS"
			'/contact'               => '/quem-cria/',
			'/contact-2'             => '/capitulos/',
		);
		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301 );
			exit;
		}

		// /product-page/<slug> → página do produto com o mesmo slug.
		if ( preg_match( '#^/product-page/([^/]+)$#', $path, $m ) ) {
			$product = get_page_by_path( sanitize_title( $m[1] ), OBJECT, 'product' );
			wp_safe_redirect( $product ? get_permalink( $product ) : wc_get_page_permalink( 'shop' ), 301 );
			exit;
		}
	}
);
