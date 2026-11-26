<?php
/**
 * Avise-me: tabela própria e acesso aos dados.
 *
 * {prefix}gilda_stock_alerts: um pedido de aviso por (produto, variação, e-mail).
 * status: pending (esperando) | sent (avisado). Cancelamentos e limpezas apagam a linha.
 */

defined( 'ABSPATH' ) || exit;

const GILDA_STOCK_ALERTS_DB_VERSION = '1';

function gilda_stock_alerts_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'gilda_stock_alerts';
}

/**
 * Cria/atualiza a tabela (mu-plugin não tem hook de ativação: confere a versão a cada carga).
 */
function gilda_stock_alerts_install(): void {
	if ( get_option( 'gilda_stock_alerts_db_version' ) === GILDA_STOCK_ALERTS_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = gilda_stock_alerts_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			token char(32) NOT NULL,
			created_at datetime NOT NULL,
			consent_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_variation_email (product_id,variation_id,email),
			KEY status_variation (status,variation_id),
			KEY email (email)
		) {$charset};"
	);
	update_option( 'gilda_stock_alerts_db_version', GILDA_STOCK_ALERTS_DB_VERSION );
}
add_action( 'init', 'gilda_stock_alerts_install', 5 );

/**
 * Grava (ou reativa) um pedido de aviso. Mesmo e-mail na mesma variação não duplica:
 * se já existir, volta para "pending" (caso já tenha sido avisado antes) e renova o consentimento.
 *
 * @return int|false ID do pedido.
 */
function gilda_stock_alerts_add( int $product_id, int $variation_id, string $email ) {
	global $wpdb;
	$table = gilda_stock_alerts_table();
	$now   = current_time( 'mysql', true );
	$email = strtolower( trim( $email ) );

	$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"INSERT INTO {$table} (product_id, variation_id, email, status, token, created_at, consent_at)
			VALUES (%d, %d, %s, 'pending', %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				created_at = IF(status = 'pending', created_at, VALUES(created_at)),
				status = 'pending', sent_at = NULL, consent_at = VALUES(consent_at)",
			$product_id,
			$variation_id,
			$email,
			wp_generate_password( 32, false ),
			$now,
			$now
		)
	);
	if ( false === $ok ) {
		return false;
	}
	return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d AND variation_id = %d AND email = %s", $product_id, $variation_id, $email )
	);
}

function gilda_stock_alerts_get( int $id ): ?object {
	global $wpdb;
	$table = gilda_stock_alerts_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $row ?: null;
}

/**
 * Pedidos esperando aviso para um produto simples ou uma variação.
 *
 * @return object[]
 */
function gilda_stock_alerts_pending_for( int $product_or_variation_id, int $limit = 50 ): array {
	global $wpdb;
	$table = gilda_stock_alerts_table();
	return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE status = 'pending' AND ( variation_id = %d OR ( variation_id = 0 AND product_id = %d ) )
			ORDER BY id ASC LIMIT %d",
			$product_or_variation_id,
			$product_or_variation_id,
			$limit
		)
	);
}

function gilda_stock_alerts_mark_sent( int $id ): void {
	global $wpdb;
	$wpdb->update( gilda_stock_alerts_table(), array( 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

function gilda_stock_alerts_delete( int $id ): void {
	global $wpdb;
	$wpdb->delete( gilda_stock_alerts_table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Quantos pedidos pendentes o e-mail já tem (limite contra abuso).
 */
function gilda_stock_alerts_count_pending_for_email( string $email ): int {
	global $wpdb;
	$table = gilda_stock_alerts_table();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND email = %s", strtolower( trim( $email ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Link de cancelamento (vai no e-mail).
 */
function gilda_stock_alerts_cancel_url( object $alert ): string {
	return add_query_arg(
		array(
			'gilda_stock_alert_cancel' => (int) $alert->id,
			'token'                    => $alert->token,
		),
		home_url( '/' )
	);
}

/*
 * LGPD: limpeza diária. Avisos enviados somem depois de 30 dias; pedidos que nunca foram
 * atendidos, depois de 180. Ajuste com os filtros gilda_stock_alerts_retention_sent_days /
 * gilda_stock_alerts_retention_pending_days.
 */
function gilda_stock_alerts_cleanup(): int {
	global $wpdb;
	$table        = gilda_stock_alerts_table();
	$sent_days    = max( 1, (int) apply_filters( 'gilda_stock_alerts_retention_sent_days', 30 ) );
	$pending_days = max( 1, (int) apply_filters( 'gilda_stock_alerts_retention_pending_days', 180 ) );
	$now          = time();
	$deleted      = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE ( status = 'sent' AND sent_at < %s ) OR ( status = 'pending' AND created_at < %s )",
			gmdate( 'Y-m-d H:i:s', $now - $sent_days * DAY_IN_SECONDS ),
			gmdate( 'Y-m-d H:i:s', $now - $pending_days * DAY_IN_SECONDS )
		)
	);
	// Produtos/variações apagados: os pedidos perdem o sentido.
	$deleted += (int) $wpdb->query( "DELETE a FROM {$table} a LEFT JOIN {$wpdb->posts} p ON p.ID = IF(a.variation_id > 0, a.variation_id, a.product_id) WHERE p.ID IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $deleted;
}
add_action( 'gilda_stock_alerts_cleanup', 'gilda_stock_alerts_cleanup' );
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'gilda_stock_alerts_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gilda_stock_alerts_cleanup' );
		}
	}
);

/**
 * Data gravada em UTC → texto no fuso da loja.
 */
function gilda_stock_alerts_format_date( ?string $gmt, string $format = '' ): string {
	if ( ! $gmt ) {
		return '—';
	}
	return wc_format_datetime( wc_string_to_datetime( str_replace( ' ', 'T', $gmt ) . 'Z' ), $format );
}
