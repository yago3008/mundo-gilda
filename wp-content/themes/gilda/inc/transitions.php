<?php
/**
 * Transições de scroll (protótipo aplicado no tema — branch local, ver prototypes/zoom-mask/):
 *   [gilda_zoom_parallax]   colagem de 7 fotos; a central cresce até ocupar a tela.
 *   [gilda_mask_transition] "GILDA;" vazado em creme; mergulho no ";" revela a foto
 *                           e termina no banner "DEPOIS DO PONTO E VÍRGULA, VOCÊ CONTA A HISTÓRIA".
 *
 * Sem bibliotecas (CSS sticky + requestAnimationFrame em assets/js/transitions.js).
 * Com prefers-reduced-motion, as seções ficam estáticas.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * Carrega CSS/JS só nas páginas que usam as transições.
 */
function gilda_transitions_assets() {
	if ( ! is_page( array( 'capitulos', 'quem-cria' ) ) ) {
		return;
	}
	$css = '/assets/css/transitions.css';
	$js  = '/assets/js/transitions.js';
	wp_enqueue_style( 'gilda-transitions', GILDA_URI . $css, array( 'gilda-theme' ), filemtime( GILDA_DIR . $css ) );
	wp_enqueue_script( 'gilda-transitions', GILDA_URI . $js, array(), filemtime( GILDA_DIR . $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'gilda_transitions_assets', 20 );

/**
 * Resolve "chave" ou "chave:índice" de gilda_image() para um ID de anexo.
 *
 * @param string $spec Ex.: "hero", "chapter_3:2".
 * @return int
 */
function gilda_transition_image_id( $spec ) {
	list( $key, $index ) = array_pad( explode( ':', $spec, 2 ), 2, null );
	$value = gilda_image( $key );
	if ( is_array( $value ) ) {
		return (int) ( $value[ (int) $index ] ?? 0 );
	}
	return (int) $value;
}

/**
 * Camadas da colagem do Zoom Parallax (até 7 fotos; a 1ª é a central).
 *
 * @param string|int[] $specs IDs de anexo (capítulos) ou lista "chave[:índice]" separada por vírgula.
 * @param bool         $eager Carregar já (abertura da página) ou só perto da tela.
 * @return string
 */
function gilda_zp_layers( $specs, $eager = false ) {
	$scales = array( 4, 5, 6, 5, 6, 8, 9 );
	$layers = '';
	$ids    = is_array( $specs )
		? array_map( 'absint', $specs )
		: array_map( 'gilda_transition_image_id', array_map( 'trim', explode( ',', $specs ) ) );
	// O WP 6.7+ põe sizes="auto" em imagens lazy, e o navegador escolheria a foto pelo tamanho
	// da moldura pequena da colagem (que depois é ampliada 4–9×). Aqui o tamanho é o final.
	add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
	foreach ( array_slice( $ids, 0, 7 ) as $i => $id ) {
		if ( ! $id ) {
			continue;
		}
		// central em alta (versão "scaled" de até 2560 px, em WebP, com srcset); laterais em 1536
		$img     = wp_get_attachment_image(
			$id,
			0 === $i ? 'full' : '1536x1536',
			false,
			array(
				'alt'      => 0 === $i ? gilda_image_alt( $id ) : '',
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
				'sizes'    => 0 === $i ? '100vw' : '60vw',
			)
		);
		$layers .= sprintf( '<div class="gilda-zp__layer" data-scale="%d"><div class="gilda-zp__frame">%s</div></div>', $scales[ $i ], $img );
	}
	remove_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
	return $layers;
}

/**
 * [gilda_zoom_parallax images="hero,chapter_3:0,..." title="" kicker=""]
 * A primeira imagem é a central (a que cresce até a tela cheia). Até 7 imagens.
 */
function gilda_zoom_parallax_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'images' => 'hero,chapter_3:0,chapter_3:1,category_camisas,chapter_3:7,chapter_3:2,chapter_3:3',
			'title'  => '',
			'kicker' => '',
			'label'  => __( 'Fotos dos capítulos da GILDA;', 'gilda' ),
		),
		$atts,
		'gilda_zoom_parallax'
	);

	$layers = gilda_zp_layers( $atts['images'], true );
	if ( '' === $layers ) {
		return '';
	}

	$title = '';
	if ( '' !== $atts['title'] ) {
		$title = sprintf(
			'<div class="gilda-zp__title" aria-hidden="true">%s<span>%s</span></div>',
			'' !== $atts['kicker'] ? '<small>' . esc_html( $atts['kicker'] ) . '</small>' : '',
			esc_html( $atts['title'] )
		);
	}

	return sprintf(
		'<section class="gilda-zp alignfull" aria-label="%s"><div class="gilda-zp__sticky">%s%s</div></section>',
		esc_attr( $atts['label'] ),
		$layers,
		$title
	);
}
add_shortcode( 'gilda_zoom_parallax', 'gilda_zoom_parallax_shortcode' );

/**
 * [gilda_mask_transition image="story_banner" word="GILDA;" band="..." headline="..."]
 */
