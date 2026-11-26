<?php
/**
 * Dados dos capítulos do Livro GILDA; (textos e fotos do site atual).
 * Usado pelo pattern gilda/book e pela história contínua [gilda_book_story] (inc/transitions.php).
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array[] Cada capítulo: title, key (gilda_image), name, zoom (fotos da abertura), anchor, text[], links[].
 */
function gilda_book_chapters() {
	return array(
		array(
			'title' => __( 'CAPÍTULO 1', 'gilda' ),
			'key'   => 'chapter_1',
			'name'  => __( 'Borboleta 88', 'gilda' ),
			// 1ª foto = principal (vira tela cheia). Todas 4000×6000 no original.
			'zoom'  => 'chapter_1:1,chapter_1:0,chapter_1:3,chapter_1:2,chapter_1:7,chapter_1:4,chapter_1:6',
			'story' => 'chapter_1:0,chapter_1:2,chapter_1:3,chapter_1:7,chapter_1:4,chapter_1:6',
			'anchor' => 'capitulo-1',
			'text'  => array(
				'A GILDA; apresenta seu primeiro capítulo, inspirado na borboleta 88.',
				'Símbolo de transformação e movimento, a borboleta conduz este capítulo a partir de suas formas, cores e delicadezas. Seu desenho singular se desdobra em peças que traduzem a leveza sem abrir mão da estrutura característica da alfaiataria.',
				'O capítulo apresenta duas camisas, em branco e azul, e um lenço com estampa exclusiva em aquarela, criada a partir das formas circulares presentes nas asas da borboleta 88.',
				'Este capítulo marca o início de um percurso: o instante em que algo deixa de ser apenas ideia, ganha forma e se prepara para seguir adiante.',
				'<em>Um começo. Um movimento. Um novo voo.</em>',
			),
			'links' => array(
				array( __( 'peças disponíveis: Camisa Casulo', 'gilda' ), gilda_product_url( 'camisa-casulo' ) ),
			),
		),
		array(
			'title' => __( 'CAPÍTULO 2', 'gilda' ),
			'key'   => 'chapter_2',
			'name'  => __( 'Os filetes da borboleta', 'gilda' ),
			// chapter_2:0 (estampa) tem só 736 px: fica fora das fotos grandes
			// principal: bermuda Envolto laranja (original 3712×5568). A do banco (lookbook:3) veio muito comprimida do Wix.
			'zoom'  => 'chapter_2:2,chapter_2:1,chapter_3_lookbook:7,chapter_3_lookbook:3,chapter_3_lookbook:4,category_lencos,chapter_2:0',
			'story' => 'chapter_2:1,chapter_3_lookbook:4,chapter_2:2,category_lencos,chapter_3_lookbook:7',
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
			'name'  => __( 'Ritual Brasileiro', 'gilda' ),
			// principal: Camisa Grão (foto de produto do Wix em alta, punho duplo em destaque).
			// As fotos de chapter_3 vieram do Wix com só 1023×1537 ou muito comprimidas.
			'zoom'  => 'chapter_3_hd:0,chapter_3:1,chapter_3_hd:1,chapter_3:0,chapter_3:7,chapter_3:2,chapter_3:3',
			'story' => 'chapter_3_hd:1,chapter_3:0,chapter_3_hd:0,chapter_3:1,chapter_3:7,chapter_3:3',
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
}
