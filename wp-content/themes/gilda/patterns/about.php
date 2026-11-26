<?php
/**
 * Title: Página Quem Cria
 * Slug: gilda/about
 * Categories: gilda-pages
 * Keywords: quem cria, sobre, giulia, maria clara
 * Description: História das criadoras e manifesto da marca.
 * Inserter: no
 *
 * @package gilda
 */

$gilda_people = array(
	array(
		'name'  => 'giulia',
		'image' => 'about_giulia',
		'focus' => 'gilda-portrait--focus-high', // foto em pé no quadro deitado: mantém rosto, ";" do suéter e livro
		'tone'  => '#c9b8a0',
		'text'  => array(
			'Antes mesmo de entender o que era moda, eu já a vivia.',
			'Fosse desenhando roupas, quando criança, e aprendendo a melhorar meu traço, ou costurando, com minha mãe, roupinhas para minhas bonecas com retalhos coloridos e muita imaginação. Era brincadeira, era criação — e sem que eu percebesse, já era também um presságio do que viria a ser a minha essência.',
			'Durante anos, segui outro caminho. A medicina parecia o destino certo, talvez por querer cuidar, acolher, fazer pelos outros. E, de fato, essa vontade ainda me habita. Mas foi ao me permitir ouvir minha própria voz, que compreendi: o que eu buscava curar não era apenas o corpo, mas tocar almas, provocar sensações, traduzir sentimentos em forma, cor e textura.',
			'Escolher essa profissão foi um gesto de coragem, e também de amor. Amor por mim, pela minha história, e por tudo aquilo que pulsa quando me deixo criar.',
			'Minha família, com ternura e apoio, foi meu alicerce nesse processo. Não apenas me ampararam, como acreditaram. E acreditar, às vezes, é mais valioso do que qualquer certeza.',
			'Hoje, sigo em jornada. Observando, sentindo, aprendendo. Me edificando com o que é verdadeiro, me inspirando nos que vieram antes, e buscando, em cada passo, criar algo que traduza o meu amor pela vida, pela moda e por tudo que pode ser dito sem palavras — apenas costurado com a alma e vestido com história.',
		),
	),
	array(
		'name'  => 'maria clara',
		'image' => 'about_maria_clara',
		'tone'  => '#e3dfd4',
		'text'  => array(
			'Ou<br>Amor pra minha esposa;<br>Mãe Clara pra minha filha;<br>Clara pros amigos;<br>Lila pra minha mãe &amp;<br>Maria pros meus irmãos.',
			'Cresci no interior do Mato Grosso e sonhava grande. A vida me levou a estudar Direito — talvez porque todo mundo dizia que era uma boa ideia. E lá fui eu advogar, com aquela sensação constante de que eu tinha estacionado no lugar errado.',
			'Depois de muito estresse, café frio e umas boas sessões de terapia, percebi que eu não cabia naquele terno. Foi então que a moda entrou na minha vida e me salvou. Descobri que criar roupas era, na verdade, costurar partes de mim que andavam esquecidas.',
			'Hoje, casada e mãe, bagunçou (e coloriu) tudo de vez. Foi por elas e por mim mesma, que resolvi transformar esse amor em trabalho.',
			'Então, pode entrar, tirar os sapatos e ficar à vontade. Aqui é pra vestir histórias e se sentir em casa.',
		),
	),
);
?>
<!-- wp:group {"align":"full","className":"gilda-about__intro","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull gilda-about__intro"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">ALFAIATARIA CRIATIVA</h1>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"gilda-about__founders has-brown-background-color has-cream-color has-background has-text-color","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull gilda-about__founders has-brown-background-color has-cream-color has-background has-text-color"><!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center">quem cria</h2>
<!-- /wp:heading -->
<!-- wp:columns {"className":"gilda-about__people"} -->
<div class="wp-block-columns gilda-about__people">
<?php foreach ( $gilda_people as $gilda_person ) : ?>
<!-- wp:column -->
<div class="wp-block-column"><article class="gilda-founder">
<div class="gilda-founder__photo">
<?php
$gilda_photo = gilda_image( $gilda_person['image'] );
if ( $gilda_photo ) {
	// Sem lightbox (clique para ampliar) nas fotos das criadoras.
	echo gilda_image_block(
		$gilda_photo,
		'large',
		array(
			'className' => trim( 'gilda-portrait ' . ( $gilda_person['focus'] ?? '' ) ),
			'lightbox'  => array( 'enabled' => false ),
		)
	); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
	echo gilda_cover_block( $gilda_person['image'], $gilda_person['tone'], array( 'minHeight' => 360 ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
<h3 class="gilda-founder__signature"><?php echo esc_html( $gilda_person['name'] ); ?></h3>
</div>
<div class="gilda-founder__story">
<?php foreach ( $gilda_person['text'] as $gilda_index => $gilda_paragraph ) : ?>
<p<?php echo 0 === $gilda_index ? ' class="gilda-founder__opening"' : ''; ?>><?php echo wp_kses( $gilda_paragraph, array( 'br' => array() ) ); ?></p>
<?php endforeach; ?>
</div>
</article></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<?php
// Transição com máscara (inc/transitions.php) no lugar do banner fixo; volta ao banner se a foto não existir.
if ( function_exists( 'gilda_mask_transition_shortcode' ) && gilda_transition_image_id( 'story_banner' ) ) :
	echo gilda_mask_transition_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput
else :
	?>
<!-- wp:group {"align":"full","className":"gilda-about__closing","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull gilda-about__closing">
<div class="gilda-about__closing-title"><p>DEPOIS DO PONTO E VÍRGULA,</p></div>
<div class="gilda-about__closing-image" role="img" aria-label="Livros e histórias que inspiram a criação">
<h2>VOCÊ CONTA A HISTÓRIA</h2>
</div>
</div>
<!-- /wp:group -->
<?php endif; ?>
