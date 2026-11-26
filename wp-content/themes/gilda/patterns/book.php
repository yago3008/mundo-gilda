<?php
/**
 * Title: Página Livro GILDA;
 * Slug: gilda/book
 * Categories: gilda-pages
 * Keywords: livro, capítulos, borboleta 88, ritual brasileiro
 * Description: "LIVRO GILDA;" com 3 capítulos: galeria com lightbox, narrativa e links "peças disponíveis" (tasks 9.1–9.4). Textos e fotos do site atual.
 * Inserter: no
 *
 * @package gilda
 */

$gilda_chapters = gilda_book_chapters();

/**
 * Gallery block (core/gallery with nested images; lightbox comes from theme.json).
 *
 * @param int[] $ids Attachment IDs.
 * @return string
 */
$gilda_gallery = static function ( $ids ) {
	$images = '';
	foreach ( $ids as $id ) {
		$images .= gilda_image_block( $id, 'medium_large' ) . "\n\n";
	}
	return "<!-- wp:gallery {\"columns\":4,\"linkTo\":\"none\",\"sizeSlug\":\"medium_large\",\"className\":\"gilda-chapter-gallery\"} -->\n" .
		'<figure class="wp-block-gallery has-nested-images columns-4 is-cropped gilda-chapter-gallery">' . $images . "</figure>\n<!-- /wp:gallery -->";
};
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"},"margin":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:heading {"textAlign":"center","level":1,"fontSize":"display","style":{"typography":{"fontWeight":"300","letterSpacing":"0.08em"}}} -->
<h1 class="wp-block-heading has-text-align-center has-display-font-size" style="font-weight:300;letter-spacing:0.08em">LIVRO GILDA;</h1>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<?php foreach ( $gilda_chapters as $gilda_chapter ) : ?>
<!-- wp:group {"align":"full","anchor":"<?php echo esc_attr( $gilda_chapter['anchor'] ); ?>","backgroundColor":"linen","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"4px"}}},"layout":{"type":"constrained","contentSize":"1170px"}} -->
<div id="<?php echo esc_attr( $gilda_chapter['anchor'] ); ?>" class="wp-block-group alignfull has-linen-background-color has-background" style="margin-top:4px;padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)"><!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60","top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"64px"} -->
<div class="wp-block-column" style="flex-basis:64px"><!-- wp:heading {"className":"gilda-vertical-heading","style":{"typography":{"fontSize":"34px","fontWeight":"300","letterSpacing":"0.15em"}}} -->
<h2 class="wp-block-heading gilda-vertical-heading" style="font-size:34px;font-weight:300;letter-spacing:0.15em"><?php echo esc_html( $gilda_chapter['title'] ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%"><?php echo $gilda_gallery( (array) gilda_image( $gilda_chapter['key'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

<!-- wp:buttons {"layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-buttons"><?php foreach ( $gilda_chapter['links'] as $gilda_link ) : ?><!-- wp:button {"className":"is-style-editorial-link"} -->
<div class="wp-block-button is-style-editorial-link"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $gilda_link[1] ); ?>"><?php echo esc_html( $gilda_link[0] ); ?></a></div>
<!-- /wp:button --><?php endforeach; ?></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><?php foreach ( $gilda_chapter['text'] as $gilda_paragraph ) : ?><!-- wp:paragraph {"fontFamily":"serif","fontSize":"large"} -->
<p class="has-serif-font-family has-large-font-size"><?php echo wp_kses( $gilda_paragraph, array( 'em' => array() ) ); ?></p>
<!-- /wp:paragraph -->

<?php endforeach; ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
<?php endforeach; ?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:site-title {"level":0,"textAlign":"center","isLink":false,"className":"gilda-logo","style":{"typography":{"fontSize":"48px"}}} /--></div>
<!-- /wp:group -->
