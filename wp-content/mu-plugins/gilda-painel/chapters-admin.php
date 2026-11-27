<?php
/**
 * Tela de edição dos capítulos (sem o editor de blocos) e o campo "Capítulo" na peça.
 *
 * Regras do formulário:
 * - nome, pelo menos um parágrafo, foto principal e exatamente 6 fotos da abertura para publicar
 *   (decisão §16.2). Faltou algo: salva como rascunho e diz o que falta.
 * - "Peças deste capítulo" grava o capítulo em cada peça (uma peça, um capítulo: marcar aqui tira a
 *   peça do capítulo anterior).
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Tela de edição ---------- */

add_action(
	'add_meta_boxes_' . GILDA_CHAPTER_CPT,
	static function () {
		remove_meta_box( 'slugdiv', GILDA_CHAPTER_CPT, 'normal' );
		add_meta_box( 'gilda-chapter-text', 'Textos', 'gilda_chapter_box_text', GILDA_CHAPTER_CPT, 'normal', 'high' );
		add_meta_box( 'gilda-chapter-opening', 'Fotos da abertura (colagem que dá zoom)', 'gilda_chapter_box_opening', GILDA_CHAPTER_CPT, 'normal', 'high' );
		add_meta_box( 'gilda-chapter-story', 'Fotos da narrativa', 'gilda_chapter_box_story', GILDA_CHAPTER_CPT, 'normal', 'default' );
		add_meta_box( 'gilda-chapter-gallery', 'Galeria (Livro GILDA; e card da página inicial)', 'gilda_chapter_box_gallery', GILDA_CHAPTER_CPT, 'normal', 'default' );
		add_meta_box( 'gilda-chapter-products', 'Peças deste capítulo', 'gilda_chapter_box_products', GILDA_CHAPTER_CPT, 'normal', 'default' );
		add_meta_box( 'gilda-chapter-status', 'No site', 'gilda_chapter_box_status', GILDA_CHAPTER_CPT, 'side', 'default' );
	}
);

add_filter(
	'enter_title_here',
	static fn( $text, $post ) => GILDA_CHAPTER_CPT === $post->post_type ? 'Nome do capítulo (ex.: Ritual Brasileiro)' : $text,
	10,
	2
);

/** Número (automático) logo abaixo do nome. */
add_action(
	'edit_form_after_title',
	static function ( $post ) {
		if ( GILDA_CHAPTER_CPT !== $post->post_type ) {
			return;
		}
		$number = (int) get_post_meta( $post->ID, '_gilda_numero', true );
		if ( ! $number ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$number = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX( CAST( meta_value AS UNSIGNED ) ) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_gilda_numero' AND p.post_type = %s", GILDA_CHAPTER_CPT ) );
			$label  = sprintf( 'Este será o <strong>CAPÍTULO %d</strong>. O número é dado sozinho, na ordem em que os capítulos são criados.', $number );
		} else {
			$label = sprintf( 'Este é o <strong>CAPÍTULO %1$d</strong> (no site: âncora <code>#capitulo-%1$d</code>, filtro da loja "Capítulo %1$d").', $number );
		}
		echo '<p class="gilda-chapter-number">' . wp_kses( $label, array( 'strong' => array(), 'code' => array() ) ) . '</p>';
		wp_nonce_field( 'gilda_chapter_save', 'gilda_chapter_nonce' );
	}
);

/** CSS/JS só na tela do capítulo e da peça. */
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		if ( GILDA_CHAPTER_CPT === $screen->post_type ) {
			wp_enqueue_style( 'gilda-painel-admin', GILDA_PAINEL_URL . 'assets/painel-admin.css', array(), GILDA_PAINEL_VERSION );
			if ( 'edit.php' !== $hook ) {
				wp_enqueue_media();
				wp_enqueue_script( 'gilda-painel-media', GILDA_PAINEL_URL . 'assets/painel-media.js', array( 'jquery', 'jquery-ui-sortable' ), GILDA_PAINEL_VERSION, true );
			}
		}
	}
);

/**
 * Lista de fotos com seletor de mídia e arrastar para ordenar (assets/painel-media.js).
 *
 * @param string $name  Campo (sem []).
 * @param int[]  $ids   Fotos.
 * @param int    $max   Máximo (0 = sem limite).
 * @param array  $texts title, button, empty (textos do seletor).
 */
