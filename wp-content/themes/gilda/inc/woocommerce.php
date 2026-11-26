<?php
/**
 * WooCommerce integration (Epics 3, 5, 6 and task 11.2).
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1.6 Breadcrumb: GILDA; › All Products › [Produto]
 * ---------------------------------------------------------------------- */

/**
 * @param array $defaults Breadcrumb args.
 * @return array
 */
function gilda_breadcrumb_defaults( $defaults ) {
	$defaults['delimiter']   = '<span class="gilda-crumb-sep" aria-hidden="true">›</span>';
	$defaults['wrap_before'] = '<nav class="woocommerce-breadcrumb gilda-breadcrumb" aria-label="' . esc_attr__( 'Você está em', 'gilda' ) . '">';
	$defaults['wrap_after']  = '</nav>';
	$defaults['home']        = 'GILDA;';
	return $defaults;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'gilda_breadcrumb_defaults' );

/**
 * On product pages always show Home › Shop › Product (no category levels).
 *
 * @param array $crumbs Crumbs as [ label, url ] pairs.
 * @return array
 */
function gilda_breadcrumb_trail( $crumbs ) {
	if ( ! is_product() ) {
		return $crumbs;
	}
	$shop_id = wc_get_page_id( 'shop' );
	return array(
		array( 'GILDA;', home_url( '/' ) ),
		array( $shop_id > 0 ? get_the_title( $shop_id ) : __( 'All Products', 'gilda' ), wc_get_page_permalink( 'shop' ) ),
		array( get_the_title( get_queried_object_id() ), '' ), // Last level: not a link.
	);
}
add_filter( 'woocommerce_get_breadcrumb', 'gilda_breadcrumb_trail' );

/* -------------------------------------------------------------------------
 * 5.2 Accessible price label
 * ---------------------------------------------------------------------- */

/**
 * @param string $html Price HTML.
 * @return string
 */
function gilda_price_label( $html ) {
	if ( '' === $html || false !== strpos( $html, 'gilda-price-label' ) ) {
		return $html;
	}
	return '<span class="screen-reader-text gilda-price-label">' . esc_html__( 'Preço', 'gilda' ) . '</span>' . $html;
}
add_filter( 'woocommerce_get_price_html', 'gilda_price_label', 20 );

/* -------------------------------------------------------------------------
 * 11.2 Sold out ("Esgotado") everywhere
 * ---------------------------------------------------------------------- */

/**
 * Loop button label.
 *
 * @param string     $text    Label.
 * @param WC_Product $product Product.
 * @return string
 */
function gilda_loop_add_to_cart_text( $text, $product ) {
	if ( $product && ! $product->is_in_stock() ) {
		return __( 'Esgotado', 'gilda' );
	}
	if ( $product && $product->is_type( 'simple' ) && $product->is_purchasable() ) {
		return __( 'Adicionar ao carrinho', 'gilda' );
	}
	return $text;
}
add_filter( 'woocommerce_product_add_to_cart_text', 'gilda_loop_add_to_cart_text', 10, 2 );

/**
 * Single product button label (5.8).
 *
 * @return string
 */
