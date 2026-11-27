<?php
/**
 * Plugin Name: GILDA; E-mails
 * Description: E-mails da loja com o visual da GILDA; (logo, cores, títulos em serifa, rodapé com os Dados da loja) e a tela "E-mails" no painel das donas, para ligar/desligar e editar assunto, título e texto de todos os e-mails que o site manda (WooCommerce, avise-me, Correios e os avisos de senha/e-mail do WordPress), com prévia ao vivo e envio de teste.
 *
 * - Os textos dos e-mails do WooCommerce ficam nas próprias configurações deles (woocommerce_{id}_settings):
 *   a tela só é um jeito simples de editar o que a tela técnica do WooCommerce (bloqueada para as donas) editaria.
 * - "Senha alterada" e "E-mail da conta alterado" são do WordPress (texto puro): aqui ganham o mesmo visual e
 *   textos editáveis (opção gilda_email_{id}).
 * - Tela em gilda-emails/screen.php.
 */

defined( 'ABSPATH' ) || exit;

const GILDA_EMAILS_PAGE    = 'gilda-emails';
const GILDA_EMAILS_VERSION = '1.0.0';

/* -------------------------------------------------------------------------
 * Catálogo: quais e-mails aparecem na tela, com nome e quando saem
 * ---------------------------------------------------------------------- */

/**
 * E-mails editáveis, na ordem da tela: id => [grupo, nome, quando sai, trava (não pode desligar)].
 *
 * @return array<string,array>
 */
function gilda_emails_catalog(): array {
	$c = 'cliente';
	$l = 'loja';
	return array(
		'customer_processing_order'                   => array( $c, 'Pedido confirmado', 'Quando o pagamento é aprovado e o pedido entra em preparação.' ),
		'customer_on_hold_order'                      => array( $c, 'Pedido aguardando pagamento', 'Quando o pedido foi feito, mas o pagamento ainda não caiu (ex.: Pix não pago).' ),
		'customer_completed_order'                    => array( $c, 'Pedido concluído', 'Quando vocês marcam o pedido como concluído.' ),
		'correios_automatico_preparing_to_ship_email' => array( $c, 'Envio: preparando', 'Quando a etiqueta dos Correios é gerada.' ),
		'correios_automatico_tracking_code_email'     => array( $c, 'Envio: em transporte', 'Quando o pacote é postado, com o código de rastreio.' ),
		'correios_automatico_out_for_delivery_email'  => array( $c, 'Envio: saiu para entrega', 'Quando os Correios saem para entregar.' ),
		'correios_automatico_waiting_pickup_email'    => array( $c, 'Envio: aguardando retirada', 'Quando o pacote está esperando na agência.' ),
		'correios_automatico_delivered_email'         => array( $c, 'Envio: entregue', 'Quando os Correios confirmam a entrega.' ),
		'correios_automatico_returning_email'         => array( $c, 'Envio: devolução', 'Quando o pacote está voltando para a loja.' ),
		'customer_refunded_order'                     => array( $c, 'Reembolso', 'Quando um pedido é reembolsado.' ),
		'customer_failed_order'                       => array( $c, 'Pagamento não aprovado', 'Quando o pagamento é recusado.' ),
		'customer_cancelled_order'                    => array( $c, 'Pedido cancelado', 'Quando o pedido é cancelado.' ),
		'customer_note'                               => array( $c, 'Recado no pedido', 'Quando vocês escrevem uma nota para a cliente dentro do pedido.' ),
		'customer_invoice'                            => array( $c, 'Detalhes do pedido', 'Quando vocês mandam os detalhes do pedido pela tela do pedido.' ),
		'gilda_back_in_stock'                         => array( $c, 'Avise-me: voltou ao estoque', 'Quando a peça que a cliente pediu volta ao estoque.' ),
		'customer_new_account'                        => array( $c, 'Boas-vindas', 'Quando a cliente cria a conta.' ),
		'customer_reset_password'                     => array( $c, 'Redefinir senha', 'Quando a cliente pede para trocar a senha esquecida.', true ),
		'customer_verify_email'                       => array( $c, 'Confirmar e-mail', 'Quando é preciso confirmar o e-mail da cliente.', true ),
		'wp_password_change'                          => array( $c, 'Senha alterada', 'Quando a senha da conta é trocada (aviso de segurança).' ),
		'wp_email_change'                             => array( $c, 'E-mail da conta alterado', 'Quando o e-mail da conta é trocado (vai para o e-mail antigo).' ),
		'new_order'                                   => array( $l, 'Novo pedido', 'Para vocês, a cada pedido novo.' ),
		'cancelled_order'                             => array( $l, 'Pedido cancelado', 'Para vocês, quando um pedido é cancelado.' ),
		'failed_order'                                => array( $l, 'Pagamento não aprovado', 'Para vocês, quando um pagamento é recusado.' ),
	);
}

