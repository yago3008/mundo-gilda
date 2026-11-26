<?php
/**
 * Avise-me: quando o estoque volta, agenda o envio (Action Scheduler, fora da requisição que
 * mudou o estoque) e manda o e-mail "voltou ao estoque" (classe WC_Email própria).
 */

defined( 'ABSPATH' ) || exit;

const GILDA_STOCK_ALERTS_BATCH = 50;

// E-mail configurável em WooCommerce → Configurações → E-mails.
add_filter(
	'woocommerce_email_classes',
	static function ( $emails ) {
		require_once GILDA_STOCK_ALERTS_DIR . 'class-gilda-email-back-in-stock.php';
		$emails['Gilda_Email_Back_In_Stock'] = new Gilda_Email_Back_In_Stock();
		return $emails;
	}
);

/**
 * Estoque voltou (os hooks só disparam quando o status MUDA): agenda o envio.
 *
 * @param int        $id     Produto ou variação.
 * @param string     $status Novo status.
 */
function gilda_stock_alerts_on_stock_status( $id, $status ): void {
	if ( 'instock' !== $status || ! function_exists( 'as_enqueue_async_action' ) ) {
		return;
	}
	$ids = array( (int) $id );
	// Variável com estoque no pai: as variações que usam esse estoque também voltaram.
	$product = wc_get_product( $id );
	if ( $product instanceof WC_Product_Variable ) {
		$ids = array_map( 'intval', $product->get_children() );
	}
	foreach ( $ids as $target_id ) {
		if ( ! gilda_stock_alerts_pending_for( $target_id, 1 ) ) {
			continue;
		}
		if ( ! as_has_scheduled_action( 'gilda_stock_alerts_send', array( $target_id ), 'gilda' ) ) {
			as_enqueue_async_action( 'gilda_stock_alerts_send', array( $target_id ), 'gilda' );
		}
	}
}
add_action( 'woocommerce_product_set_stock_status', 'gilda_stock_alerts_on_stock_status', 10, 2 );
add_action( 'woocommerce_variation_set_stock_status', 'gilda_stock_alerts_on_stock_status', 10, 2 );

/**
 * Envia um lote de avisos para o produto simples/variação. Se sobrar gente, agenda outro lote.
 *
 * @param int $id Produto simples ou variação.
 * @return int Quantos e-mails foram enviados.
 */
function gilda_stock_alerts_send( $id ): int {
	$id      = (int) $id;
	$product = wc_get_product( $id );
	// Pode ter esgotado de novo entre o agendamento e o envio.
	if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
		return 0;
	}

	$mailer = WC()->mailer();
	$emails = $mailer->get_emails();
	$email  = $emails['Gilda_Email_Back_In_Stock'] ?? null;
	if ( ! $email instanceof Gilda_Email_Back_In_Stock || ! $email->is_enabled() ) {
		return 0;
	}

	$sent   = 0;
	$alerts = gilda_stock_alerts_pending_for( $id, GILDA_STOCK_ALERTS_BATCH );
	foreach ( $alerts as $alert ) {
		if ( $email->trigger( $alert ) ) {
			gilda_stock_alerts_mark_sent( (int) $alert->id );
			++$sent;
		}
		// Falha de envio: fica pendente e entra no próximo "voltou ao estoque".
	}

	if ( count( $alerts ) === GILDA_STOCK_ALERTS_BATCH && $sent > 0 ) {
		as_enqueue_async_action( 'gilda_stock_alerts_send', array( $id ), 'gilda' );
	}
	do_action( 'gilda_stock_alerts_batch_sent', $id, $sent );
	return $sent;
}
add_action( 'gilda_stock_alerts_send', 'gilda_stock_alerts_send' );

// Pré-visualização em WooCommerce → Configurações → E-mails: usa uma variação real da loja.
add_filter(
	'woocommerce_prepare_email_for_preview',
	static function ( $email ) {
		// O preview do WooCommerce põe um pedido fictício em ->object; aqui precisamos de um produto.
		if ( $email instanceof Gilda_Email_Back_In_Stock && ! $email->object instanceof WC_Product ) {
			$ids           = wc_get_products( array( 'type' => 'variation', 'limit' => 1, 'return' => 'ids' ) );
			$email->object = $ids ? wc_get_product( $ids[0] ) : null;
			$email->alert  = (object) array( 'id' => 0, 'token' => 'preview', 'created_at' => current_time( 'mysql', true ) );
			if ( $email->object ) {
				$email->placeholders['{product_name}'] = wp_strip_all_tags( wc_get_product( $email->object->get_parent_id() ?: $email->object->get_id() )->get_name() );
			}
		}
		return $email;
	}
);