function gilda_single_add_to_cart_text() {
	return __( 'Adicionar ao carrinho', 'gilda' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'gilda_single_add_to_cart_text' );

/**
 * Resolve the product of a block from its context.
 *
 * @param WP_Block|null $instance Block instance.
 * @return WC_Product|null
 */
function gilda_block_product( $instance ) {
	$id = ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : get_the_ID();
	$product = $id ? wc_get_product( $id ) : null;
	return $product instanceof WC_Product ? $product : null;
}

/**
 * Product image in collections: sold-out badge + quick view trigger (3.2).
 *
 * @param string        $content  Block HTML.
 * @param array         $block    Parsed block.
 * @param WP_Block|null $instance Block instance.
 * @return string
 */
function gilda_render_product_image( $content, $block, $instance = null ) {
	$product = gilda_block_product( $instance );
	if ( ! $product || is_product() && (int) get_queried_object_id() === $product->get_id() ) {
		return $content;
	}

	$extra = '';
	if ( ! $product->is_in_stock() ) {
		$extra .= '<span class="gilda-badge gilda-badge--soldout">' . esc_html__( 'Esgotado', 'gilda' ) . '</span>';
		$content = str_replace( 'wc-block-components-product-image', 'wc-block-components-product-image is-soldout', $content );
	}
	$extra .= sprintf(
		'<button type="button" class="gilda-quickview-btn" data-product-id="%1$d" aria-label="%2$s">%3$s</button>',
		$product->get_id(),
		/* translators: %s: product name. */
		esc_attr( sprintf( __( 'Visualização rápida de %s', 'gilda' ), $product->get_name() ) ),
		gilda_icon( 'eye', 20 )
	);

	return '<div class="gilda-card-media">' . $content . $extra . '</div>';
}
add_filter( 'render_block_woocommerce/product-image', 'gilda_render_product_image', 10, 3 );

/**
 * Collection add-to-cart button: disabled "Esgotado" when out of stock.
 *
 * @param string        $content  Block HTML.
 * @param array         $block    Parsed block.
 * @param WP_Block|null $instance Block instance.
 * @return string
 */
function gilda_render_product_button( $content, $block, $instance = null ) {
	$product = gilda_block_product( $instance );
	if ( $product && ! $product->is_in_stock() ) {
		return '<div class="wp-block-button wc-block-components-product-button gilda-card-action"><button type="button" class="wp-block-button__link gilda-btn gilda-btn--disabled" disabled>' . esc_html__( 'Esgotado', 'gilda' ) . '</button></div>';
	}
	return str_replace( 'wc-block-components-product-button', 'wc-block-components-product-button gilda-card-action', $content );
}
add_filter( 'render_block_woocommerce/product-button', 'gilda_render_product_button', 10, 3 );

/**
 * Single product: replace "Fora de estoque" with a disabled button.
 *
 * @param string     $html    Stock HTML.
 * @param WC_Product $product Product.
 * @return string
 */
function gilda_stock_html( $html, $product ) {
	if ( $product && ! $product->is_in_stock() && ! $product->is_type( 'variation' ) ) {
		return '<p class="stock out-of-stock"><button type="button" class="gilda-btn gilda-btn--disabled" disabled>' . esc_html__( 'Esgotado', 'gilda' ) . '</button></p>';
	}
	return $html;
}
add_filter( 'woocommerce_get_stock_html', 'gilda_stock_html', 10, 2 );

/* -------------------------------------------------------------------------
 * 5.5 / 5.6 Variation selectors
 * ---------------------------------------------------------------------- */

/**
 * Placeholder "Selecionar" for every attribute dropdown. The colour select
 * (pa_cor) is progressively enhanced into radio swatches by gilda.js.
 *
 * @param array $args Dropdown args.
 * @return array
 */
function gilda_variation_dropdown_args( $args ) {
	$args['show_option_none'] = __( 'Selecionar', 'gilda' );
	$args['class']            = trim( ( $args['class'] ?? '' ) . ' gilda-select' );
	return $args;
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'gilda_variation_dropdown_args' );

/* -------------------------------------------------------------------------
 * 5.9 "Comprar" (buy now)
 * ---------------------------------------------------------------------- */

/**
 * Second submit button inside the add-to-cart form.
 */
function gilda_buy_now_button() {
	global $product;
	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}
	printf(
		'<button type="submit" name="gilda_buy_now" value="1" class="button alt gilda-buy-now">%s</button>',
		esc_html__( 'Comprar', 'gilda' )
	);
}
add_action( 'woocommerce_after_add_to_cart_button', 'gilda_buy_now_button' );

/**
 * Buy now goes straight to checkout.
 *
 * @param string $url Redirect URL.
 * @return string
 */
function gilda_buy_now_redirect( $url ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce validates the add-to-cart request.
	if ( isset( $_REQUEST['gilda_buy_now'] ) ) {
		return wc_get_checkout_url();
	}
	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'gilda_buy_now_redirect', 99 );

/* -------------------------------------------------------------------------
 * 1.5 Header cart counter stays in sync with classic AJAX add-to-cart
 * ---------------------------------------------------------------------- */

/**
 * @param array $fragments Fragments.
 * @return array
 */
function gilda_cart_fragments( $fragments ) {
	$fragments['a.gilda-cart-link'] = gilda_cart_link_html();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'gilda_cart_fragments' );

/* -------------------------------------------------------------------------
 * 11.5 Order received copy
 * ---------------------------------------------------------------------- */

/**
 * @return string
 */
function gilda_thankyou_text() {
	return __( 'Um novo capítulo começa. Enviamos a confirmação do pedido para o seu e-mail.', 'gilda' );
}
add_filter( 'woocommerce_thankyou_order_received_text', 'gilda_thankyou_text' );

/* -------------------------------------------------------------------------
 * Misc
 * ---------------------------------------------------------------------- */

// 4.6: four products per row on the classic fallback, 12 per page.
add_filter( 'loop_shop_columns', static fn() => 4 );
add_filter( 'loop_shop_per_page', static fn() => 12 );

/**
 * Related products: up to 8 for the carousel (5.12).
 *
 * @param array $args Args.
 * @return array
 */
function gilda_related_products_args( $args ) {
	$args['posts_per_page'] = 8;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'gilda_related_products_args' );
