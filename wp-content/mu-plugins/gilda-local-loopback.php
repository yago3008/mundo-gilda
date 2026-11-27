<?php
/**
 * Plugin Name: GILDA; Tarefas agendadas no ambiente local
 * Description: Só no ambiente local (Docker). O WordPress roda as tarefas agendadas (WP-Cron e a fila do WooCommerce, que manda por exemplo o e-mail "voltou ao estoque") chamando o próprio site. Dentro do container o site atende na porta 80, mas o endereço do site é http://localhost:8080 (ou 8480…): a chamada falhava e nenhuma tarefa rodava. Aqui essas chamadas ao próprio site vão para a porta 80 do container. Em produção não faz nada.
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() ) {
	return;
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		static $busy = false;
		if ( $busy || false !== $pre ) {
			return $pre;
		}
		$home = wp_parse_url( home_url() );
		$to   = wp_parse_url( $url );
		if ( empty( $home['port'] ) || empty( $to['host'] ) || ( $to['host'] ?? '' ) !== ( $home['host'] ?? '' ) || (int) ( $to['port'] ?? 80 ) !== (int) $home['port'] ) {
			return $pre;
		}
		// O WP-Cron chama com 0,01 s e sem esperar resposta: no container isso nem chegava ao Apache.
		$args['timeout'] = max( 1, (float) ( $args['timeout'] ?? 1 ) );
		$busy            = true;
		$result = wp_remote_request( (string) preg_replace( '~^(https?://[^/:]+):\d+~', '$1', $url ), $args );
		$busy   = false;
		return $result;
	},
	10,
	3
);
