<?php
/**
 * Dynamic pieces used inside block templates through core/shortcode.
 *
 * [gilda_account_link]         Header login / account (1.4)
 * [gilda_cart_link]            Header cart with counter (1.5)
 * [gilda_payment_methods]      Footer payment icons (10.2)
 * [gilda_share]                Product share buttons (5.11)
 * [gilda_shipping_calculator]  Product shipping calculator (5.10)
 * [gilda_result_count]         Catalog result count (4.2)
 * [gilda_product_filters]      Catalog sidebar filters (4.1–4.5) — inc/filters.php
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Announcement marquee (barra de avisos rolando no topo).
 * Mensagens editáveis pelo filtro `gilda_announcement_messages`.
 *
 * @return string
 */
function gilda_shortcode_announcement() {
	$messages = apply_filters(
		'gilda_announcement_messages',
		array(
			__( '10% OFF na primeira compra com o cupom primeiracompra', 'gilda' ),
			__( 'Alfaiataria criativa — feito à mão em Curitiba/PR', 'gilda' ),
			__( 'Novas peças a cada capítulo', 'gilda' ),
			__( '@gildapontoevirgula', 'gilda' ),
		)
	);
	$messages = array_values( array_filter( array_map( 'trim', (array) $messages ) ) );
	if ( ! $messages ) {
		return '';
	}

	$items = '';
	foreach ( $messages as $message ) {
		$items .= '<span class="gilda-marquee__item">' . esc_html( $message ) . '</span>';
	}
	// Conteúdo duplicado (a 2ª cópia é ignorada por leitores de tela) para o loop contínuo.
	$track = '<div class="gilda-marquee__track">' . $items . '<span class="gilda-marquee__group" aria-hidden="true">' . $items . '</span></div>';

	return '<div class="gilda-marquee"><div class="gilda-marquee__viewport">' . $track . '</div></div>';
}
add_shortcode( 'gilda_announcement', 'gilda_shortcode_announcement' );

/**
 * Header account link.
 *
 * @return string
 */
function gilda_shortcode_account_link() {
	$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();

	if ( is_user_logged_in() ) {
		$user    = wp_get_current_user();
		$name    = $user->first_name ? $user->first_name : $user->display_name;
		$initial = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
		return sprintf(
			'<a class="gilda-account-link is-logged-in" href="%1$s" aria-label="%2$s"><span class="gilda-account-initial" aria-hidden="true">%3$s</span><span class="gilda-account-text">%4$s</span></a>',
			esc_url( $url ),
			/* translators: %s: customer first name. */
			esc_attr( sprintf( __( 'Minha conta, %s conectada', 'gilda' ), $name ) ),
			esc_html( $initial ),
			/* translators: %s: customer first name. */
			esc_html( sprintf( __( 'Olá, %s', 'gilda' ), $name ) )
		);
	}

	return sprintf(
		'<a class="gilda-account-link" href="%1$s" aria-label="%2$s">%3$s<span class="gilda-account-text">%4$s</span></a>',
		esc_url( $url ),
		esc_attr__( 'Entrar na minha conta', 'gilda' ),
		gilda_icon( 'user' ),
		esc_html__( 'Entrar', 'gilda' )
	);
}
add_shortcode( 'gilda_account_link', 'gilda_shortcode_account_link' );

/**
 * Header cart link.
 *
 * @return string
 */
function gilda_shortcode_cart_link() {
	return gilda_cart_link_html();
}
add_shortcode( 'gilda_cart_link', 'gilda_shortcode_cart_link' );

/**
 * Payment methods row. Replace the badges with the official SVG files
 * (assets/images/payments/*.svg) when the brand assets are available.
 *
 * @return string
 */
function gilda_shortcode_payment_methods() {
	$methods = array(
		'visa'       => 'Visa',
		'mastercard' => 'Mastercard',
		'pix'        => 'Pix',
	);
	$items   = '';
	foreach ( $methods as $slug => $label ) {
		$file = GILDA_DIR . '/assets/images/payments/' . $slug . '.svg';
		if ( file_exists( $file ) ) {
			$items .= sprintf(
				'<li><img src="%1$s" alt="%2$s" width="48" height="30" loading="lazy" /></li>',
				esc_url( GILDA_URI . '/assets/images/payments/' . $slug . '.svg' ),
				esc_attr( $label )
			);
		} else {
			$items .= sprintf( '<li><span class="gilda-pay gilda-pay--%1$s" role="img" aria-label="%2$s">%2$s</span></li>', esc_attr( $slug ), esc_html( $label ) );
		}
	}
	return sprintf(
		'<div class="gilda-payments"><p>%1$s</p><ul class="gilda-payments__list">%2$s</ul></div>',
		esc_html__( 'Aceitamos os seguintes métodos de pagamento:', 'gilda' ),
		$items
	);
}
add_shortcode( 'gilda_payment_methods', 'gilda_shortcode_payment_methods' );