function gilda_photo_field( string $name, array $ids, int $max, array $texts ): void {
	$multiple = 1 !== $max;
	$input    = $multiple ? $name . '[]' : $name;
	printf(
		'<div class="gilda-photos%1$s" data-gilda-photos data-name="%2$s" data-max="%3$d" data-title="%4$s" data-button="%5$s" data-alt="1">',
		$multiple ? '' : ' gilda-photos--single',
		esc_attr( $input ),
		(int) $max,
		esc_attr( $texts['title'] ),
		esc_attr( $texts['button'] )
	);
	// Campo vazio antes da lista: sem foto nenhuma, o PHP ainda recebe o campo (e apaga o que havia).
	printf( '<input type="hidden" name="%s" value="" />', esc_attr( $multiple ? $name . '_present' : $name ) );
	echo '<ul class="gilda-photos__list">';
	foreach ( $ids as $id ) {
		gilda_photo_tile( $id, $input );
	}
	echo '</ul><p class="gilda-photos__bar">';
	printf( '<button type="button" class="button gilda-photos__add">%s</button>', esc_html( $texts['add'] ?? ( $multiple ? 'Adicionar fotos' : ( $ids ? 'Trocar foto' : 'Escolher foto' ) ) ) );
	if ( $max > 1 ) {
		printf( ' <span class="gilda-photos__count" aria-live="polite"><strong>%1$d</strong> de %2$d</span>', count( $ids ), (int) $max );
	}
	echo '</p></div>';
}

/**
 * Uma foto da lista (também usada como modelo no JavaScript).
 *
 * @param int    $id    Foto.
 * @param string $input Nome do campo.
 */
function gilda_photo_tile( int $id, string $input ): void {
	$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	printf(
		'<li class="gilda-photo" data-id="%1$d"><img src="%2$s" alt="" /><input type="hidden" name="%3$s" value="%1$d" /><label class="gilda-photo__alt"><span>Descrição da foto</span><input type="text" name="gilda_alt[%1$d]" value="%4$s" placeholder="Ex.: modelo com a Camisa Grão marrom" /></label><button type="button" class="gilda-photo__remove" aria-label="Tirar esta foto">×</button></li>',
		(int) $id,
		esc_url( (string) wp_get_attachment_image_url( $id, 'medium' ) ),
		esc_attr( $input ),
		esc_attr( $alt )
	);
}

/** Textos: um parágrafo por campo (cada um é um passo da narrativa). */
function gilda_chapter_box_text( WP_Post $post ): void {
	$paragraphs = array_values( array_filter( (array) get_post_meta( $post->ID, '_gilda_textos', true ), 'strlen' ) );
	if ( ! $paragraphs ) {
		$paragraphs = array( '' );
	}
	echo '<p class="description">Cada parágrafo vira um passo da narrativa na página Capítulos (a foto troca a cada passo) e também aparece no Livro GILDA;. Arraste <span class="dashicons dashicons-move" aria-hidden="true"></span> para mudar a ordem.</p>';
	echo '<ol class="gilda-paragraphs" data-gilda-paragraphs>';
	foreach ( $paragraphs as $paragraph ) {
		gilda_paragraph_row( (string) $paragraph );
	}
	echo '</ol>';
	echo '<p><button type="button" class="button gilda-paragraphs__add">+ Adicionar parágrafo</button></p>';
	echo '<template id="gilda-paragraph-template">';
	gilda_paragraph_row( '' );
	echo '</template>';
}

/**
 * Um parágrafo. Itálico do parágrafo inteiro vira uma caixinha; itálico no meio do texto pode ser
 * escrito entre asteriscos (*assim*).
 *
 * @param string $paragraph Texto guardado (com <em>).
 */
