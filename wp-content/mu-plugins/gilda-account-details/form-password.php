<?php
/**
 * "Senha" na conta da GILDA; (item próprio do menu, separado dos detalhes da conta).
 * Os ids password_current / password_1 / password_2 e a classe "edit-account" fazem o medidor de força
 * de senha do WooCommerce funcionar aqui também (wc-password-strength-meter).
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="woocommerce-EditAccountForm edit-account gilda-password-form" action="" method="post">
	<p class="gilda-password-form__intro"><?php esc_html_e( 'Para trocar a senha, confirme a atual e escolha a nova.', 'gilda' ); ?></p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="password_current"><?php esc_html_e( 'Senha atual', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" required aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="password_1"><?php esc_html_e( 'Nova senha', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" required aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="password_2"><?php esc_html_e( 'Confirmar nova senha', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" required aria-required="true" />
	</p>

	<p>
		<?php wp_nonce_field( 'gilda_change_password', 'gilda-change-password-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="gilda_change_password" value="1"><?php esc_html_e( 'Alterar senha', 'gilda' ); ?></button>
		<input type="hidden" name="action" value="gilda_change_password" />
	</p>
</form>
