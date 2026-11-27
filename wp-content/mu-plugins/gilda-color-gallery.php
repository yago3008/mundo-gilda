<?php
/**
 * Plugin Name: GILDA; Fotos por cor
 * Description: Liga as fotos de cada peça às cores. Na página do produto, ao escolher a cor, a galeria já mostra só as fotos daquela cor. No painel, a caixa "Fotos por cor" (editar peça) diz quais fotos são de cada cor.
 *
 * Onde fica guardado: na galeria de cada variação (recurso do próprio WooCommerce 11:
 * WC_Product_Variation::get_gallery_image_ids()). Todas as variações de uma cor (todos os tamanhos)
 * recebem as mesmas fotos, e a primeira vira a foto da variação (aparece no carrinho e nos e-mails).
 * O WooCommerce já troca a galeria quando cor + tamanho estão escolhidos; color-gallery.js troca assim
 * que a cor é escolhida.
 *
 * Carga inicial: data/product-color-images.json (scripts/import-color-images.php, pelo setup.sh).
 */

defined( 'ABSPATH' ) || exit;

const GILDA_COLOR_GALLERY_TAXONOMY = 'pa_cor';
const GILDA_COLOR_GALLERY_VERSION  = '1.1.0';

/** Cores (slug => nome) usadas nas variações do produto. */
function gilda_color_gallery_colors( WC_Product $product ): array {
	if ( ! $product->is_type( 'variable' ) ) {
		return array();
	}
	$attributes = $product->get_attributes();
	if ( empty( $attributes[ GILDA_COLOR_GALLERY_TAXONOMY ] ) || ! $attributes[ GILDA_COLOR_GALLERY_TAXONOMY ]->get_variation() ) {
		return array();
	}
	$colors = array();
	foreach ( wc_get_product_terms( $product->get_id(), GILDA_COLOR_GALLERY_TAXONOMY, array( 'fields' => 'all' ) ) as $term ) {
		$colors[ $term->slug ] = $term->name;
	}
	return $colors;
}

/** Todas as fotos da peça (principal + galeria), na ordem da galeria. */
function gilda_color_gallery_product_images( WC_Product $product ): array {
	return array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) ) );
}

/**
 * Fotos por cor, lidas das variações: [ slug da cor => [ids] ].
 */
function gilda_color_gallery_get( WC_Product $product ): array {
	$map = array();
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			continue;
		}
		$color = (string) ( $variation->get_variation_attributes( false )[ GILDA_COLOR_GALLERY_TAXONOMY ] ?? '' );
		$ids   = array_map( 'intval', $variation->get_gallery_image_ids() );
		if ( '' !== $color && $ids && empty( $map[ $color ] ) ) {
			$map[ $color ] = $ids;
		}
	}
	return $map;
}

/**
 * Grava as fotos por cor em todas as variações. Cor sem fotos volta ao padrão (galeria da peça).
 *
 * @param array $map [ slug da cor => [ids de anexo] ].
 */
function gilda_color_gallery_set( WC_Product $product, array $map ): void {
	$allowed = gilda_color_gallery_product_images( $product );
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof WC_Product_Variation ) {
			continue;
		}
		$color = (string) ( $variation->get_variation_attributes( false )[ GILDA_COLOR_GALLERY_TAXONOMY ] ?? '' );
		// Só fotos que são da própria peça, na ordem escolhida.
		$ids = array_values( array_intersect( array_map( 'intval', (array) ( $map[ $color ] ?? array() ) ), $allowed ) );
		$variation->set_gallery_image_ids( $ids );
		$variation->set_image_id( $ids ? $ids[0] : 0 );
		$variation->save();
	}
}

