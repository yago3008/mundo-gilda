<?php
/**
 * "Nova peça": tela simplificada para cadastrar e editar peças (painel-proto/Peca.dc.html; plano §2 de
 * docs/development/painel-conteudo.md). Os dados continuam no WooCommerce: pedidos, estoque, relatórios
 * e o tema não mudam.
 *
 * - Com cores e/ou tamanhos: produto variável, uma variação por combinação (estoque controlado; 0 =
 *   esgotado). Sem nenhum: produto simples.
 * - Editar NÃO recria variações: elas são encontradas pela combinação de atributos (como o
 *   scripts/import-products.php), então pedidos antigos e "avise-me" continuam apontando para o mesmo ID.
 *   Combinação desmarcada vira "privada" (some da loja); marcada de novo, volta com o mesmo ID.
 * - 1ª foto = foto principal; 2ª = 1ª da galeria (a do "passar o mouse" no card da loja).
 * - Peso e embalagem vêm preenchidos pela categoria (os padrões do import) e podem ser mudados.
 *
 * Para a dona: "Peças → Nova peça", os atalhos do painel e "Editar" de uma peça abrem esta tela quando a
 * peça é simples ou variável só com Cor/Tamanho e preço igual em tudo; senão, a tela do WooCommerce.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** Peso e embalagem genéricos, para categoria sem padrão definido. */
const GILDA_PECA_GENERIC_SHIPPING = array( 0.30, 30, 25, 5 );

/**
 * Peso (kg) e embalagem (C × L × A cm) padrão de cada categoria (categorias.php), por slug.
 * Categoria sem padrão: null ('' = genérico).
 *
 * @return array<string,float[]|null>
 */
function gilda_peca_shipping_defaults(): array {
	$out   = array( '' => GILDA_PECA_GENERIC_SHIPPING );
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$out[ $term->slug ] = gilda_category_shipping( (int) $term->term_id );
	}
	return $out;
}

/** Endereço da tela (nova peça, ou editar uma). */
function gilda_peca_edit_url( int $product_id = 0 ): string {
	return admin_url( 'admin.php?page=' . GILDA_PECA_PAGE . ( $product_id ? '&product=' . $product_id : '' ) );
}

/**
 * A peça cabe nesta tela? Simples, ou variável só com Cor/Tamanho de atributo global, com variações
 * que definem todos os atributos e o mesmo preço, sem promoção.
 *
 * @param WC_Product $product Peça.
 * @return bool
 */
function gilda_peca_supported( WC_Product $product ): bool {
	if ( $product->is_type( 'simple' ) ) {
		return '' === (string) $product->get_sale_price( 'edit' );
	}
	if ( ! $product->is_type( 'variable' ) ) {
		return false;
	}
	$taxonomies = array();
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->is_taxonomy() || ! in_array( $attribute->get_name(), array( 'pa_cor', 'pa_tamanho' ), true ) || ! $attribute->get_variation() ) {
			return false;
		}
		$taxonomies[] = $attribute->get_name();
	}
	$prices = array();
	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation ) {
			continue;
		}
		if ( '' !== (string) $variation->get_sale_price( 'edit' ) ) {
			return false;
		}
		$attrs = $variation->get_attributes( 'edit' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $attrs[ $taxonomy ] ) ) {
				return false; // "qualquer cor": não dá para mostrar na grade
			}
		}
		if ( 'publish' === $variation->get_status( 'edit' ) ) {
			$prices[ (string) $variation->get_regular_price( 'edit' ) ] = true;
		}
	}
	return count( $prices ) <= 1;
}

/* ---------- Registro, redirecionamentos e links ---------- */

add_action(
	'admin_menu',
	static function () {
		$hook = add_submenu_page(
			'edit.php?post_type=product',
			'Nova peça',
			gilda_is_owner() ? 'Nova peça' : 'Nova peça (painel)',
			'edit_products',
			GILDA_PECA_PAGE,
			'gilda_peca_screen'
		);
		add_action( 'load-' . $hook, 'gilda_peca_load' );
	}
);

/** Para a dona, "Adicionar produto" e "Editar" abrem esta tela. */
add_action(
	'load-post-new.php',
	static function () {
		if ( gilda_is_owner() && 'product' === ( $_GET['post_type'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( gilda_peca_edit_url() );
			exit;
		}
	}
);
add_action(
	'load-post.php',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! gilda_is_owner() || ! $id || 'product' !== get_post_type( $id ) || isset( $_GET['classic'] ) || 'edit' !== ( $_GET['action'] ?? '' ) ) {
			return;
		}
		// phpcs:enable
		$product = wc_get_product( $id );
		if ( $product && gilda_peca_supported( $product ) ) {
			wp_safe_redirect( gilda_peca_edit_url( $id ) );
			exit;
		}
	}
);
add_filter(
	'get_edit_post_link',
	static function ( $link, $post_id ) {
		if ( ! is_admin() || ! gilda_is_owner() || 'product' !== get_post_type( $post_id ) ) {
			return $link;
		}
		$product = wc_get_product( $post_id );
		return ( $product && gilda_peca_supported( $product ) ) ? gilda_peca_edit_url( (int) $post_id ) : $link;
	},
	10,
	2
);

