<?php
/**
 * E-mail "voltou ao estoque" (texto puro).
 *
 * Pode ser sobrescrito no tema: wp-content/themes/gilda/woocommerce/emails/plain/gilda-back-in-stock.php
 *
 * Variáveis: as mesmas do template HTML.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

esc_html_e( 'Oi!', 'gilda' );
echo "\n\n";
/* translators: %s: produto */
printf( esc_html__( 'A peça que você pediu para avisar voltou ao estoque: %s.', 'gilda' ), esc_html( $product_title ) );
echo "\n";
if ( $variation_text ) {
	echo esc_html( $variation_text ) . "\n";
}
echo "\n" . esc_html__( 'Ver a peça:', 'gilda' ) . ' ' . esc_url_raw( $product_url ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

if ( $requested_at ) {
	/* translators: %s: data */
	printf( esc_html__( 'Você recebeu este e-mail porque pediu um aviso em %s. Esse é o único aviso: depois dele, seu e-mail sai da lista.', 'gilda' ), esc_html( $requested_at ) );
	echo "\n";
}
echo esc_html__( 'Apagar meu e-mail desta lista:', 'gilda' ) . ' ' . esc_url_raw( $cancel_url ) . "\n\n";

echo "----------------------------------------\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
