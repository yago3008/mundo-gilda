<?php
/**
 * Avise-me + LGPD: exportar e apagar dados pelas ferramentas de privacidade do WordPress
 * (Ferramentas → Exportar/Apagar dados pessoais) e texto sugerido para a política de privacidade.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $exporters ) {
		$exporters['gilda-stock-alerts'] = array(
			'exporter_friendly_name' => __( 'Avise-me (lista de espera)', 'gilda' ),
			'callback'               => static function ( $email ) {
				global $wpdb;
				$table = gilda_stock_alerts_table();
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", strtolower( trim( $email ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$items = array();
				foreach ( $rows as $row ) {
					$target  = wc_get_product( $row->variation_id ?: $row->product_id );
					$items[] = array(
						'group_id'    => 'gilda-stock-alerts',
						'group_label' => __( 'Avise-me (lista de espera)', 'gilda' ),
						'item_id'     => 'gilda-stock-alert-' . $row->id,
						'data'        => array(
							array( 'name' => __( 'Produto', 'gilda' ), 'value' => $target ? $target->get_name() : '#' . $row->product_id ),
							array( 'name' => __( 'E-mail', 'gilda' ), 'value' => $row->email ),
							array( 'name' => __( 'Situação', 'gilda' ), 'value' => 'sent' === $row->status ? __( 'Avisado', 'gilda' ) : __( 'Esperando', 'gilda' ) ),
							array( 'name' => __( 'Pedido em (UTC)', 'gilda' ), 'value' => $row->created_at ),
							array( 'name' => __( 'Consentimento em (UTC)', 'gilda' ), 'value' => $row->consent_at ),
							array( 'name' => __( 'Avisado em (UTC)', 'gilda' ), 'value' => (string) $row->sent_at ),
						),
					);
				}
				return array( 'data' => $items, 'done' => true );
			},
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $erasers ) {
		$erasers['gilda-stock-alerts'] = array(
			'eraser_friendly_name' => __( 'Avise-me (lista de espera)', 'gilda' ),
			'callback'             => static function ( $email ) {
				global $wpdb;
				$removed = (int) $wpdb->delete( gilda_stock_alerts_table(), array( 'email' => strtolower( trim( $email ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return array(
					'items_removed'  => $removed > 0,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			},
		);
		return $erasers;
	}
);

add_action(
	'admin_init',
	static function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'GILDA; Avise-me',
				wp_kses_post(
					'<p>' . __( 'Quando um produto ou tamanho está esgotado, você pode deixar seu e-mail para ser avisado quando ele voltar. Guardamos o e-mail, o produto escolhido e a data do pedido e do consentimento, só para mandar esse aviso. Depois do aviso, o registro é apagado em até 30 dias; se o produto não voltar, em até 180 dias. Você pode cancelar a qualquer momento pelo link no e-mail ou pedindo à loja.', 'gilda' ) . '</p>'
				)
			);
		}
	}
);