/** E-mail do WooCommerce pelo id, ou null. */
function gilda_emails_wc( string $id ) {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}
	foreach ( WC()->mailer()->get_emails() as $email ) {
		if ( $email->id === $id ) {
			return $email;
		}
	}
	return null;
}

/** É um dos avisos do WordPress (não do WooCommerce)? */
function gilda_emails_is_wp( string $id ): bool {
	return in_array( $id, array( 'wp_password_change', 'wp_email_change' ), true );
}

/* -------------------------------------------------------------------------
 * Avisos do WordPress: textos padrão, editáveis
 * ---------------------------------------------------------------------- */

function gilda_emails_wp_defaults( string $id ): array {
	if ( 'wp_email_change' === $id ) {
		return array(
			'enabled' => 'yes',
			'subject' => 'O e-mail da sua conta foi alterado',
			'heading' => 'e-mail alterado',
			'content' => "Olá, {first_name}.\n\nO e-mail da sua conta na {site_title} foi trocado para {new_email}.\n\nSe não foi você, fale com a gente agora pelo WhatsApp ou respondendo este e-mail.",
		);
	}
	return array(
		'enabled' => 'yes',
		'subject' => 'Sua senha foi alterada',
		'heading' => 'senha alterada',
		'content' => "Olá, {first_name}.\n\nA senha da sua conta na {site_title} acabou de ser alterada.\n\nSe não foi você, fale com a gente agora pelo WhatsApp ou respondendo este e-mail.",
	);
}

function gilda_emails_wp_settings( string $id ): array {
	return array_merge( gilda_emails_wp_defaults( $id ), (array) get_option( 'gilda_email_' . $id, array() ) );
}

/** HTML do aviso do WordPress, no mesmo visual dos e-mails da loja. */
function gilda_emails_wp_html( string $heading, string $content ): string {
	if ( ! function_exists( 'WC' ) ) {
		return nl2br( esc_html( $content ) );
	}
	$mailer = WC()->mailer();
	$body   = wpautop( esc_html( $content ) );
	$html   = $mailer->wrap_message( $heading, $body );
	$email  = new WC_Email();
	return $email->style_inline( $html );
}

/** Troca os {marcadores} dos avisos do WordPress. */
function gilda_emails_wp_replace( string $text, WP_User $user, string $new_email = '' ): string {
	return strtr(
		$text,
		array(
			'{first_name}' => $user->first_name ? $user->first_name : $user->display_name,
			'{site_title}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{new_email}'  => $new_email,
			'{site_url}'   => home_url( '/' ),
		)
	);
}

