<?php
/**
 * Title: Capítulos em destaque
 * Slug: gilda/shop-section
 * Categories: gilda
 * Keywords: capítulos, fotos, livro GILDA
 * Description: Cards dos capítulos (os 5 mais recentes publicados no painel), com uma foto rotativa para cada um.
 * Viewport Width: 1440
 *
 * @package gilda
 */

// Decisão §16.3: no máximo 5 cards, os capítulos mais recentes (em ordem crescente).
$gilda_featured_chapters = array_slice( gilda_book_chapters(), -5 );
if ( ! $gilda_featured_chapters ) {
	return;
}
?>
<!-- wp:group {"align":"full","className":"gilda-chapter-showcase","backgroundColor":"white","layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignfull gilda-chapter-showcase has-white-background-color has-background"><!-- wp:heading {"textAlign":"center","className":"is-style-headline"} -->
<h2 class="wp-block-heading has-text-align-center is-style-headline"><?php echo esc_html__( 'CAPÍTULOS', 'gilda' ); ?></h2>
<!-- /wp:heading -->
<div class="gilda-chapter-showcase__grid" data-count="<?php echo (int) count( $gilda_featured_chapters ); ?>" style="--gilda-chapter-cards:<?php echo (int) count( $gilda_featured_chapters ); ?>">
<?php foreach ( $gilda_featured_chapters as $gilda_featured_chapter ) :
	$gilda_ids    = $gilda_featured_chapter['gallery'];
	$gilda_slides = array_values( array_filter( array_map( static function ( $gilda_id ) {
		return gilda_image_url( $gilda_id, 'large' );
	}, $gilda_ids ) ) );
	$gilda_image = $gilda_slides[0] ?? '';
	$gilda_url   = gilda_chapter_shop_url( $gilda_featured_chapter['anchor'] );
	?>
	<a class="gilda-chapter-card" href="<?php echo esc_url( $gilda_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver fotos do %s', 'gilda' ), $gilda_featured_chapter['title'] ) ); ?>"<?php echo $gilda_slides ? ' data-chapter-slides="' . esc_attr( wp_json_encode( $gilda_slides ) ) . '"' : ''; ?>>
		<?php if ( $gilda_image ) : ?>
		<img class="gilda-chapter-card__image" src="<?php echo esc_url( $gilda_image ); ?>" alt="" />
		<?php endif; ?>
		<span class="gilda-chapter-card__title"><?php echo esc_html( $gilda_featured_chapter['title'] ); ?></span>
	</a>
<?php endforeach; ?>
</div></div>
<!-- /wp:group -->
