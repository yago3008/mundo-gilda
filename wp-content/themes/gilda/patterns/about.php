<?php
/**
 * Title: Página Quem Cria
 * Slug: gilda/about
 * Categories: gilda-pages
 * Keywords: quem cria, sobre, giulia, maria clara
 * Description: Manchete, bloco marrom com as criadoras e encerramento "VOCÊ CONTA A HISTÓRIA" (task 8.1). Textos do site atual; fotos importadas.
 * Inserter: no
 *
 * @package gilda
 */

$gilda_people = array(
	array(
		'name'  => 'giulia',
		'image' => 'about_giulia',
		'tone'  => '#c9b8a0',
		'text'  => array(
			'"Antes mesmo de entender o que era moda, eu já a vivia.',
			'Fosse desenhando roupas, quando criança, e aprendendo a melhorar meu traço, ou costurando, com minha mãe, roupinhas para minhas bonecas com retalhos coloridos e muita imaginação. Era brincadeira, era criação — e sem que eu percebesse, já era também um presságio do que viria a ser a minha essência.',
			'Durante anos, segui outro caminho. A medicina parecia o destino certo, talvez por querer cuidar, acolher, fazer pelos outros. E, de fato, essa vontade ainda me habita. Mas foi ao me permitir ouvir minha própria voz, que compreendi: o que eu buscava curar não era apenas o corpo, mas tocar almas, provocar sensações, traduzir sentimentos em forma, cor e textura.',
			'Escolher essa profissão foi um gesto de coragem, e também de amor. Amor por mim, pela minha história, e por tudo aquilo que pulsa quando me deixo criar.',
			'Minha família, com ternura e apoio, foi meu alicerce nesse processo. Não apenas me ampararam, como acreditaram. E acreditar, às vezes, é mais valioso do que qualquer certeza.',
			'Hoje, sigo em jornada. Observando, sentindo, aprendendo. Me edificando com o que é verdadeiro, me inspirando nos que vieram antes, e buscando, em cada passo, criar algo que traduza o meu amor pela vida, pela moda e por tudo que pode ser dito sem palavras — apenas costurado com a alma e vestido com história."',
		),
	),
	array(
		'name'  => 'maria clara',
		'image' => 'about_maria_clara',
		'tone'  => '#e3dfd4',
		'text'  => array(
			'"Ou<br>Amor pra minha esposa;<br>Mãe Clara pra minha filha;<br>Clara pros amigos;<br>Lila pra minha mãe &amp;<br>Maria pros meus irmãos.',
			'Cresci no interior do Mato Grosso e sonhava grande. A vida me levou a estudar Direito — talvez porque todo mundo dizia que era uma boa ideia. E lá fui eu advogar, com aquela sensação constante de que eu tinha estacionado no lugar errado.',
			'Depois de muito estresse, café frio e umas boas sessões de terapia, percebi que eu não cabia naquele terno. Foi então que a moda entrou na minha vida e me salvou. Descobri que criar roupas era, na verdade, costurar partes de mim que andavam esquecidas.',
			'Hoje, casada e mãe, bagunçou (e coloriu) tudo de vez. Foi por elas e por mim mesma, que resolvi transformar esse amor em trabalho.',
			'Então, pode entrar, tirar os sapatos e ficar à vontade. Aqui é pra vestir histórias e se sentir em casa."',
		),
	),
);
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"margin":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:heading {"textAlign":"center","level":2,"className":"is-style-headline","style":{"typography":{"fontSize":"clamp(32px, 5vw, 56px)","lineHeight":"1.1"}}} -->
<h2 class="wp-block-heading has-text-align-center is-style-headline" style="font-size:clamp(32px, 5vw, 56px);line-height:1.1"><?php echo esc_html__( 'ALFAIATARIA', 'gilda' ); ?><br><?php echo esc_html__( 'CRIATIVA', 'gilda' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","backgroundColor":"brown","textColor":"cream","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|70","margin":{"top":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|cream"}}}},"layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignfull has-cream-color has-brown-background-color has-text-color has-background has-link-color" style="margin-top:0;padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","level":1,"className":"is-style-editorial","style":{"typography":{"fontSize":"clamp(44px, 6vw, 72px)","textTransform":"none"}}} -->
<h1 class="wp-block-heading has-text-align-center is-style-editorial" style="font-size:clamp(44px, 6vw, 72px);text-transform:none">quem <span style="font-family:var(--wp--preset--font-family--headline);font-style:normal">CRIA</span></h1>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70","top":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns">
<?php foreach ( $gilda_people as $gilda_person ) : ?>
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<?php
$gilda_photo = gilda_image( $gilda_person['image'] );
if ( $gilda_photo ) {
	echo gilda_image_block( $gilda_photo, 'large', array( 'className' => 'gilda-portrait' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
	echo gilda_cover_block( $gilda_person['image'], $gilda_person['tone'], array( 'minHeight' => 360 ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>

<!-- wp:heading {"level":2,"className":"is-style-editorial","style":{"typography":{"fontSize":"36px","textTransform":"none"},"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<h2 class="wp-block-heading is-style-editorial" style="margin-top:var(--wp--preset--spacing--40);font-size:36px;text-transform:none"><?php echo esc_html( $gilda_person['name'] ); ?></h2>
<!-- /wp:heading -->
<?php foreach ( $gilda_person['text'] as $gilda_paragraph ) : ?>

<!-- wp:paragraph {"fontFamily":"serif","fontSize":"large"} -->
<p class="has-serif-font-family has-large-font-size"><?php echo wp_kses( $gilda_paragraph, array( 'br' => array() ) ); ?></p>
<!-- /wp:paragraph -->
<?php endforeach; ?></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
<div class="wp-block-group"><!-- wp:paragraph {"fontFamily":"headline","style":{"typography":{"fontSize":"22px","letterSpacing":"0.05em"}}} -->
<p class="has-headline-font-family" style="font-size:22px;letter-spacing:0.05em"><?php echo esc_html__( 'VOCÊ CONTA A HISTÓRIA', 'gilda' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:site-title {"level":0,"isLink":false,"className":"gilda-logo is-light","style":{"typography":{"fontSize":"26px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
