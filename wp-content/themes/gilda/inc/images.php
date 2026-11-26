<?php
/**
 * Site imagery imported from the original site (scripts/import-site-images.php).
 *
 * The import stores attachment IDs in the `gilda_site_images` option:
 *   [ 'hero' => 12, 'chapter_1' => [ 40, 41, … ], … ]
 * Patterns call these helpers and fall back to colour placeholders when an
 * image hasn't been imported yet, so the theme works on a fresh install.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attachment ID(s) for an image role.
 *
 * @param string $key Role, e.g. 'hero', 'category_camisas', 'chapter_1'.
 * @return int|int[]  0 / [] when missing.
 */
function gilda_image( $key ) {
	static $map = null;
	if ( null === $map ) {
		$map = (array) get_option( 'gilda_site_images', array() );
	}
	$value = $map[ $key ] ?? 0;
	if ( is_array( $value ) ) {
		return array_values( array_filter( array_map( 'absint', $value ), 'wp_attachment_is_image' ) );
	}
	$value = absint( $value );
	return ( $value && wp_attachment_is_image( $value ) ) ? $value : 0;
}

/**
 * URL of an attachment at a given size ('' when missing).
 *
 * @param int    $id   Attachment ID.
 * @param string $size Size slug.
 * @return string
 */
function gilda_image_url( $id, $size = 'large' ) {
	$url = $id ? wp_get_attachment_image_url( $id, $size ) : '';
	return $url ? $url : '';
}

/**
 * Alt text of an attachment.
 *
 * @param int $id Attachment ID.
 * @return string
 */
function gilda_image_alt( $id ) {
	return $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
}

/**
 * Block markup for a core/image (static, matches the block's saved HTML).
 *
 * @param int    $id    Attachment ID.
 * @param string $size  Size slug.
 * @param array  $extra Extra block attributes (e.g. className, href).
 * @return string
 */
function gilda_image_block( $id, $size = 'large', $extra = array() ) {
	$url = gilda_image_url( $id, $size );
	if ( ! $url ) {
		return '';
	}
	$href  = $extra['href'] ?? '';
	$class = $extra['className'] ?? '';
	unset( $extra['href'] );

	$attrs = array_merge(
		array(
			'id'              => (int) $id,
			'sizeSlug'        => $size,
			'linkDestination' => $href ? 'custom' : 'none',
		),
		$extra
	);

	$img = sprintf(
		'<img src="%1$s" alt="%2$s" class="wp-image-%3$d"/>',
		esc_url( $url ),
		esc_attr( gilda_image_alt( $id ) ),
		(int) $id
	);
	if ( $href ) {
		$img = sprintf( '<a href="%s">%s</a>', esc_url( $href ), $img );
	}

	return sprintf(
		"<!-- wp:image %1\$s -->\n<figure class=\"wp-block-image size-%2\$s%3\$s\">%4\$s</figure>\n<!-- /wp:image -->",
		wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		esc_attr( $size ),
		$class ? ' ' . esc_attr( $class ) : '',
		$img
	);
}

/**
 * Cover block markup: the imported photo when available, otherwise a flat
 * placeholder colour. Inner HTML is passed through untouched.
 *
 * @param string $key   Image role.
 * @param string $color Placeholder colour (hex).
 * @param array  $attrs Extra cover attributes (minHeight, className, contentPosition, style…).
 * @param string $inner Inner blocks markup.
 * @param int    $dim   Overlay opacity (0–100) used on top of photos.
 * @return string
 */