/* ---------- Nova cor (pelo botão "+ Nova cor") ---------- */

add_action(
	'wp_ajax_gilda_new_color',
	static function () {
		check_ajax_referer( 'gilda_peca_save', 'nonce' );
		if ( ! current_user_can( 'manage_product_terms' ) || ! taxonomy_exists( 'pa_cor' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão para criar cores.' ), 403 );
		}
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$hex  = strtolower( sanitize_text_field( wp_unslash( $_POST['hex'] ?? '' ) ) );
		if ( '' === $name || ! preg_match( '/^#[0-9a-f]{6}$/', $hex ) ) {
			wp_send_json_error( array( 'message' => 'Escreva o nome e escolha a cor.' ), 400 );
		}
		$term = term_exists( $name, 'pa_cor' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'pa_cor' );
		}
		if ( is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => $term->get_error_message() ), 400 );
		}
		$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		update_term_meta( $term_id, 'gilda_hex', $hex );
		$saved = get_term( $term_id, 'pa_cor' );
		wp_send_json_success( array( 'id' => $term_id, 'slug' => $saved->slug, 'name' => $saved->name, 'hex' => $hex, 'code' => gilda_sku_color_code( $saved->slug ) ) );
	}
);

/* ---------- Descrição: texto simples <-> HTML ---------- */

/**
 * HTML da descrição para a caixa de texto: parágrafos separados por linha em branco, **negrito** e *itálico*.
 *
 * @param string $html Descrição guardada.
 * @return array{0:string,1:bool} [texto, true se precisa editar como HTML]
 */
function gilda_peca_description_to_text( string $html ): array {
	if ( preg_match( '~<(?!/?(p|br|strong|b|em|i)\b)[a-z]~i', $html ) ) {
		return array( $html, true ); // tabela, lista etc.: mostra o HTML como está
	}
	$text = preg_replace( '~<br\s*/?>~i', "\n", $html );
	$text = preg_replace( '~</p>\s*~i', "\n\n", $text );
	$text = preg_replace( '~<p[^>]*>~i', '', $text );
	$text = preg_replace( '~</?(strong|b)>~i', '**', $text );
	$text = preg_replace( '~</?(em|i)>~i', '*', $text );
	return array( trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ), false );
}

/**
 * Texto da caixa → HTML da descrição.
 *
 * @param string $text Texto.
 * @param bool   $html Veio em modo HTML.
 * @return string
 */
function gilda_peca_text_to_description( string $text, bool $html ): string {
	if ( $html ) {
		return wp_kses_post( $text );
	}
	$text = esc_html( sanitize_textarea_field( $text ) );
	$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
	$text = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $text );
	return trim( wpautop( $text ) );
}

/** "429,00" / "1.234,56" / "429.9" → 429.00 */
function gilda_peca_parse_price( string $raw ): float {
	$raw = preg_replace( '/[^\d,.]/', '', $raw );
	if ( str_contains( $raw, ',' ) ) {
		$raw = str_replace( array( '.', ',' ), array( '', '.' ), $raw );
	}
	return round( (float) $raw, 2 );
}

/** Número com vírgula para os campos ("0,35"). */
function gilda_peca_num( $value ): string {
	return '' === (string) $value ? '' : str_replace( '.', ',', (string) ( 0 + (float) $value ) );
}

/* ---------- Gravação ---------- */

/** Carrega a tela (CSS/JS) ou grava o formulário. */
function gilda_peca_load(): void {
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		gilda_peca_save();
		exit;
	}
	wp_enqueue_media();
	gilda_painel_enqueue_ui();
	wp_enqueue_style( 'gilda-peca', GILDA_PAINEL_URL . 'assets/peca-edit.css', array( 'gilda-painel-ui' ), gilda_painel_ver( 'assets/peca-edit.css' ) );
	wp_enqueue_script( 'gilda-painel-media', GILDA_PAINEL_URL . 'assets/painel-media.js', array( 'jquery', 'jquery-ui-sortable' ), gilda_painel_ver( 'assets/painel-media.js' ), true );
	wp_enqueue_script( 'gilda-peca', GILDA_PAINEL_URL . 'assets/peca-edit.js', array( 'jquery', 'gilda-painel-media' ), gilda_painel_ver( 'assets/peca-edit.js' ), true );
}

