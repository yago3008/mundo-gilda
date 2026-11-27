<?php
/**
 * SKU das peças: a chave que liga o site ao futuro ERP (docs/development/integracao-erp.md).
 *
 * Formato (o do protótipo do ERP): CAT-NOME-COR-TAM, em maiúsculas, sem acento. Ex.: CAM-GRAO-OW-PP.
 * - Variação: CAT-NOME-COR-TAM (sem a parte que a peça não tem: só cor, só tamanho).
 * - Produto (o "pai" e a peça simples): CAT-NOME. É o que o Meta Pixel manda como content_id.
 * - Único na loja: se já existir, ganha -2, -3…
 * - Depois de criado, NUNCA muda sozinho (nem se a peça mudar de nome ou de categoria). Só uma edição
 *   manual no campo SKU da tela da peça muda.
 *
 * Quem cria: a tela "Nova peça" (peca.php) e, para o que já existia, scripts/import-skus.php (semente).
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** Código da categoria (3 letras). */
function gilda_sku_category_code( string $category_slug ): string {
	$known = array( 'camisas' => 'CAM', 'calcas' => 'CAL', 'bermudas' => 'BER', 'lencos' => 'LEN' );
	if ( isset( $known[ $category_slug ] ) ) {
		return $known[ $category_slug ];
	}
	$code = substr( gilda_sku_clean( str_replace( '-', '', $category_slug ) ), 0, 3 );
	return '' !== $code ? $code : 'GIL';
}

/** Código da cor. */
function gilda_sku_color_code( string $color_slug ): string {
	$known = array(
		'off-white'   => 'OW',
		'marrom'      => 'MR',
		'branco'      => 'BR',
		'azul-claro'  => 'AZC',
		'laranja'     => 'LJ',
		'preto'       => 'PT',
		'verde-musgo' => 'VM',
	);
	if ( isset( $known[ $color_slug ] ) ) {
		return $known[ $color_slug ];
	}
	$words = array_values( array_filter( explode( '-', sanitize_title( $color_slug ) ) ) );
	if ( count( $words ) > 1 ) {
		return gilda_sku_clean( implode( '', array_map( static fn( $w ) => substr( $w, 0, 1 ), array_slice( $words, 0, 3 ) ) ) );
	}
	return substr( gilda_sku_clean( $words[0] ?? 'X' ), 0, 3 );
}

/** Código do tamanho (o nome do tamanho: PP, P, M, 38, UNICO…). */
function gilda_sku_size_code( string $size_name ): string {
	return str_replace( '-', '', gilda_sku_clean( $size_name ) );
}

/**
 * NOME: o nome da peça em slug, sem a 1ª palavra quando ela repete a categoria
 * (camisa-grao → GRAO, calca-torra → TORRA, lenco-oitenta-e-oito → OITENTA-E-OITO).
 * Usa o nome (e não o slug do Wix, que às vezes é só "calca").
 *
 * @param string $name          Nome da peça.
 * @param string $category_slug Slug da categoria.
 * @return string
 */
function gilda_sku_name_token( string $name, string $category_slug ): string {
	$words = array_values( array_filter( explode( '-', sanitize_title( remove_accents( $name ) ) ) ) );
	$stem  = rtrim( $category_slug, 's' ); // camisas → camisa, calcas → calca
	if ( count( $words ) > 1 && '' !== $stem && ( $words[0] === $stem || $words[0] === $category_slug ) ) {
		array_shift( $words );
	}
	$token = gilda_sku_clean( implode( '-', $words ) );
	return '' !== $token ? $token : 'PECA';
}

/** Maiúsculas, sem acento, só letras, números e hífen. */
function gilda_sku_clean( string $text ): string {
	$text = strtoupper( remove_accents( $text ) );
	$text = (string) preg_replace( '/[^A-Z0-9]+/', '-', $text );
	return trim( (string) preg_replace( '/-+/', '-', $text ), '-' );
}

/** Slug da 1ª categoria da peça ('' sem categoria). */
function gilda_sku_product_category( WC_Product $product ): string {
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$ids    = $parent ? $parent->get_category_ids() : array();
	foreach ( $ids as $id ) {
		$term = get_term( $id, 'product_cat' );
		if ( $term instanceof WP_Term && (int) get_option( 'default_product_cat' ) !== (int) $term->term_id ) {
			return $term->slug;
		}
	}
	return '';
}

/**
 * SKU sugerido (sem conferir se já existe).
 *
 * @param string      $name          Nome da peça.
 * @param string      $category_slug Categoria.
 * @param string|null $color_slug    Cor da variação (null: sem cor).
 * @param string|null $size_name     Tamanho da variação (null: sem tamanho).
 * @return string
 */
function gilda_sku_build( string $name, string $category_slug, ?string $color_slug = null, ?string $size_name = null ): string {
	$parts = array( gilda_sku_category_code( $category_slug ), gilda_sku_name_token( $name, $category_slug ) );
	if ( null !== $color_slug && '' !== $color_slug ) {
		$parts[] = gilda_sku_color_code( $color_slug );
	}
	if ( null !== $size_name && '' !== $size_name ) {
		$parts[] = gilda_sku_size_code( $size_name );
	}
	return implode( '-', array_filter( $parts, 'strlen' ) );
}

/**
 * O SKU (ou ele com -2, -3…) que ninguém mais usa.
 *
 * @param string $sku        SKU desejado.
 * @param int    $product_id Peça/variação que vai usar (ela mesma não conta como repetida).
 * @return string
 */
function gilda_sku_unique( string $sku, int $product_id = 0 ): string {
	$candidate = $sku;
	for ( $n = 2; ! wc_product_has_unique_sku( $product_id, $candidate ); $n++ ) {
		$candidate = $sku . '-' . $n;
	}
	return $candidate;
}

/**
 * SKU calculado para um produto ou variação (a partir dos dados de agora).
 *
 * @param WC_Product $product Produto ou variação.
 * @return string
 */
function gilda_sku_for( WC_Product $product ): string {
	$parent   = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$category = gilda_sku_product_category( $product );
	if ( ! $product->is_type( 'variation' ) ) {
		return gilda_sku_build( $parent->get_name(), $category );
	}
	$attrs = $product->get_attributes( 'edit' );
	$color = (string) ( $attrs['pa_cor'] ?? '' );
	$size  = '';
	if ( ! empty( $attrs['pa_tamanho'] ) ) {
		$term = get_term_by( 'slug', $attrs['pa_tamanho'], 'pa_tamanho' );
		$size = $term ? $term->name : (string) $attrs['pa_tamanho'];
	}
	return gilda_sku_build( $parent->get_name(), $category, $color, $size );
}

/**
 * Dá um SKU à peça/variação SÓ se ela ainda não tem (nunca troca o que existe). Não salva: chame save().
 *
 * @param WC_Product $product Produto ou variação (já salvo, com ID).
 * @return bool Se mudou.
 */
function gilda_sku_ensure( WC_Product $product ): bool {
	if ( '' !== (string) $product->get_sku( 'edit' ) ) {
		return false;
	}
	$product->set_sku( gilda_sku_unique( gilda_sku_for( $product ), $product->get_id() ) );
	return true;
}