foreach ( array( 'password_change_email' => 'wp_password_change', 'email_change_email' => 'wp_email_change' ) as $gilda_em_filter => $gilda_em_id ) {
	add_filter(
		$gilda_em_filter,
		static function ( $mail, $user, $userdata ) use ( $gilda_em_id ) {
			$settings = gilda_emails_wp_settings( $gilda_em_id );
			$wp_user  = $user instanceof WP_User ? $user : new WP_User( (int) ( is_array( $user ) ? ( $user['ID'] ?? 0 ) : 0 ) );
			if ( is_array( $user ) ) {
				$wp_user->first_name   = $user['first_name'] ?? $wp_user->first_name;
				$wp_user->display_name = $user['display_name'] ?? $wp_user->display_name;
			}
			$new_email       = is_array( $userdata ) ? (string) ( $userdata['user_email'] ?? '' ) : '';
			$mail['subject'] = gilda_emails_wp_replace( $settings['subject'], $wp_user, $new_email );
			$mail['message'] = gilda_emails_wp_html(
				gilda_emails_wp_replace( $settings['heading'], $wp_user, $new_email ),
				gilda_emails_wp_replace( $settings['content'], $wp_user, $new_email )
			);
			$mail['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );
			return $mail;
		},
		20,
		3
	);
}
unset( $gilda_em_filter, $gilda_em_id );
add_filter( 'send_password_change_email', static fn( $send ) => $send && 'no' !== gilda_emails_wp_settings( 'wp_password_change' )['enabled'], 20 );
add_filter( 'send_email_change_email', static fn( $send ) => $send && 'no' !== gilda_emails_wp_settings( 'wp_email_change' )['enabled'], 20 );

/* -------------------------------------------------------------------------
 * Visual: logo em PNG, títulos em serifa, rodapé com os Dados da loja
 * ---------------------------------------------------------------------- */

/** Logo da loja em PNG (WebP não aparece no Outlook), gerado uma vez por logo. */
function gilda_emails_logo_url(): string {
	$id = function_exists( 'gilda_store_logo_id' ) ? (int) gilda_store_logo_id() : 0;
	if ( ! $id ) {
		return '';
	}
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return (string) wp_get_attachment_url( $id );
	}
	$key    = $id . '-' . (int) filemtime( $file );
	$cached = get_option( 'gilda_email_logo' );
	if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key && ! empty( $cached['url'] ) ) {
		return $cached['url'];
	}
	$url    = (string) wp_get_attachment_url( $id );
	$editor = wp_get_image_editor( $file );
	if ( ! is_wp_error( $editor ) ) {
		$editor->resize( 480, null, false );
		$upload = wp_upload_dir();
		$dest   = trailingslashit( $upload['basedir'] ) . 'gilda-email/logo-' . $key . '.png';
		wp_mkdir_p( dirname( $dest ) );
		// O site converte as imagens geradas para WebP (gilda-images.php); aqui precisa ser PNG.
		$no_webp = static fn() => array();
		add_filter( 'image_editor_output_format', $no_webp, PHP_INT_MAX );
		$saved = $editor->save( $dest, 'image/png' );
		remove_filter( 'image_editor_output_format', $no_webp, PHP_INT_MAX );
		if ( ! is_wp_error( $saved ) ) {
			$url = trailingslashit( $upload['baseurl'] ) . 'gilda-email/' . basename( $saved['path'] );
		}
	}
	update_option( 'gilda_email_logo', array( 'key' => $key, 'url' => $url ), false );
	return $url;
}

add_filter( 'pre_option_woocommerce_email_header_image', static fn( $v ) => gilda_emails_logo_url() ?: $v );
add_filter( 'pre_option_woocommerce_email_header_alignment', static fn() => 'center' );

// Rodapé: assinatura da marca + contatos e empresa dos Dados da loja.
add_filter(
	'woocommerce_email_footer_text',
	static function ( $text ) {
		if ( ! function_exists( 'gilda_store' ) ) {
			return $text;
		}
		$s     = static fn( $k ) => trim( (string) gilda_store( $k ) );
		$lines = array( '<strong>GILDA;</strong> · alfaiataria criativa feita à mão em Curitiba/PR' );
		$contact = array();
		if ( $s( 'instagram' ) && function_exists( 'gilda_store_instagram_url' ) ) {
			$contact[] = '<a href="' . esc_url( gilda_store_instagram_url() ) . '">@' . esc_html( function_exists( 'gilda_store_instagram_handle' ) ? gilda_store_instagram_handle( $s( 'instagram' ) ) : $s( 'instagram' ) ) . '</a>';
		}
		if ( $s( 'whatsapp' ) && function_exists( 'gilda_store_whatsapp_url' ) ) {
			$contact[] = '<a href="' . esc_url( gilda_store_whatsapp_url() ) . '">WhatsApp</a>';
		}
		if ( $s( 'email' ) ) {
			$contact[] = '<a href="mailto:' . esc_attr( $s( 'email' ) ) . '">' . esc_html( $s( 'email' ) ) . '</a>';
		}
		if ( $contact ) {
			$lines[] = implode( ' · ', $contact );
		}
		$company = array_filter( array( $s( 'razao_social' ), $s( 'cnpj' ) ? 'CNPJ ' . $s( 'cnpj' ) : '' ) );
		if ( $company ) {
			$lines[] = '<span style="opacity:.75">' . esc_html( implode( ' · ', $company ) ) . '</span>';
		}
		return implode( '<br>', $lines );
	},
	20
);

