<?php
/**
 * Importa as imagens do site atual (logo, hero, categorias, Quem Cria e Livro GILDA;)
 * listadas em data/site-images.json para a biblioteca de mídia, e grava os IDs na
 * opção `gilda_site_images`, que o tema usa nos patterns (wp-content/themes/gilda/inc/images.php).
 *
 * Uso: SITE_IMAGES=1 ./scripts/setup.sh
 *      (ou: docker compose run --rm wpcli wp eval-file /scripts/import-site-images.php)
 *
 * Idempotente: cada URL é baixada uma vez (meta _gilda_source_url, igual ao import-products.php).
 */

defined( 'ABSPATH' ) || exit;

$file = '/data/site-images.json';
if ( ! file_exists( $file ) ) {
	WP_CLI::error( "Arquivo $file não encontrado." );
}
$data = json_decode( file_get_contents( $file ), true );

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$find = static function ( string $source ): int {
	$ids = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_gilda_source_url', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $source, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	return $ids ? (int) $ids[0] : 0;
};

$sideload = static function ( string $url, string $alt ) use ( $find ): int {
	$id = $find( $url );
	if ( ! $id ) {
		$id = media_sideload_image( $url, 0, null, 'id' );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "Imagem $url: " . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, '_gilda_source_url', $url );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
};

// Arquivo do próprio repositório (data/<file>), ex.: fotos enviadas pelas donas que não estão no Wix.
// A chave inclui o hash do arquivo: se a foto for trocada no repo, ela é importada de novo.
$sideload_file = static function ( string $file, string $alt ) use ( $find ): int {
	$path = '/data/' . ltrim( $file, '/' );
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Arquivo $path não encontrado." );
		return 0;
	}
	$source = 'file:' . $file . '#' . md5_file( $path );
	$id     = $find( $source );
	if ( ! $id ) {
		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), 0, $alt );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			WP_CLI::warning( "Arquivo $path: " . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, '_gilda_source_url', $source );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
};

// Recorte (ex.: a folha com 3 versões do logo). crop = [x, y, w, h, largura_final, altura_final].
$sideload_cropped = static function ( string $url, string $alt, array $crop, string $name ) use ( $find ): int {
	$source = $url . '#crop=' . implode( ',', $crop );
	$id     = $find( $source );
	if ( ! $id ) {
		$tmp = download_url( $url, 120 );
		if ( is_wp_error( $tmp ) ) {
			WP_CLI::warning( "Imagem $url: " . $tmp->get_error_message() );
			return 0;
		}
		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $tmp );
			WP_CLI::warning( 'Editor de imagem indisponível: ' . $editor->get_error_message() );
			return 0;
		}
		list( $x, $y, $w, $h, $dw, $dh ) = $crop;
		$editor->crop( $x, $y, $w, $h, $dw, $dh );
		$uploads = wp_upload_dir();
		$saved   = $editor->save( trailingslashit( $uploads['path'] ) . $name . '.png', 'image/png' );
		wp_delete_file( $tmp );
		if ( is_wp_error( $saved ) ) {
			WP_CLI::warning( 'Falha ao salvar recorte: ' . $saved->get_error_message() );
			return 0;
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $alt,
				'post_status'    => 'inherit',
			),
			$saved['path']
		);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $saved['path'] ) );
		update_post_meta( $id, '_gilda_source_url', $source );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
};

$map = array();
foreach ( $data['images'] as $key => $image ) {
	$alt = (string) ( $image['alt'] ?? '' );
	if ( ! empty( $image['urls'] ) ) {
		// Cada item é uma URL ou "file:images/…" (arquivo do repositório, em data/).
		$map[ $key ] = array_values(
			array_filter(
				array_map(
					static fn( $u ) => str_starts_with( $u, 'file:' ) ? $sideload_file( substr( $u, 5 ), $alt ) : $sideload( $u, $alt ),
					$image['urls']
				)
			)
		);
		WP_CLI::log( sprintf( '  • %s (%d imagens)', $key, count( $map[ $key ] ) ) );
	} elseif ( ! empty( $image['file'] ) ) {
		$map[ $key ] = $sideload_file( $image['file'], $alt );
		WP_CLI::log( "  • $key (arquivo do repositório)" );
	} elseif ( ! empty( $image['crop'] ) ) {
		$map[ $key ] = $sideload_cropped( $image['url'], $alt, $image['crop'], 'gilda-' . $key );
		WP_CLI::log( "  • $key (recortado)" );
	} else {
		$map[ $key ] = $sideload( $image['url'], $alt );
		WP_CLI::log( "  • $key" );
	}
}

update_option( 'gilda_site_images', $map, false );
WP_CLI::success( count( $map ) . ' grupos de imagens do site importados.' );