function gilda_cover_block( $key, $color, $attrs, $inner, $dim = 20 ) {
	$id  = gilda_image( $key );
	$url = gilda_image_url( is_array( $id ) ? 0 : $id, 'full' );

	$min_height = (int) ( $attrs['minHeight'] ?? 420 );
	$position   = $attrs['contentPosition'] ?? 'bottom center';
	$class      = $attrs['className'] ?? '';
	$align      = $attrs['align'] ?? '';
	$padding    = $attrs['padding'] ?? '';

	$block = array(
		'dimRatio'           => $url ? $dim : 100,
		'customOverlayColor' => $url ? '#241a16' : $color,
		'isUserOverlayColor' => true,
		'minHeight'          => $min_height,
		'minHeightUnit'      => 'px',
		'contentPosition'    => $position,
		'layout'             => array( 'type' => 'constrained' ),
	);
	if ( $url ) {
		$block = array( 'url' => $url, 'id' => (int) $id ) + $block;
	}
	if ( $align ) {
		$block['align'] = $align;
	}
	if ( $class ) {
		$block['className'] = $class;
	}
	if ( $padding ) {
		$block['style'] = array( 'spacing' => array( 'padding' => $padding ) );
	}

	$classes = array( 'wp-block-cover' );
	if ( $align ) {
		$classes[] = 'align' . $align;
	}
	if ( 'center center' !== $position ) {
		$classes[] = 'has-custom-content-position';
		$classes[] = 'is-position-' . str_replace( ' ', '-', $position );
	}
	if ( $class ) {
		$classes[] = $class;
	}

	$style = '';
	if ( $padding ) {
		foreach ( $padding as $side => $value ) {
			$css    = 0 === strpos( (string) $value, 'var:preset|spacing|' ) ? 'var(--wp--preset--spacing--' . substr( $value, 19 ) . ')' : $value;
			$style .= "padding-{$side}:{$css};";
		}
	}
	$style .= "min-height:{$min_height}px";

	$overlay_color = $url ? '#241a16' : $color;
	$ratio         = $url ? $dim : 100;
	$background    = sprintf(
		'<span aria-hidden="true" class="wp-block-cover__background has-background-dim-%1$d has-background-dim" style="background-color:%2$s"></span>',
		$ratio,
		esc_attr( $overlay_color )
	);
	if ( $ratio > 0 && 0 !== $ratio % 10 ) {
		$background = str_replace( 'has-background-dim-' . $ratio . ' has-background-dim', 'has-background-dim', $background );
	}
	$image = $url ? sprintf(
		'<img class="wp-block-cover__image-background wp-image-%1$d" alt="%2$s" src="%3$s" data-object-fit="cover"/>',
		(int) $id,
		esc_attr( gilda_image_alt( $id ) ),
		esc_url( $url )
	) : '';

	return sprintf(
		"<!-- wp:cover %1\$s -->\n<div class=\"%2\$s\" style=\"%3\$s\">%4\$s%5\$s<div class=\"wp-block-cover__inner-container\">%6\$s</div></div>\n<!-- /wp:cover -->",
		wp_json_encode( $block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $style ),
		$background,
		$image,
		$inner
	);
}

/**
 * Logo (task 1.3): replace the text site title with the imported logo image
 * wherever the site-title block carries the `gilda-logo` class.
 *
 * @param string $content Block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function gilda_render_logo( $content, $block ) {
	$class = $block['attrs']['className'] ?? '';
	if ( false === strpos( $class, 'gilda-logo' ) ) {
		return $content;
	}
	$id = gilda_image( 'logo' );
	if ( ! $id ) {
		return $content;
	}
	$img = sprintf(
		'<img class="gilda-logo__img" src="%1$s" alt="%2$s" width="%3$d" height="%4$d" />',
		esc_url( gilda_image_url( $id, 'medium_large' ) ),
		esc_attr( get_bloginfo( 'name' ) ),
		200,
		50
	);
	$is_link = ! isset( $block['attrs']['isLink'] ) || $block['attrs']['isLink'];
	if ( $is_link ) {
		$img = sprintf( '<a href="%1$s" rel="home">%2$s</a>', esc_url( home_url( '/' ) ), $img );
	}
	return sprintf( '<div class="wp-block-site-title %1$s has-logo">%2$s</div>', esc_attr( $class ), $img );
}
add_filter( 'render_block_core/site-title', 'gilda_render_logo', 10, 2 );
