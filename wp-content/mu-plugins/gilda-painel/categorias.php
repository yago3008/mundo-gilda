<?php
/**
 * Peso e embalagem padrão de cada categoria (term meta), editáveis na tela da categoria
 * (Categorias e cores → Categorias). A tela "Nova peça" preenche o Envio com eles.
 *
 * Semente: Camisas, Calças, Bermudas e Lenços recebem os valores do scripts/import-products.php uma vez;
 * depois, quem manda é o painel (um valor mudado ou apagado pelas donas nunca é refeito).
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** Campos (meta => rótulo). */
function gilda_category_shipping_fields(): array {
	return array(
		'gilda_peso'        => 'Peso (kg)',
		'gilda_comprimento' => 'Comprimento (cm)',
		'gilda_largura'     => 'Largura (cm)',
		'gilda_altura'      => 'Altura (cm)',
	);
}

/**
 * Peso e embalagem padrão da categoria, ou null se ela não tem.
 *
 * @param int $term_id Categoria.
 * @return float[]|null [peso, comprimento, largura, altura]
 */
function gilda_category_shipping( int $term_id ): ?array {
	$values = array();
	foreach ( array_keys( gilda_category_shipping_fields() ) as $key ) {
		$value = (string) get_term_meta( $term_id, $key, true );
		if ( '' === $value || (float) $value <= 0 ) {
			return null;
		}
		$values[] = (float) $value;
	}
	return $values;
}

/** Semente das 4 categorias da loja (só o que ainda não foi semeado; nunca sobrescreve). */
function gilda_category_shipping_seed(): void {
	$seed   = array(
		'camisas'  => array( 0.35, 30, 25, 5 ),
		'calcas'   => array( 0.55, 35, 28, 6 ),
		'bermudas' => array( 0.40, 30, 25, 5 ),
		'lencos'   => array( 0.10, 20, 15, 2 ),
	);
	$done   = (array) get_option( 'gilda_category_shipping_seeded', array() );
	$update = false;
	foreach ( $seed as $slug => $values ) {
		if ( in_array( $slug, $done, true ) ) {
			continue;
		}
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term ) {
			continue; // a categoria ainda não existe (setup antes do import): tenta na próxima vez
		}
		foreach ( array_keys( gilda_category_shipping_fields() ) as $i => $key ) {
			if ( ! metadata_exists( 'term', $term->term_id, $key ) ) {
				update_term_meta( $term->term_id, $key, (string) $values[ $i ] );
			}
		}
		$done[] = $slug;
		$update = true;
	}
	if ( $update ) {
		update_option( 'gilda_category_shipping_seeded', $done, false );
	}
}
add_action(
	'init',
	static function () {
		if ( ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) && taxonomy_exists( 'product_cat' ) ) {
			gilda_category_shipping_seed();
		}
	},
	40
);

/* ---------- Campos na tela da categoria ---------- */

/** Campos no formulário "Adicionar categoria". */
add_action(
	'product_cat_add_form_fields',
	static function () {
		echo '<div class="form-field gilda-cat-shipping"><label>Envio padrão das peças desta categoria</label><div style="display:flex;gap:8px;flex-wrap:wrap">';
		foreach ( gilda_category_shipping_fields() as $key => $label ) {
			printf(
				'<label style="flex:1 1 90px">%1$s<input type="text" inputmode="decimal" name="%2$s" value="" /></label>',
				esc_html( $label ),
				esc_attr( $key )
			);
		}
		echo '</div><p>Peso e medidas da embalagem que a tela "Nova peça" já traz preenchidos (dá para mudar em cada peça). O frete é calculado com isso.</p></div>';
	}
);

/** Campos no formulário "Editar categoria". */
add_action(
	'product_cat_edit_form_fields',
	static function ( $term ) {
		echo '<tr class="form-field gilda-cat-shipping"><th scope="row"><label>Envio padrão</label></th><td><div style="display:flex;gap:8px;flex-wrap:wrap">';
		foreach ( gilda_category_shipping_fields() as $key => $label ) {
			$value = (string) get_term_meta( $term->term_id, $key, true );
			printf(
				'<label style="flex:1 1 90px">%1$s<input type="text" inputmode="decimal" name="%2$s" value="%3$s" /></label>',
				esc_html( $label ),
				esc_attr( $key ),
				esc_attr( '' === $value ? '' : str_replace( '.', ',', $value ) )
			);
		}
		echo '</div><p class="description">Peso e medidas da embalagem que a tela "Nova peça" já traz preenchidos (dá para mudar em cada peça). O frete é calculado com isso.</p></td></tr>';
	}
);

