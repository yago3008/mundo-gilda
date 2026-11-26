<?php
/**
 * Importa os produtos de data/products.json (gerado por export-wix-products.mjs) para o WooCommerce.
 *
 * SEMENTE: cria só as peças que ainda não foram importadas. Peça que já existe (ou que já foi importada
 * uma vez e as donas apagaram) NÃO é tocada: preço, estoque, fotos, textos e variações editados no painel
 * ficam como estão. Os slugs já importados ficam na opção `gilda_seeded_products`.
 *
 * Uso: docker compose run --rm wpcli wp eval-file /scripts/import-products.php
 *      IMPORT_IMAGES=1        → também baixa as fotos do Wix (só para peças novas ou ainda sem foto)
 *      IMPORT_PRODUCTS=refresh → RESSINCRONIZA tudo com o JSON do Wix (nome, preço, estoque, peso,
 *                                variações — apaga as que saíram). Só antes da virada; nunca em produção.
 *      (pelo setup: IMPORT_IMAGES=1 ./scripts/setup.sh, IMPORT_PRODUCTS=refresh ./scripts/setup.sh)
 *
 * Peso e dimensões: o Wix não tem esses dados. Usamos valores PROVISÓRIOS por categoria
 * (necessários para o cálculo de frete) até a GILDA; mandar as medidas reais das embalagens.
 */

defined( 'ABSPATH' ) || exit;

$file = '/data/products.json';
if ( ! file_exists( $file ) ) {
	WP_CLI::error( "Arquivo $file não encontrado. Rode antes: node scripts/export-wix-products.mjs" );
}
$data          = json_decode( file_get_contents( $file ), true );
$import_images = (bool) getenv( 'IMPORT_IMAGES' );
$refresh       = 'refresh' === getenv( 'IMPORT_PRODUCTS' );
$seeded        = array_flip( (array) get_option( 'gilda_seeded_products', array() ) );
// Loja que já passou pelo import antigo (antes desta lista existir): se alguma peça do JSON já está lá,
// todas foram importadas uma vez. Assim uma peça que as donas apagaram não volta.
if ( null === get_option( 'gilda_seeded_products', null ) ) {
	$json_slugs = array_map( static fn( $p ) => sanitize_title( $p['slug'] ), $data['products'] );
	foreach ( $json_slugs as $json_slug ) {
		if ( get_page_by_path( $json_slug, OBJECT, 'product' ) ) {
			$seeded = array_flip( $json_slugs );
			break;
		}
	}
}
if ( $refresh ) {
	WP_CLI::warning( 'IMPORT_PRODUCTS=refresh: sobrescrevendo as peças com os dados do Wix.' );
}

// Peso (kg) e embalagem (C x L x A cm) provisórios por categoria.
$shipping_defaults = array(
	'Camisas'  => array( 0.35, 30, 25, 5 ),
	'Calças'   => array( 0.55, 35, 28, 6 ),
	'Bermudas' => array( 0.40, 30, 25, 5 ),
	'Lenços'   => array( 0.10, 20, 15, 2 ),
);

// Padroniza nomes de cor vindos do Wix ("azulclaro" → "Azul Claro").
$normalize = static function ( string $label ): string {
	$fix = array( 'azulclaro' => 'Azul Claro' );
	$key = mb_strtolower( trim( $label ) );
	return $fix[ $key ] ?? mb_convert_case( $key, MB_CASE_TITLE, 'UTF-8' );
};

$term_id = static function ( string $taxonomy, string $name ): int {
	$term = get_term_by( 'name', $name, $taxonomy );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$res = wp_insert_term( $name, $taxonomy );
	if ( is_wp_error( $res ) ) {
		WP_CLI::error( "Termo $name em $taxonomy: " . $res->get_error_message() );
	}
	return (int) $res['term_id'];
};

$category_id = static function ( string $name ) use ( $term_id ): int {
	return $term_id( 'product_cat', $name );
};

