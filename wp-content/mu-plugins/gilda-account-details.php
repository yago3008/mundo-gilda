<?php
/**
 * Plugin Name: GILDA; Detalhes da conta
 * Description: "Minha conta" → Detalhes da conta sem "Nome de exibição" (é sempre o primeiro nome) e com "Telefone para contato"; a troca de senha vira o item próprio "Senha" no menu da conta.
 *
 * - Nome de exibição = primeiro nome, sempre: ao salvar a conta, no cadastro, no checkout e em qualquer
 *   mudança do primeiro nome (e uma vez para quem já tinha conta).
 * - Telefone: salvo como billing_phone (o checkout já vem preenchido). Opcional; se preenchido, precisa
 *   ter DDD + número (10 ou 11 dígitos). Sai dos formulários de endereço da conta (fica só aqui).
 * - Senha: endpoint /minha-conta/senha/ com senha atual + nova + confirmação. A troca usa wp_update_user
 *   (a sessão continua; o WordPress manda o e-mail de aviso).
 * - Regra de senha do site inteiro: no mínimo 6 caracteres, sem exigir "senha forte" (o medidor de força do
 *   WooCommerce sai do cadastro, do checkout, da redefinição e da troca de senha).
 * - Os formulários ficam em gilda-account-details/ (o original do WooCommerce não tem gancho para tirar
 *   o nome de exibição nem a senha). O visual da área logada continua em gilda-account-dashboard.php.
 */

defined( 'ABSPATH' ) || exit;

const GILDA_ACCOUNT_PASSWORD_ENDPOINT = 'senha';
const GILDA_ACCOUNT_DETAILS_VERSION   = '1';
const GILDA_PASSWORD_MIN_LENGTH       = 6;

/* -------------------------------------------------------------------------
 * Senha: mínimo de 6 caracteres, sem "senha forte"
 * ---------------------------------------------------------------------- */

/** Mensagem de erro se a senha for curta demais; '' se está ok. */
function gilda_password_too_short( $password ) {
	return mb_strlen( (string) $password ) < GILDA_PASSWORD_MIN_LENGTH
		/* translators: %d: mínimo de caracteres */
		? sprintf( __( 'A senha precisa ter pelo menos %d caracteres.', 'gilda' ), GILDA_PASSWORD_MIN_LENGTH )
		: '';
}

// Sem o medidor de força (ele bloqueava o botão até a senha ser "forte").
add_action(
	'wp_print_scripts',
	static function () {
		if ( ! is_admin() ) {
			wp_dequeue_script( 'wc-password-strength-meter' );
		}
	},
	100
);
add_filter( 'woocommerce_min_password_strength', static fn() => 0 );

// Cadastro em "Minha conta" e criação de conta no checkout.
add_action(
	'woocommerce_register_post',
	static function ( $username, $email, $errors ) {
		if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- o WooCommerce já verificou o nonce; senha não é limpa
		$pass = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ( isset( $_POST['account_password'] ) ? (string) wp_unslash( $_POST['account_password'] ) : '' );
		$msg  = '' !== $pass ? gilda_password_too_short( $pass ) : '';
		if ( $msg ) {
			$errors->add( 'gilda_password_length', $msg );
		}
	},
	10,
	3
);
add_action(
	'woocommerce_after_checkout_validation',
	static function ( $data, $errors ) {
		if ( empty( $data['createaccount'] ) || 'yes' === get_option( 'woocommerce_registration_generate_password' ) ) {
			return;
		}
		$msg = gilda_password_too_short( $data['account_password'] ?? '' );
		if ( $msg ) {
			$errors->add( 'gilda_password_length', $msg );
		}
	},
	10,
	2
);

// "Esqueci a senha" → nova senha.
add_action(
	'validate_password_reset',
	static function ( $errors ) {
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- o WooCommerce já verificou; senha não é limpa
		$pass = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
		$msg  = '' !== $pass ? gilda_password_too_short( $pass ) : '';
		if ( $msg ) {
			$errors->add( 'gilda_password_length', $msg );
		}
	}
);

/* -------------------------------------------------------------------------
 * Telefone
 * ---------------------------------------------------------------------- */

/** Só os dígitos do telefone, sem o 55 do país. */
function gilda_account_phone_digits( $value ) {
	$digits = preg_replace( '/\D+/', '', (string) $value );
	if ( strlen( $digits ) > 11 && str_starts_with( $digits, '55' ) ) {
		$digits = substr( $digits, 2 );
	}
	return $digits;
}

/** (41) 99999-9999 / (41) 3333-4444; outro formato fica como veio. */
function gilda_account_format_phone( $value ) {
	$d = gilda_account_phone_digits( $value );
	if ( 11 === strlen( $d ) ) {
		return sprintf( '(%s) %s-%s', substr( $d, 0, 2 ), substr( $d, 2, 5 ), substr( $d, 7 ) );
	}
	if ( 10 === strlen( $d ) ) {
		return sprintf( '(%s) %s-%s', substr( $d, 0, 2 ), substr( $d, 2, 4 ), substr( $d, 6 ) );
	}
	return trim( (string) $value );
}