/* -------------------------------------------------------------------------
 * Página do produto
 * ---------------------------------------------------------------------- */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_enqueue_script(
			'gilda-color-gallery',
			plugins_url( 'gilda-color-gallery/color-gallery.js', __FILE__ ),
			array( 'jquery', 'wc-add-to-cart-variation' ),
			GILDA_COLOR_GALLERY_VERSION,
			array( 'in_footer' => true )
		);
		wp_localize_script( 'gilda-color-gallery', 'gildaColorGallery', array( 'attribute' => 'attribute_' . GILDA_COLOR_GALLERY_TAXONOMY ) );
	},
	20
);

/* -------------------------------------------------------------------------
 * Painel: caixa "Fotos por cor" na edição da peça
 * ---------------------------------------------------------------------- */
add_action(
	'add_meta_boxes_product',
	static function ( $post ) {
		$product = wc_get_product( $post->ID );
		if ( ! $product || ! gilda_color_gallery_colors( $product ) ) {
			return;
		}
		add_meta_box( 'gilda-color-gallery', __( 'Fotos por cor', 'gilda' ), 'gilda_color_gallery_metabox', 'product', 'normal', 'default' );
	}
);

function gilda_color_gallery_metabox( WP_Post $post ): void {
	$product = wc_get_product( $post->ID );
	$colors  = gilda_color_gallery_colors( $product );
	$images  = gilda_color_gallery_product_images( $product );
	$map     = gilda_color_gallery_get( $product );
	wp_nonce_field( 'gilda_color_gallery_save', 'gilda_color_gallery_nonce' );
	?>
	<p class="description" style="margin-top:0"><?php esc_html_e( 'Marque as fotos de cada cor. Na loja, quando a cliente escolher a cor, aparecem só as fotos marcadas, na ordem da galeria. Cor sem nenhuma foto marcada mostra todas. Fotos novas: adicione primeiro na "Galeria do produto" e salve.', 'gilda' ); ?></p>
	<input type="hidden" name="gilda_color_gallery_present" value="1">
	<?php foreach ( $colors as $slug => $name ) : ?>
		<?php $checked = $map[ $slug ] ?? array(); ?>
		<fieldset style="margin:16px 0 8px">
			<legend style="font-weight:600;margin-bottom:8px">
				<?php echo esc_html( $name ); ?>
				<span style="font-weight:400;color:#666">(<?php echo esc_html( sprintf( /* translators: %d: quantidade */ _n( '%d foto', '%d fotos', count( $checked ), 'gilda' ), count( $checked ) ) ); ?>)</span>
			</legend>
			<div style="display:flex;flex-wrap:wrap;gap:8px">
				<?php foreach ( $images as $id ) : ?>
					<label style="display:block;width:84px;text-align:center;cursor:pointer">
						<?php echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'display:block;width:84px;height:84px;object-fit:cover;border:2px solid ' . ( in_array( $id, $checked, true ) ? '#75230d' : '#ddd' ) ) ); ?>
						<input type="checkbox" name="gilda_color_gallery[<?php echo esc_attr( $slug ); ?>][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $checked, true ) ); ?> style="margin-top:4px">
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endforeach; ?>
	<?php
}

add_action(
	'woocommerce_process_product_meta',
	static function ( $post_id ) {
		if ( empty( $_POST['gilda_color_gallery_present'] ) || ! isset( $_POST['gilda_color_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['gilda_color_gallery_nonce'] ) ), 'gilda_color_gallery_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$raw    = isset( $_POST['gilda_color_gallery'] ) && is_array( $_POST['gilda_color_gallery'] ) ? wp_unslash( $_POST['gilda_color_gallery'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- inteiros abaixo
		$images = gilda_color_gallery_product_images( $product );
		$map    = array();
		foreach ( array_keys( gilda_color_gallery_colors( $product ) ) as $slug ) {
			$picked = array_map( 'intval', (array) ( $raw[ $slug ] ?? array() ) );
			// Mantém a ordem da galeria da peça.
			$map[ $slug ] = array_values( array_intersect( $images, $picked ) );
		}
		gilda_color_gallery_set( $product, $map );
	},
	50
);
