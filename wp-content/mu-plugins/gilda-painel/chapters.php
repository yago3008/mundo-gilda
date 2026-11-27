<?php
/**
 * Capítulos: tipo de conteúdo `gilda_capitulo` e a leitura que o tema usa.
 *
 * Um capítulo guarda (post meta):
 *   post_title               nome ("Ritual Brasileiro")
 *   _gilda_numero            número, dado automaticamente na criação (gera "CAPÍTULO N" e a âncora capitulo-N)
 *   _gilda_textos            parágrafos (string[]; <em> permitido)
 *   _gilda_foto_principal    ID da foto principal (a que dá o zoom e vira tela cheia)
 *   _gilda_fotos_abertura    6 IDs (colagem do zoom; principal + 6 = 7, decisão §16.2)
 *   _gilda_fotos_narrativa   IDs (fotos que trocam na narrativa)
 *   _gilda_fotos_galeria     IDs (Livro GILDA; e card da home)
 * A peça aponta para o capítulo (decisão §16.1): meta `_gilda_capitulo` (ID do capítulo) no produto.
 * Texto alternativo: o da própria foto na biblioteca de mídia.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

const GILDA_CHAPTER_CPT        = 'gilda_capitulo';
const GILDA_PRODUCT_CHAPTER    = '_gilda_capitulo';
const GILDA_CHAPTER_OPENING    = 6; // fotos da abertura além da principal
const GILDA_HOME_CHAPTER_CARDS = 5; // decisão §16.3

add_action(
	'init',
	static function () {
		register_post_type(
			GILDA_CHAPTER_CPT,
			array(
				'labels'              => array(
					'name'                  => 'Capítulos',
					'singular_name'         => 'Capítulo',
					'menu_name'             => 'Capítulos',
					'all_items'             => 'Todos os capítulos',
					'add_new'               => 'Novo capítulo',
					'add_new_item'          => 'Novo capítulo',
					'edit_item'             => 'Editar capítulo',
					'new_item'              => 'Novo capítulo',
					'view_item'             => 'Ver capítulo',
					'search_items'          => 'Buscar capítulos',
					'not_found'             => 'Nenhum capítulo ainda.',
					'not_found_in_trash'    => 'Nenhum capítulo na lixeira.',
					'item_published'        => 'Capítulo publicado: já aparece no site.',
					'item_updated'          => 'Capítulo salvo.',
					'item_reverted_to_draft' => 'Capítulo tirado do site (rascunho).',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false, // tela própria, sem o editor de blocos
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 56,
				'menu_icon'           => 'dashicons-book-alt',
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'gilda_capitulo', 'gilda_capitulos' ),
				'map_meta_cap'        => true,
			)
		);
	}
);

/**
 * Número do capítulo: dado uma vez, na criação (maior número existente + 1). Nunca digitado.
 * Conta também rascunhos e lixeira, para um capítulo restaurado não repetir número.
 *
 * @param int $post_id Capítulo.
 * @return int
 */
function gilda_chapter_ensure_number( int $post_id ): int {
	$number = (int) get_post_meta( $post_id, '_gilda_numero', true );
	if ( $number > 0 ) {
		return $number;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uma consulta só na criação do capítulo.
	$max    = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MAX( CAST( m.meta_value AS UNSIGNED ) ) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = '_gilda_numero' AND p.post_type = %s AND p.ID <> %d",
			GILDA_CHAPTER_CPT,
			$post_id
		)
	);
	$number = $max + 1;
	update_post_meta( $post_id, '_gilda_numero', $number );
	return $number;
}
add_action(
	'save_post_' . GILDA_CHAPTER_CPT,
	static function ( $post_id, $post ) {
		if ( 'auto-draft' !== $post->post_status && ! wp_is_post_revision( $post_id ) ) {
			gilda_chapter_ensure_number( (int) $post_id );
		}
	},
	5,
	2
);

/**
 * Lista de IDs de foto guardada num meta (só imagens que ainda existem).
 *
 * @param int    $post_id Capítulo.
 * @param string $key     Meta.
 * @return int[]
 */
function gilda_chapter_photo_ids( int $post_id, string $key ): array {
	$ids = array_map( 'absint', (array) get_post_meta( $post_id, $key, true ) );
	return array_values( array_filter( $ids, static fn( $id ) => $id && wp_attachment_is_image( $id ) ) );
}

/**
 * Peças (publicadas) que apontam para o capítulo.
 *
 * @param int  $chapter_id Capítulo.
 * @param bool $published  Só publicadas (site) ou todas (painel).
 * @return int[]
 */
function gilda_chapter_product_ids_for( int $chapter_id, bool $published = true ): array {
	if ( ! $chapter_id ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => $published ? 'publish' : array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'meta_key'         => GILDA_PRODUCT_CHAPTER, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => (string) $chapter_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => false,
			)
		)
	);
}

/**
 * Dados de um capítulo no formato que o tema usa.
 *
 * @param WP_Post|int $post Capítulo.
 * @return array{id:int,number:int,title:string,anchor:string,name:string,text:string[],main:int,opening:int[],zoom:int[],story:int[],gallery:int[],products:int[],links:array}
 */
