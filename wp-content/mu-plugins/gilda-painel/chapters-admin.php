<?php
/**
 * Tela de edição dos capítulos (sem o editor de blocos) e o campo "Capítulo" na peça.
 * Visual: painel-proto/Capitulo.dc.html (cartões, "5 de 7 fotos · faltam 2", lista "Publicar" com o
 * botão desligado até estar tudo certo, prévia do card da home).
 *
 * Regras do formulário:
 * - nome, pelo menos um parágrafo, foto principal e exatamente 6 fotos da abertura para publicar
 *   (decisão §16.2). O botão só liga quando está tudo certo; o servidor confere de novo e, se faltar
 *   algo, salva como rascunho e diz o que falta.
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
		remove_meta_box( 'submitdiv', GILDA_CHAPTER_CPT, 'side' );
		add_meta_box( 'gilda-chapter-text', 'Textos', 'gilda_chapter_box_text', GILDA_CHAPTER_CPT, 'normal', 'high' );
		add_meta_box( 'gilda-chapter-opening', 'Fotos da abertura', 'gilda_chapter_box_opening', GILDA_CHAPTER_CPT, 'normal', 'high' );
		add_meta_box( 'gilda-chapter-photos', 'Fotos da história e da galeria', 'gilda_chapter_box_photos', GILDA_CHAPTER_CPT, 'normal', 'default' );
		add_meta_box( 'gilda-chapter-products', 'Peças deste capítulo', 'gilda_chapter_box_products', GILDA_CHAPTER_CPT, 'normal', 'default' );
		add_meta_box( 'gilda-chapter-status', 'Publicar', 'gilda_chapter_box_status', GILDA_CHAPTER_CPT, 'side', 'high' );
		add_meta_box( 'gilda-chapter-preview', 'Assim fica o card na home', 'gilda_chapter_box_preview', GILDA_CHAPTER_CPT, 'side', 'default' );
	}
);

// Ordem fixa das caixas (ninguém "perde" uma caixa arrastando ou fechando).
add_filter( 'get_user_option_meta-box-order_' . GILDA_CHAPTER_CPT, '__return_empty_array' );
add_filter( 'get_user_option_closedpostboxes_' . GILDA_CHAPTER_CPT, '__return_empty_array' );
add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return ( $screen && GILDA_CHAPTER_CPT === $screen->post_type && 'post' === $screen->base ) ? $classes . ' gilda-chapter-screen' : $classes;
	}
);

add_filter(
	'enter_title_here',
	static fn( $text, $post ) => GILDA_CHAPTER_CPT === $post->post_type ? 'Ex.: Ritual Brasileiro' : $text,
	10,
	2
);

/** Número que o capítulo tem (ou terá, se ainda não foi salvo). */
function gilda_chapter_number_for( WP_Post $post ): int {
	$number = (int) get_post_meta( $post->ID, '_gilda_numero', true );
	if ( $number ) {
		return $number;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX( CAST( meta_value AS UNSIGNED ) ) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_gilda_numero' AND p.post_type = %s", GILDA_CHAPTER_CPT ) );
}

/** Cabeçalho: "← Capítulos", "novo capítulo;" e o número automático. */
add_action(
	'edit_form_top',
	static function ( $post ) {
		if ( GILDA_CHAPTER_CPT !== $post->post_type ) {
			return;
		}
		$number = gilda_chapter_number_for( $post );
		$is_new = ! get_post_meta( $post->ID, '_gilda_numero', true );
		?>
		<header class="gilda-page-head gilda-panel">
			<a class="gilda-back" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . GILDA_CHAPTER_CPT ) ); ?>">← Capítulos</a>
			<h1 class="gilda-display"><?php echo $is_new ? 'novo capítulo;' : esc_html( mb_strtolower( get_the_title( $post ) ) ) . ';'; ?></h1>
			<p class="gilda-lead gilda-chapter-number">
				<?php if ( $is_new ) : ?>
					Ele entra no site como <strong>CAPÍTULO <?php echo (int) $number; ?></strong>. O número é automático.
				<?php else : ?>
					No site, ele é o <strong>CAPÍTULO <?php echo (int) $number; ?></strong> (âncora <code>#capitulo-<?php echo (int) $number; ?></code>, filtro da loja "Capítulo <?php echo (int) $number; ?>").
				<?php endif; ?>
			</p>
		</header>
		<?php
		wp_nonce_field( 'gilda_chapter_save', 'gilda_chapter_nonce' );
	}
);

