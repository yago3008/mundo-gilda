<?php
/**
 * Title: Categorias visuais
 * Slug: gilda/categories
 * Categories: gilda
 * Keywords: categorias, camisas, calças, bermudas, lenços
 * Description: 4 categorias com links para o catálogo já filtrado + "ALFAIATARIA CRIATIVA" (task 2.3). Grade 4 → 2 → 1 colunas.
 * Viewport Width: 1440
 *
 * @package gilda
 */

// Cada card abre o catálogo filtrado pelo tipo de produto.
$gilda_categories = array(
	array( 'category_camisas', __( 'CAMISAS', 'gilda' ), gilda_product_type_url( 'camisas' ), '#8a5a3c' ),
	array( 'category_calcas', __( 'CALÇAS', 'gilda' ), gilda_product_type_url( 'calcas' ), '#a08a6e' ),
	array( 'category_bermudas', __( 'BERMUDAS', 'gilda' ), gilda_product_type_url( 'bermudas' ), '#6b4a33' ),
	array( 'category_lencos', __( 'LENÇOS', 'gilda' ), gilda_product_type_url( 'lencos' ), '#4e1a08' ),
);
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignwide" style="padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"grid","minimumColumnWidth":"240px"}} -->
<div class="wp-block-group">
<?php
foreach ( $gilda_categories as $gilda_cat ) {
	$gilda_label = sprintf(
		'<!-- wp:paragraph {"align":"center","textColor":"cream","fontFamily":"display","style":{"typography":{"fontSize":"22px","letterSpacing":"0.15em"},"elements":{"link":{"color":{"text":"var:preset|color|cream"}}}}} -->' . "\n" .
		'<p class="has-text-align-center has-cream-color has-text-color has-link-color has-display-font-family" style="font-size:22px;letter-spacing:0.15em"><a href="%1$s">%2$s</a></p>' . "\n" .
		'<!-- /wp:paragraph -->',
		esc_url( $gilda_cat[2] ),
		esc_html( $gilda_cat[1] )
	);
	// phpcs:ignore WordPress.Security.EscapeOutput -- Block markup built from escaped parts.
	echo gilda_cover_block(
		$gilda_cat[0],
		$gilda_cat[3],
		array(
			'minHeight'       => 420,
			'contentPosition' => 'bottom center',
			'className'       => 'gilda-category',
			'padding'         => array( 'bottom' => '28px' ),
		),
		$gilda_label,
		30
	) . "\n";
}
?>
</div>
<!-- /wp:group -->

<!-- wp:heading {"textAlign":"center","className":"is-style-headline","style":{"typography":{"fontSize":"28px"}}} -->
<h2 class="wp-block-heading has-text-align-center is-style-headline" style="font-size:28px"><?php echo esc_html__( 'ALFAIATARIA CRIATIVA', 'gilda' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->
