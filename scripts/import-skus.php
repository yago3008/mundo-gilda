<?php
/**
 * SKU para as peças e variações que ainda não têm (as importadas do Wix vieram sem SKU).
 * Formato e regras: wp-content/mu-plugins/gilda-painel/sku.php (CAT-NOME-COR-TAM, ex.: CAM-GRAO-OW-PP;
 * o produto "pai" e a peça simples ganham CAT-NOME).
 *
 * SEMENTE, como os outros scripts de carga: só preenche o que está vazio. Um SKU que já existe (dado
 * por esta semente, pela tela "Nova peça" ou digitado pelas donas) nunca é trocado.
 *
 * Uso: roda sozinho no ./scripts/setup.sh  (ou: docker compose run --rm wpcli wp eval-file /scripts/import-skus.php)
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gilda_sku_ensure' ) ) {
	WP_CLI::error( 'O mu-plugin gilda-painel (sku.php) não está carregado.' );
}

$ids = wc_get_products(
	array(
		'limit'   => -1,
		'status'  => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'type'    => array( 'simple', 'variable' ),
		'return'  => 'ids',
		'orderby' => 'ID',
		'order'   => 'ASC',
	)
);

$created = 0;
foreach ( $ids as $id ) {
	$product = wc_get_product( $id );
	if ( ! $product ) {
		continue;
	}
	$changed = array();
	if ( gilda_sku_ensure( $product ) ) {
		$product->save();
		$changed[] = $product->get_sku();
	}
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation && gilda_sku_ensure( $variation ) ) {
				$variation->save();
				$changed[] = $variation->get_sku();
			}
		}
	}
	if ( $changed ) {
		$created += count( $changed );
		WP_CLI::log( sprintf( '  • %s: %s', $product->get_name(), implode( ', ', $changed ) ) );
	}
}

if ( $created && function_exists( 'gilda_catalog_cache_flush' ) ) {
	gilda_catalog_cache_flush();
}
WP_CLI::success( $created ? "$created SKU(s) criados." : 'Todas as peças já têm SKU.' );