/** "Nome" como cartão, com rótulo. */
add_action(
	'edit_form_before_permalink',
	static function ( $post ) {
		if ( GILDA_CHAPTER_CPT === $post->post_type ) {
			echo '<span class="gilda-title-label" aria-hidden="true">Nome do capítulo</span>';
		}
	}
);

/** CSS/JS só nas telas do capítulo. */
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || GILDA_CHAPTER_CPT !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		gilda_painel_enqueue_ui();
		wp_enqueue_style( 'gilda-chapter-edit', GILDA_PAINEL_URL . 'assets/chapter-edit.css', array( 'gilda-painel-ui' ), gilda_painel_ver( 'assets/chapter-edit.css' ) );
		if ( 'edit.php' !== $hook ) {
			wp_enqueue_media();
			wp_enqueue_script( 'gilda-painel-media', GILDA_PAINEL_URL . 'assets/painel-media.js', array( 'jquery', 'jquery-ui-sortable' ), gilda_painel_ver( 'assets/painel-media.js' ), true );
		}
	}
);

/**
 * Lista de fotos com seletor de mídia e arrastar para ordenar (assets/painel-media.js).
 *
 * @param string $name Campo (sem []).
 * @param int[]  $ids  Fotos.
 * @param int    $max  Máximo (0 = sem limite; 1 = uma foto só).
 * @param array  $opts title, button (textos do seletor); style (main|grid|strip|cover|logo); slots (uma
 *                     vaga "+ Foto N" para cada foto que falta); add (rótulo da vaga); captions (legendas
 *                     por posição); alt (campo de descrição); saved (false: a foto mostrada é só a de
 *                     reserva e o campo vai vazio).
 */
function gilda_photo_field( string $name, array $ids, int $max, array $opts ): void {
	$multiple = 1 !== $max;
	$input    = $multiple ? $name . '[]' : $name;
	$alt      = $opts['alt'] ?? true;
	printf(
		'<div class="gilda-photos gilda-photos--%1$s%2$s" data-gilda-photos data-name="%3$s" data-max="%4$d" data-title="%5$s" data-button="%6$s" data-alt="%7$s" data-slots="%8$s" data-add="%9$s" data-captions="%10$s">',
		esc_attr( $opts['style'] ?? 'strip' ),
		$multiple ? '' : ' gilda-photos--single',
		esc_attr( $input ),
		(int) $max,
		esc_attr( $opts['title'] ),
		esc_attr( $opts['button'] ),
		$alt ? '1' : '0',
		empty( $opts['slots'] ) ? '0' : '1',
		esc_attr( $opts['add'] ?? ( $multiple ? '+ Adicionar' : '+ Escolher foto' ) ),
		esc_attr( (string) wp_json_encode( $opts['captions'] ?? array() ) )
	);
	// Campo vazio antes da lista: sem foto nenhuma, o PHP ainda recebe o campo (e apaga o que havia).
	printf( '<input type="hidden" name="%s" value="" />', esc_attr( $multiple ? $name . '_present' : $name ) );
	echo '<ul class="gilda-photos__list">';
	foreach ( array_values( $ids ) as $i => $id ) {
		gilda_photo_tile( (int) $id, $input, $alt, (string) ( $opts['captions'][ $i ] ?? '' ), $opts['saved'] ?? true );
	}
	echo '</ul>';
	if ( ! $multiple ) {
		echo '<button type="button" class="gilda-link gilda-photos__swap gilda-photos__add">Trocar foto</button>';
	}
	echo '</div>';
}