/** Termos de um atributo, na ordem do painel. */
function gilda_peca_terms( string $taxonomy ): array {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'menu_order' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/** Grava a peça (WooCommerce) e volta para a tela. */
function gilda_peca_save(): void {
	check_admin_referer( 'gilda_peca_save' );
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification
	$post = wp_unslash( $_POST );
	$id   = absint( $post['product_id'] ?? 0 );

	$existing = $id ? wc_get_product( $id ) : null;
	if ( $id && ( ! $existing || ! current_user_can( 'edit_post', $id ) ) ) {
		wp_die( 'Peça não encontrada ou sem permissão.' );
	}
	if ( ! $id && ! current_user_can( 'edit_products' ) ) {
		wp_die( 'Sem permissão.' );
	}
	if ( $existing && ! gilda_peca_supported( $existing ) ) {
		wp_die( 'Esta peça usa recursos que só a tela completa do WooCommerce edita.' );
	}

	$action  = 'draft' === ( $post['gilda_action'] ?? '' ) ? 'draft' : 'publish';
	$name    = sanitize_text_field( (string) ( $post['name'] ?? '' ) );
	$price   = gilda_peca_parse_price( (string) ( $post['price'] ?? '' ) );
	$cat     = absint( $post['category'] ?? 0 );
	$chapter = absint( $post['chapter'] ?? 0 );
	$photos  = array_values( array_filter( array_map( 'absint', (array) ( $post['gilda_fotos'] ?? array() ) ), 'wp_attachment_is_image' ) );
	$colors  = array_values( array_filter( array_map( 'absint', (array) ( $post['cores'] ?? array() ) ) ) );
	$sizes   = array_values( array_filter( array_map( 'absint', (array) ( $post['tamanhos'] ?? array() ) ) ) );
	$stock   = (array) ( $post['estoque'] ?? array() );
	$skus    = (array) ( $post['sku'] ?? array() );
	$manual  = (array) ( $post['sku_manual'] ?? array() );
	$errors  = array();
	$notes   = array();

	if ( '' === $name ) {
		set_transient( 'gilda_peca_error_' . get_current_user_id(), array( 'Escreva o nome da peça.' ), 120 );
		wp_safe_redirect( gilda_peca_edit_url( $id ) );
		exit;
	}
	$variable = $colors || $sizes;
	if ( $existing && $existing->is_type( 'variable' ) && ! $variable ) {
		set_transient( 'gilda_peca_error_' . get_current_user_id(), array( 'Escolha pelo menos uma cor ou um tamanho. (Para tirar todas as variações de uma peça já vendida, use a tela completa.)' ), 120 );
		wp_safe_redirect( gilda_peca_edit_url( $id ) );
		exit;
	}
	if ( 'publish' === $action ) {
		if ( $price <= 0 ) {
			$errors[] = 'o preço';
		}
		if ( ! $cat ) {
			$errors[] = 'a categoria';
		}
		if ( ! $photos ) {
			$errors[] = 'pelo menos uma foto';
		}
		if ( $errors ) {
			$action = 'draft';
		}
	}

	// Tipo: simples → variável converte; variável → simples não (acima).
	if ( $existing && $variable && $existing->is_type( 'simple' ) ) {
		wp_set_object_terms( $id, 'variable', 'product_type' );
		$existing = wc_get_product( $id );
	}
	$product = $existing ? $existing : ( $variable ? new WC_Product_Variable() : new WC_Product_Simple() );

	$product->set_name( $name );
	$product->set_status( $action );
	$product->set_description( gilda_peca_text_to_description( (string) ( $post['description'] ?? '' ), ! empty( $post['description_html'] ) ) );
	$product->set_category_ids( $cat ? array( $cat ) : array() );
	$product->set_featured( ! empty( $post['featured'] ) );
	$product->set_image_id( $photos ? $photos[0] : 0 );
	$product->set_gallery_image_ids( array_slice( $photos, 1 ) );
	foreach ( array( 'weight', 'length', 'width', 'height' ) as $dim ) {
		$value = str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', (string) ( $post[ $dim ] ?? '' ) ) );
		$product->{"set_$dim"}( '' === $value ? '' : (string) (float) $value );
	}

	$color_terms = array_filter( array_map( static fn( $t ) => get_term( $t, 'pa_cor' ), $colors ), static fn( $t ) => $t instanceof WP_Term );
	$size_terms  = array_filter( array_map( static fn( $t ) => get_term( $t, 'pa_tamanho' ), $sizes ), static fn( $t ) => $t instanceof WP_Term );
	// Na ordem do painel (cores e tamanhos), não na ordem de clique.
	$order       = static function ( array $chosen, string $taxonomy ): array {
		$ids = wp_list_pluck( gilda_peca_terms( $taxonomy ), 'term_id' );
		usort( $chosen, static fn( $a, $b ) => array_search( $a->term_id, $ids, true ) <=> array_search( $b->term_id, $ids, true ) );
		return array_values( $chosen );
	};
	$color_terms = $order( $color_terms, 'pa_cor' );
	$size_terms  = $order( $size_terms, 'pa_tamanho' );

	if ( $variable ) {
		$attributes = array();
		foreach ( array( 'pa_cor' => $color_terms, 'pa_tamanho' => $size_terms ) as $taxonomy => $terms ) {
			if ( ! $terms ) {
				continue;
			}
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( wp_list_pluck( $terms, 'term_id' ) );
			$attribute->set_position( count( $attributes ) );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$attributes[] = $attribute;
		}
		$product->set_attributes( $attributes );
	} else {
		$product->set_regular_price( $price > 0 ? (string) $price : '' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( max( 0, (int) ( $stock[0][0] ?? 0 ) ) );
	}

	$product_id = $product->save();

	// SKU da peça (CAT-NOME): criado uma vez; só muda se a dona editar o campo.
	gilda_peca_apply_sku( $product, (string) ( $post['parent_sku'] ?? '' ), ! empty( $post['parent_sku_manual'] ), $notes );
	$product->save();

	if ( $variable ) {
		// Variações existentes pela combinação (inclui as "privadas", que voltam com o mesmo ID).
		$by_combo = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child ) {
				$attrs = array_filter( $child->get_attributes( 'edit' ) );
				ksort( $attrs );
				$by_combo[ wp_json_encode( $attrs ) ] = (int) $child_id;
			}
		}
		$kept = array();
		foreach ( $color_terms ? $color_terms : array( null ) as $color ) {
			foreach ( $size_terms ? $size_terms : array( null ) as $size ) {
				$attrs = array();
				if ( $color ) {
					$attrs['pa_cor'] = $color->slug;
				}
				if ( $size ) {
					$attrs['pa_tamanho'] = $size->slug;
				}
				ksort( $attrs );
				$key       = wp_json_encode( $attrs );
				$variation = new WC_Product_Variation( $by_combo[ $key ] ?? 0 );
				$variation->set_parent_id( $product_id );
				$variation->set_attributes( $attrs );
				$variation->set_status( 'publish' );
				$variation->set_regular_price( $price > 0 ? (string) $price : '' );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( max( 0, (int) ( $stock[ $color ? $color->term_id : 0 ][ $size ? $size->term_id : 0 ] ?? 0 ) ) );
				$cell = array( $color ? $color->term_id : 0, $size ? $size->term_id : 0 );
				gilda_peca_apply_sku(
					$variation,
					(string) ( $skus[ $cell[0] ][ $cell[1] ] ?? '' ),
					! empty( $manual[ $cell[0] ][ $cell[1] ] ),
					$notes
				);
				$kept[] = $variation->save();
			}
		}
		foreach ( array_diff( $by_combo, $kept ) as $orphan_id ) {
			$orphan = wc_get_product( $orphan_id );
			if ( $orphan && 'private' !== $orphan->get_status( 'edit' ) ) {
				$orphan->set_status( 'private' ); // some da loja; pedidos antigos continuam com o mesmo ID
				$orphan->save();
			}
		}
		WC_Product_Variable::sync( $product_id );
	}

	update_post_meta( $product_id, GILDA_PRODUCT_CHAPTER, ( $chapter && GILDA_CHAPTER_CPT === get_post_type( $chapter ) ) ? $chapter : 0 );

	foreach ( (array) ( $post['gilda_alt'] ?? array() ) as $attachment_id => $alt ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id && in_array( $attachment_id, $photos, true ) && current_user_can( 'edit_post', $attachment_id ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $alt ) );
		}
	}

	// Filtros por cor/tamanho (tabela de atributos) e caches.
	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore' ) ) {
		wc_get_container()->get( \Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore::class )->create_data_for_product( $product_id );
	}
	wc_delete_product_transients( $product_id );
	if ( function_exists( 'gilda_catalog_cache_flush' ) ) {
		gilda_catalog_cache_flush();
	}
	// phpcs:enable

	if ( $errors ) {
		set_transient( 'gilda_peca_error_' . get_current_user_id(), array( 'A peça foi salva como rascunho. Para publicar, falta: ' . implode( ', ', $errors ) . '.' ), 120 );
	}
	if ( $notes ) {
		set_transient( 'gilda_peca_info_' . get_current_user_id(), $notes, 120 );
	}
	wp_safe_redirect( add_query_arg( 'saved', $errors ? 'incomplete' : $action, gilda_peca_edit_url( $product_id ) ) );
	exit;
}

