<?php
/**
 * Plugin Name: GILDA; Imagens
 * Description: Gera as versões redimensionadas das fotos (thumbnails, 1536, 2048 e a "scaled" de 2560 px) em WebP, com qualidade 82. O original enviado fica intacto. Muitas fotos do Wix são PNG de 5–7 MB; em WebP a mesma foto fica com uma fração do peso, sem perda visível.
 *
 * Para refazer as versões de fotos já enviadas: docker compose run --rm wpcli wp media regenerate --yes
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'image_editor_output_format',
	static function ( $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

add_filter(
	'wp_editor_set_quality',
	static function ( $quality, $mime_type ) {
		return 'image/webp' === $mime_type ? 82 : $quality;
	},
	10,
	2
);