/**
 * Uma foto da lista (o JavaScript monta a mesma marcação para as novas).
 *
 * @param int    $id      Foto.
 * @param string $input   Nome do campo.
 * @param bool   $alt     Mostrar o campo de descrição.
 * @param string $caption Legenda (ex.: "Capa").
 * @param bool   $saved   Valor no campo (false = vazio, foto de reserva).
 */
function gilda_photo_tile( int $id, string $input, bool $alt = true, string $caption = '', bool $saved = true ): void {
	$alt_html = '';
	if ( $alt ) {
		$alt_html = sprintf(
			'<label class="gilda-photo__alt"><span>Descrição</span><input type="text" name="gilda_alt[%1$d]" value="%2$s" placeholder="o que aparece na foto" /></label>',
			(int) $id,
			esc_attr( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) )
		);
	}
	printf(
		'<li class="gilda-photo" data-id="%1$d"><img src="%2$s" alt="" /><input type="hidden" name="%3$s" value="%4$s" /><span class="gilda-photo__cap">%5$s</span>%6$s<button type="button" class="gilda-photo__remove" aria-label="Tirar esta foto">×</button></li>',
		(int) $id,
		esc_url( (string) wp_get_attachment_image_url( $id, 'medium' ) ),
		esc_attr( $input ),
		$saved ? (int) $id : '',
		esc_html( $caption ),
		$alt_html // phpcs:ignore WordPress.Security.EscapeOutput -- montado acima com esc_*.
	);
}

/** Textos: um parágrafo por campo (cada um é um passo da narrativa). */
function gilda_chapter_box_text( WP_Post $post ): void {
	$paragraphs = array_values( array_filter( (array) get_post_meta( $post->ID, '_gilda_textos', true ), 'strlen' ) );
	if ( ! $paragraphs ) {
		$paragraphs = array( '' );
	}
	echo '<p class="gilda-help">Cada parágrafo vira um passo da história na página Capítulos. Arraste para mudar a ordem. Para itálico só em uma parte, escreva entre asteriscos: <em>*assim*</em>.</p>';
	echo '<ol class="gilda-paragraphs" data-gilda-paragraphs>';
	foreach ( $paragraphs as $i => $paragraph ) {
		gilda_paragraph_row( (string) $paragraph, $i + 1 );
	}
	echo '</ol>';
	echo '<button type="button" class="gilda-btn gilda-btn--dashed gilda-paragraphs__add">+ Adicionar parágrafo</button>';
	echo '<template id="gilda-paragraph-template">';
	gilda_paragraph_row( '', 0 );
	echo '</template>';
}

/**
 * Um parágrafo. Itálico do parágrafo inteiro vira uma caixinha; itálico no meio do texto pode ser
 * escrito entre asteriscos (*assim*).
 *
 * @param string $paragraph Texto guardado (com <em>).
 * @param int    $n         Posição (o JavaScript renumera).
 */
function gilda_paragraph_row( string $paragraph, int $n ): void {
	$italic = (bool) preg_match( '~^<em>(.*)</em>$~s', trim( $paragraph ), $m ) && false === strpos( $m[1], '<em>' );
	$text   = $italic ? $m[1] : $paragraph;
	$text   = preg_replace( '~<em>(.*?)</em>~s', '*$1*', $text );
	?>
	<li class="gilda-paragraph<?php echo $italic ? ' is-italic' : ''; ?>">
		<button type="button" class="gilda-paragraph__handle" aria-label="Arrastar parágrafo"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg></button>
		<label class="gilda-paragraph__body"><span class="gilda-label gilda-paragraph__n">Parágrafo <?php echo (int) $n; ?></span>
			<textarea name="gilda_textos[]" rows="3"><?php echo esc_textarea( $text ); ?></textarea>
		</label>
		<div class="gilda-paragraph__tools">
			<label class="gilda-paragraph__italic"><input type="checkbox" class="gilda-paragraph__italic-box" <?php checked( $italic ); ?> /> Itálico</label>
			<input type="hidden" name="gilda_textos_italico[]" value="<?php echo $italic ? '1' : '0'; ?>" />
			<button type="button" class="gilda-btn gilda-btn--danger gilda-paragraph__remove">Remover</button>
		</div>
	</li>
	<?php
}

