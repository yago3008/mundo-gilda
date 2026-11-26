<?php
/**
 * Title: Seção Loja + Instagram
 * Slug: gilda/shop-section
 * Categories: gilda
 * Keywords: loja, borboleta, instagram
 * Description: "LOJA" com ícone da borboleta 88 levando para Produtos; logo e @gildapontoevirgula (tasks 2.4 e 2.5).
 * Viewport Width: 1440
 *
 * @package gilda
 */

$gilda_products  = home_url( '/produtos/' );
$gilda_butterfly = gilda_image( 'butterfly' );
?>
<!-- wp:group {"align":"full","backgroundColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|50","margin":{"top":"0"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group alignfull has-white-background-color has-background" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:heading {"textAlign":"center","className":"is-style-headline","style":{"typography":{"fontSize":"36px","letterSpacing":"0.3em"}}} -->
<h2 class="wp-block-heading has-text-align-center is-style-headline" style="font-size:36px;letter-spacing:0.3em"><?php echo esc_html__( 'LOJA', 'gilda' ); ?></h2>
<!-- /wp:heading -->

<?php if ( $gilda_butterfly ) : ?>
<?php echo gilda_image_block( $gilda_butterfly, 'medium', array( 'className' => 'gilda-butterfly', 'href' => $gilda_products ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php else : ?>
<!-- wp:image {"width":"140px","height":"140px","sizeSlug":"full","linkDestination":"custom","align":"center"} -->
<figure class="wp-block-image aligncenter size-full is-resized"><a href="<?php echo esc_url( $gilda_products ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/borboleta-88.svg' ) ); ?>" alt="<?php echo esc_attr__( 'Borboleta 88 — ver produtos', 'gilda' ); ?>" style="width:140px;height:140px"/></a></figure>
<!-- /wp:image -->
<?php endif; ?>

<!-- wp:site-title {"level":0,"textAlign":"center","isLink":false,"className":"gilda-logo","style":{"typography":{"fontSize":"28px"}}} /-->

<!-- wp:paragraph {"align":"center","fontFamily":"headline","style":{"typography":{"fontSize":"18px","letterSpacing":"0.03em"}}} -->
<p class="has-text-align-center has-headline-font-family" style="font-size:18px;letter-spacing:0.03em"><a href="https://www.instagram.com/gildapontoevirgula/" target="_blank" rel="noreferrer noopener">@gildapontoevirgula</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
