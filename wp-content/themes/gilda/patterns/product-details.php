<?php
/**
 * Title: Descrição do produto (composição + guia de tamanhos)
 * Slug: gilda/product-details
 * Categories: gilda, woo-commerce
 * Keywords: composição, tamanhos, medidas, guia
 * Post Types: product
 * Description: Texto editorial, composição em destaque e tabela "Tamanhos em centímetros" (tasks 5.3 e 5.4). Insira na descrição de cada produto.
 *
 * @package gilda
 */

$gilda_sizes = array(
	array( 'PP', '88', '70' ),
	array( 'P', '92', '74' ),
	array( 'M', '96', '78' ),
	array( 'G', '100', '82' ),
	array( 'GG', '104', '86' ),
);
?>
<!-- wp:paragraph -->
<p><?php echo esc_html__( '[Texto editorial do produto: conceito, inspiração e caimento.]', 'gilda' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"gilda-composition"} -->
<p class="gilda-composition"><?php echo esc_html__( 'Composição: 100% algodão egípcio', 'gilda' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2,"className":"gilda-label"} -->
<h2 class="wp-block-heading gilda-label"><?php echo esc_html__( 'Tamanhos em centímetros:', 'gilda' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"gilda-size-guide"} -->
<figure class="wp-block-table gilda-size-guide"><table><thead><tr><th><?php echo esc_html__( 'Tamanho', 'gilda' ); ?></th><th><?php echo esc_html__( 'Busto (cm)', 'gilda' ); ?></th><th><?php echo esc_html__( 'Cintura (cm)', 'gilda' ); ?></th></tr></thead><tbody><?php foreach ( $gilda_sizes as $gilda_row ) : ?><tr><td><?php echo esc_html( $gilda_row[0] ); ?></td><td><?php echo esc_html( $gilda_row[1] ); ?></td><td><?php echo esc_html( $gilda_row[2] ); ?></td></tr><?php endforeach; ?></tbody></table><figcaption class="wp-element-caption"><?php echo esc_html__( 'Modelo veste tamanho PP.', 'gilda' ); ?></figcaption></figure>
<!-- /wp:table -->