$sideload = static function ( string $url, int $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$existing = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_gilda_source_url', 'meta_value' => $url, 'fields' => 'ids', 'numberposts' => 1 ) );
	if ( $existing ) {
		return $existing[0];
	}
	$id = media_sideload_image( $url, $post_id, null, 'id' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "Imagem $url: " . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_gilda_source_url', $url );
	return $id;
};

$tax_of = array( 'Cor' => 'pa_cor', 'Tamanho' => 'pa_tamanho' );

foreach ( $data['products'] as $p ) {
	$slug     = sanitize_title( $p['slug'] );
	$existing = get_page_by_path( $slug, OBJECT, 'product' );
	$variable = ! empty( $p['attributes'] );

	// Semente: peça já importada (mesmo que apagada depois) ou já existente fica como está.
	if ( ! $refresh && ( $existing || isset( $seeded[ $slug ] ) ) ) {
		$seeded[ $slug ] = true;
		if ( $existing && $import_images && ! empty( $p['images'] ) && 'trash' !== $existing->post_status ) {
			$current = wc_get_product( $existing->ID );
			if ( $current && ! $current->get_image_id() ) { // só completa quem está sem foto
				$ids = array_values( array_filter( array_map( static fn( $url ) => $sideload( $url, $existing->ID ), $p['images'] ) ) );
				if ( $ids ) {
					$current->set_image_id( array_shift( $ids ) );
					$current->set_gallery_image_ids( $ids );
					$current->save();
					WP_CLI::log( "  • {$p['name']} (#{$existing->ID}): fotos adicionadas" );
					continue;
				}
			}
		}
		WP_CLI::log( $existing ? "  • {$p['name']}: já existe, mantida" : "  • {$p['name']}: já foi importada e não está mais na loja (apagada no painel), não recriada" );
		continue;
	}
	$seeded[ $slug ] = true;

	$product = $variable ? new WC_Product_Variable( $existing ? $existing->ID : 0 ) : new WC_Product_Simple( $existing ? $existing->ID : 0 );
	if ( $existing && $product->get_type() !== ( $variable ? 'variable' : 'simple' ) ) {
		wp_set_object_terms( $existing->ID, $variable ? 'variable' : 'simple', 'product_type' );
		$product = wc_get_product( $existing->ID );
	}

	$product->set_name( $p['name'] );
	$product->set_slug( $slug );
	$product->set_status( 'publish' );
	$product->set_description( $p['description'] );
	$product->set_category_ids( array_map( $category_id, $p['categories'] ) );

	$cat                     = $p['categories'][0] ?? '';
	[ $weight, $l, $w, $h ]  = $shipping_defaults[ $cat ] ?? array( 0.3, 30, 25, 5 );
	$product->set_weight( $weight );
	$product->set_length( $l );
	$product->set_width( $w );
	$product->set_height( $h );

	if ( $variable ) {
		$attrs = array();
		foreach ( $p['attributes'] as $i => $a ) {
			$taxonomy = $tax_of[ $a['name'] ];
			$ids      = array();
			foreach ( $a['values'] as $v ) {
				$tid   = $term_id( $taxonomy, 'pa_cor' === $taxonomy ? $normalize( $v['label'] ) : $v['label'] );
				$ids[] = $tid;
				// Cor da bolinha (swatch) para o front: term meta "gilda_hex" (ex.: #b4522d).
				// Não troca a cor que já estiver gravada (pode ter sido ajustada no painel), salvo no refresh.
				if ( 'pa_cor' === $taxonomy && ! empty( $v['hex'] ) && preg_match( '/^#[0-9a-f]{6}$/i', $v['hex'] )
					&& ( $refresh || '' === (string) get_term_meta( $tid, 'gilda_hex', true ) ) ) {
					update_term_meta( $tid, 'gilda_hex', strtolower( $v['hex'] ) );
				}
			}
			$attr = new WC_Product_Attribute();
			$attr->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attr->set_name( $taxonomy );
			$attr->set_options( $ids );
			$attr->set_position( $i );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$attrs[] = $attr;
		}
		$product->set_attributes( $attrs );
	} else {
		$product->set_regular_price( (string) $p['price'] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( (int) ( $p['stock_quantity'] ?? 0 ) );
		$product->set_stock_status( $p['stock_status'] ?? 'outofstock' );
	}

	$product_id = $product->save();

	if ( $variable ) {
		// Atualiza as variações existentes pela combinação de atributos (não recria: pedidos
		// antigos continuam apontando para a mesma variação). Remove só as que saíram do JSON.
		$existing_variations = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			$attrs = $child->get_attributes();
			ksort( $attrs );
			$existing_variations[ wp_json_encode( $attrs ) ] = $child_id;
		}
		$kept = array();

		foreach ( $p['variations'] as $v ) {
			$attrs = array();
			foreach ( $v['attributes'] as $name => $label ) {
				$taxonomy            = $tax_of[ $name ];
				$term                = get_term( $term_id( $taxonomy, 'pa_cor' === $taxonomy ? $normalize( $label ) : $label ), $taxonomy );
				$attrs[ $taxonomy ] = $term->slug;
			}
			ksort( $attrs );
			$key       = wp_json_encode( $attrs );
			$variation = new WC_Product_Variation( $existing_variations[ $key ] ?? 0 );
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( $attrs );
			$variation->set_regular_price( (string) $v['price'] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( (int) ( $v['stock_quantity'] ?? 0 ) );
			$variation->set_stock_status( $v['stock_status'] );
			$kept[] = $variation->save();
		}
		foreach ( array_diff( $existing_variations, $kept ) as $orphan_id ) {
			wp_delete_post( $orphan_id, true );
		}
		WC_Product_Variable::sync( $product_id );
	}

	if ( $import_images && ! empty( $p['images'] ) ) {
		$ids = array_filter( array_map( static fn( $url ) => $sideload( $url, $product_id ), $p['images'] ) );
		$ids = array_values( $ids );
		if ( $ids ) {
			$product = wc_get_product( $product_id );
			$product->set_image_id( array_shift( $ids ) );
			$product->set_gallery_image_ids( $ids );
			$product->save();
		}
	}

	$n = $variable ? count( $p['variations'] ) . ' variações' : 'simples';
	WP_CLI::log( "  • {$p['name']} (#$product_id, $n)" );
}

// Ordem dos tamanhos nos seletores e filtros (PP→GG, 36→44), em vez de alfabética.
$size_order = array( 'PP', 'P', 'M', 'G', 'GG', '36', '38', '40', '42', '44' );
foreach ( $size_order as $pos => $name ) {
	$term = get_term_by( 'name', $name, 'pa_tamanho' );
	if ( $term && ( $refresh || '' === (string) get_term_meta( $term->term_id, 'gilda_seeded_order', true ) ) ) {
		update_term_meta( $term->term_id, 'gilda_seeded_order', 1 );
		update_term_meta( $term->term_id, 'order', $pos );
	}
}

// Tabela de atributos usada pelos filtros (contagens e "só com estoque").
update_option( 'woocommerce_attribute_lookup_enabled', 'yes' );
update_option( 'woocommerce_attribute_lookup_direct_updates', 'no' );
$lookup = wc_get_container()->get( \Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore::class );
foreach ( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) as $pid ) {
	$lookup->create_data_for_product( $pid );
}

update_option( 'gilda_seeded_products', array_keys( $seeded ), false );
wc_delete_product_transients();
WP_CLI::success( count( $data['products'] ) . ' produtos conferidos.' );