function gilda_paragraph_row( string $paragraph ): void {
	$italic = (bool) preg_match( '~^<em>(.*)</em>$~s', trim( $paragraph ), $m ) && false === strpos( $m[1], '<em>' );
	$text   = $italic ? $m[1] : $paragraph;
	$text   = preg_replace( '~<em>(.*?)</em>~s', '*$1*', $text );
	?>
	<li class="gilda-paragraph">
		<span class="gilda-paragraph__handle dashicons dashicons-move" title="Arraste para mudar a ordem" aria-hidden="true"></span>
		<div class="gilda-paragraph__body">
			<textarea name="gilda_textos[]" rows="3" aria-label="Parágrafo"><?php echo esc_textarea( $text ); ?></textarea>
			<label class="gilda-paragraph__italic"><input type="checkbox" class="gilda-paragraph__italic-box" <?php checked( $italic ); ?> /> Parágrafo todo em itálico (frase de fechamento)</label>
			<input type="hidden" name="gilda_textos_italico[]" value="<?php echo $italic ? '1' : '0'; ?>" />
		</div>
		<button type="button" class="gilda-paragraph__remove button-link" aria-label="Apagar este parágrafo">Apagar</button>
	</li>
	<?php
}

/** Abertura: principal + 6. */
function gilda_chapter_box_opening( WP_Post $post ): void {
	$main    = absint( get_post_meta( $post->ID, '_gilda_foto_principal', true ) );
	$main    = ( $main && wp_attachment_is_image( $main ) ) ? array( $main ) : array();
	$opening = gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_abertura' );
	?>
	<p class="description">A abertura do capítulo é uma colagem de <strong>7 fotos</strong>: a principal fica no centro e cresce até ocupar a tela inteira, com "CAPÍTULO N;" e o nome por cima. As outras 6 ficam em volta. Para publicar, o capítulo precisa das 7.</p>
	<div class="gilda-opening">
		<div class="gilda-opening__main">
			<h4>Foto principal</h4>
			<p class="description">Vira tela cheia: use a foto em alta (pelo menos 2560 px no lado maior). No computador ela aparece em paisagem, com foco um pouco acima do centro.</p>
			<?php gilda_photo_field( 'gilda_foto_principal', $main, 1, array( 'title' => 'Foto principal do capítulo', 'button' => 'Usar esta foto' ) ); ?>
		</div>
		<div class="gilda-opening__others">
			<h4>As outras 6 fotos da colagem</h4>
			<p class="description">Exatamente 6, com pelo menos 1536 px. A ordem define a posição na colagem.</p>
			<?php gilda_photo_field( 'gilda_fotos_abertura', $opening, GILDA_CHAPTER_OPENING, array( 'title' => 'Fotos da colagem (escolha 6)', 'button' => 'Usar estas fotos' ) ); ?>
		</div>
	</div>
	<?php
}

