<?php
/**
 * Title: Página Produtos; (carrossel)
 * Slug: gilda/products-page
 * Categories: gilda-pages, woo-commerce
 * Keywords: produtos, carrossel, capítulos, alfaiataria
 * Description: Headings editoriais decorativos + carrossel cíclico com todos os produtos (tasks 3.1, 3.2, 3.3).
 * Inserter: no
 *
 * @package gilda
 */

$gilda_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:heading {"level":1,"className":"screen-reader-text"} -->
<h1 class="wp-block-heading screen-reader-text"><?php echo esc_html__( 'Produtos', 'gilda' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:group {"className":"gilda-decorative","textColor":"terracotta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group gilda-decorative has-terracotta-color has-text-color"><!-- wp:paragraph {"fontFamily":"display","style":{"typography":{"fontStyle":"italic","fontWeight":"300","fontSize":"clamp(56px, 9vw, 120px)","lineHeight":"1"}}} -->
<p class="has-display-font-family" style="font-size:clamp(56px, 9vw, 120px);font-style:italic;font-weight:300;line-height:1">produtos;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontFamily":"serif","style":{"typography":{"fontSize":"28px","letterSpacing":"0.2em"}}} -->
<p class="has-serif-font-family" style="font-size:28px;letter-spacing:0.2em">capítulos</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontFamily":"display","style":{"typography":{"fontStyle":"italic","fontWeight":"300","fontSize":"40px"}}} -->
<p class="has-display-font-family" style="font-size:40px;font-style:italic;font-weight:300">histórias</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:woocommerce/product-collection {"queryId":12,"query":{"perPage":24,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"menu_order","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"collection":"woocommerce/product-collection/product-catalog","hideControls":["inherit"],"className":"gilda-carousel"} -->
<div class="wp-block-woocommerce-product-collection gilda-carousel"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true,"aspectRatio":"3/4","scale":"cover"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->

<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true} /-->
<!-- /wp:woocommerce/product-template --></div>
<!-- /wp:woocommerce/product-collection -->

<!-- wp:group {"className":"gilda-decorative","textColor":"terracotta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group gilda-decorative has-terracotta-color has-text-color"><!-- wp:paragraph {"fontFamily":"headline","style":{"typography":{"fontSize":"clamp(32px, 6vw, 72px)","lineHeight":"1"}}} -->
<p class="has-headline-font-family" style="font-size:clamp(32px, 6vw, 72px);line-height:1">ALFAIATARIA</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontFamily":"serif","style":{"typography":{"fontSize":"28px","fontStyle":"italic"}}} -->
<p class="has-serif-font-family" style="font-size:28px;font-style:italic">palavras</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-editorial-link"} -->
<div class="wp-block-button is-style-editorial-link"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $gilda_shop ); ?>"><?php echo esc_html__( 'ver todos os produtos e filtrar', 'gilda' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
