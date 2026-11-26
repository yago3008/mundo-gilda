<?php
/**
 * Title: Página Livro GILDA;
 * Slug: gilda/book
 * Categories: gilda-pages
 * Keywords: livro, capítulos, borboleta 88, ritual brasileiro, lookbook
 * Description: "LIVRO GILDA;" com 3 capítulos: galeria com lightbox, narrativa e links "peças disponíveis" (tasks 9.1–9.4). Textos e fotos do site atual.
 * Inserter: no
 *
 * @package gilda
 */

$gilda_chapters = array(
	array(
		'title' => __( 'CAPÍTULO 1', 'gilda' ),
		'key'   => 'chapter_1',
		'anchor' => 'capitulo-1',
		'text'  => array(
			'A GILDA; apresenta seu primeiro capítulo, inspirado na borboleta 88.',
			'Símbolo de transformação e movimento, a borboleta conduz este capítulo a partir de suas formas, cores e delicadezas. Seu desenho singular se desdobra em peças que traduzem a leveza sem abrir mão da estrutura característica da alfaiataria.',
			'O capítulo apresenta duas camisas, em branco e azul, e um lenço com estampa exclusiva em aquarela, criada a partir das formas circulares presentes nas asas da borboleta 88.',
			'Este capítulo marca o início de um percurso: o instante em que algo deixa de ser apenas ideia, ganha forma e se prepara para seguir adiante.',
			'<em>Um começo. Um movimento. Um novo voo.</em>',
		),
		'links' => array(
			array( __( 'peças disponíveis', 'gilda' ), gilda_product_url( 'camisa-casulo' ) ),
		),
	),
	array(
		'title' => __( 'CAPÍTULO 2', 'gilda' ),
		'key'   => 'chapter_2',
		'anchor' => 'capitulo-2',
		'text'  => array(
			'No segundo capítulo, a GILDA; continua a observar a borboleta, agora mais de perto.',
			'A inspiração parte dos filetes que percorrem suas asas: linhas delicadas que sustentam, delimitam e conduzem o movimento. Essa construção se traduz na bermuda ENVOLTO, com pregas frontais e passantes integrados, e no lenço MANACÁ, cuja estampa em aquarela reinterpreta os traços das asas.',
			'Entre linhas precisas e formas fluidas, o capítulo aproxima a estrutura da alfaiataria da leveza encontrada na natureza.',
			'<em>Mais do que contornar uma forma, os filetes revelam os caminhos que existem dentro dela.</em>',
		),
		'links' => array(
			array( __( 'peças disponíveis: Bermuda Envolto', 'gilda' ), gilda_product_url( 'bermuda-envolto' ) ),
			array( __( 'peças disponíveis: Lenço Manacá', 'gilda' ), gilda_product_url( 'lenco-manaca' ) ),
		),
	),
	array(
		'title' => __( 'CAPÍTULO 3', 'gilda' ),
		'key'   => 'chapter_3',
		'anchor' => 'capitulo-3',
		'text'  => array(
			'Agora, a GILDA; apresenta um novo capítulo: RITUAL BRASILEIRO.',
			'Inspirado no café, mas, sobretudo, no tempo que se cria ao redor dele, o capítulo parte de um gesto cotidiano profundamente presente na cultura brasileira: fazer uma pausa. Na companhia de alguém ou de si mesma, esse é o momento de observar, conversar, descansar, respirar ou simplesmente passar um café.',
			'RITUAL BRASILEIRO apresenta as camisas GRÃO e CAFÉ, confeccionadas em algodão egípcio e marcadas por um desenho especial nos punhos, além de uma calça de alfaiataria com pregas frontais profundas.',
			'Mais do que uma referência ao café, este capítulo é um convite para reconhecer o valor dos pequenos rituais que atravessam os nossos dias.',
			'<em>Uma pausa. Uma conversa. Um café.</em>',
		),
		'links' => array(
			array( __( 'peças disponíveis: Camisa Grão', 'gilda' ), gilda_product_url( 'camisa-grao' ) ),
			array( __( 'peças disponíveis: Bermuda Envolto', 'gilda' ), gilda_product_url( 'bermuda-envolto' ) ),
			array( __( 'peças disponíveis: Camisa Casulo', 'gilda' ), gilda_product_url( 'camisa-casulo' ) ),
		),
	),
);

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
<?php
$gilda_lookbook = 'chapter_3' === $gilda_chapter['key'] ? (array) gilda_image( 'chapter_3_lookbook' ) : array();
if ( $gilda_lookbook ) :
	?>

<!-- wp:group {"className":"gilda-lookbook","style":{"spacing":{"margin":{"top":"var:preset|spacing|70"},"blockGap":"8px"}},"layout":{"type":"grid","minimumColumnWidth":"220px"}} -->
<div class="wp-block-group gilda-lookbook" style="margin-top:var(--wp--preset--spacing--70)">
	<?php
	foreach ( $gilda_lookbook as $gilda_id ) {
		echo gilda_image_block( $gilda_id, 'large' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
</div>
<!-- /wp:group -->
<?php endif; ?></div>
<!-- /wp:group -->
<?php endforeach; ?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:site-title {"level":0,"textAlign":"center","isLink":false,"className":"gilda-logo","style":{"typography":{"fontSize":"48px"}}} /--></div>
<!-- /wp:group -->
