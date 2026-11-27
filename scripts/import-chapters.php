<?php
/**
 * Migra os 3 capítulos que estavam escritos no tema (inc/book-chapters.php, até nov/2026) para o tipo de
 * conteúdo Capítulo (mu-plugin gilda-painel) e liga as peças a eles (decisão §16.4 de
 * docs/development/painel-conteudo.md).
 *
 * SEMENTE, como os outros scripts de carga:
 * - cada capítulo é criado uma vez só (opção `gilda_chapters_seeded`). Se as donas apagarem um,
 *   ele não volta; o que elas editarem nunca é sobrescrito.
 * - fotos: vêm das fotos do site (opção `gilda_site_images`, scripts/import-site-images.php) e são
 *   guardadas por ID. Um capítulo semeado sem nenhuma foto (setup rodado sem SITE_IMAGES) ganha as
 *   fotos quando elas existirem.
 * - peças: só recebem capítulo se ainda não têm o campo (nem "nenhum capítulo" escolhido no painel).
 *
 * Uso: roda sozinho no ./scripts/setup.sh  (ou: docker compose run --rm wpcli wp eval-file /scripts/import-chapters.php)
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GILDA_CHAPTER_CPT' ) ) {
	WP_CLI::error( 'O mu-plugin gilda-painel não está carregado.' );
}

/*
 * As fotos estavam descritas por posição nas listas de fotos do site ("chapter_3:7" = 8ª foto do grupo
 * chapter_3). Aqui elas viram IDs uma única vez.
 *   main:    principal da abertura (zoom)       opening: as outras 6 da colagem
 *   story:   narrativa                          gallery: Livro GILDA; e card da home
 */
$chapters = array(
	1 => array(
		'name'     => 'Borboleta 88',
		'main'     => 'chapter_1:1',
		'opening'  => 'chapter_1:0,chapter_1:3,chapter_1:2,chapter_1:7,chapter_1:4,chapter_1:6',
		'story'    => 'chapter_1:0,chapter_1:2,chapter_1:3,chapter_1:7,chapter_1:4,chapter_1:6',
		'gallery'  => 'chapter_1',
		'text'     => array(
			'A GILDA; apresenta seu primeiro capítulo, inspirado na borboleta 88.',
			'Símbolo de transformação e movimento, a borboleta conduz este capítulo a partir de suas formas, cores e delicadezas. Seu desenho singular se desdobra em peças que traduzem a leveza sem abrir mão da estrutura característica da alfaiataria.',
			'O capítulo apresenta duas camisas, em branco e azul, e um lenço com estampa exclusiva em aquarela, criada a partir das formas circulares presentes nas asas da borboleta 88.',
			'Este capítulo marca o início de um percurso: o instante em que algo deixa de ser apenas ideia, ganha forma e se prepara para seguir adiante.',
			'<em>Um começo. Um movimento. Um novo voo.</em>',
		),
		'products' => array( 'Camisa Casulo', 'Calça Asas', 'Lenço oitenta-e-oito' ),
	),
	2 => array(
		'name'     => 'Os filetes da borboleta',
		// as 3 fotos do antigo lookbook do capítulo 3 são do capítulo 2 (decisão §16.9)
		'main'     => 'chapter_2:2',
		'opening'  => 'chapter_2:1,chapter_3_lookbook:7,chapter_3_lookbook:3,chapter_3_lookbook:4,category_lencos,chapter_2:0',
		'story'    => 'chapter_2:1,chapter_3_lookbook:4,chapter_2:2,category_lencos,chapter_3_lookbook:7',
		'gallery'  => 'chapter_2',
		'text'     => array(
			'No segundo capítulo, a GILDA; continua a observar a borboleta, agora mais de perto.',
			'A inspiração parte dos filetes que percorrem suas asas: linhas delicadas que sustentam, delimitam e conduzem o movimento. Essa construção se traduz na bermuda ENVOLTO, com pregas frontais e passantes integrados, e no lenço MANACÁ, cuja estampa em aquarela reinterpreta os traços das asas.',
			'Entre linhas precisas e formas fluidas, o capítulo aproxima a estrutura da alfaiataria da leveza encontrada na natureza.',
			'<em>Mais do que contornar uma forma, os filetes revelam os caminhos que existem dentro dela.</em>',
		),
		'products' => array( 'Bermuda Envolto', 'Lenço Manacá' ),
	),
	3 => array(
		'name'     => 'Ritual Brasileiro',
		'main'     => 'chapter_3_hd:0',
		'opening'  => 'chapter_3:1,chapter_3_hd:1,chapter_3:0,chapter_3:7,chapter_3:2,chapter_3:3',
		'story'    => 'chapter_3_hd:1,chapter_3:0,chapter_3_hd:0,chapter_3:1,chapter_3:7,chapter_3:3',
		'gallery'  => 'chapter_3',
		'text'     => array(
			'Agora, a GILDA; apresenta um novo capítulo: RITUAL BRASILEIRO.',
			'Inspirado no café, mas, sobretudo, no tempo que se cria ao redor dele, o capítulo parte de um gesto cotidiano profundamente presente na cultura brasileira: fazer uma pausa. Na companhia de alguém ou de si mesma, esse é o momento de observar, conversar, descansar, respirar ou simplesmente passar um café.',
			'RITUAL BRASILEIRO apresenta as camisas GRÃO e CAFÉ, confeccionadas em algodão egípcio e marcadas por um desenho especial nos punhos, além de uma calça de alfaiataria com pregas frontais profundas.',
			'Mais do que uma referência ao café, este capítulo é um convite para reconhecer o valor dos pequenos rituais que atravessam os nossos dias.',
			'<em>Uma pausa. Uma conversa. Um café.</em>',
		),
		'products' => array( 'Camisa Grão', 'Calça Torra' ),
	),
);

