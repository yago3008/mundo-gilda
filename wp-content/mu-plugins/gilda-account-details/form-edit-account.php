<?php
/**
 * "Detalhes da conta" da GILDA; (substitui myaccount/form-edit-account.php do WooCommerce 11.0.0).
 *
 * Diferenças do original:
 * - sem "Nome de exibição": é sempre o primeiro nome (mu-plugin gilda-account-details);
 * - com "Telefone para contato" (salvo como telefone de cobrança: o checkout já vem preenchido);
 * - sem "Alteração de senha": virou o item "Senha" do menu da conta.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

$gilda_phone = (string) get_user_meta( $user->ID, 'billing_phone', true );

do_action( 'woocommerce_before_edit_account_form' );
?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
		<label for="account_first_name"><?php esc_html_e( 'Nome', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
		<label for="account_last_name"><?php esc_html_e( 'Sobrenome', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email"><?php esc_html_e( 'E-mail', 'gilda' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_phone"><?php esc_html_e( 'Telefone para contato', 'gilda' ); ?></label>
		<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text gilda-phone-input" name="account_phone" id="account_phone" autocomplete="tel-national" inputmode="tel" placeholder="(41) 99999-9999" maxlength="16" value="<?php echo esc_attr( gilda_account_format_phone( $gilda_phone ) ); ?>" aria-describedby="account_phone_description" />
		<span id="account_phone_description"><em><?php esc_html_e( 'Usamos só para falar com você sobre os seus pedidos.', 'gilda' ); ?></em></span>
	</p>

	<?php do_action( 'woocommerce_edit_account_form_fields' ); ?>

	<?php do_action( 'woocommerce_edit_account_form' ); ?>

	<p>
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Salvar alterações', 'gilda' ); ?>"><?php esc_html_e( 'Salvar alterações', 'gilda' ); ?></button>
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