function gilda_mask_transition_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'image'    => 'story_banner',
			'word'     => 'GILDA;',
			'band'     => __( 'DEPOIS DO PONTO E VÍRGULA,', 'gilda' ),
			'headline' => __( 'VOCÊ CONTA A HISTÓRIA', 'gilda' ),
		),
		$atts,
		'gilda_mask_transition'
	);

	$id  = gilda_transition_image_id( $atts['image'] );
	$img = $id
		? wp_get_attachment_image( $id, 'full', false, array( 'alt' => gilda_image_alt( $id ), 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '100vw' ) )
		: '';

	ob_start();
	?>
<section class="gilda-mask alignfull" aria-label="<?php echo esc_attr( wp_strip_all_tags( $atts['band'] . ' ' . $atts['headline'] ) ); ?>">
	<div class="gilda-mask__sticky">
		<div class="gilda-mask__photo"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<svg class="gilda-mask__svg" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid slice">
			<defs>
				<mask id="gilda-mask-cut" maskUnits="userSpaceOnUse">
					<rect class="gilda-mask__white" fill="#fff"></rect>
					<g class="gilda-mask__zoom"><text class="gilda-mask__word" fill="#000" text-anchor="middle" dominant-baseline="central"><?php echo esc_html( $atts['word'] ); ?></text></g>
				</mask>
			</defs>
			<rect class="gilda-mask__cover" mask="url(#gilda-mask-cut)"></rect>
		</svg>
		<div class="gilda-mask__shade" aria-hidden="true"></div>
		<div class="gilda-mask__band" aria-hidden="true"><p><?php echo esc_html( $atts['band'] ); ?></p></div>
		<div class="gilda-mask__headline"><h2><?php echo esc_html( $atts['headline'] ); ?></h2></div>
		<div class="gilda-mask__semi" aria-hidden="true">;</div>
	</div>
</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gilda_mask_transition', 'gilda_mask_transition_shortcode' );

/**
 * [gilda_book_story] — história contínua dos capítulos (página Capítulos):
 * para cada capítulo, colagem → zoom → "CAPÍTULO N;" → narrativa com fotos trocando no scroll.
 * Depois dela vem o pattern gilda/book ("LIVRO GILDA;" com todos os capítulos).
 */
function gilda_book_story_shortcode() {
	$out = '';
	foreach ( gilda_book_chapters() as $n => $chapter ) {
		// 1. abertura: colagem + zoom + título do capítulo
		$layers = gilda_zp_layers( $chapter['zoom'], 0 === $n );
		if ( $layers ) {
			$out .= sprintf(
				'<section class="gilda-zp gilda-zp--chapter alignfull" aria-label="%1$s"><div class="gilda-zp__sticky">%2$s<div class="gilda-zp__title gilda-zp__title--chapter"><h2>%3$s<span class="gilda-semi">;</span></h2><p>%4$s</p></div></div></section>',
				esc_attr( $chapter['title'] . ' — ' . $chapter['name'] ),
				$layers,
				esc_html( $chapter['title'] ),
				esc_html( $chapter['name'] )
			);
		}

		// 2. narrativa: um parágrafo por "passo", foto do capítulo trocando junto (sem fotos próprias: as da galeria)
		$ids    = $chapter['story'] ? $chapter['story'] : $chapter['gallery'];
		$images = '';
		foreach ( $ids as $i => $id ) {
			$images .= sprintf(
				'<figure class="gilda-story__img%s">%s</figure>',
				0 === $i ? ' is-on' : '',
				wp_get_attachment_image( $id, 'full', false, array( 'alt' => gilda_image_alt( $id ), 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 781px) 100vw, 58vw' ) )
			);
		}

		$steps = '';
		foreach ( $chapter['text'] as $i => $paragraph ) {
			$steps .= sprintf( '<p class="gilda-story__step%s">%s</p>', 0 === $i ? ' is-on' : '', wp_kses( $paragraph, array( 'em' => array() ) ) );
		}
		if ( ! empty( $chapter['links'] ) ) {
			$links = '';
			foreach ( $chapter['links'] as $link ) {
				$links .= sprintf( '<a href="%s">%s</a>', esc_url( $link[1] ), esc_html( $link[0] ) );
			}
			$steps .= '<div class="gilda-story__step gilda-story__links">' . $links . '</div>';
		}
		$count = count( $chapter['text'] ) + ( empty( $chapter['links'] ) ? 0 : 1 );

		$out .= sprintf(
			'<section class="gilda-story alignfull" style="--steps:%1$d" data-steps="%1$d" aria-label="%2$s"><div class="gilda-story__sticky"><div class="gilda-story__media">%3$s</div><div class="gilda-story__text"><p class="gilda-story__eyebrow">%4$s · %5$s</p><div class="gilda-story__steps">%6$s</div><div class="gilda-story__bar" aria-hidden="true"><span></span></div></div></div></section>',
			$count,
			esc_attr( $chapter['title'] ),
			$images,
			esc_html( sprintf( /* translators: %d: chapter number */ __( 'Capítulo %d', 'gilda' ), $chapter['number'] ) ),
			esc_html( $chapter['name'] ),
			$steps
		);
	}
	return $out;
}
add_shortcode( 'gilda_book_story', 'gilda_book_story_shortcode' );