/** Abertura: principal + 6. */
function gilda_chapter_box_opening( WP_Post $post ): void {
	$main    = absint( get_post_meta( $post->ID, '_gilda_foto_principal', true ) );
	$main    = ( $main && wp_attachment_is_image( $main ) ) ? array( $main ) : array();
	$opening = gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_abertura' );
	?>
	<div class="gilda-opening__head">
		<p class="gilda-help">1 principal + 6 da colagem. No site, a colagem dá zoom até a principal ocupar a tela inteira: use uma foto grande, com 2.000 px ou mais. A ordem das 6 define a posição de cada uma.</p>
		<span class="gilda-pill" role="status" data-gilda-opening-count></span>
	</div>
	<div class="gilda-opening">
		<div class="gilda-opening__main">
			<?php gilda_photo_field( 'gilda_foto_principal', $main, 1, array( 'title' => 'Foto principal do capítulo', 'button' => 'Usar esta foto', 'style' => 'main', 'captions' => array( 'Principal' ), 'add' => '+ Foto principal' ) ); ?>
		</div>
		<div class="gilda-opening__others">
			<?php gilda_photo_field( 'gilda_fotos_abertura', $opening, GILDA_CHAPTER_OPENING, array( 'title' => 'Fotos da colagem (escolha 6)', 'button' => 'Usar estas fotos', 'style' => 'grid', 'slots' => true, 'add' => '+ Foto %d' ) ); ?>
		</div>
	</div>
	<?php
}

