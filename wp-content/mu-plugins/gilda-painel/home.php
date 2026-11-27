<?php
/**
 * Painel GILDA; — página inicial do painel (protótipo painel-proto/Inicio.dc.html):
 * "olá, <nome>;", atalhos de criar, 4 números de verdade (pedidos para enviar, vendas da semana,
 * combinações acabando, avise-me), "Pedidos para enviar", atalhos e "Acabando no estoque".
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** Status de pedido pago (WooCommerce). */
function gilda_paid_statuses(): array {
	return array( 'wc-processing', 'wc-completed' );
}

/**
 * Números do topo do painel.
 *
 * @return array{to_ship:int,week_total:float,week_count:int,low_stock:int,alerts:int}
 */
function gilda_painel_kpis(): array {
	$kpis = array( 'to_ship' => 0, 'week_total' => 0.0, 'week_count' => 0, 'low_stock' => 0, 'alerts' => 0 );
	if ( function_exists( 'wc_orders_count' ) ) {
		$kpis['to_ship'] = (int) wc_orders_count( 'processing' );
	}
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders(
			array(
				'status'       => gilda_paid_statuses(),
				'date_created' => '>' . ( time() - 7 * DAY_IN_SECONDS ),
				'limit'        => -1,
			)
		);
		foreach ( $orders as $order ) {
			$kpis['week_total'] += (float) $order->get_total();
		}
		$kpis['week_count'] = count( $orders );
	}
	$kpis['low_stock'] = count( gilda_low_stock_items() );
	$kpis['alerts']    = gilda_pending_stock_alerts();
	return $kpis;
}

/** Pedidos "avise-me" esperando (mu-plugin gilda-stock-alerts). */
function gilda_pending_stock_alerts(): int {
	if ( ! function_exists( 'gilda_stock_alerts_table' ) ) {
		return 0;
	}
	global $wpdb;
	$table = gilda_stock_alerts_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabela própria, nome fixo.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
}

/**
 * Combinações (variações ou peças simples) com estoque controlado de 1 ou 2, de peças publicadas.
 *
 * @return array<int,array{label:string,stock:int,url:string}>
 */
function gilda_low_stock_items(): array {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$items = array();
	$ids   = get_posts(
		array(
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => 'publish',
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_manage_stock', 'value' => 'yes' ),
				array( 'key' => '_stock', 'value' => array( 1, 2 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $parent || 'publish' !== $parent->get_status() || $parent->is_type( 'variable' ) && $product === $parent ) {
			continue;
		}
		$label = $parent->get_name();
		if ( $product->is_type( 'variation' ) ) {
			$parts = array();
			$attrs = $product->get_attributes();
			ksort( $attrs ); // cor antes do tamanho
			foreach ( $attrs as $taxonomy => $slug ) {
				$term    = get_term_by( 'slug', $slug, $taxonomy );
				$parts[] = $term ? $term->name : $slug;
			}
			$label .= $parts ? ' · ' . implode( ' · ', $parts ) : '';
		}
		$items[] = array(
			'label' => $label,
			'stock' => (int) $product->get_stock_quantity(),
			'url'   => gilda_peca_edit_url( $parent->get_id() ),
		);
	}
	usort( $items, static fn( $a, $b ) => $a['stock'] <=> $b['stock'] ?: strcmp( $a['label'], $b['label'] ) );
	return $items;
}

/**
 * Itens do pedido em uma linha ("Camisa Grão · Off White · M").
 *
 * @param WC_Order $order Pedido.
 * @return string
 */
function gilda_order_items_line( WC_Order $order ): string {
	$lines = array();
	foreach ( $order->get_items() as $item ) {
		$name    = $item->get_name();
		$product = $item->get_product();
		if ( $product && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$parts  = array( $parent ? $parent->get_name() : $name );
			$attrs  = $product->get_attributes();
			ksort( $attrs ); // cor antes do tamanho
			foreach ( $attrs as $taxonomy => $slug ) {
				$term    = get_term_by( 'slug', $slug, $taxonomy );
				$parts[] = $term ? $term->name : $slug;
			}
			$name = implode( ' · ', $parts );
		}
		$lines[] = $item->get_quantity() > 1 ? $item->get_quantity() . '× ' . $name : $name;
	}
	return implode( ', ', $lines );
}

/** Miniatura da 1ª peça do pedido. */
function gilda_order_thumb( WC_Order $order ): string {
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}
		$image = $product->get_image_id();
		if ( ! $image && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$image  = $parent ? $parent->get_image_id() : 0;
		}
		if ( $image ) {
			return (string) wp_get_attachment_image_url( $image, 'thumbnail' );
		}
	}
	return '';
}

