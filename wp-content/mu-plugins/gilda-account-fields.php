<?php
/**
 * Plugin Name: GILDA; Campos do cadastro
 * Description: Cadastro em "Minha conta" com Nome e Confirmar senha (requisito 7.2). Valida e salva o nome no perfil do cliente.
 */

defined( 'ABSPATH' ) || exit;

// Nome (antes do e-mail).
add_action(
	'woocommerce_register_form_start',
	static function () {
		$value = isset( $_POST['gilda_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gilda_full_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="reg_gilda_full_name"><?php esc_html_e( 'Nome', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Obrigatório', 'gilda' ); ?></span></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="gilda_full_name" id="reg_gilda_full_name" autocomplete="name" value="<?php echo esc_attr( $value ); ?>" required aria-required="true" />
		</p>
		<?php
	}
);

// Confirmar senha (depois da senha).
add_action(
	'woocommerce_register_form',
	static function () {
		if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) ) {
			return;
		}
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="reg_gilda_password2"><?php esc_html_e( 'Confirmar senha', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Obrigatório', 'gilda' ); ?></span></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="gilda_password2" id="reg_gilda_password2" autocomplete="new-password" required aria-required="true" />
		</p>
		<?php
	}
);

// Validação (só no formulário de "Minha conta"; o checkout tem os próprios campos).
add_action(
	'woocommerce_register_post',
	static function ( $username, $email, $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification -- o WooCommerce já verificou o nonce do formulário.
		if ( ! isset( $_POST['register'] ) ) {
			return;
		}
		$name = isset( $_POST['gilda_full_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['gilda_full_name'] ) ) ) : '';
		if ( '' === $name ) {
			$errors->add( 'gilda_full_name', __( 'Informe seu nome.', 'gilda' ) );
		}
		if ( 'yes' !== get_option( 'woocommerce_registration_generate_password' ) ) {
			$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$pass2 = isset( $_POST['gilda_password2'] ) ? (string) wp_unslash( $_POST['gilda_password2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( $pass !== $pass2 ) {
				$errors->add( 'gilda_password2', __( 'As senhas não coincidem.', 'gilda' ) );
			}
		}
		// phpcs:enable
	},
	10,
	3
);

// Salva nome e sobrenome no usuário e no endereço de cobrança.
add_action(
	'woocommerce_created_customer',
	static function ( $customer_id ) {
		if ( empty( $_POST['gilda_full_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$name  = trim( sanitize_text_field( wp_unslash( $_POST['gilda_full_name'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$parts = preg_split( '/\s+/', $name, 2 );
		$first = $parts[0];
		$last  = $parts[1] ?? '';

		$customer = new WC_Customer( $customer_id );
		$customer->set_first_name( $first );
		$customer->set_last_name( $last );
		$customer->set_display_name( $first ); // nome de exibição = primeiro nome (gilda-account-details.php)
		$customer->set_billing_first_name( $first );
		$customer->set_billing_last_name( $last );
		$customer->save();
	}
);
