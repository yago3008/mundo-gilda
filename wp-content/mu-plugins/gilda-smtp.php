<?php
/**
 * Plugin Name: GILDA; SMTP
 * Description: Envia os e-mails do WordPress pelo SMTP definido nas variáveis de ambiente (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE). Localmente aponta para o Mailpit.
 */

defined( 'ABSPATH' ) || exit;

// Remetente padrão (o "wordpress@localhost" do WP é rejeitado como endereço inválido).
add_filter(
	'wp_mail_from',
	static function ( $from ) {
		$env = getenv( 'MAIL_FROM' );
		return ( $env && str_starts_with( $from, 'wordpress@' ) ) ? $env : $from;
	}
);

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$host = getenv( 'SMTP_HOST' );
		if ( ! $host ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = (int) ( getenv( 'SMTP_PORT' ) ?: 587 );
		$phpmailer->SMTPSecure = getenv( 'SMTP_SECURE' ) ?: '';
		$phpmailer->SMTPAutoTLS = (bool) getenv( 'SMTP_SECURE' );

		$user = getenv( 'SMTP_USER' );
		if ( $user ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = getenv( 'SMTP_PASS' );
		}
	}
);
