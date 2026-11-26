<?php
/**
 * Plugin Name: GILDA; Cupom de primeira compra
 * Description: Cupons marcados com _gilda_first_purchase_only=yes só valem para quem ainda não tem pedido pago (verifica pela conta logada e pelo e-mail de cobrança).
 */

defined( 'ABSPATH' ) || exit;

/**
 * True se já existe pedido pago/concluído para o cliente ou e-mail.
 */
function gilda_has_previous_order( int $customer_id, string $email ): bool {
	$statuses = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );

	if ( $customer_id ) {
		$orders = wc_get_orders( array( 'customer_id' => $customer_id, 'status' => $statuses, 'limit' => 1, 'return' => 'ids' ) );
		if ( $orders ) {
			return true;
		}
	}

	if ( $email ) {
		$orders = wc_get_orders( array( 'billing_email' => $email, 'status' => $statuses, 'limit' => 1, 'return' => 'ids' ) );
		if ( $orders ) {
			return true;
		}
	}

	return false;
}

add_filter(
	'woocommerce_coupon_is_valid',
	static function ( $valid, $coupon, $discounts = null ) {
		if ( ! $valid || 'yes' !== $coupon->get_meta( '_gilda_first_purchase_only' ) ) {
			return $valid;
		}

		$customer_id = get_current_user_id();
		$email       = '';

		// No checkout o e-mail digitado vem no POST; fora dele, usa o do cliente.
		if ( isset( $_POST['billing_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$email = sanitize_email( wp_unslash( $_POST['billing_email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( WC()->customer ) {
			$email = (string) WC()->customer->get_billing_email();
		}

		// Ao validar um pedido já criado (ex.: Store API / pedido no admin), usa os dados do pedido.
		if ( $discounts instanceof WC_Discounts && $discounts->get_object() instanceof WC_Order ) {
			$order       = $discounts->get_object();
			$customer_id = $order->get_customer_id();
			$email       = $order->get_billing_email();
		}

		if ( gilda_has_previous_order( (int) $customer_id, $email ) ) {
			throw new Exception( esc_html__( 'Este cupom é válido apenas na primeira compra.', 'gilda' ), 100 );
		}

		return $valid;
	},
	10,
	3
);