/**
 * SKU de uma peça/variação ao salvar (sku.php):
 * - editado à mão (e diferente do atual): usa o digitado, se ninguém mais usa;
 * - sem SKU ainda: gera o automático (CAT-NOME[-COR][-TAM], único);
 * - já tem SKU: fica como está (mudar o nome ou a categoria não troca o SKU).
 *
 * @param WC_Product $item   Produto ou variação (o pai já salvo).
 * @param string     $posted Valor do campo.
 * @param bool       $manual A dona mexeu no campo.
 * @param string[]   $notes  Avisos para a tela.
 */
function gilda_peca_apply_sku( WC_Product $item, string $posted, bool $manual, array &$notes ): void {
	$current = (string) $item->get_sku( 'edit' );
	$posted  = gilda_sku_clean( $posted );
	if ( $manual && '' !== $posted && $posted !== $current ) {
		if ( wc_product_has_unique_sku( $item->get_id(), $posted ) ) {
			try {
				$item->set_sku( $posted );
				return;
			} catch ( WC_Data_Exception $e ) {
				unset( $e ); // cai no aviso abaixo
			}
		}
		$notes[] = sprintf( 'O SKU %1$s já é de outra peça, então ficou %2$s.', $posted, '' !== $current ? $current : 'o automático' );
	}
	if ( '' === (string) $item->get_sku( 'edit' ) ) {
		try {
			$item->set_sku( gilda_sku_unique( gilda_sku_for( $item ), $item->get_id() ) );
		} catch ( WC_Data_Exception $e ) {
			unset( $e ); // sem SKU desta vez; a próxima gravação (ou a semente) tenta de novo
		}
	}
}

