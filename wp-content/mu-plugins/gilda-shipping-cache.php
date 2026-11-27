<?php
/**
 * Plugin Name: GILDA; Frete atualizado com o peso
 * Description: Quando o peso ou as medidas de uma peça (ou variação) mudam, as cotações de frete guardadas deixam de valer: a do simulador da página da peça (cache de 6 h em wp-content/themes/gilda/inc/shipping.php) e a do carrinho/checkout (o WooCommerce guarda as taxas na sessão por um "hash" do pacote que não inclui o peso).
 *
 * Como: troca a versão "shipping" dos transients do WooCommerce, que entra na chave dos dois caches.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'woocommerce_before_product_object_save',
	static function ( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			return; // peça nova: ainda não tem cotação guardada
		}
		$changes = $product->get_changes();
		foreach ( array( 'weight', 'length', 'width', 'height' ) as $prop ) {
			if ( array_key_exists( $prop, $changes ) ) {
				WC_Cache_Helper::get_transient_version( 'shipping', true );
				return;
			}
		}
	}
);