/* -------------------------------------------------------------------------
 * Formulário "Detalhes da conta" e "Senha" (modelos próprios)
 * ---------------------------------------------------------------------- */
add_filter(
	'woocommerce_locate_template',
	static function ( $template, $template_name ) {
		if ( 'myaccount/form-edit-account.php' === $template_name ) {
			return __DIR__ . '/gilda-account-details/form-edit-account.php';
		}
		return $template;
	},
	20,
	2
);

// Nome de exibição não é mais pedido…
add_filter(
	'woocommerce_save_account_details_required_fields',
	static function ( $fields ) {
		unset( $fields['account_display_name'] );
		return $fields;
	}
);

// …e é sempre o primeiro nome; telefone validado (antes de salvar).
add_action(
	'woocommerce_save_account_details_errors',
	static function ( $errors, $user ) {
		if ( ! empty( $user->first_name ) ) {
			$user->display_name = $user->first_name;
		}
		// phpcs:ignore WordPress.Security.NonceVerification -- o WooCommerce já verificou o nonce do formulário.
		$phone = isset( $_POST['account_phone'] ) ? gilda_account_phone_digits( wp_unslash( $_POST['account_phone'] ) ) : '';
		if ( '' !== $phone && ! in_array( strlen( $phone ), array( 10, 11 ), true ) ) {
			$errors->add( 'account_phone', __( 'Confira o telefone: use o DDD e o número, por exemplo (41) 99999-9999.', 'gilda' ), array( 'id' => 'account_phone' ) );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_save_account_details',
	static function ( $user_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- idem.
		if ( ! isset( $_POST['account_phone'] ) ) {
			return;
		}
		$phone = gilda_account_phone_digits( wp_unslash( $_POST['account_phone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		update_user_meta( $user_id, 'billing_phone', $phone ? gilda_account_format_phone( $phone ) : '' );
	}
);

// Endereços em "Minha conta" sem o telefone: ele fica em "Detalhes da conta" (o checkout continua pedindo).
// Fora da lista de campos, o WooCommerce nem valida nem apaga o telefone ao salvar o endereço.
// Prioridade 200: o plugin de frete (woo-better-shipping-calculator-for-brazil) refaz esses campos na 99/100.
function gilda_account_address_without_phone( $fields ) {
	if ( is_admin() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
		return $fields;
	}
	foreach ( array_keys( $fields ) as $key ) {
		if ( preg_match( '/^(billing|shipping)_(cell)?phone(_country)?$/', (string) $key ) ) {
			unset( $fields[ $key ] );
		}
	}
	return $fields;
}
add_filter( 'woocommerce_billing_fields', 'gilda_account_address_without_phone', 200 );
add_filter( 'woocommerce_shipping_fields', 'gilda_account_address_without_phone', 200 );

/* -------------------------------------------------------------------------
 * Nome de exibição = primeiro nome, em qualquer lugar
 * ---------------------------------------------------------------------- */

/** Aplica o primeiro nome como nome de exibição (sem passar por wp_update_user: nada de e-mails/ganchos em cascata). */
function gilda_account_display_first_name( $user_id, $first = null ) {
	$user_id = (int) $user_id;
	$first   = trim( (string) ( null === $first ? get_user_meta( $user_id, 'first_name', true ) : $first ) );
	if ( ! $user_id || '' === $first ) {
		return;
	}
	$user = get_userdata( $user_id );
	if ( ! $user || $user->display_name === $first ) {
		return;
	}
	global $wpdb;
	$wpdb->update( $wpdb->users, array( 'display_name' => $first ), array( 'ID' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_user_cache( $user_id );
}

foreach ( array( 'added_user_meta', 'updated_user_meta' ) as $gilda_ad_hook ) {
	add_action(
		$gilda_ad_hook,
		static function ( $meta_id, $user_id, $meta_key, $value ) {
			if ( 'first_name' === $meta_key ) {
				gilda_account_display_first_name( $user_id, $value );
			}
		},
		10,
		4
	);
}
unset( $gilda_ad_hook );

// Salvar o perfil pelo painel (ou qualquer wp_update_user) também respeita a regra.
add_action( 'profile_update', static fn( $user_id ) => gilda_account_display_first_name( $user_id ), 20 );
add_action( 'woocommerce_created_customer', static fn( $user_id ) => gilda_account_display_first_name( $user_id ), 30 );

// Uma vez: quem já tinha conta passa a exibir o primeiro nome.
add_action(
	'init',
	static function () {
		if ( GILDA_ACCOUNT_DETAILS_VERSION === get_option( 'gilda_account_details_version' ) ) {
			return;
		}
		update_option( 'gilda_account_details_version', GILDA_ACCOUNT_DETAILS_VERSION, false );
		foreach ( get_users( array( 'fields' => 'ID', 'number' => -1 ) ) as $user_id ) {
			gilda_account_display_first_name( $user_id );
		}
		// endpoint novo (/minha-conta/senha/): as regras de URL precisam ser refeitas uma vez
		flush_rewrite_rules( false );
	},
	99
);

/* -------------------------------------------------------------------------
 * "Senha": item próprio do menu da conta
 * ---------------------------------------------------------------------- */
add_filter(
	'woocommerce_get_query_vars',
	static function ( $vars ) {
		$vars[ GILDA_ACCOUNT_PASSWORD_ENDPOINT ] = GILDA_ACCOUNT_PASSWORD_ENDPOINT;
		return $vars;
	}
);

add_filter(
	'woocommerce_account_menu_items',
	static function ( $items ) {
		$out = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$out[ GILDA_ACCOUNT_PASSWORD_ENDPOINT ] = __( 'Senha', 'gilda' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out[ GILDA_ACCOUNT_PASSWORD_ENDPOINT ] ) ) {
			$out[ GILDA_ACCOUNT_PASSWORD_ENDPOINT ] = __( 'Senha', 'gilda' );
		}
		return $out;
	},
	20
);

add_filter( 'woocommerce_endpoint_' . GILDA_ACCOUNT_PASSWORD_ENDPOINT . '_title', static fn() => __( 'Senha', 'gilda' ) );

add_action(
	'woocommerce_account_' . GILDA_ACCOUNT_PASSWORD_ENDPOINT . '_endpoint',
	static function () {
		include __DIR__ . '/gilda-account-details/form-password.php';
	}
);

// Máscara do telefone em "Detalhes".
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_user_logged_in() ) {
			return;
		}
		if ( is_wc_endpoint_url( 'edit-account' ) ) {
			wp_register_script( 'gilda-account-phone', '', array(), GILDA_ACCOUNT_DETAILS_VERSION, true );
			wp_enqueue_script( 'gilda-account-phone' );
			wp_add_inline_script(
				'gilda-account-phone',
				"(function(){var i=document.getElementById('account_phone');if(!i)return;var f=function(){var d=i.value.replace(/\\D+/g,'');if(d.length>11&&d.indexOf('55')===0)d=d.slice(2);d=d.slice(0,11);var o=d;if(d.length>2)o='('+d.slice(0,2)+') '+d.slice(2);if(d.length>6)o='('+d.slice(0,2)+') '+d.slice(2,d.length===11?7:6)+'-'+d.slice(d.length===11?7:6);i.value=o;};i.addEventListener('input',f);})();"
			);
		}
	},
	20
);

// Troca de senha (formulário da tela "Senha").
add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado logo abaixo.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['action'] ) || 'gilda_change_password' !== $_POST['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return;
		}
		// phpcs:enable
		$nonce = isset( $_POST['gilda-change-password-nonce'] ) ? sanitize_key( wp_unslash( $_POST['gilda-change-password-nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gilda_change_password' ) || ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- senhas não são alteradas/limpas
		$current = isset( $_POST['password_current'] ) ? (string) wp_unslash( $_POST['password_current'] ) : '';
		$pass1   = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
		$pass2   = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';
		// phpcs:enable

		$ok = true;
		if ( '' === $current || '' === $pass1 || '' === $pass2 ) {
			wc_add_notice( __( 'Preencha a senha atual, a nova senha e a confirmação.', 'gilda' ), 'error' );
			$ok = false;
		} elseif ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			wc_add_notice( __( 'A senha atual não confere.', 'gilda' ), 'error', array( 'id' => 'password_current' ) );
			$ok = false;
		} elseif ( gilda_password_too_short( $pass1 ) ) {
			wc_add_notice( gilda_password_too_short( $pass1 ), 'error', array( 'id' => 'password_1' ) );
			$ok = false;
		} elseif ( $pass1 !== $pass2 ) {
			wc_add_notice( __( 'A nova senha e a confirmação não são iguais.', 'gilda' ), 'error', array( 'id' => 'password_2' ) );
			$ok = false;
		} elseif ( $pass1 === $current ) {
			wc_add_notice( __( 'A nova senha precisa ser diferente da atual.', 'gilda' ), 'error', array( 'id' => 'password_1' ) );
			$ok = false;
		}

		if ( $ok ) {
			$result = wp_update_user(
				array(
					'ID'        => $user->ID,
					'user_pass' => $pass1,
				)
			);
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			} else {
				wc_add_notice( __( 'Senha alterada.', 'gilda' ) );
			}
		}
		wp_safe_redirect( wc_get_account_endpoint_url( GILDA_ACCOUNT_PASSWORD_ENDPOINT ) );
		exit;
	}
);
