<?php
/**
 * E-mail "voltou ao estoque" (HTML).
 *
 * Pode ser sobrescrito no tema: wp-content/themes/gilda/woocommerce/emails/gilda-back-in-stock.php
 *
 * @var WC_Product|null $product            Produto simples ou variação.
 * @var string          $product_title      Nome do produto (sem cor/tamanho).
 * @var string          $variation_text     "Cor: Marrom, Tamanho: M" (vazio em produto simples).
 * @var string          $product_url        Link para a peça (variação já selecionada).
 * @var string          $cancel_url         Link de cancelamento.
 * @var string          $requested_at       Data do pedido de aviso.
 * @var string          $email_heading
 * @var string          $additional_content
 * @var WC_Email        $email
 */

defined( 'ABSPATH' ) || exit;

$base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$text_color = wc_light_or_dark( $base_color, '#202020', '#ffffff' );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Oi!', 'gilda' ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: produto */
		esc_html__( 'A peça que você pediu para avisar voltou ao estoque: %s.', 'gilda' ),
		'<strong>' . esc_html( $product_title ) . '</strong>'
	);
	?>
</p>
<?php if ( $variation_text ) : ?>
	<p><?php echo esc_html( $variation_text ); ?></p>
<?php endif; ?>
<?php if ( $product && '' !== $product->get_price_html() ) : ?>
	<p><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
<?php endif; ?>

<p style="margin:24px 0;">
	<a href="<?php echo esc_url( $product_url ); ?>" style="display:inline-block;padding:12px 24px;background:<?php echo esc_attr( $base_color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;text-decoration:none;font-weight:bold;"><?php esc_html_e( 'Ver a peça', 'gilda' ); ?></a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<p style="font-size:12px;opacity:.8;">
	<?php
	if ( $requested_at ) {
		/* translators: %s: data */
		printf( esc_html__( 'Você recebeu este e-mail porque pediu um aviso em %s. Esse é o único aviso: depois dele, seu e-mail sai da lista.', 'gilda' ), esc_html( $requested_at ) );
	}
	?>
	<a href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Apagar meu e-mail desta lista', 'gilda' ); ?></a>.
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
