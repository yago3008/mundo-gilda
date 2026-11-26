<?php
/**
 * Title: Hero com cupom
 * Slug: gilda/hero
 * Categories: gilda, banner
 * Keywords: hero, cupom, banner, primeiracompra
 * Description: Banner da home com faixa de cupom e CTA "disponível" (task 2.1). Foto e texto do cupom editáveis no editor.
 * Viewport Width: 1440
 *
 * @package gilda
 */

$gilda_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
$gilda_mark = gilda_image( 'hero_mark' );

ob_start();
?>
<!-- wp:paragraph {"className":"gilda-hero__ribbon","backgroundColor":"terracotta","textColor":"cream","fontFamily":"serif","style":{"spacing":{"padding":{"top":"16px","bottom":"16px","left":"var:preset|spacing|50","right":"64px"}},"typography":{"fontSize":"21px","lineHeight":"1.35","letterSpacing":"0.02em"}}} -->
<p class="gilda-hero__ribbon has-cream-color has-terracotta-background-color has-text-color has-background has-serif-font-family" style="padding-top:16px;padding-right:64px;padding-bottom:16px;padding-left:var(--wp--preset--spacing--50);font-size:21px;letter-spacing:0.02em;line-height:1.35"><?php echo esc_html__( 'Use o cupom para 10% off na sua primeira compra pelo site:', 'gilda' ); ?> <strong>primeiracompra</strong></p>
<!-- /wp:paragraph -->

<?php if ( $gilda_mark ) : ?>
<?php echo gilda_image_block( $gilda_mark, 'medium', array( 'className' => 'gilda-hero__mark gilda-decorative' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php else : ?>
<!-- wp:paragraph {"className":"gilda-hero__mark gilda-decorative gilda-semicolon-mark","style":{"typography":{"fontSize":"220px","lineHeight":"0.8"},"spacing":{"margin":{"top":"var:preset|spacing|50"},"padding":{"left":"var:preset|spacing|50"}}}} -->
<p class="gilda-hero__mark gilda-decorative gilda-semicolon-mark" style="margin-top:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);font-size:220px;line-height:0.8">;</p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:buttons {"style":{"spacing":{"padding":{"left":"var:preset|spacing|50"},"margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:button {"className":"is-style-pill"} -->
<div class="wp-block-button is-style-pill"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $gilda_shop ); ?>"><?php echo esc_html__( 'disponível', 'gilda' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php
$gilda_inner = ob_get_clean();

// phpcs:ignore WordPress.Security.EscapeOutput -- Block markup built from escaped parts.
echo gilda_cover_block(
	'hero',
	'#b5a48e',
	array(
		'minHeight'       => 680,
		'contentPosition' => 'top left',
		'align'           => 'full',
		'className'       => 'gilda-hero',
		'padding'         => array(
			'top'    => 'var:preset|spacing|60',
			'bottom' => 'var:preset|spacing|70',
			'left'   => '0',
			'right'  => 'var:preset|spacing|50',
		),
	),
	$gilda_inner,
	10
);
