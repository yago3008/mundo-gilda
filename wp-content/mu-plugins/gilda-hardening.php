<?php
/**
 * Plugin Name: GILDA; Segurança
 * Description: Endurecimento básico: sem editor de arquivos no painel, sem XML-RPC, sem enumeração de usuários, cabeçalhos de segurança e limite de tentativas de login.
 *
 * Variáveis de ambiente opcionais:
 *   GILDA_LOGIN_MAX_ATTEMPTS   tentativas antes de bloquear (padrão 5)
 *   GILDA_LOGIN_LOCKOUT_MIN    minutos de bloqueio (padrão 15)
 *   GILDA_CLIENT_IP_HEADER     cabeçalho com o IP real quando há proxy/CDN na frente
 *                              (ex.: HTTP_CF_CONNECTING_IP na Cloudflare). Sem isso usa REMOTE_ADDR.
 */

defined( 'ABSPATH' ) || exit;

// Editor de temas/plugins no painel desligado (código só entra pelo git).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Sem versão do WordPress no HTML/feeds.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// XML-RPC: não usamos (era alvo clássico de força bruta).
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
add_action(
	'init',
	static function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			exit;
		}
	}
);

// Enumeração de usuários: /?author=1, /author/<login>/ e /wp-json/wp/v2/users para visitantes.
// Roda antes do redirect canônico do WP (que levaria ?author=1 para /author/<login>/).
add_action(
	'parse_request',
	static function ( $wp ) {
		if ( is_admin() || current_user_can( 'list_users' ) ) {
			return;
		}
		if ( isset( $_GET['author'] ) || ! empty( $wp->query_vars['author'] ) || ! empty( $wp->query_vars['author_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);
add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);
// Mensagem de erro de login genérica (não revela se o e-mail existe), exceto o aviso de bloqueio.
add_filter(
	'login_errors',
	static function ( $message ) {
		$state = get_transient( gilda_login_key() );
		if ( is_array( $state ) && ! empty( $state['locked_until'] ) && $state['locked_until'] > time() ) {
			return $message;
		}
		return __( 'E-mail/usuário ou senha incorretos.', 'gilda' );
	}
);

// Cabeçalhos de segurança.
add_action(
	'send_headers',
	static function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=()' );
		if ( is_ssl() && 'production' === wp_get_environment_type() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
);

/* ---------------------------------------------------------------------------
 * Limite de tentativas de login (por IP). Vale para wp-login.php e "Minha conta".
 * ------------------------------------------------------------------------ */

function gilda_client_ip(): string {
	$header = (string) getenv( 'GILDA_CLIENT_IP_HEADER' );
	$raw    = ( $header && ! empty( $_SERVER[ $header ] ) ) ? $_SERVER[ $header ] : ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$ip     = trim( explode( ',', (string) $raw )[0] );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function gilda_login_key(): string {
	return 'gilda_login_' . md5( gilda_client_ip() );
}

add_filter(
	'authenticate',
	static function ( $user, $username ) {
		if ( empty( $username ) ) {
			return $user;
		}
		$state = get_transient( gilda_login_key() );
		if ( is_array( $state ) && ! empty( $state['locked_until'] ) && $state['locked_until'] > time() ) {
			$minutes = (int) ceil( ( $state['locked_until'] - time() ) / 60 );
			return new WP_Error(
				'gilda_locked',
				sprintf(
					/* translators: %d: minutes */
					_n( 'Muitas tentativas de login. Tente de novo em %d minuto.', 'Muitas tentativas de login. Tente de novo em %d minutos.', $minutes, 'gilda' ),
					$minutes
				)
			);
		}
		return $user;
	},
	30,
	2
);

add_action(
	'wp_login_failed',
	static function () {
		$max     = (int) ( getenv( 'GILDA_LOGIN_MAX_ATTEMPTS' ) ?: 5 );
		$lockout = (int) ( getenv( 'GILDA_LOGIN_LOCKOUT_MIN' ) ?: 15 ) * MINUTE_IN_SECONDS;
		$state   = get_transient( gilda_login_key() );
		$state   = is_array( $state ) ? $state : array( 'fails' => 0, 'locked_until' => 0 );

		++$state['fails'];
		if ( $state['fails'] >= $max ) {
			$state = array( 'fails' => 0, 'locked_until' => time() + $lockout );
		}
		set_transient( gilda_login_key(), $state, $lockout );
	}
);

add_action(
	'wp_login',
	static function () {
		delete_transient( gilda_login_key() );
	}
);