function gilda_chapter_data( $post ): array {
	$post   = get_post( $post );
	$id     = (int) $post->ID;
	$number = (int) get_post_meta( $id, '_gilda_numero', true );
	$main   = absint( get_post_meta( $id, '_gilda_foto_principal', true ) );
	$main   = ( $main && wp_attachment_is_image( $main ) ) ? $main : 0;

	$opening = gilda_chapter_photo_ids( $id, '_gilda_fotos_abertura' );
	$text    = array_values(
		array_filter(
			array_map(
				static fn( $p ) => trim( wp_kses( (string) $p, array( 'em' => array() ) ) ),
				(array) get_post_meta( $id, '_gilda_textos', true )
			),
			'strlen'
		)
	);

	$products = gilda_chapter_product_ids_for( $id );
	$links    = array();
	foreach ( $products as $product_id ) {
		/* translators: %s: nome da peça */
		$links[] = array( sprintf( __( 'peças disponíveis: %s', 'gilda' ), get_the_title( $product_id ) ), get_permalink( $product_id ) );
	}

	return array(
		'id'       => $id,
		'number'   => $number,
		/* translators: %d: número do capítulo */
		'title'    => sprintf( __( 'CAPÍTULO %d', 'gilda' ), $number ),
		'anchor'   => 'capitulo-' . $number,
		'name'     => get_the_title( $post ),
		'text'     => $text,
		'main'     => $main,
		'opening'  => $opening,
		// colagem: a 1ª é a principal (posição fixa das 7 molduras)
		'zoom'     => array_merge( array( $main ), array_slice( $opening, 0, GILDA_CHAPTER_OPENING ) ),
		'story'    => gilda_chapter_photo_ids( $id, '_gilda_fotos_narrativa' ),
		'gallery'  => gilda_chapter_photo_ids( $id, '_gilda_fotos_galeria' ),
		'products' => $products,
		'links'    => $links,
	);
}

/**
 * Capítulos publicados, do 1º ao último (por número).
 *
 * @return array[] Ver gilda_chapter_data().
 */
function gilda_chapters(): array {
	if ( isset( $GLOBALS['gilda_chapters_cache'] ) ) {
		return $GLOBALS['gilda_chapters_cache'];
	}
	$posts = get_posts(
		array(
			'post_type'        => GILDA_CHAPTER_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'meta_key'         => '_gilda_numero', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'          => 'meta_value_num',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	$GLOBALS['gilda_chapters_cache'] = array_map( 'gilda_chapter_data', $posts );
	return $GLOBALS['gilda_chapters_cache'];
}

/**
 * Capítulo publicado pela âncora ("capitulo-3"), ou null.
 *
 * @param string $anchor Âncora / valor do filtro da loja.
 * @return array|null
 */
function gilda_chapter_by_anchor( string $anchor ): ?array {
	foreach ( gilda_chapters() as $chapter ) {
		if ( $chapter['anchor'] === $anchor ) {
			return $chapter;
		}
	}
	return null;
}

/**
 * Capítulo (publicado) de uma peça, ou null.
 *
 * @param int $product_id Peça.
 * @return array|null
 */
function gilda_product_chapter( int $product_id ): ?array {
	$chapter_id = absint( get_post_meta( $product_id, GILDA_PRODUCT_CHAPTER, true ) );
	if ( ! $chapter_id || 'publish' !== get_post_status( $chapter_id ) || GILDA_CHAPTER_CPT !== get_post_type( $chapter_id ) ) {
		return null;
	}
	foreach ( gilda_chapters() as $chapter ) {
		if ( $chapter['id'] === $chapter_id ) {
			return $chapter;
		}
	}
	return null;
}

// O catálogo guardado (loja e filtro "Capítulo") muda quando um capítulo muda: criar, editar, publicar,
// tirar do site, mandar para a lixeira ou apagar.
function gilda_chapter_changed( $post_id = 0 ): void {
	if ( $post_id && GILDA_CHAPTER_CPT !== get_post_type( $post_id ) ) {
		return;
	}
	$GLOBALS['gilda_chapters_cache'] = null;
	if ( function_exists( 'gilda_catalog_cache_flush' ) ) {
		gilda_catalog_cache_flush();
	}
}
add_action( 'save_post_' . GILDA_CHAPTER_CPT, 'gilda_chapter_changed', 99 );
add_action( 'before_delete_post', 'gilda_chapter_changed' );
add_action( 'trashed_post', 'gilda_chapter_changed' );
add_action( 'untrashed_post', 'gilda_chapter_changed' );
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ) {
		if ( GILDA_CHAPTER_CPT === $post->post_type && $new_status !== $old_status ) {
			gilda_chapter_changed();
		}
	},
	10,
	3
);

// Capítulo apagado de vez: as peças dele ficam sem capítulo.
add_action(
	'before_delete_post',
	static function ( $post_id ) {
		if ( GILDA_CHAPTER_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		foreach ( gilda_chapter_product_ids_for( (int) $post_id, false ) as $product_id ) {
			update_post_meta( $product_id, GILDA_PRODUCT_CHAPTER, 0 );
		}
	}
);
