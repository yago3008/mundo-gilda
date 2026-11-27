<?php
/**
 * "Pedido recebido" (templates/order-confirmation.html): topo com "obrigada;", número do pedido e as etapas,
 * e o cartão de resumo ao lado, com os botões. Os detalhes (peças, endereços, CPF) continuam nos blocos do
 * WooCommerce; aqui só o que eles não têm.
 *
 * Os dados do pedido só aparecem para quem pode vê-lo (a chave do pedido no endereço, como no link do
 * e-mail, ou a própria cliente logada), igual à regra dos blocos do WooCommerce.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/** Pedido da página "Pedido recebido", se quem está vendo pode ver; senão null. */
function gilda_confirmation_order() {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return null;
	}
	$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
	if ( ! $order ) {
		return null;
	}
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$is_owner = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
	return ( $key && hash_equals( $order->get_order_key(), $key ) ) || $is_owner ? $order : null;
}

/** HTML sem quebras entre tags (o bloco de shortcode passa pelo wpautop, que enfiaria <br>/<p>). */
function gilda_confirmation_compact( string $html ): string {
	return (string) preg_replace( '/>\s+</', '><', trim( $html ) );
}

/**
 * [gilda_order_hero] — "obrigada;", número/data e as etapas do pedido.
 *
 * @return string
 */
function gilda_shortcode_order_hero() {
	$order = gilda_confirmation_order();
	ob_start();
	?>
	<section class="gilda-thanks__hero">
		<?php if ( $order ) : ?>
			<p class="gilda-thanks__eyebrow">
				<?php
				/* translators: 1: número do pedido, 2: data */
				echo esc_html( sprintf( __( 'Pedido nº %1$s · %2$s', 'gilda' ), $order->get_order_number(), wc_format_datetime( $order->get_date_created(), 'd/m/Y' ) ) );
				?>
			</p>
		<?php endif; ?>
		<h1 class="gilda-thanks__title"><?php echo $order && $order->has_status( 'failed' ) ? esc_html__( 'quase lá', 'gilda' ) : esc_html__( 'obrigada', 'gilda' ); ?><span>;</span></h1>
		<p class="gilda-thanks__lead">
			<?php
			if ( $order && $order->has_status( 'failed' ) ) {
				esc_html_e( 'O pagamento não foi aprovado. Seu pedido está guardado: tente pagar de novo pelo link do e-mail ou fale com a gente.', 'gilda' );
			} elseif ( $order ) {
				/* translators: %s: e-mail da cliente */
				echo esc_html( sprintf( __( 'Um novo capítulo começa. Enviamos a confirmação para %s.', 'gilda' ), $order->get_billing_email() ) );
			} else {
				esc_html_e( 'Um novo capítulo começa. Enviamos a confirmação do pedido para o seu e-mail.', 'gilda' );
			}
			?>
		</p>
		<?php
		if ( $order && ! $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) :
			$paid  = $order->is_paid();
			$done  = $order->has_status( 'completed' );
			$steps = array(
				array( __( 'Pedido recebido', 'gilda' ), true ),
				array( $paid ? __( 'Pagamento aprovado', 'gilda' ) : __( 'Aguardando pagamento', 'gilda' ), $paid ),
				array( __( 'Em preparação', 'gilda' ), $done ),
				array( __( 'Enviado', 'gilda' ), $done ),
			);
			// A etapa atual é a primeira que ainda não terminou.
			$current = 0;
			foreach ( $steps as $i => $step ) {
				if ( ! $step[1] ) {
					$current = $i;
					break;
				}
				$current = $i;
			}
			?>
			<ol class="gilda-thanks__steps" aria-label="<?php esc_attr_e( 'Andamento do pedido', 'gilda' ); ?>">
				<?php foreach ( $steps as $i => $step ) : ?>
					<?php $state = $step[1] ? 'is-done' : ( $i === $current ? 'is-current' : '' ); ?>
					<li class="<?php echo esc_attr( $state ); ?>"<?php echo $i === $current && ! $step[1] ? ' aria-current="step"' : ''; ?>>
						<span class="gilda-thanks__dot" aria-hidden="true"></span>
						<span class="gilda-thanks__step"><?php echo esc_html( $step[0] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php
	return gilda_confirmation_compact( (string) ob_get_clean() );
}
add_shortcode( 'gilda_order_hero', 'gilda_shortcode_order_hero' );

/**
 * [gilda_order_aside] — resumo (total em destaque, pagamento, entrega, e-mail) e os próximos passos.
 *
 * @return string
 */
function gilda_shortcode_order_aside() {
	$order = gilda_confirmation_order();
	if ( ! $order ) {
		return '';
	}
	$track = is_user_logged_in()
		? wc_get_endpoint_url( 'view-order', (string) $order->get_id(), wc_get_page_permalink( 'myaccount' ) )
		: home_url( '/rastrear-encomenda/' );
	$rows  = array_filter(
		array(
			__( 'Pagamento', 'gilda' ) => $order->get_payment_method_title(),
			__( 'Entrega', 'gilda' )   => $order->get_shipping_method(),
			__( 'E-mail', 'gilda' )    => $order->get_billing_email(),
		)
	);
	ob_start();
	?>
	<aside class="gilda-thanks__card" aria-labelledby="gilda-thanks-total">
		<p class="gilda-thanks__card-label" id="gilda-thanks-total"><?php esc_html_e( 'Total do pedido', 'gilda' ); ?></p>
		<p class="gilda-thanks__total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
		<dl class="gilda-thanks__facts">
			<?php foreach ( $rows as $label => $value ) : ?>
				<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( wp_strip_all_tags( (string) $value ) ); ?></dd></div>
			<?php endforeach; ?>
		</dl>
		<div class="gilda-thanks__actions">
			<a class="gilda-thanks__btn" href="<?php echo esc_url( $track ); ?>"><?php esc_html_e( 'Acompanhar meu pedido', 'gilda' ); ?></a>
			<a class="gilda-thanks__btn gilda-thanks__btn--ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Continuar comprando', 'gilda' ); ?></a>
		</div>
		<p class="gilda-thanks__help">
			<?php
			$wa = function_exists( 'gilda_store_whatsapp_url' ) ? gilda_store_whatsapp_url() : '';
			if ( $wa ) {
				/* translators: %s: link do WhatsApp */
				echo wp_kses_post( sprintf( __( 'Alguma dúvida? Fale com a gente pelo <a href="%s" target="_blank" rel="noopener">WhatsApp</a>.', 'gilda' ), esc_url( $wa ) ) );
			} else {
				esc_html_e( 'Alguma dúvida? Responda o e-mail de confirmação.', 'gilda' );
			}
			?>
		</p>
	</aside>
	<?php
	return gilda_confirmation_compact( (string) ob_get_clean() );
}
add_shortcode( 'gilda_order_aside', 'gilda_shortcode_order_aside' );
