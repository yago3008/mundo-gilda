<?php
/**
 * Avise-me: tela WooCommerce → Avise-me.
 *
 * - Resumo: quantas pessoas esperam cada produto/cor/tamanho, com o estoque atual.
 * - Detalhe (?product_id=): os e-mails, com "apagar" e "enviar agora".
 */

defined( 'ABSPATH' ) || exit;

const GILDA_STOCK_ALERTS_PAGE = 'gilda-stock-alerts';

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'woocommerce',
			__( 'Avise-me', 'gilda' ),
			__( 'Avise-me', 'gilda' ),
			'manage_woocommerce',
			GILDA_STOCK_ALERTS_PAGE,
			'gilda_stock_alerts_admin_page'
		);
	},
	60
);

function gilda_stock_alerts_admin_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => GILDA_STOCK_ALERTS_PAGE ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Estoque atual para exibição: "3 em estoque" / "Esgotado".
 */
function gilda_stock_alerts_admin_stock( ?WC_Product $product ): string {
	if ( ! $product ) {
		return '<em>' . esc_html__( 'removido', 'gilda' ) . '</em>';
	}
	if ( ! $product->is_in_stock() ) {
		return '<mark class="outofstock">' . esc_html__( 'Esgotado', 'gilda' ) . '</mark>';
	}
	$qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
	/* translators: %d: quantidade */
	return '<mark class="instock">' . esc_html( null === $qty ? __( 'Em estoque', 'gilda' ) : sprintf( __( '%d em estoque', 'gilda' ), $qty ) ) . '</mark>';
}

function gilda_stock_alerts_admin_variation_label( int $variation_id ): string {
	if ( ! $variation_id ) {
		return '—';
	}
	$variation = wc_get_product( $variation_id );
	return $variation instanceof WC_Product_Variation ? wc_get_formatted_variation( $variation, true, false, false ) : '#' . $variation_id;
}

// Ações (apagar / enviar agora).
add_action(
	'admin_post_gilda_stock_alert_admin',
	static function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'gilda' ), 403 );
		}
		check_admin_referer( 'gilda_stock_alert_admin' );
		$do   = sanitize_key( wp_unslash( $_REQUEST['do'] ?? '' ) );
		$id   = absint( $_REQUEST['id'] ?? 0 );
		$back = gilda_stock_alerts_admin_url( array_filter( array( 'product_id' => absint( $_REQUEST['product_id'] ?? 0 ) ) ) );
		$msg  = '';

		if ( 'delete' === $do && $id ) {
			gilda_stock_alerts_delete( $id );
			$msg = 'deleted';
		} elseif ( 'send' === $do && $id ) {
			// $id = produto simples ou variação.
			$msg = 'sent-' . gilda_stock_alerts_send( $id );
		}
		wp_safe_redirect( add_query_arg( 'msg', $msg, $back ) );
		exit;
	}
);

function gilda_stock_alerts_admin_action_url( string $do, int $id, int $product_id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'     => 'gilda_stock_alert_admin',
				'do'         => $do,
				'id'         => $id,
				'product_id' => $product_id,
			),
			admin_url( 'admin-post.php' )
		),
		'gilda_stock_alert_admin'
	);
}