/* ---------- Tela ---------- */

/** Monta e mostra a tela. */
function gilda_peca_screen(): void {
	$id      = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$product = $id ? wc_get_product( $id ) : null;
	if ( $id && ( ! $product || ! current_user_can( 'edit_post', $id ) ) ) {
		echo '<div class="wrap gilda-panel"><p>Peça não encontrada.</p></div>';
		return;
	}
	if ( $product && ! gilda_peca_supported( $product ) ) {
		printf(
			'<div class="wrap gilda-panel"><p>Esta peça usa recursos que esta tela não edita (promoção, preços diferentes por variação ou outros atributos). <a href="%s">Abrir na tela completa do WooCommerce</a>.</p></div>',
			esc_url( admin_url( 'post.php?post=' . $id . '&action=edit&classic=1' ) )
		);
		return;
	}

	$live      = $product && 'publish' === $product->get_status();
	$cats      = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
	$cats      = is_wp_error( $cats ) ? array() : $cats;
	$cat_ids   = $product ? $product->get_category_ids() : array();
	$cat_id    = $cat_ids ? (int) $cat_ids[0] : 0;
	$chapter   = $product ? absint( get_post_meta( $id, GILDA_PRODUCT_CHAPTER, true ) ) : 0;
	$chapters  = get_posts( array( 'post_type' => GILDA_CHAPTER_CPT, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'meta_key' => '_gilda_numero', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$photos    = $product ? array_values( array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) ) ) : array();
	$all_cores = gilda_peca_terms( 'pa_cor' );
	$all_tams  = gilda_peca_terms( 'pa_tamanho' );
	$hex_of    = function_exists( 'gilda_product_colors' ) ? gilda_product_colors() : array();
	$sel_cores = array();
	$sel_tams  = array();
	$stock     = array();
	$skus      = array();
	$price     = '';

	if ( $product && $product->is_type( 'variable' ) ) {
		foreach ( $product->get_attributes() as $attribute ) {
			if ( 'pa_cor' === $attribute->get_name() ) {
				$sel_cores = array_map( 'intval', $attribute->get_options() );
			} elseif ( 'pa_tamanho' === $attribute->get_name() ) {
				$sel_tams = array_map( 'intval', $attribute->get_options() );
			}
		}
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || 'publish' !== $variation->get_status( 'edit' ) ) {
				continue;
			}
			$attrs = $variation->get_attributes( 'edit' );
			$c     = ! empty( $attrs['pa_cor'] ) ? get_term_by( 'slug', $attrs['pa_cor'], 'pa_cor' ) : null;
			$s     = ! empty( $attrs['pa_tamanho'] ) ? get_term_by( 'slug', $attrs['pa_tamanho'], 'pa_tamanho' ) : null;
			$stock[ ( $c ? $c->term_id : 0 ) . '-' . ( $s ? $s->term_id : 0 ) ] = (int) $variation->get_stock_quantity();
			$skus[ ( $c ? $c->term_id : 0 ) . '-' . ( $s ? $s->term_id : 0 ) ]  = (string) $variation->get_sku( 'edit' );
			if ( '' === $price ) {
				$price = (string) $variation->get_regular_price( 'edit' );
			}
		}
	} elseif ( $product ) {
		$price         = (string) $product->get_regular_price( 'edit' );
		$stock['0-0'] = (int) $product->get_stock_quantity();
	}

	list( $description, $description_html ) = $product ? gilda_peca_description_to_text( (string) $product->get_description( 'edit' ) ) : array( '', false );

	$cat_slug  = '';
	foreach ( $cats as $cat ) {
		if ( $cat->term_id === $cat_id ) {
			$cat_slug = $cat->slug;
		}
	}
	$defaults  = gilda_peca_shipping_defaults();
	$ship      = $product
		? array( $product->get_weight(), $product->get_length(), $product->get_width(), $product->get_height() )
		: ( $defaults[ $cat_slug ] ?? $defaults[''] ) ?? $defaults[''];

	$info   = get_transient( 'gilda_peca_info_' . get_current_user_id() );
	delete_transient( 'gilda_peca_info_' . get_current_user_id() );
	$error  = get_transient( 'gilda_peca_error_' . get_current_user_id() );
	delete_transient( 'gilda_peca_error_' . get_current_user_id() );
	$saved  = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$colors = array();
	foreach ( $all_cores as $term ) {
		$colors[] = array( 'id' => $term->term_id, 'name' => $term->name, 'hex' => $hex_of[ $term->slug ] ?? '#D9D3C2' );
	}
	$config = array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'gilda_peca_save' ),
		'stock'    => (object) $stock,
		'skus'     => (object) array_filter( $skus, 'strlen' ),
		'defaults' => $defaults,
		'colors'   => $colors,
	);
	?>
	<div class="wrap gilda-panel gilda-peca">
		<header class="gilda-page-head">
			<a class="gilda-back" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">← Peças</a>
			<h1 class="gilda-display"><?php echo $product ? esc_html( mb_strtolower( $product->get_name() ) ) . ';' : 'nova peça;'; ?></h1>
			<p class="gilda-lead">Publicada, ela aparece na loja, nos filtros, no capítulo dela e nos relacionados.</p>
		</header>

		<?php if ( $error ) : ?>
			<div class="gilda-notice gilda-notice--err" role="alert"><p><?php echo esc_html( implode( ' ', (array) $error ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( $info ) : ?>
			<div class="gilda-notice" role="status"><p><?php echo esc_html( implode( ' ', (array) $info ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
		<?php elseif ( 'publish' === $saved && $product ) : ?>
			<div class="gilda-notice gilda-notice--ok" role="status"><p>Peça publicada: já está na loja. <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">Ver na loja ↗</a></p></div>
		<?php elseif ( 'draft' === $saved ) : ?>
			<div class="gilda-notice" role="status"><p>Rascunho salvo. A peça ainda não aparece na loja.</p></div>
		<?php endif; ?>

		<form method="post" class="gilda-edit" id="gilda-peca-form" data-gilda-peca="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>">
			<?php wp_nonce_field( 'gilda_peca_save' ); ?>
			<input type="hidden" name="product_id" value="<?php echo (int) $id; ?>" />
			<div class="gilda-edit__main">

				<section class="gilda-card gilda-grid-2" aria-labelledby="p-info">
					<h2 id="p-info" class="gilda-h2 gilda-span">A peça</h2>
					<label class="gilda-label gilda-span">Nome
						<input type="text" name="name" id="gilda-peca-name" class="gilda-input gilda-input--serif" required placeholder="Ex.: Camisa Grão" value="<?php echo esc_attr( $product ? $product->get_name() : '' ); ?>" />
					</label>
					<label class="gilda-label">Preço (R$)
						<input type="text" name="price" id="gilda-peca-price" class="gilda-input" inputmode="decimal" placeholder="429,00" value="<?php echo esc_attr( '' === $price ? '' : number_format( (float) $price, 2, ',', '.' ) ); ?>" />
					</label>
					<label class="gilda-label">Categoria
						<select name="category" id="gilda-peca-category" class="gilda-input">
							<option value="0" data-slug="">Escolha…</option>
							<?php foreach ( $cats as $cat ) : ?>
								<option value="<?php echo (int) $cat->term_id; ?>" data-slug="<?php echo esc_attr( $cat->slug ); ?>" data-code="<?php echo esc_attr( gilda_sku_category_code( $cat->slug ) ); ?>" <?php selected( $cat_id, $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="gilda-label gilda-span">Capítulo
						<select name="chapter" id="gilda-peca-chapter" class="gilda-input">
							<?php foreach ( $chapters as $c ) : ?>
								<option value="<?php echo (int) $c->ID; ?>" <?php selected( $chapter, $c->ID ); ?>><?php echo esc_html( sprintf( 'Capítulo %d — %s', (int) get_post_meta( $c->ID, '_gilda_numero', true ), get_the_title( $c ) ) . ( 'publish' === $c->post_status ? '' : ' (rascunho)' ) ); ?></option>
							<?php endforeach; ?>
							<option value="0" <?php selected( $chapter, 0 ); ?>>Sem capítulo</option>
						</select>
					</label>
					<label class="gilda-label gilda-span">Código da peça (SKU)
						<input type="text" name="parent_sku" id="gilda-peca-sku" class="gilda-input gilda-input--sku" value="<?php echo esc_attr( $product ? (string) $product->get_sku( 'edit' ) : '' ); ?>" data-stored="<?php echo esc_attr( $product ? (string) $product->get_sku( 'edit' ) : '' ); ?>" autocomplete="off" spellcheck="false" />
						<input type="hidden" name="parent_sku_manual" value="0" data-gilda-sku-manual />
						<span class="gilda-help gilda-help--plain">Gerado sozinho (categoria + nome) e não muda depois, nem se a peça mudar de nome. Cada combinação de cor e tamanho tem o seu, na tabela de estoque. É a chave da peça no catálogo do Instagram/Facebook e, no futuro, no sistema de gestão.</span>
					</label>
					<label class="gilda-label gilda-span">Descrição
						<textarea name="description" rows="6" class="gilda-input" placeholder="Composição, caimento, cuidados e a tabela de medidas."><?php echo esc_textarea( $description ); ?></textarea>
						<span class="gilda-help gilda-help--plain"><?php echo $description_html ? 'Esta descrição tem tabela ou lista, por isso aparece em HTML.' : 'Deixe uma linha em branco entre os parágrafos. *itálico* e **negrito** funcionam.'; ?></span>
					</label>
					<?php if ( $description_html ) : ?>
						<input type="hidden" name="description_html" value="1" />
					<?php endif; ?>
				</section>

				<section class="gilda-card" aria-labelledby="p-fotos">
					<div class="gilda-card__intro">
						<h2 id="p-fotos" class="gilda-h2">Fotos</h2>
						<span>A primeira é a capa; a segunda aparece ao passar o mouse na loja. Arraste para ordenar.</span>
					</div>
					<?php gilda_photo_field( 'gilda_fotos', $photos, 0, array( 'title' => 'Fotos da peça', 'button' => 'Usar estas fotos', 'style' => 'cover', 'captions' => array( 'Capa', 'Ao passar o mouse' ), 'add' => '+ Adicionar fotos' ) ); ?>
				</section>

				<section class="gilda-card" aria-labelledby="p-var">
					<h2 id="p-var" class="gilda-h2">Cores, tamanhos e estoque</h2>
					<fieldset class="gilda-fieldset">
						<legend>Cores</legend>
						<div class="gilda-chips" data-gilda-colors>
							<?php foreach ( $all_cores as $term ) : ?>
								<label class="gilda-chip"><input type="checkbox" name="cores[]" value="<?php echo (int) $term->term_id; ?>" data-name="<?php echo esc_attr( $term->name ); ?>" data-hex="<?php echo esc_attr( $hex_of[ $term->slug ] ?? '#D9D3C2' ); ?>" data-code="<?php echo esc_attr( gilda_sku_color_code( $term->slug ) ); ?>" <?php checked( in_array( $term->term_id, $sel_cores, true ) ); ?> /><span class="gilda-swatch" style="background:<?php echo esc_attr( $hex_of[ $term->slug ] ?? '#D9D3C2' ); ?>" aria-hidden="true"></span><?php echo esc_html( $term->name ); ?></label>
							<?php endforeach; ?>
							<?php if ( current_user_can( 'manage_product_terms' ) ) : ?>
								<button type="button" class="gilda-chip gilda-chip--add" data-gilda-newcolor-open>+ Nova cor</button>
							<?php endif; ?>
						</div>
						<div class="gilda-newcolor" data-gilda-newcolor hidden>
							<label class="gilda-label">Nome da cor<input type="text" class="gilda-input" data-gilda-newcolor-name placeholder="Ex.: Verde Oliva" /></label>
							<label class="gilda-label">Cor<input type="color" value="#77270c" data-gilda-newcolor-hex /></label>
							<button type="button" class="gilda-btn" data-gilda-newcolor-save>Criar cor</button>
							<button type="button" class="gilda-link" data-gilda-newcolor-cancel>cancelar</button>
							<span class="gilda-help" role="status" data-gilda-newcolor-msg></span>
						</div>
					</fieldset>
					<fieldset class="gilda-fieldset">
						<legend>Tamanhos</legend>
						<div class="gilda-sizes" data-gilda-sizes>
							<?php foreach ( $all_tams as $term ) : ?>
								<label class="gilda-size"><input type="checkbox" name="tamanhos[]" value="<?php echo (int) $term->term_id; ?>" data-name="<?php echo esc_attr( $term->name ); ?>" data-code="<?php echo esc_attr( gilda_sku_size_code( $term->name ) ); ?>" <?php checked( in_array( $term->term_id, $sel_tams, true ) ); ?> /><?php echo esc_html( $term->name ); ?></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<div class="gilda-fieldset">
						<span class="gilda-label">Estoque de cada combinação</span>
						<div class="gilda-stock" data-gilda-stock></div>
						<span class="gilda-help">Com 0, a combinação aparece como "Esgotado" e as clientes podem pedir "Avise-me". Sem cores e tamanhos, a peça tem um estoque só. Embaixo de cada quantidade fica o SKU da combinação.</span>
					</div>
				</section>

				<section class="gilda-card gilda-ship" aria-labelledby="p-envio">
					<div class="gilda-card__intro">
						<h2 id="p-envio" class="gilda-h2">Envio</h2>
						<span data-gilda-ship-help><?php echo $product ? 'Peso e medidas da embalagem: o frete é calculado com isso.' : 'Escolha a categoria: peso e medidas vêm preenchidos com o padrão dela. Ajuste se a embalagem for diferente: o frete é calculado com isso.'; ?></span>
					</div>
					<div class="gilda-grid-4">
						<label class="gilda-label">Peso (kg)<input type="text" name="weight" inputmode="decimal" class="gilda-input" data-gilda-ship="0" value="<?php echo esc_attr( gilda_peca_num( $ship[0] ) ); ?>" /></label>
						<label class="gilda-label">Compr. (cm)<input type="text" name="length" inputmode="decimal" class="gilda-input" data-gilda-ship="1" value="<?php echo esc_attr( gilda_peca_num( $ship[1] ) ); ?>" /></label>
						<label class="gilda-label">Largura (cm)<input type="text" name="width" inputmode="decimal" class="gilda-input" data-gilda-ship="2" value="<?php echo esc_attr( gilda_peca_num( $ship[2] ) ); ?>" /></label>
						<label class="gilda-label">Altura (cm)<input type="text" name="height" inputmode="decimal" class="gilda-input" data-gilda-ship="3" value="<?php echo esc_attr( gilda_peca_num( $ship[3] ) ); ?>" /></label>
					</div>
				</section>
			</div>

			<aside class="gilda-edit__side">
				<section class="gilda-card gilda-publish" aria-labelledby="p-publicar">
					<h2 id="p-publicar" class="gilda-h2">Publicar</h2>
					<span class="gilda-publish__status">Situação: <strong><?php echo $live ? 'na loja' : 'rascunho'; ?></strong></span>
					<label class="gilda-check"><input type="checkbox" name="featured" value="1" <?php checked( $product && $product->get_featured() ); ?> />Destaque na página inicial</label>
					<button type="submit" name="gilda_action" value="publish" class="gilda-btn gilda-btn--big" id="gilda-peca-publish"><?php echo $live ? 'Salvar alterações;' : 'Publicar peça;'; ?></button>
					<button type="submit" name="gilda_action" value="draft" class="gilda-btn gilda-btn--ghost gilda-btn--block" formnovalidate><?php echo $live ? 'Tirar da loja (rascunho)' : 'Salvar rascunho'; ?></button>
					<?php if ( $product ) : ?>
						<p class="gilda-publish__note"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php echo $live ? 'Ver na loja ↗' : 'Pré-visualizar ↗'; ?></a> · <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit&classic=1' ) ); ?>">tela completa</a></p>
					<?php endif; ?>
				</section>
				<section class="gilda-card gilda-preview" aria-labelledby="p-preview">
					<h2 id="p-preview" class="gilda-eyebrow">Assim fica na loja</h2>
					<img class="gilda-preview__img" data-gilda-preview-img src="<?php echo esc_url( $photos ? (string) wp_get_attachment_image_url( $photos[0], 'medium_large' ) : '' ); ?>" alt="" <?php echo $photos ? '' : 'hidden'; ?> />
					<span class="gilda-preview__img gilda-preview__img--empty" data-gilda-preview-empty <?php echo $photos ? 'hidden' : ''; ?>></span>
					<span class="gilda-preview__name" data-gilda-preview-name><?php echo esc_html( $product ? $product->get_name() : 'Nome da peça' ); ?></span>
					<span class="gilda-preview__price" data-gilda-preview-price><?php echo esc_html( '' === $price ? 'R$ —' : gilda_money( (float) $price ) ); ?></span>
				</section>
			</aside>
		</form>
	</div>
	<?php
}

/** Título da aba do navegador ("Nova peça" / nome da peça). */
add_filter(
	'admin_title',
	static function ( $title ) {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( GILDA_PECA_PAGE !== ( $_GET['page'] ?? '' ) ) {
			return $title;
		}
		$id = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
		// phpcs:enable
		return ( $id ? get_the_title( $id ) : 'Nova peça' ) . ' ‹ ' . get_bloginfo( 'name' );
	}
);