/**
 * Share buttons for the current product.
 *
 * @return string
 */
function gilda_shortcode_share() {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return '';
	}
	$url   = rawurlencode( get_permalink( $id ) );
	$title = rawurlencode( wp_strip_all_tags( get_the_title( $id ) ) );
	$image = rawurlencode( (string) get_the_post_thumbnail_url( $id, 'large' ) );

	$networks = array(
		'whatsapp'  => array( __( 'Compartilhar no WhatsApp', 'gilda' ), "https://api.whatsapp.com/send?text={$title}%20{$url}" ),
		'facebook'  => array( __( 'Compartilhar no Facebook', 'gilda' ), "https://www.facebook.com/sharer/sharer.php?u={$url}" ),
		'x'         => array( __( 'Compartilhar no X', 'gilda' ), "https://x.com/intent/post?url={$url}&text={$title}" ),
		'pinterest' => array( __( 'Compartilhar no Pinterest', 'gilda' ), "https://pinterest.com/pin/create/button/?url={$url}&media={$image}&description={$title}" ),
	);

	$links = '';
	foreach ( $networks as $key => $network ) {
		$links .= sprintf(
			'<li><a class="gilda-share__link" href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s" data-share-popup>%3$s</a></li>',
			esc_url( $network[1] ),
			esc_attr( $network[0] ),
			gilda_icon( $key, 20 )
		);
	}

	return sprintf(
		'<nav class="gilda-share" aria-label="%1$s"><span class="gilda-label">%2$s</span><ul>%3$s</ul></nav>',
		esc_attr__( 'Compartilhar nas redes sociais', 'gilda' ),
		esc_html__( 'Compartilhar', 'gilda' ),
		$links
	);
}
add_shortcode( 'gilda_share', 'gilda_shortcode_share' );

/**
 * Shipping calculator form. Works with the REST endpoint in inc/shipping.php.
 *
 * @return string
 */
function gilda_shortcode_shipping_calculator() {
	$id = get_queried_object_id();
	if ( ! $id || ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$product = wc_get_product( $id );
	if ( ! $product || $product->is_virtual() ) {
		return '';
	}
	ob_start();
	?>
	<form class="gilda-shipping" data-product-id="<?php echo esc_attr( $id ); ?>" novalidate>
		<h2 class="gilda-shipping__title"><?php esc_html_e( 'Calcule seu frete', 'gilda' ); ?></h2>
		<div class="gilda-shipping__row">
			<label class="screen-reader-text" for="gilda-cep"><?php esc_html_e( 'CEP', 'gilda' ); ?></label>
			<input id="gilda-cep" class="gilda-input" type="text" name="cep" inputmode="numeric" autocomplete="postal-code" placeholder="<?php esc_attr_e( 'CEP', 'gilda' ); ?>" maxlength="9" pattern="\d{5}-?\d{3}" aria-describedby="gilda-cep-feedback" />
			<button type="submit" class="gilda-btn gilda-btn--secondary"><?php esc_html_e( 'Calcular', 'gilda' ); ?></button>
		</div>
		<a class="gilda-shipping__help" href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Não sei meu CEP', 'gilda' ); ?></a>
		<div id="gilda-cep-feedback" class="gilda-shipping__feedback" aria-live="polite"></div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'gilda_shipping_calculator', 'gilda_shortcode_shipping_calculator' );

/**
 * Catalog result count.
 *
 * @return string
 */
function gilda_shortcode_result_count() {
	global $wp_query;
	$total = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
	return sprintf(
		'<p class="gilda-result-count" role="status">%s</p>',
		/* translators: %d: number of products. */
		esc_html( sprintf( _n( '%d produto', '%d produtos', $total, 'gilda' ), $total ) )
	);
}
add_shortcode( 'gilda_result_count', 'gilda_shortcode_result_count' );