/** R$ 1.234,56 (sem HTML). */
function gilda_money( float $value ): string {
	return 'R$ ' . number_format( $value, 2, ',', '.' );
}

/** Atalho do cupom: o primeiro cupom publicado ("primeiracompra · 10%"). */
function gilda_coupon_summary(): string {
	$coupons = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'ASC' ) );
	if ( ! $coupons || ! class_exists( 'WC_Coupon' ) ) {
		return 'criar um cupom';
	}
	$coupon = new WC_Coupon( $coupons[0]->ID );
	$amount = (float) $coupon->get_amount();
	$value  = 'percent' === $coupon->get_discount_type() ? rtrim( rtrim( number_format( $amount, 2, ',', '' ), '0' ), ',' ) . '%' : gilda_money( $amount );
	return $coupon->get_code() . ' · ' . $value;
}

/** A página. */
function gilda_painel_screen(): void {
	$user     = wp_get_current_user();
	$name     = $user->first_name ? $user->first_name : $user->display_name;
	$kpis     = gilda_painel_kpis();
	$orders   = function_exists( 'wc_get_orders' ) ? wc_get_orders( array( 'status' => 'processing', 'limit' => 6, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
	$low      = array_slice( gilda_low_stock_items(), 0, 6 );
	$chapters = function_exists( 'gilda_chapters' ) ? count( gilda_chapters() ) : 0;
	$date     = wp_date( 'l, j \d\e F' );
	?>
	<div class="wrap gilda-panel gilda-home">
		<header class="gilda-page-head gilda-page-head--split">
			<div>
				<span class="gilda-eyebrow"><?php echo esc_html( mb_convert_case( mb_substr( $date, 0, 1 ), MB_CASE_UPPER ) . mb_substr( $date, 1 ) ); ?></span>
				<h1 class="gilda-display">olá, <?php echo esc_html( mb_strtolower( $name ) ); ?>;</h1>
			</div>
			<div class="gilda-actions">
				<a class="gilda-btn gilda-btn--ghost" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . GILDA_CHAPTER_CPT ) ); ?>">Novo capítulo</a>
				<a class="gilda-btn" href="<?php echo esc_url( gilda_peca_edit_url() ); ?>">+ Nova peça</a>
			</div>
		</header>

		<section class="gilda-kpis" aria-label="Resumo">
			<a class="gilda-kpi gilda-kpi--accent" href="<?php echo esc_url( admin_url( gilda_orders_menu_slug() . ( str_contains( gilda_orders_menu_slug(), '?' ) ? '&' : '?' ) . 'status=wc-processing' ) ); ?>" data-kpi="to-ship">
				<span class="gilda-eyebrow">Para separar e enviar</span>
				<span class="gilda-kpi__value"><?php echo (int) $kpis['to_ship']; ?></span>
				<span class="gilda-kpi__note">pedidos pagos esperando envio</span>
			</a>
			<div class="gilda-kpi" data-kpi="week">
				<span class="gilda-eyebrow">Vendas da semana</span>
				<span class="gilda-kpi__value"><?php echo esc_html( gilda_money( $kpis['week_total'] ) ); ?></span>
				<span class="gilda-kpi__note"><?php echo esc_html( sprintf( _n( '%d pedido', '%d pedidos', $kpis['week_count'], 'gilda' ), $kpis['week_count'] ) ); ?> nos últimos 7 dias</span>
			</div>
			<div class="gilda-kpi" data-kpi="low-stock">
				<span class="gilda-eyebrow">Acabando</span>
				<span class="gilda-kpi__value"><?php echo (int) $kpis['low_stock']; ?></span>
				<span class="gilda-kpi__note">combinações com 1 ou 2 peças</span>
			</div>
			<div class="gilda-kpi" data-kpi="alerts">
				<span class="gilda-eyebrow">Avise-me</span>
				<span class="gilda-kpi__value"><?php echo (int) $kpis['alerts']; ?></span>
				<span class="gilda-kpi__note">clientes esperando peças voltarem</span>
			</div>
		</section>

		<div class="gilda-home__cols">
			<section class="gilda-card gilda-orders" aria-labelledby="gilda-orders-t">
				<div class="gilda-card__head">
					<h2 id="gilda-orders-t" class="gilda-h2">Pedidos para enviar</h2>
					<a class="gilda-link" href="<?php echo esc_url( admin_url( gilda_orders_menu_slug() ) ); ?>">ver todos</a>
				</div>
				<?php if ( ! $orders ) : ?>
					<p class="gilda-empty">Nenhum pedido esperando envio. Quando um pedido for pago, ele aparece aqui.</p>
				<?php else : ?>
					<ul class="gilda-orders__list">
						<?php foreach ( $orders as $order ) : ?>
							<?php
							$thumb    = gilda_order_thumb( $order );
							$customer = trim( $order->get_formatted_billing_full_name() );
							$shipping = $order->get_shipping_method();
							?>
							<li>
								<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
									<?php else : ?>
										<span class="gilda-orders__ph" aria-hidden="true"></span>
									<?php endif; ?>
									<span class="gilda-orders__main">
										<strong>#<?php echo esc_html( $order->get_order_number() ); ?> · <?php echo esc_html( $customer ? $customer : $order->get_billing_email() ); ?></strong>
										<span class="gilda-muted"><?php echo esc_html( gilda_order_items_line( $order ) . ( $shipping ? ' — ' . $shipping : '' ) ); ?></span>
									</span>
									<span class="gilda-orders__total"><?php echo esc_html( gilda_money( (float) $order->get_total() ) ); ?></span>
									<span class="gilda-tag gilda-tag--ok">Pago</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="gilda-shortcuts" aria-labelledby="gilda-shortcuts-t">
				<h2 id="gilda-shortcuts-t" class="gilda-h2">Atalhos</h2>
				<div class="gilda-shortcuts__grid">
					<a class="gilda-shortcut" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>"><strong>Cupons</strong><span><?php echo esc_html( gilda_coupon_summary() ); ?></span></a>
					<a class="gilda-shortcut" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . GILDA_CHAPTER_CPT ) ); ?>"><strong>Capítulos</strong><span><?php echo esc_html( sprintf( '%d no site', $chapters ) ); ?></span></a>
					<a class="gilda-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=gilda-dados-da-loja' ) ); ?>"><strong>Dados da loja</strong><span>contatos e endereço</span></a>
					<a class="gilda-shortcut" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><strong>Páginas</strong><span>políticas, dúvidas</span></a>
				</div>
				<div class="gilda-lowstock">
					<span class="gilda-eyebrow">Acabando no estoque</span>
					<?php if ( ! $low ) : ?>
						<span>Nenhuma combinação com 1 ou 2 peças.</span>
					<?php endif; ?>
					<?php foreach ( $low as $item ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?> — <strong><?php echo esc_html( 1 === $item['stock'] ? 'resta 1' : 'restam ' . $item['stock'] ); ?></strong></a>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
	</div>
	<?php
}
