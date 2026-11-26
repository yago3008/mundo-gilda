<?php
/**
 * Plugin Name: GILDA; Botão Comprar
 * Description: Botão "Comprar" na página do produto (requisito 5.9): valida cor/tamanho como o "Adicionar ao carrinho", adiciona e vai direto para o checkout.
 *
 * Para o front (K1venn): o botão é um <button type="submit" name="gilda_buy_now" value="1"
 * class="gilda-buy-now ..."> dentro do formulário de compra. Qualquer botão com esse name/value
 * no formulário tem o mesmo comportamento. Fora do formulário, use o link
 * ?gilda_buy_now=1&add-to-cart=<id>[&variation_id=<id>&attribute_pa_cor=<slug>&attribute_pa_tamanho=<slug>&quantity=N].
 */

defined( 'ABSPATH' ) || exit;

// Botão ao lado do "Adicionar ao carrinho" — só se o tema não desenhar o dele
// (o tema "gilda" tem gilda_buy_now_button(); o redirect abaixo continua valendo para os dois).
add_action(
	'woocommerce_after_add_to_cart_button',
	static function () {
		global $product;
		if ( has_action( 'woocommerce_after_add_to_cart_button', 'gilda_buy_now_button' ) ) {
			return;
		}
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		$classes = 'gilda-buy-now single_add_to_cart_button button alt' . ( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' );
		printf(
			'<button type="submit" name="gilda_buy_now" value="1" class="%s">%s</button>',
			esc_attr( $classes ),
			esc_html__( 'Comprar', 'gilda' )
		);
	}
);

// Depois de adicionar: vai para o checkout em vez de ficar na página.
add_filter(
	'woocommerce_add_to_cart_redirect',
	static function ( $url ) {
		if ( ! empty( $_REQUEST['gilda_buy_now'] ) && 0 === wc_notice_count( 'error' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// O checkout já mostra o produto; o aviso "foi adicionado ao carrinho" fica redundante.
			wc_clear_notices();
			return wc_get_checkout_url();
		}
		return $url;
	},
	99
);