/** Narrativa. */
function gilda_chapter_box_story( WP_Post $post ): void {
	echo '<p class="description">Fotos que trocam enquanto a pessoa lê os parágrafos (uma por parágrafo, na ordem). Fotos a mais que parágrafos não aparecem. Sem fotos aqui, a narrativa usa as da galeria.</p>';
	gilda_photo_field( 'gilda_fotos_narrativa', gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_narrativa' ), 0, array( 'title' => 'Fotos da narrativa', 'button' => 'Adicionar à narrativa' ) );
}

/** Galeria. */
function gilda_chapter_box_gallery( WP_Post $post ): void {
	echo '<p class="description">Galeria do capítulo no Livro GILDA; (recortada em retrato 3:4) e fotos que se revezam no card do capítulo na página inicial. A primeira é a capa do card. Pelo menos 768 px; retrato funciona melhor.</p>';
	gilda_photo_field( 'gilda_fotos_galeria', gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_galeria' ), 0, array( 'title' => 'Galeria do capítulo', 'button' => 'Adicionar à galeria' ) );
}

/** Peças: marcar aqui grava o capítulo na peça. */
function gilda_chapter_box_products( WP_Post $post ): void {
	$products = get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	if ( ! $products ) {
		echo '<p>Nenhuma peça cadastrada ainda.</p>';
		return;
	}
	echo '<p class="description">Marque as peças que fazem parte deste capítulo. Elas aparecem nos botões "peças disponíveis", no filtro "Capítulo" da loja e na página de cada peça. Cada peça pertence a um capítulo só: marcar aqui tira a peça do capítulo em que ela estava.</p>';
	echo '<input type="hidden" name="gilda_pecas_present" value="1" />';
	echo '<ul class="gilda-products">';
	foreach ( $products as $product ) {
		$current = absint( get_post_meta( $product->ID, GILDA_PRODUCT_CHAPTER, true ) );
		$note    = '';
		if ( $current && $current !== $post->ID && get_post( $current ) && 'trash' !== get_post_status( $current ) ) {
			$note = sprintf( 'hoje no Capítulo %d', (int) get_post_meta( $current, '_gilda_numero', true ) );
		}
		if ( 'publish' !== $product->post_status ) {
			$note = trim( $note . ' · peça fora do site', ' ·' );
		}
		printf(
			'<li><label><input type="checkbox" name="gilda_pecas[]" value="%1$d" %2$s /> %3$s</label>%4$s</li>',
			(int) $product->ID,
			checked( $current, $post->ID, false ),
			esc_html( get_the_title( $product ) ),
			$note ? ' <span class="gilda-products__note">(' . esc_html( $note ) . ')</span>' : ''
		);
	}
	echo '</ul>';
}

/** Situação no site + "Tirar do site". */
function gilda_chapter_box_status( WP_Post $post ): void {
	$live = 'publish' === $post->post_status;
	if ( $live ) {
		echo '<p class="gilda-status gilda-status--live"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> Publicado: aparece na página Capítulos, no Livro GILDA;, na página inicial e no filtro da loja.</p>';
		echo '<p><button type="submit" name="gilda_unpublish" value="1" class="button">Tirar do site</button></p>';
		echo '<p class="description">O capítulo vira rascunho e some do site; nada é apagado. Para voltar, clique em "Publicar".</p>';
	} else {
		echo '<p class="gilda-status"><span class="dashicons dashicons-hidden" aria-hidden="true"></span> Rascunho: ainda não aparece no site. Quando estiver pronto, clique em "Publicar".</p>';
	}
	$problems = gilda_chapter_missing( gilda_chapter_form_state( $post ) );
	echo '<div class="gilda-checklist" data-gilda-checklist><p><strong>Para publicar:</strong></p><ul>';
	foreach ( gilda_chapter_requirements() as $key => $label ) {
		$ok = ! isset( $problems[ $key ] );
		printf( '<li class="%1$s" data-req="%2$s">%3$s</li>', $ok ? 'is-ok' : 'is-missing', esc_attr( $key ), esc_html( $label ) );
	}
	echo '</ul></div>';
}

/* ---------- Validação e gravação ---------- */

/** O que um capítulo precisa ter para ir ao site. */
function gilda_chapter_requirements(): array {
	return array(
		'name'    => 'Nome do capítulo',
		'text'    => 'Pelo menos um parágrafo',
		'main'    => 'Foto principal',
		'opening' => sprintf( 'As %d fotos da colagem', GILDA_CHAPTER_OPENING ),
	);
}

/**
 * Estado atual guardado de um capítulo (para a lista de pendências).
 *
 * @param WP_Post $post Capítulo.
 * @return array{name:string,text:array,main:int,opening:int[]}
 */
function gilda_chapter_form_state( WP_Post $post ): array {
	$main = absint( get_post_meta( $post->ID, '_gilda_foto_principal', true ) );
	return array(
		'name'    => 'auto-draft' === $post->post_status ? '' : trim( $post->post_title ),
		'text'    => array_filter( (array) get_post_meta( $post->ID, '_gilda_textos', true ), 'strlen' ),
		'main'    => ( $main && wp_attachment_is_image( $main ) ) ? $main : 0,
		'opening' => gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_abertura' ),
	);
}

/**
 * Pendências (chave => texto).
 *
 * @param array $state Ver gilda_chapter_form_state().
 * @return array<string,string>
 */
function gilda_chapter_missing( array $state ): array {
	$labels  = gilda_chapter_requirements();
	$missing = array();
	if ( '' === $state['name'] ) {
		$missing['name'] = $labels['name'];
	}
	if ( ! $state['text'] ) {
		$missing['text'] = $labels['text'];
	}
	if ( ! $state['main'] ) {
		$missing['main'] = $labels['main'];
	}
	$count = count( $state['opening'] );
	if ( GILDA_CHAPTER_OPENING !== $count ) {
		$missing['opening'] = $count < GILDA_CHAPTER_OPENING
			? sprintf( 'Faltam %d foto(s) na colagem (são %d)', GILDA_CHAPTER_OPENING - $count, GILDA_CHAPTER_OPENING )
			: sprintf( 'A colagem tem %d fotos; tire %d (são exatamente %d)', $count, $count - GILDA_CHAPTER_OPENING, GILDA_CHAPTER_OPENING );
	}
	return $missing;
}

/** IDs de imagem vindos do formulário. */
function gilda_chapter_posted_ids( string $field ): array {
	$raw = isset( $_POST[ $field ] ) ? (array) wp_unslash( $_POST[ $field ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	return array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
}

/**
 * Parágrafos vindos do formulário: *texto* vira itálico; a caixinha deixa o parágrafo todo em itálico.
 *
 * @return string[]
 */
function gilda_chapter_posted_paragraphs(): array {
	// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	$texts   = isset( $_POST['gilda_textos'] ) ? (array) wp_unslash( $_POST['gilda_textos'] ) : array();
	$italics = isset( $_POST['gilda_textos_italico'] ) ? (array) wp_unslash( $_POST['gilda_textos_italico'] ) : array();
	// phpcs:enable
	$out = array();
	foreach ( array_values( $texts ) as $i => $text ) {
		$text = trim( sanitize_textarea_field( (string) $text ) );
		$text = (string) preg_replace( '/\s*\n\s*/', ' ', $text ); // um parágrafo é uma linha
		if ( '' === $text ) {
			continue;
		}
		$text = (string) preg_replace( '/\*([^*]+)\*/', '<em>$1</em>', $text );
		if ( ! empty( $italics[ $i ] ) && '1' === (string) $italics[ $i ] ) {
			$text = '<em>' . str_replace( array( '<em>', '</em>' ), '', $text ) . '</em>';
		}
		$out[] = wp_kses( $text, array( 'em' => array() ) );
	}
	return $out;
}

/** O formulário do capítulo veio junto (e é válido)? */
function gilda_chapter_form_submitted(): bool {
	return isset( $_POST['gilda_chapter_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['gilda_chapter_nonce'] ) ), 'gilda_chapter_save' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/**
 * Antes de gravar: incompleto ou "Tirar do site" → rascunho.
 */
add_filter(
	'wp_insert_post_data',
	static function ( $data, $postarr ) {
		if ( GILDA_CHAPTER_CPT !== $data['post_type'] || ! gilda_chapter_form_submitted() ) {
			return $data;
		}
		if ( ! empty( $_POST['gilda_unpublish'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['post_status'] = 'draft';
			$GLOBALS['gilda_chapter_notice'] = 'unpublished';
			return $data;
		}
		if ( ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$main    = gilda_chapter_posted_ids( 'gilda_foto_principal' );
		$missing = gilda_chapter_missing(
			array(
				'name'    => trim( (string) $data['post_title'] ),
				'text'    => gilda_chapter_posted_paragraphs(),
				'main'    => $main ? $main[0] : 0,
				'opening' => gilda_chapter_posted_ids( 'gilda_fotos_abertura' ),
			)
		);
		if ( $missing ) {
			$data['post_status']             = 'draft';
			$GLOBALS['gilda_chapter_notice'] = 'incomplete';
			$GLOBALS['gilda_chapter_missing'] = $missing;
		}
		return $data;
	},
	10,
	2
);

/** Grava os campos. */
add_action(
	'save_post_' . GILDA_CHAPTER_CPT,
	static function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! gilda_chapter_form_submitted() ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_gilda_textos', gilda_chapter_posted_paragraphs() );
		$main = gilda_chapter_posted_ids( 'gilda_foto_principal' );
		update_post_meta( $post_id, '_gilda_foto_principal', $main ? $main[0] : 0 );
		update_post_meta( $post_id, '_gilda_fotos_abertura', gilda_chapter_posted_ids( 'gilda_fotos_abertura' ) );
		update_post_meta( $post_id, '_gilda_fotos_narrativa', gilda_chapter_posted_ids( 'gilda_fotos_narrativa' ) );
		update_post_meta( $post_id, '_gilda_fotos_galeria', gilda_chapter_posted_ids( 'gilda_fotos_galeria' ) );

		// Texto alternativo de cada foto (o da biblioteca de mídia, vale em todo o site).
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		foreach ( (array) ( $_POST['gilda_alt'] ?? array() ) as $attachment_id => $alt ) {
			$attachment_id = absint( $attachment_id );
			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) && current_user_can( 'edit_post', $attachment_id ) ) {
				$alt = sanitize_text_field( wp_unslash( (string) $alt ) );
				if ( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) !== $alt ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
				}
			}
		}

		// Peças: marcadas apontam para cá; desmarcadas que apontavam para cá ficam sem capítulo.
		if ( isset( $_POST['gilda_pecas_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$checked = array_map( 'absint', (array) wp_unslash( $_POST['gilda_pecas'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			foreach ( gilda_chapter_product_ids_for( (int) $post_id, false ) as $product_id ) {
				if ( ! in_array( $product_id, $checked, true ) ) {
					update_post_meta( $product_id, GILDA_PRODUCT_CHAPTER, 0 );
				}
			}
			foreach ( $checked as $product_id ) {
				if ( 'product' === get_post_type( $product_id ) && current_user_can( 'edit_post', $product_id ) ) {
					update_post_meta( $product_id, GILDA_PRODUCT_CHAPTER, (int) $post_id );
				}
			}
		}
	},
	10
);

/** Leva o aviso (incompleto / tirado do site) para a tela depois de salvar. */
add_filter(
	'redirect_post_location',
	static function ( $location, $post_id ) {
		if ( GILDA_CHAPTER_CPT !== get_post_type( $post_id ) || empty( $GLOBALS['gilda_chapter_notice'] ) ) {
			return $location;
		}
		$location = add_query_arg( 'gilda_notice', $GLOBALS['gilda_chapter_notice'], $location );
		if ( ! empty( $GLOBALS['gilda_chapter_missing'] ) ) {
			$location = add_query_arg( 'gilda_missing', implode( ',', array_keys( $GLOBALS['gilda_chapter_missing'] ) ), $location );
			$location = remove_query_arg( 'message', $location );
		}
		return $location;
	},
	10,
	2
);

add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || GILDA_CHAPTER_CPT !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$notice = isset( $_GET['gilda_notice'] ) ? sanitize_key( wp_unslash( $_GET['gilda_notice'] ) ) : '';
		if ( 'unpublished' === $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>Capítulo tirado do site. Ele continua salvo como rascunho.</p></div>';
		} elseif ( 'incomplete' === $notice ) {
			global $post;
			$missing = $post ? gilda_chapter_missing( gilda_chapter_form_state( $post ) ) : array();
			echo '<div class="notice notice-warning gilda-notice-incomplete"><p><strong>O capítulo foi salvo como rascunho, mas ainda não foi publicado.</strong> Falta:</p><ul>';
			foreach ( $missing as $text ) {
				echo '<li>' . esc_html( $text ) . '</li>';
			}
			echo '</ul></div>';
		}
		// phpcs:enable
	}
);

/** Mensagens do WordPress em português simples. */
add_filter(
	'post_updated_messages',
	static function ( $messages ) {
		$messages[ GILDA_CHAPTER_CPT ] = array(
			1  => 'Capítulo salvo.',
			4  => 'Capítulo salvo.',
			6  => 'Capítulo publicado: já aparece no site.',
			7  => 'Capítulo salvo.',
			8  => 'Capítulo enviado.',
			10 => 'Rascunho salvo.',
		);
		return $messages;
	}
);

/* ---------- Lista de capítulos ---------- */

add_filter(
	'manage_' . GILDA_CHAPTER_CPT . '_posts_columns',
	static function () {
		return array(
			'cb'           => '<input type="checkbox" />',
			'gilda_numero' => 'Nº',
			'title'        => 'Nome',
			'gilda_cover'  => 'Foto',
			'gilda_pecas'  => 'Peças',
			'gilda_status' => 'No site',
		);
	}
);

add_action(
	'manage_' . GILDA_CHAPTER_CPT . '_posts_custom_column',
	static function ( $column, $post_id ) {
		switch ( $column ) {
			case 'gilda_numero':
				echo '<strong>' . (int) get_post_meta( $post_id, '_gilda_numero', true ) . '</strong>';
				break;
			case 'gilda_cover':
				$main = absint( get_post_meta( $post_id, '_gilda_foto_principal', true ) );
				if ( $main ) {
					echo wp_get_attachment_image( $main, array( 60, 60 ), false, array( 'style' => 'width:60px;height:60px;object-fit:cover' ) );
				}
				break;
			case 'gilda_pecas':
				$names = array_map( 'get_the_title', gilda_chapter_product_ids_for( (int) $post_id, false ) );
				echo $names ? esc_html( implode( ', ', $names ) ) : '—';
				break;
			case 'gilda_status':
				$problems = gilda_chapter_missing( gilda_chapter_form_state( get_post( $post_id ) ) );
				if ( 'publish' === get_post_status( $post_id ) ) {
					echo '<span class="gilda-badge gilda-badge--live">Publicado</span>';
				} else {
					echo '<span class="gilda-badge">Rascunho</span>';
				}
				if ( $problems ) {
					echo '<br><small>Falta: ' . esc_html( implode( '; ', $problems ) ) . '</small>';
				}
				break;
		}
	},
	10,
	2
);

// Ordem padrão: por número.
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() && $q->is_main_query() && GILDA_CHAPTER_CPT === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_gilda_numero' );
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'ASC' );
		}
	}
);

// Sem "edição rápida" (ela publicaria sem as 7 fotos).
add_filter(
	'post_row_actions',
	static function ( $actions, $post ) {
		if ( GILDA_CHAPTER_CPT === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	},
	10,
	2
);
add_filter( 'bulk_actions-edit-' . GILDA_CHAPTER_CPT, static fn( $actions ) => array_diff_key( $actions, array( 'edit' => true ) ) );

/* ---------- Campo "Capítulo" na peça ---------- */

add_action(
	'add_meta_boxes_product',
	static function () {
		add_meta_box( 'gilda-product-chapter', 'Capítulo', 'gilda_product_chapter_box', 'product', 'side', 'default' );
	}
);

/** Escolher o capítulo da peça. */
function gilda_product_chapter_box( WP_Post $post ): void {
	$current  = absint( get_post_meta( $post->ID, GILDA_PRODUCT_CHAPTER, true ) );
	$chapters = get_posts(
		array(
			'post_type'   => GILDA_CHAPTER_CPT,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => -1,
			'meta_key'    => '_gilda_numero', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'meta_value_num',
			'order'       => 'ASC',
		)
	);
	wp_nonce_field( 'gilda_product_chapter', 'gilda_product_chapter_nonce' );
	echo '<label for="gilda-product-chapter-select" class="screen-reader-text">Capítulo da peça</label>';
	echo '<select id="gilda-product-chapter-select" name="gilda_capitulo" style="width:100%">';
	echo '<option value="0">— Nenhum capítulo —</option>';
	foreach ( $chapters as $chapter ) {
		printf(
			'<option value="%1$d" %2$s>%3$s</option>',
			(int) $chapter->ID,
			selected( $current, $chapter->ID, false ),
			esc_html(
				sprintf( 'Capítulo %d — %s', (int) get_post_meta( $chapter->ID, '_gilda_numero', true ), get_the_title( $chapter ) )
				. ( 'publish' === $chapter->post_status ? '' : ' (rascunho)' )
			)
		);
	}
	echo '</select>';
	echo '<p class="description">A peça aparece nos botões do capítulo, no filtro "Capítulo" da loja e mostra "Esta peça é do Capítulo N" na página dela. Uma peça, um capítulo.</p>';
}

add_action(
	'save_post_product',
	static function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['gilda_product_chapter_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['gilda_product_chapter_nonce'] ) ), 'gilda_product_chapter' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$chapter = absint( $_POST['gilda_capitulo'] ?? 0 );
		if ( $chapter && GILDA_CHAPTER_CPT !== get_post_type( $chapter ) ) {
			$chapter = 0;
		}
		update_post_meta( $post_id, GILDA_PRODUCT_CHAPTER, $chapter );
	}
);

// Coluna "Capítulo" na lista de peças.
add_filter(
	'manage_edit-product_columns',
	static function ( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'product_cat' === $key ) {
				$new['gilda_capitulo'] = 'Capítulo';
			}
		}
		if ( ! isset( $new['gilda_capitulo'] ) ) {
			$new['gilda_capitulo'] = 'Capítulo';
		}
		return $new;
	},
	20
);
add_action(
	'manage_product_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'gilda_capitulo' !== $column ) {
			return;
		}
		$chapter = absint( get_post_meta( $post_id, GILDA_PRODUCT_CHAPTER, true ) );
		if ( $chapter && GILDA_CHAPTER_CPT === get_post_type( $chapter ) && 'trash' !== get_post_status( $chapter ) ) {
			printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $chapter ) ), esc_html( sprintf( 'Capítulo %d', (int) get_post_meta( $chapter, '_gilda_numero', true ) ) ) );
		} else {
			echo '—';
		}
	},
	10,
	2
);
