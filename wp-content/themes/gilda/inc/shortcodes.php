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
	$instagram = gilda_store_value( 'instagram', 'gildapontoevirgula' );
	$instagram = function_exists( 'gilda_store_instagram_handle' ) ? gilda_store_instagram_handle( $instagram ) : ltrim( $instagram, '@' );
	$messages  = apply_filters(
		'gilda_announcement_messages',
		array(
			// Cupom da "Promoção em destaque" do painel (código e desconto lidos do cupom; sem promoção, nada).
			function_exists( 'gilda_promo_message' ) ? gilda_promo_message() : __( '10% OFF na primeira compra com o cupom primeiracompra', 'gilda' ),
			__( 'Alfaiataria criativa — feito à mão em Curitiba/PR', 'gilda' ),
			__( 'Novas peças a cada capítulo', 'gilda' ),
			$instagram ? '@' . $instagram : '', // Dados da loja
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
			<a class="gilda-shipping__help" href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Não sei meu CEP', 'gilda' ); ?></a>
		</div>
		<div id="gilda-cep-feedback" class="gilda-shipping__feedback" aria-live="polite"></div>
	</form>
	<?php
	// Sem quebras de linha entre as tags: o template passa o HTML pelo wpautop, que enfiava <br> e <p>
	// (vazios) no meio do formulário e afastava o "Não sei meu CEP" do campo.
	return (string) preg_replace( '/>\s+</', '><', trim( (string) ob_get_clean() ) );
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

/**
 * Um dado da tela "Dados da loja" do painel (mu-plugin gilda-painel), com reserva se o plugin faltar.
 *
 * @param string $field    Campo (razao_social, cnpj, endereco, cep, cidade, uf, telefone, whatsapp, email, instagram).
 * @param string $fallback Valor sem o plugin.
 * @return string
 */
function gilda_store_value( $field, $fallback = '' ) {
	return function_exists( 'gilda_store' ) ? trim( (string) gilda_store( $field ) ) : $fallback;
}

/**
 * [gilda_footer_social] — Instagram e WhatsApp do rodapé (Dados da loja).
 *
 * @return string
 */
function gilda_shortcode_footer_social() {
	$items     = '';
	$instagram = function_exists( 'gilda_store_instagram_url' ) ? gilda_store_instagram_url() : 'https://www.instagram.com/gildapontoevirgula/';
	$handle    = function_exists( 'gilda_store_instagram_handle' ) ? gilda_store_instagram_handle( gilda_store_value( 'instagram' ) ) : 'gildapontoevirgula';
	$whatsapp  = function_exists( 'gilda_store_whatsapp_url' ) ? gilda_store_whatsapp_url() : 'https://wa.me/5541991008303';
	if ( $instagram ) {
		$items .= sprintf(
			'<li><a href="%1$s" target="_blank" rel="noreferrer noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"></circle></svg><span>@%2$s</span></a></li>',
			esc_url( $instagram ),
			esc_html( $handle )
		);
	}
	if ( $whatsapp ) {
		$items .= sprintf(
			'<li><a href="%1$s" target="_blank" rel="noreferrer noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"></path><path d="M9 9.5c.3 2 2.5 4.2 4.5 4.5l1-1.2 1.8.8c-.2 1-1.1 1.6-2.1 1.5-3.2-.4-5.8-3-6.2-6.2-.1-1 .5-1.9 1.5-2.1l.8 1.8L9 9.5z"></path></svg><span>%2$s</span></a></li>',
			esc_url( $whatsapp ),
			esc_html__( 'WhatsApp', 'gilda' )
		);
	}
	return $items ? '<ul class="gilda-footer__social-list">' . $items . '</ul>' : '';
}
add_shortcode( 'gilda_footer_social', 'gilda_shortcode_footer_social' );

/**
 * [gilda_footer_contact] — telefone e e-mail do rodapé (Dados da loja).
 *
 * @return string
 */
function gilda_shortcode_footer_contact() {
	$phone     = gilda_store_value( 'telefone', '41.991008303' );
	$phone_url = function_exists( 'gilda_store_phone_url' ) ? gilda_store_phone_url() : 'tel:+5541991008303';
	$email     = gilda_store_value( 'email', 'mundogilda@gmail.com' );
	$items     = '';
	if ( $phone ) {
		$items .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $phone_url, array( 'tel' ) ), esc_html( $phone ) );
	}
	if ( $email ) {
		$items .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( 'mailto:' . $email, array( 'mailto' ) ), esc_html( $email ) );
	}
	return $items ? '<ul class="wp-block-list gilda-footer__links">' . $items . '</ul>' : '';
}
add_shortcode( 'gilda_footer_contact', 'gilda_shortcode_footer_contact' );

/**
 * [gilda_footer_legal] — razão social, CNPJ, endereço, cidade/UF e CEP (Dados da loja).
 *
 * @return string
 */
function gilda_shortcode_footer_legal() {
	$sep   = '&nbsp;·&nbsp;';
	$line1 = array_filter(
		array(
			esc_html( gilda_store_value( 'razao_social', 'GILDA ALFAIATARIA LTDA' ) ),
			( '' !== gilda_store_value( 'cnpj', '45.822.638/0001-09' ) ) ? esc_html__( 'CNPJ', 'gilda' ) . ' ' . esc_html( gilda_store_value( 'cnpj', '45.822.638/0001-09' ) ) : '',
		)
	);
	$city  = implode( '/', array_filter( array( gilda_store_value( 'cidade', 'Curitiba' ), gilda_store_value( 'uf', 'PR' ) ) ) );
	$line2 = array_filter(
		array(
			esc_html( implode( ', ', array_filter( array( gilda_store_value( 'endereco' ), $city ) ) ) ),
			( '' !== gilda_store_value( 'cep', '82.015-384' ) ) ? esc_html__( 'CEP', 'gilda' ) . ' ' . esc_html( gilda_store_value( 'cep', '82.015-384' ) ) : '',
		)
	);
	$lines = array_filter( array( implode( $sep, $line1 ), implode( $sep, $line2 ) ) );
	return $lines ? '<p class="gilda-footer__legal wp-block-paragraph">' . implode( '<br>', $lines ) . '</p>' : '';
}
add_shortcode( 'gilda_footer_legal', 'gilda_shortcode_footer_legal' );

/**
 * [gilda_copyright] — "© ano by GILDA;", com o ano atual.
 *
 * @return string
 */
function gilda_shortcode_copyright() {
	return sprintf(
		'<p class="gilda-footer__copyright wp-block-paragraph">© %1$s by GILDA;&nbsp;·&nbsp;%2$s</p>',
		esc_html( wp_date( 'Y' ) ),
		esc_html__( 'Todos os direitos reservados.', 'gilda' )
	);
}
add_shortcode( 'gilda_copyright', 'gilda_shortcode_copyright' );
