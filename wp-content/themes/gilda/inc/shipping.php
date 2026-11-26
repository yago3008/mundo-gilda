<?php
/**
 * Product-page shipping quote (tasks 5.10 and 11.6).
 *
 * GET /wp-json/gilda/v1/shipping?product_id=12&cep=82015384[&variation_id=34][&quantity=1]
 *
 * Uses the shipping zones/methods configured in WooCommerce, so any Correios
 * or Melhor Envio plugin that registers shipping methods is picked up
 * automatically. Results are cached per CEP + product + quantity.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register route.
 */
function gilda_register_shipping_route() {
	register_rest_route(
		'gilda/v1',
		'/shipping',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'gilda_shipping_quote',
			'permission_callback' => '__return_true',
			'args'                => array(
				'product_id'   => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
				'variation_id' => array( 'type' => 'integer', 'default' => 0 ),
				'quantity'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 99 ),
				'cep'          => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => static fn( $value ) => preg_replace( '/\D/', '', (string) $value ),
					'validate_callback' => static fn( $value ) => 1 === preg_match( '/^\d{5}-?\d{3}$/', (string) $value ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'gilda_register_shipping_route' );

/**
 * Make sure cart/session/customer objects exist inside a REST request.
 */
function gilda_shipping_bootstrap_wc() {
	if ( function_exists( 'wc_load_cart' ) && ( null === WC()->session || null === WC()->customer ) ) {
		wc_load_cart();
	}
}

/**
 * Callback.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gilda_shipping_quote( WP_REST_Request $request ) {
	$cep          = (string) $request['cep'];
	$product_id   = (int) $request['product_id'];
	$variation_id = (int) $request['variation_id'];
	$quantity     = (int) $request['quantity'];

	$product = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $product || ! $product->needs_shipping() ) {
		return new WP_Error( 'gilda_invalid_product', __( 'Produto inválido para cálculo de frete.', 'gilda' ), array( 'status' => 400 ) );
	}

	$cache_key = 'gilda_ship_' . md5( implode( '|', array( $cep, $product->get_id(), $quantity, WC_Cache_Helper::get_transient_version( 'shipping' ) ) ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return rest_ensure_response( $cached );
	}

	gilda_shipping_bootstrap_wc();

	$line_total = (float) $product->get_price() * $quantity;
	$package    = array(
		'contents'        => array(
			'gilda_quote' => array(
				'key'          => 'gilda_quote',
				'product_id'   => $variation_id ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $variation_id,
				'quantity'     => $quantity,
				'data'         => $product,
				'line_total'   => $line_total,
				'line_tax'     => 0,
				'line_subtotal'     => $line_total,
				'line_subtotal_tax' => 0,
			),
		),
		'contents_cost'   => $line_total,
		'applied_coupons' => array(),
		'user'            => array( 'ID' => get_current_user_id() ),
		'destination'     => array(
			'country'   => 'BR',
			'state'     => '',
			'postcode'  => substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 ),
			'city'      => '',
			'address'   => '',
			'address_1' => '',
			'address_2' => '',
		),
		'cart_subtotal'   => $line_total,
	);

	$result = WC()->shipping()->calculate_shipping_for_package( $package, 'gilda_quote' );
	$rates  = array();

	foreach ( (array) ( $result['rates'] ?? array() ) as $rate ) {
		/** @var WC_Shipping_Rate $rate */
		$cost    = (float) $rate->get_cost() + array_sum( (array) $rate->get_taxes() );
		$meta     = $rate->get_meta_data();
		$delivery = '';
		foreach ( $meta as $meta_key => $meta_value ) {
			// Correios / Melhor Envio plugins store the forecast under different keys.
			if ( is_scalar( $meta_value ) && preg_match( '/forecast|prazo|entrega|delivery/i', (string) $meta_key ) ) {
				$delivery = (string) $meta_value;
				break;
			}
		}
		$rates[] = array(
			'id'       => $rate->get_id(),
			'label'    => wp_strip_all_tags( $rate->get_label() ),
			'cost'     => $cost,
			'price'    => $cost > 0 ? wp_strip_all_tags( html_entity_decode( wc_price( $cost ) ) ) : __( 'Grátis', 'gilda' ),
			'delivery' => wp_strip_all_tags( $delivery ),
		);
	}

	set_transient( $cache_key, $rates, 6 * HOUR_IN_SECONDS );

	return rest_ensure_response( $rates );
}
