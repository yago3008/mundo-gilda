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
 * Etapas do pedido (recebido → pagamento → preparação → enviado), ou '' para pedido falho/cancelado/reembolsado.
 * Usado no "Pedido recebido" e no "Ver pedido" da conta.
 */
function gilda_order_steps_html( WC_Order $order ): string {
	if ( $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) {
		return '';
	}
	$paid  = $order->is_paid();
	$done  = $order->has_status( 'completed' );
	$steps = array(
		array( __( 'Pedido recebido', 'gilda' ), true ),
		array( $paid ? __( 'Pagamento aprovado', 'gilda' ) : __( 'Aguardando pagamento', 'gilda' ), $paid ),
		array( __( 'Em preparação', 'gilda' ), $done ),
		array( __( 'Enviado', 'gilda' ), $done ),
	);
	// A etapa atual é a primeira que ainda não terminou.
	$current = count( $steps ) - 1;
	foreach ( $steps as $i => $step ) {
		if ( ! $step[1] ) {
			$current = $i;
			break;
		}
	}
	ob_start();
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
	<?php
	return gilda_confirmation_compact( (string) ob_get_clean() );
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
		<?php echo $order ? gilda_order_steps_html( $order ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado e escapado em gilda_order_steps_html(). ?>
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

/*
 * ---------- "Ver pedido" da conta (/minha-conta/ver-pedido/{id}/) ----------
 * O template do WooCommerce continua o mesmo (sem cópia no tema); por ganchos entram o topo (nº, situação
 * e etapas), o cartão do rastreio dos Correios e uma marca no total. A frase "O pedido #… foi realizado
 * em…" fica escondida no CSS (o topo diz o mesmo), e o visual dos cartões está no theme.css (.gilda-view-order).
 */

/** Classe no body só nessa tela, para o CSS. */
add_filter(
	'body_class',
	static function ( $classes ) {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
			$classes[] = 'gilda-view-order';
		}
		return $classes;
	}
);

/** Título e frase de cada situação do pedido (em minúsculas, como o "obrigada;"). */
function gilda_view_order_status_copy( WC_Order $order ): array {
	switch ( $order->get_status() ) {
		case 'pending':
			return array( __( 'aguardando pagamento', 'gilda' ), __( 'Assim que o pagamento for confirmado, a gente começa a preparar suas peças.', 'gilda' ) );
		case 'on-hold':
			return array( __( 'aguardando confirmação', 'gilda' ), __( 'Estamos esperando a confirmação do pagamento. Você recebe um e-mail quando ela chegar.', 'gilda' ) );
		case 'processing':
			return array( __( 'em preparação', 'gilda' ), __( 'Pagamento aprovado. Suas peças estão sendo separadas e embaladas com carinho.', 'gilda' ) );
		case 'completed':
			return array( __( 'a caminho', 'gilda' ), __( 'Seu pedido foi enviado. Acompanhe a entrega pelo rastreio abaixo.', 'gilda' ) );
		case 'cancelled':
			return array( __( 'cancelado', 'gilda' ), __( 'Este pedido foi cancelado. Se tiver alguma dúvida, fale com a gente.', 'gilda' ) );
		case 'refunded':
			return array( __( 'reembolsado', 'gilda' ), __( 'O valor deste pedido foi devolvido pela mesma forma de pagamento.', 'gilda' ) );
		case 'failed':
			return array( __( 'pagamento recusado', 'gilda' ), __( 'O pagamento não foi aprovado. Você pode tentar de novo ou falar com a gente.', 'gilda' ) );
		default:
			return array( mb_strtolower( wc_get_order_status_name( $order->get_status() ) ), '' );
	}
}

/** Topo: "Pedido nº · data", a situação em destaque, uma frase e as etapas. */
add_action(
	'woocommerce_view_order',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		list( $title, $lead ) = gilda_view_order_status_copy( $order );
		$count                = $order->get_item_count();
		?>
		<header class="gilda-view-order__hero">
			<p class="gilda-thanks__eyebrow">
				<?php
				/* translators: 1: número do pedido, 2: data */
				echo esc_html( sprintf( __( 'Pedido nº %1$s · %2$s', 'gilda' ), $order->get_order_number(), wc_format_datetime( $order->get_date_created(), 'd/m/Y' ) ) );
				?>
			</p>
			<h2 class="gilda-view-order__title"><?php echo esc_html( $title ); ?><span>;</span></h2>
			<?php if ( $lead ) : ?>
				<p class="gilda-view-order__lead"><?php echo esc_html( $lead ); ?></p>
			<?php endif; ?>
			<?php echo gilda_order_steps_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na função. ?>
			<dl class="gilda-view-order__facts">
				<div><dt><?php esc_html_e( 'Total', 'gilda' ); ?></dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Peças', 'gilda' ); ?></dt><dd><?php echo esc_html( (string) $count ); ?></dd></div>
				<?php if ( $order->get_shipping_method() ) : ?>
					<div><dt><?php esc_html_e( 'Entrega', 'gilda' ); ?></dt><dd><?php echo esc_html( $order->get_shipping_method() ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</header>
		<?php
	},
	1
);

/*
 * Rastreio dos Correios (plugin Infixs, no mesmo gancho com prioridade 5): vai para dentro de um cartão.
 * Sem plugin (ou sem saída), nada é impresso.
 */
add_action(
	'woocommerce_view_order',
	static function () {
		ob_start();
	},
	4
);
add_action(
	'woocommerce_view_order',
	static function () {
		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			echo '<section class="gilda-view-order__card gilda-view-order__track">' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saída do próprio plugin.
		}
	},
	6
);

/** Marca o total na tabela do pedido, para o CSS destacá-lo (a linha não tem classe própria). */
add_filter(
	'woocommerce_get_order_item_totals',
	static function ( $totals ) {
		if ( isset( $totals['order_total']['value'] ) && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
			$totals['order_total']['value'] = '<span class="gilda-order-total">' . $totals['order_total']['value'] . '</span>';
		}
		return $totals;
	}
);
