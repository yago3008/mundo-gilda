<?php
/**
 * Carga inicial das fotos por cor (mu-plugin gilda-color-gallery) a partir de data/product-color-images.json.
 *
 * Formato: { "<slug da peça>": { "<slug da cor>": ["<id da foto no Wix>", ...] } }
 * O id do Wix é o nome do arquivo (ex.: ff503a_745e5e08...~mv2), procurado no _gilda_source_url das
 * fotos já importadas. A ordem da lista é a ordem na galeria daquela cor.
 *
 * Só preenche peça que ainda não tem nenhuma foto por cor: o que as donas mudarem no painel fica.
 * REFRESH=1 regrava tudo a partir do JSON.
 *
 * Uso: docker compose run --rm wpcli wp eval-file /scripts/import-color-images.php
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gilda_color_gallery_set' ) ) {
	WP_CLI::warning( 'mu-plugin gilda-color-gallery não carregado.' );
	return;
}

$file = rtrim( getenv( 'GILDA_DATA_DIR' ) ?: '/data', '/' ) . '/product-color-images.json'; // produção: GILDA_DATA_DIR
if ( ! is_readable( $file ) ) {
	WP_CLI::log( '  (sem data/product-color-images.json)' );
	return;
}
$data    = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$refresh = (bool) getenv( 'REFRESH' );

/** Anexo importado do Wix cujo endereço de origem contém o id. */
$attachment_for = static function ( string $wix_id ): int {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $wix_id ) . '%';
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gilda_source_url' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

foreach ( (array) $data as $slug => $colors ) {
	if ( str_starts_with( (string) $slug, '_' ) ) {
		continue; // comentários no JSON
	}
	$post = get_page_by_path( (string) $slug, OBJECT, 'product' );
	$product = $post ? wc_get_product( $post->ID ) : null;
	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		WP_CLI::log( "  {$slug}: peça não encontrada, pulando" );
		continue;
	}
	if ( ! $refresh && gilda_color_gallery_get( $product ) ) {
		WP_CLI::log( "  {$slug}: já tem fotos por cor, mantido" );
		continue;
	}
	$map = array();
	foreach ( (array) $colors as $color => $wix_ids ) {
		$ids = array_values( array_filter( array_map( $attachment_for, (array) $wix_ids ) ) );
		if ( count( $ids ) !== count( (array) $wix_ids ) ) {
			WP_CLI::log( "  {$slug}/{$color}: " . ( count( (array) $wix_ids ) - count( $ids ) ) . ' foto(s) ainda não importada(s) (rode com IMPORT_IMAGES=1)' );
		}
		$map[ (string) $color ] = $ids;
	}
	gilda_color_gallery_set( $product, $map );
	WP_CLI::log( "  {$slug}: " . implode( ', ', array_map( static fn( $c, $ids ) => $c . ' ' . count( $ids ), array_keys( $map ), $map ) ) );
}