// Tipografia e detalhes da marca (os e-mails não carregam as fontes do site: serifa do sistema nos títulos).
add_filter(
	'woocommerce_email_styles',
	static function ( $css ) {
		return $css . '
#template_header_image img { max-width: 170px !important; height: auto !important; }
#template_header_image { padding-top: 32px !important; padding-bottom: 8px !important; }
#template_header h1, #header_wrapper h1, h1 {
	font-family: Georgia, "Times New Roman", serif !important; font-style: italic !important; font-weight: 400 !important;
	text-transform: lowercase; letter-spacing: -0.01em; color: #241A16 !important; font-size: 34px !important; line-height: 1.1 !important;
}
h2, h3 { font-family: Georgia, "Times New Roman", serif !important; font-weight: 400 !important; color: #77270C !important; }
a { color: #77270C !important; }
#template_container { border: 0 !important; box-shadow: none !important; border-top: 4px solid #77270C !important; }
.td, table.td, .td th, .td td { border-color: #D9D3C2 !important; }
#template_footer #credit, #template_footer #credit p { font-size: 12px !important; line-height: 1.7 !important; color: #676767 !important; }
#template_footer #credit a { color: #77270C !important; }
';
	}
);

/* -------------------------------------------------------------------------
 * Tela "E-mails" no painel
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		$hook = add_menu_page( 'E-mails', 'E-mails', 'gilda_painel', GILDA_EMAILS_PAGE, 'gilda_emails_screen', 'dashicons-email', 37 );
		add_action( 'load-' . $hook, 'gilda_emails_load' );
		// No menu enxuto da dona, o ícone desenhado igual aos outros itens.
		global $menu;
		foreach ( (array) $menu as $i => $item ) {
			if ( isset( $item[2] ) && GILDA_EMAILS_PAGE === $item[2] ) {
				$menu[ $i ][4] = trim( ( $item[4] ?? '' ) . ' gilda-ico gilda-ico--emails' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	},
	10000 // depois do menu da dona (gilda-painel/role.php monta o dela na 9999)
);

add_action(
	'admin_head',
	static function () {
		echo '<style>body.gilda-owner #adminmenu li.gilda-ico--emails{--ico:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'black\' stroke-width=\'1.6\'%3E%3Crect x=\'3\' y=\'5\' width=\'18\' height=\'14\' rx=\'1\'/%3E%3Cpath d=\'M3 7l9 6 9-6\'/%3E%3C/svg%3E")}</style>';
	}
);

function gilda_emails_load(): void {
	if ( function_exists( 'gilda_painel_enqueue_ui' ) ) {
		gilda_painel_enqueue_ui();
	}
	wp_enqueue_style( 'gilda-emails', plugins_url( 'gilda-emails/emails.css', __FILE__ ), array(), GILDA_EMAILS_VERSION . '.' . (int) @filemtime( __DIR__ . '/gilda-emails/emails.css' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	wp_enqueue_script( 'gilda-emails', plugins_url( 'gilda-emails/emails.js', __FILE__ ), array(), GILDA_EMAILS_VERSION . '.' . (int) @filemtime( __DIR__ . '/gilda-emails/emails.js' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

function gilda_emails_screen(): void {
	include __DIR__ . '/gilda-emails/screen.php';
}

/** Valores atuais de um e-mail para a tela: enabled, subject, heading, content, recipient (+ padrões). */
function gilda_emails_values( string $id ): array {
	if ( gilda_emails_is_wp( $id ) ) {
		$v             = gilda_emails_wp_settings( $id );
		$v['defaults'] = gilda_emails_wp_defaults( $id );
		return $v;
	}
	$email = gilda_emails_wc( $id );
	if ( ! $email ) {
		return array();
	}
	return array(
		'enabled'   => $email->is_enabled() ? 'yes' : 'no',
		'subject'   => (string) $email->get_option( 'subject', '' ),
		'heading'   => (string) $email->get_option( 'heading', '' ),
		'content'   => (string) $email->get_option( 'additional_content', $email->get_default_additional_content() ),
		'recipient' => $email->is_customer_email() ? '' : (string) $email->get_option( 'recipient', get_option( 'admin_email' ) ),
		'defaults'  => array(
			'subject' => (string) $email->get_default_subject(),
			'heading' => (string) $email->get_default_heading(),
			'content' => (string) $email->get_default_additional_content(),
		),
	);
}

/** Marcadores que dá para usar nos textos. */
function gilda_emails_placeholders( string $id ): array {
	if ( gilda_emails_is_wp( $id ) ) {
		return 'wp_email_change' === $id ? array( '{first_name}', '{site_title}', '{new_email}' ) : array( '{first_name}', '{site_title}' );
	}
	$email = gilda_emails_wc( $id );
	return $email ? array_keys( (array) $email->placeholders ) : array();
}

function gilda_emails_check( string $nonce_action ): void {
	if ( ! current_user_can( 'gilda_painel' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'gilda' ), 403 );
	}
	check_admin_referer( $nonce_action );
}

/** Texto vindo do formulário: assunto/título em uma linha, texto com quebras. */
function gilda_emails_clean( array $src ): array {
	return array(
		'subject'   => sanitize_text_field( wp_unslash( $src['subject'] ?? '' ) ),
		'heading'   => sanitize_text_field( wp_unslash( $src['heading'] ?? '' ) ),
		'content'   => sanitize_textarea_field( wp_unslash( $src['content'] ?? '' ) ),
		'recipient' => implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) wp_unslash( $src['recipient'] ?? '' ) ) ) ) ) ),
	);
}

