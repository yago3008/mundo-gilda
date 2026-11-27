<?php
/**
 * Title: Produtos em destaque
 * Slug: gilda/featured
 * Categories: gilda, woo-commerce
 * Keywords: destaque, produtos, carrossel
 * Description: 3 produtos marcados como "Destaque" no WooCommerce (task 2.2). Grade no desktop, carrossel no mobile.
 * Viewport Width: 1440
 *
 * @package gilda
 */

// Só aparece com as 3 peças marcadas como destaque (no painel: "Destaque na página inicial"),
// para nunca ficar uma peça solta na home.
$gilda_featured = function_exists( 'wc_get_products' )
	? wc_get_products(
		array(
			'featured'   => true,
			'status'     => 'publish',
			'visibility' => 'catalog',
			'limit'      => 3,
			'return'     => 'ids',
		)
	)
	: array();
if ( count( $gilda_featured ) < 3 ) {
	return;
}
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:heading {"textAlign":"center","className":"is-style-headline"} -->
<h2 class="wp-block-heading has-text-align-center is-style-headline"><?php echo esc_html__( 'DESTAQUES', 'gilda' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-collection {"queryId":11,"query":{"perPage":3,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"featured":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true},"collection":"woocommerce/product-collection/featured","hideControls":["inherit","featured"],"className":"gilda-carousel gilda-carousel--3 gilda-featured"} -->
<div class="wp-block-woocommerce-product-collection gilda-carousel gilda-carousel--3 gilda-featured"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true,"aspectRatio":"3/4","scale":"cover"} /-->

<!-- wp:post-title {"textAlign":"center","level":3,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- /wp:woocommerce/product-template --></div>
<!-- /wp:woocommerce/product-collection --></div>
<!-- /wp:group -->