function gilda_stock_alerts_admin_page(): void {
	global $wpdb;
	$table      = gilda_stock_alerts_table();
	$product_id = absint( $_GET['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg        = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

	echo '<style>.gilda-stock-alerts-admin mark{background:none;font-weight:600}.gilda-stock-alerts-admin mark.instock{color:#008a20}.gilda-stock-alerts-admin mark.outofstock{color:#b32d2e}</style>';
	echo '<div class="wrap gilda-stock-alerts-admin"><h1 class="wp-heading-inline">' . esc_html__( 'Avise-me', 'gilda' ) . '</h1>';
	if ( $product_id ) {
		echo ' <a class="page-title-action" href="' . esc_url( gilda_stock_alerts_admin_url() ) . '">' . esc_html__( '← Todos os produtos', 'gilda' ) . '</a>';
	}
	echo '<hr class="wp-header-end">';

	if ( 'deleted' === $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pedido de aviso apagado.', 'gilda' ) . '</p></div>';
	} elseif ( str_starts_with( $msg, 'sent-' ) ) {
		$n = (int) substr( $msg, 5 );
		/* translators: %d: quantidade */
		$text = $n ? sprintf( _n( '%d aviso enviado.', '%d avisos enviados.', $n, 'gilda' ), $n ) : __( 'Nenhum aviso enviado (o item está esgotado, o e-mail está desativado ou não há ninguém esperando).', 'gilda' );
		echo '<div class="notice notice-' . ( $n ? 'success' : 'warning' ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}

	$email_settings = admin_url( 'admin.php?page=wc-settings&tab=email&section=gilda_email_back_in_stock' );
	echo '<p>' . wp_kses_post(
		sprintf(
			/* translators: %s: link das configurações do e-mail */
			__( 'Quem pediu aviso de um item esgotado recebe um e-mail automático assim que o estoque volta (texto e ativação em <a href="%s">Configurações → E-mails → Avise-me</a>). Avisos enviados são apagados depois de 30 dias e pedidos não atendidos depois de 180 (LGPD).', 'gilda' ),
			esc_url( $email_settings )
		)
	) . '</p>';

	if ( $product_id ) {
		gilda_stock_alerts_admin_detail( $product_id );
		echo '</div>';
		return;
	}

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT product_id, variation_id,
			SUM(status = 'pending') AS pending,
			SUM(status = 'sent') AS sent,
			MIN(CASE WHEN status = 'pending' THEN created_at END) AS oldest
		FROM {$table}
		GROUP BY product_id, variation_id
		ORDER BY product_id, variation_id"
	);

	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'Ninguém pediu aviso ainda.', 'gilda' ) . '</em></p></div>';
		return;
	}

	// Agrupa por produto, com o produto mais esperado primeiro.
	$products = array();
	foreach ( $rows as $row ) {
		$pid = (int) $row->product_id;
		$products[ $pid ]['rows'][]  = $row;
		$products[ $pid ]['pending'] = ( $products[ $pid ]['pending'] ?? 0 ) + (int) $row->pending;
	}
	uasort( $products, static fn( $a, $b ) => $b['pending'] <=> $a['pending'] );

	echo '<table class="widefat striped gilda-stock-alerts-summary"><thead><tr>';
	echo '<th>' . esc_html__( 'Produto', 'gilda' ) . '</th>';
	echo '<th>' . esc_html__( 'Cor / tamanho', 'gilda' ) . '</th>';
	echo '<th>' . esc_html__( 'Estoque agora', 'gilda' ) . '</th>';
	echo '<th style="text-align:right">' . esc_html__( 'Esperando', 'gilda' ) . '</th>';
	echo '<th style="text-align:right">' . esc_html__( 'Já avisados', 'gilda' ) . '</th>';
	echo '<th>' . esc_html__( 'Pedido mais antigo', 'gilda' ) . '</th>';
	echo '<th></th>';
	echo '</tr></thead><tbody>';

	foreach ( $products as $pid => $group ) {
		$product = wc_get_product( $pid );
		$name    = $product ? $product->get_name() : '#' . $pid;
		$first   = true;
		foreach ( $group['rows'] as $row ) {
			$vid    = (int) $row->variation_id;
			$target = wc_get_product( $vid ?: $pid );
			echo '<tr>';
			if ( $first ) {
				printf(
					'<td rowspan="%d"><strong><a href="%s">%s</a></strong><br><span class="description">%s</span></td>',
					count( $group['rows'] ),
					esc_url( gilda_stock_alerts_admin_url( array( 'product_id' => $pid ) ) ),
					esc_html( $name ),
					/* translators: %d: pessoas */
					esc_html( sprintf( _n( '%d pessoa esperando', '%d pessoas esperando', $group['pending'], 'gilda' ), $group['pending'] ) )
				);
				$first = false;
			}
			echo '<td>' . esc_html( gilda_stock_alerts_admin_variation_label( $vid ) ) . '</td>';
			echo '<td>' . wp_kses_post( gilda_stock_alerts_admin_stock( $target ?: null ) ) . '</td>';
			echo '<td style="text-align:right"><strong>' . (int) $row->pending . '</strong></td>';
			echo '<td style="text-align:right">' . (int) $row->sent . '</td>';
			echo '<td>' . esc_html( gilda_stock_alerts_format_date( $row->oldest ) ) . '</td>';
			echo '<td>';
			if ( (int) $row->pending > 0 && $target && $target->is_in_stock() ) {
				echo '<a class="button button-small" href="' . esc_url( gilda_stock_alerts_admin_action_url( 'send', $vid ?: $pid, 0 ) ) . '">' . esc_html__( 'Enviar avisos agora', 'gilda' ) . '</a>';
			}
			echo '</td></tr>';
		}
	}
	echo '</tbody></table></div>';
}

function gilda_stock_alerts_admin_detail( int $product_id ): void {
	global $wpdb;
	$table   = gilda_stock_alerts_table();
	$product = wc_get_product( $product_id );
	$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d ORDER BY variation_id, status, created_at", $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	echo '<h2>' . esc_html( $product ? $product->get_name() : '#' . $product_id );
	if ( $product ) {
		echo ' <a class="button button-small" href="' . esc_url( get_edit_post_link( $product_id ) ) . '">' . esc_html__( 'Editar produto / estoque', 'gilda' ) . '</a>';
	}
	echo '</h2>';

	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'Nenhum pedido de aviso para este produto.', 'gilda' ) . '</em></p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array( __( 'E-mail', 'gilda' ), __( 'Cor / tamanho', 'gilda' ), __( 'Estoque agora', 'gilda' ), __( 'Situação', 'gilda' ), __( 'Pedido em', 'gilda' ), __( 'Avisado em', 'gilda' ), '' ) as $th ) {
		echo '<th>' . esc_html( $th ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$target = wc_get_product( (int) $row->variation_id ?: $product_id );
		echo '<tr>';
		echo '<td>' . esc_html( $row->email ) . '</td>';
		echo '<td>' . esc_html( gilda_stock_alerts_admin_variation_label( (int) $row->variation_id ) ) . '</td>';
		echo '<td>' . wp_kses_post( gilda_stock_alerts_admin_stock( $target ?: null ) ) . '</td>';
		echo '<td>' . esc_html( 'sent' === $row->status ? __( 'Avisado', 'gilda' ) : __( 'Esperando', 'gilda' ) ) . '</td>';
		echo '<td>' . esc_html( gilda_stock_alerts_format_date( $row->created_at, 'd/m/Y H:i' ) ) . '</td>';
		echo '<td>' . esc_html( gilda_stock_alerts_format_date( $row->sent_at, 'd/m/Y H:i' ) ) . '</td>';
		printf(
			'<td><a href="%s" class="submitdelete" onclick="return confirm(%s)">%s</a></td>',
			esc_url( gilda_stock_alerts_admin_action_url( 'delete', (int) $row->id, $product_id ) ),
			esc_attr( wp_json_encode( __( 'Apagar este pedido de aviso?', 'gilda' ) ) ),
			esc_html__( 'Apagar', 'gilda' )
		);
		echo '</tr>';
	}
	echo '</tbody></table>';
}