// Salvar.
add_action(
	'admin_post_gilda_email_save',
	static function () {
		gilda_emails_check( 'gilda_email_form' ); // salvar e testar saem do mesmo formulário
		$id      = sanitize_key( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- verificado acima
		$catalog = gilda_emails_catalog();
		if ( ! isset( $catalog[ $id ] ) ) {
			wp_die( 'E-mail desconhecido.' );
		}
		$in      = gilda_emails_clean( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$locked  = ! empty( $catalog[ $id ][3] );
		$enabled = $locked || ! empty( $_POST['enabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification

		if ( gilda_emails_is_wp( $id ) ) {
			$d = gilda_emails_wp_defaults( $id );
			update_option(
				'gilda_email_' . $id,
				array(
					'enabled' => $enabled,
					'subject' => '' !== $in['subject'] ? $in['subject'] : $d['subject'],
					'heading' => '' !== $in['heading'] ? $in['heading'] : $d['heading'],
					'content' => '' !== $in['content'] ? $in['content'] : $d['content'],
				),
				false
			);
		} else {
			$email = gilda_emails_wc( $id );
			if ( $email ) {
				$settings                       = (array) get_option( $email->get_option_key(), array() );
				$settings['enabled']            = $enabled;
				$settings['subject']            = $in['subject'];   // vazio = volta ao padrão do WooCommerce
				$settings['heading']            = $in['heading'];
				$settings['additional_content'] = $in['content'];
				if ( ! $email->is_customer_email() && '' !== $in['recipient'] ) {
					$settings['recipient'] = $in['recipient'];
				}
				update_option( $email->get_option_key(), $settings );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => GILDA_EMAILS_PAGE, 'email' => $id, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

// Restaurar textos padrão.
add_action(
	'admin_post_gilda_email_reset',
	static function () {
		gilda_emails_check( 'gilda_email_reset' );
		$id = sanitize_key( wp_unslash( $_GET['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( gilda_emails_is_wp( $id ) ) {
			$enabled = gilda_emails_wp_settings( $id )['enabled'];
			update_option( 'gilda_email_' . $id, array( 'enabled' => $enabled ), false );
		} elseif ( $email = gilda_emails_wc( $id ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$settings = (array) get_option( $email->get_option_key(), array() );
			unset( $settings['subject'], $settings['heading'], $settings['additional_content'] );
			update_option( $email->get_option_key(), $settings );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => GILDA_EMAILS_PAGE, 'email' => $id, 'reset' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * HTML de prévia de um e-mail (com os textos que estão no formulário, mesmo antes de salvar).
 *
 * @return array{subject:string,html:string}
 */
function gilda_emails_render_preview( string $id, array $override = array() ): array {
	$override = array_filter( $override, static fn( $v ) => '' !== $v );
	if ( gilda_emails_is_wp( $id ) ) {
		$s          = array_merge( gilda_emails_wp_settings( $id ), $override );
		$user       = wp_get_current_user();
		$user_clone = new WP_User( $user->ID );
		$user_clone->first_name = $user->first_name ? $user->first_name : 'Maria';
		return array(
			'subject' => gilda_emails_wp_replace( $s['subject'], $user_clone, 'novo@email.com' ),
			'html'    => gilda_emails_wp_html( gilda_emails_wp_replace( $s['heading'], $user_clone, 'novo@email.com' ), gilda_emails_wp_replace( $s['content'], $user_clone, 'novo@email.com' ) ),
		);
	}
	$email = gilda_emails_wc( $id );
	if ( ! $email || ! class_exists( '\Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview' ) ) {
		return array( 'subject' => '', 'html' => '' );
	}
	$map    = array( 'subject' => 'subject', 'heading' => 'heading', 'content' => 'additional_content' );
	$filter = static function ( $value, $obj, $v2, $key ) use ( $email, $override, $map ) {
		if ( $obj->id === $email->id ) {
			foreach ( $map as $field => $opt ) {
				if ( $opt === $key && isset( $override[ $field ] ) ) {
					return $override[ $field ];
				}
			}
		}
		return $value;
	};
	add_filter( 'woocommerce_email_get_option', $filter, 99, 4 );
	try {
		$preview = \Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview::instance();
		$preview->set_email_type( get_class( $email ) );
		$html    = $preview->render();
		$subject = $preview->get_subject();
	} catch ( \Throwable $e ) {
		// E-mails de outros plugins podem não ter prévia com pedido de exemplo: mostra título + texto.
		$heading = $override['heading'] ?? $email->get_heading();
		$content = $override['content'] ?? $email->get_additional_content();
		$html    = gilda_emails_wp_html( wp_strip_all_tags( (string) $email->format_string( $heading ) ), wp_strip_all_tags( (string) $email->format_string( $content ) ) );
		$subject = (string) $email->format_string( $override['subject'] ?? $email->get_subject() );
	}
	remove_filter( 'woocommerce_email_get_option', $filter, 99 );
	return array( 'subject' => wp_strip_all_tags( (string) $subject ), 'html' => (string) $html );
}

// Prévia (iframe da tela).
add_action(
	'admin_post_gilda_email_preview',
	static function () {
		if ( ! current_user_can( 'gilda_painel' ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gilda_email_preview' ) ) {
			wp_die( 'Sem permissão.', 403 );
		}
		$id  = sanitize_key( wp_unslash( $_GET['email'] ?? '' ) );
		$out = gilda_emails_render_preview( $id, gilda_emails_clean( $_GET ) );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		// Links da prévia abrem fora da tela.
		echo str_replace( '<head>', '<head><base target="_blank">', $out['html'] ? $out['html'] : '<p style="font-family:sans-serif;padding:24px">Prévia indisponível para este e-mail.</p>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML do próprio e-mail
		exit;
	}
);

// Enviar teste para o e-mail de quem está usando o painel.
add_action(
	'admin_post_gilda_email_test',
	static function () {
		gilda_emails_check( 'gilda_email_form' );
		$id   = sanitize_key( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$to   = wp_get_current_user()->user_email;
		$out  = gilda_emails_render_preview( $id, gilda_emails_clean( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$sent = $out['html'] && $to && wp_mail( $to, '[Teste] ' . $out['subject'], $out['html'], array( 'Content-Type: text/html; charset=UTF-8' ) );
		wp_safe_redirect( add_query_arg( array( 'page' => GILDA_EMAILS_PAGE, 'email' => $id, 'test' => $sent ? 'ok' : 'erro' ), admin_url( 'admin.php' ) ) );
		exit;
	}
);