/**
 * Grava os campos (as telas de categoria do WordPress já conferem o nonce do formulário).
 *
 * @param int $term_id Categoria.
 */
function gilda_category_shipping_save( $term_id ): void {
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	foreach ( array_keys( gilda_category_shipping_fields() ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			continue;
		}
		$raw   = str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', (string) wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$value = '' === $raw ? '' : (string) (float) $raw;
		update_term_meta( (int) $term_id, $key, $value );
	}
}
add_action( 'created_product_cat', 'gilda_category_shipping_save' );
add_action( 'edited_product_cat', 'gilda_category_shipping_save' );

/* -------------------------------------------------------------------------
 * Cores (pa_cor): a cor da bolinha também se escolhe na tela de Cores
 * (antes só pelo "+ Nova cor" da Nova peça; criada aqui, ficava bege).
 * Grava o term meta "gilda_hex", que o tema lê em gilda_product_colors().
 * ---------------------------------------------------------------------- */

/** Cor gravada no termo ('' se não tiver). */
function gilda_color_term_hex( int $term_id ): string {
	$hex = strtolower( (string) get_term_meta( $term_id, 'gilda_hex', true ) );
	return preg_match( '/^#[0-9a-f]{6}$/', $hex ) ? $hex : '';
}

add_action(
	'pa_cor_add_form_fields',
	static function () {
		echo '<div class="form-field gilda-color-field"><label for="gilda-cor-hex">Cor da bolinha</label>'
			. '<input type="color" id="gilda-cor-hex" name="gilda_hex" value="#b4522d" style="width:72px;height:44px;padding:2px" />'
			. '<p>É a bolinha que a cliente vê nos filtros da loja e na página da peça.</p></div>';
		// O WordPress limpa o formulário depois de adicionar e o seletor voltaria para preto (a próxima cor
		// seria salva preta sem querer): volta para a cor padrão.
		echo "<script>(function(){var c=document.getElementById('gilda-cor-hex');if(!c||!window.jQuery)return;jQuery(document).ajaxComplete(function(e,x,o){if(o&&typeof o.data==='string'&&o.data.indexOf('action=add-tag')>-1){c.value='#b4522d';}});})();</script>";
	}
);

add_action(
	'pa_cor_edit_form_fields',
	static function ( $term ) {
		$hex = gilda_color_term_hex( (int) $term->term_id );
		printf(
			'<tr class="form-field gilda-color-field"><th scope="row"><label for="gilda-cor-hex">Cor da bolinha</label></th><td><input type="color" id="gilda-cor-hex" name="gilda_hex" value="%1$s" style="width:72px;height:44px;padding:2px" /><p class="description">%2$s</p></td></tr>',
			esc_attr( $hex ? $hex : '#d9d3c2' ),
			esc_html( $hex ? 'É a bolinha que a cliente vê nos filtros da loja e na página da peça.' : 'Esta cor ainda não tem bolinha própria (aparece bege no site). Escolha a cor e salve.' )
		);
	}
);

/**
 * Grava a cor (as telas de termos do WordPress já conferem o nonce do formulário).
 *
 * @param int $term_id Cor.
 */
function gilda_color_term_save( $term_id ): void {
	if ( ! current_user_can( 'manage_product_terms' ) || ! isset( $_POST['gilda_hex'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$hex = strtolower( sanitize_text_field( wp_unslash( $_POST['gilda_hex'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( preg_match( '/^#[0-9a-f]{6}$/', $hex ) ) {
		update_term_meta( (int) $term_id, 'gilda_hex', $hex );
	}
}
add_action( 'created_pa_cor', 'gilda_color_term_save' );
add_action( 'edited_pa_cor', 'gilda_color_term_save' );

// Coluna com a bolinha na lista de Cores.
add_filter(
	'manage_edit-pa_cor_columns',
	static function ( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$out['gilda_swatch'] = 'Cor';
			}
			$out[ $key ] = $label;
		}
		return $out;
	}
);
add_filter(
	'manage_pa_cor_custom_column',
	static function ( $content, $column, $term_id ) {
		if ( 'gilda_swatch' !== $column ) {
			return $content;
		}
		$hex = gilda_color_term_hex( (int) $term_id );
		return $hex
			? sprintf( '<span style="display:inline-block;width:22px;height:22px;border-radius:50%%;border:1px solid #a69f88;background:%s" title="%s"></span>', esc_attr( $hex ), esc_attr( $hex ) )
			: '<span title="sem cor: aparece bege no site" style="color:#a31621">sem cor</span>';
	},
	10,
	3
);