$site_images = (array) get_option( 'gilda_site_images', array() );

/** "chave" (grupo inteiro) ou "chave:índice" → IDs de imagens que existem. */
$resolve = static function ( string $specs ) use ( $site_images ): array {
	$ids = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $specs ) ) ) as $spec ) {
		list( $key, $index ) = array_pad( explode( ':', $spec, 2 ), 2, null );
		$value = $site_images[ $key ] ?? 0;
		if ( null === $index ) {
			$ids = array_merge( $ids, array_map( 'absint', (array) $value ) );
		} elseif ( is_array( $value ) ) {
			$ids[] = absint( $value[ (int) $index ] ?? 0 );
		} else {
			$ids[] = absint( $value );
		}
	}
	return array_values( array_filter( $ids, static fn( $id ) => $id && wp_attachment_is_image( $id ) ) );
};

$set_photos = static function ( int $post_id, array $chapter ) use ( $resolve ): int {
	$main = $resolve( $chapter['main'] );
	update_post_meta( $post_id, '_gilda_foto_principal', $main ? $main[0] : 0 );
	update_post_meta( $post_id, '_gilda_fotos_abertura', $resolve( $chapter['opening'] ) );
	update_post_meta( $post_id, '_gilda_fotos_narrativa', $resolve( $chapter['story'] ) );
	update_post_meta( $post_id, '_gilda_fotos_galeria', $resolve( $chapter['gallery'] ) );
	return count( $main ) + count( $resolve( $chapter['opening'] ) );
};

$has_photos = static function ( int $post_id ): bool {
	foreach ( array( '_gilda_fotos_abertura', '_gilda_fotos_narrativa', '_gilda_fotos_galeria' ) as $key ) {
		if ( array_filter( (array) get_post_meta( $post_id, $key, true ) ) ) {
			return true;
		}
	}
	return (bool) absint( get_post_meta( $post_id, '_gilda_foto_principal', true ) );
};

$seeded = (array) get_option( 'gilda_chapters_seeded', array() );

foreach ( $chapters as $number => $chapter ) {
	$key = 'capitulo-' . $number;

	if ( isset( $seeded[ $key ] ) ) {
		$post_id = (int) $seeded[ $key ];
		$post    = get_post( $post_id );
		if ( ! $post || GILDA_CHAPTER_CPT !== $post->post_type ) {
			WP_CLI::log( "  • Capítulo $number (apagado no painel, não volta)" );
			continue;
		}
		if ( ! $has_photos( $post_id ) && $site_images ) {
			$count = $set_photos( $post_id, $chapter );
			WP_CLI::log( "  • Capítulo $number (fotos que faltavam: $count de 7 na abertura)" );
		} else {
			WP_CLI::log( "  • Capítulo $number (já existe, mantido)" );
		}
	} else {
		// Alguém já criou um capítulo com esse número no painel: não duplica.
		$existing = get_posts(
			array(
				'post_type'   => GILDA_CHAPTER_CPT,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_gilda_numero', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $number, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( $existing ) {
			$post_id = (int) $existing[0];
			WP_CLI::log( "  • Capítulo $number (já criado no painel, mantido)" );
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => GILDA_CHAPTER_CPT,
					'post_status' => 'publish',
					'post_title'  => $chapter['name'],
					'meta_input'  => array(
						'_gilda_numero' => $number,
						'_gilda_textos' => $chapter['text'],
					),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( "Capítulo $number: " . $post_id->get_error_message() );
				continue;
			}
			$count = $set_photos( (int) $post_id, $chapter );
			WP_CLI::log( "  • Capítulo $number — {$chapter['name']} (criado; $count de 7 fotos na abertura)" );
		}
		$seeded[ $key ] = (int) $post_id;
		update_option( 'gilda_chapters_seeded', $seeded, false );
	}

	// Peças: só as que nunca tiveram o campo.
	// Pelo nome: os slugs do Wix perdem acentos de jeitos diferentes ("calça" virou "calca").
	foreach ( $chapter['products'] as $name ) {
		$found   = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'title' => $name, 'numberposts' => 1 ) );
		$product = $found ? $found[0] : null;
		if ( ! $product ) {
			WP_CLI::log( "      peça \"$name\" não existe (ainda)" );
			continue;
		}
		if ( metadata_exists( 'post', $product->ID, GILDA_PRODUCT_CHAPTER ) ) {
			continue;
		}
		update_post_meta( $product->ID, GILDA_PRODUCT_CHAPTER, (int) $post_id );
		WP_CLI::log( "      {$product->post_title} → Capítulo $number" );
	}
}

if ( function_exists( 'gilda_catalog_cache_flush' ) ) {
	gilda_catalog_cache_flush();
}
WP_CLI::success( 'Capítulos conferidos.' );