/** História (narrativa) e galeria. */
function gilda_chapter_box_photos( WP_Post $post ): void {
	echo '<p class="gilda-help">As da história trocam junto com os parágrafos (uma por parágrafo fica melhor; sem fotos aqui, a história usa as da galeria). A galeria aparece no Livro GILDA; e no card da home: a primeira é a capa do card.</p>';
	echo '<div id="gilda-chapter-story" class="gilda-photo-group"><span class="gilda-label">História</span>';
	gilda_photo_field( 'gilda_fotos_narrativa', gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_narrativa' ), 0, array( 'title' => 'Fotos da história', 'button' => 'Adicionar à história' ) );
	echo '</div><div id="gilda-chapter-gallery" class="gilda-photo-group"><span class="gilda-label">Galeria</span>';
	gilda_photo_field( 'gilda_fotos_galeria', gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_galeria' ), 0, array( 'title' => 'Galeria do capítulo', 'button' => 'Adicionar à galeria' ) );
	echo '</div>';
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
	echo '<p class="gilda-help">Cada peça fica em um capítulo só. Marcar aqui tira a peça do capítulo anterior. Elas aparecem nos botões "peças disponíveis", no filtro da loja e na página de cada peça.</p>';
	if ( ! $products ) {
		echo '<p class="gilda-empty">Nenhuma peça cadastrada ainda.</p>';
		return;
	}
	echo '<input type="hidden" name="gilda_pecas_present" value="1" />';
	echo '<ul class="gilda-products">';
	foreach ( $products as $product ) {
		$current = absint( get_post_meta( $product->ID, GILDA_PRODUCT_CHAPTER, true ) );
		$note    = 'sem capítulo';
		if ( $current === $post->ID ) {
			$note = 'neste capítulo';
		} elseif ( $current && get_post( $current ) && 'trash' !== get_post_status( $current ) ) {
			$note = sprintf( 'hoje no Capítulo %d', (int) get_post_meta( $current, '_gilda_numero', true ) );
		}
		if ( 'publish' !== $product->post_status ) {
			$note .= ' · fora do site';
		}
		$thumb = get_the_post_thumbnail_url( $product, 'thumbnail' );
		printf(
			'<li><label><input type="checkbox" name="gilda_pecas[]" value="%1$d" %2$s />%3$s<span class="gilda-products__name">%4$s</span><span class="gilda-products__note">%5$s</span></label></li>',
			(int) $product->ID,
			checked( $current, $post->ID, false ),
			$thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" />' : '<span class="gilda-products__ph" aria-hidden="true"></span>',
			esc_html( get_the_title( $product ) ),
			esc_html( $note )
		);
	}
	echo '</ul>';
}

/** Publicar: situação, o que falta, Publicar capítulo; / Salvar rascunho / Tirar do site. */
function gilda_chapter_box_status( WP_Post $post ): void {
	$live     = 'publish' === $post->post_status;
	$problems = gilda_chapter_missing( gilda_chapter_form_state( $post ) );
	?>
	<input type="hidden" name="original_post_status" value="<?php echo esc_attr( $post->post_status ); ?>" />
	<input type="hidden" name="post_status" value="<?php echo $live ? 'publish' : 'draft'; ?>" />
	<span class="gilda-publish__status">Situação: <strong><?php echo $live ? 'no site' : 'rascunho'; ?></strong></span>
	<ul class="gilda-checklist" data-gilda-checklist>
		<?php foreach ( gilda_chapter_requirements() as $key => $label ) : ?>
			<li class="<?php echo isset( $problems[ $key ] ) ? 'is-missing' : 'is-ok'; ?>" data-req="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></li>
		<?php endforeach; ?>
		<li class="is-optional" data-req="products" data-label="Peças escolhidas">Peças escolhidas</li>
	</ul>
	<?php if ( $live ) : ?>
		<button type="submit" name="save" id="publish" value="Atualizar" class="gilda-btn gilda-btn--big" data-gilda-publish <?php disabled( (bool) $problems ); ?>>Salvar alterações;</button>
		<button type="submit" name="gilda_unpublish" value="1" class="gilda-btn gilda-btn--ghost gilda-btn--block">Tirar do site</button>
		<p class="gilda-publish__note">"Tirar do site" deixa o capítulo como rascunho: ele some do site e nada é apagado.</p>
	<?php else : ?>
		<button type="submit" name="publish" id="publish" value="Publicar" class="gilda-btn gilda-btn--big" data-gilda-publish <?php disabled( (bool) $problems ); ?>>Publicar capítulo;</button>
		<button type="submit" name="save" id="save-post" value="Salvar rascunho" class="gilda-btn gilda-btn--ghost gilda-btn--block">Salvar rascunho</button>
		<p class="gilda-publish__note">Ao publicar, o capítulo entra na página Capítulos, no Livro GILDA;, nos cards da home e no filtro da loja.</p>
	<?php endif; ?>
	<?php if ( current_user_can( 'delete_post', $post->ID ) && 'auto-draft' !== $post->post_status ) : ?>
		<p class="gilda-publish__trash" id="delete-action"><a href="<?php echo esc_url( (string) get_delete_post_link( $post->ID ) ); ?>">Mover para a lixeira</a></p>
	<?php endif; ?>
	<?php
}

/** Prévia do card do capítulo na página inicial (1ª foto da galeria). */
function gilda_chapter_box_preview( WP_Post $post ): void {
	$gallery = gilda_chapter_photo_ids( $post->ID, '_gilda_fotos_galeria' );
	$img     = $gallery ? (string) wp_get_attachment_image_url( $gallery[0], 'medium_large' ) : '';
	printf(
		'<div class="gilda-preview__card" data-gilda-card-preview>%1$s<span class="gilda-preview__title">CAPÍTULO %2$d</span></div>',
		$img ? '<img src="' . esc_url( $img ) . '" alt="" />' : '<img alt="" hidden />',
		(int) gilda_chapter_number_for( $post )
	);
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
