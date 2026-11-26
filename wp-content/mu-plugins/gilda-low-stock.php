<?php
/**
 * Plugin Name: GILDA; Últimas unidades
 * Description: "Restam só N" quando o estoque do produto/variação está no limite configurável (WooCommerce → Configurações → Produtos → Inventário; padrão 2).
 *
 * Para o front (K1venn):
 * - Página do produto: o aviso de estoque do WooCommerce ("Em estoque") vira
 *   <p class="stock in-stock gilda-low-stock" data-gilda-low-stock="N">Restam só N</p>.
 *   Em produto variável isso vem no `availability_html` da variação, que o WooCommerce
 *   já coloca em .woocommerce-variation-availability ao escolher cor/tamanho.
 * - Dados: cada variação em data-product_variations (e no evento found_variation) tem
 *   `gilda_low_stock` (N ou null). Store API: extensions.gilda.low_stock nos produtos.
 * - PHP: gilda_low_stock_qty( $product ) → int|null e gilda_low_stock_html( $product ) → string.
 */

defined( 'ABSPATH' ) || exit;

const GILDA_LOW_STOCK_OPTION  = 'gilda_low_stock_threshold';
const GILDA_LOW_STOCK_DEFAULT = 2;

/**
 * Limite de "últimas unidades" (≤ N mostra o aviso). 0 desliga.
 */
function gilda_low_stock_threshold(): int {
	$value = get_option( GILDA_LOW_STOCK_OPTION, GILDA_LOW_STOCK_DEFAULT );
	$value = '' === $value ? GILDA_LOW_STOCK_DEFAULT : (int) $value;
	return max( 0, (int) apply_filters( 'gilda_low_stock_threshold', $value ) );
}

/**
 * Quantas unidades restam, se o produto/variação estiver nas últimas unidades; senão null.
 *
 * @param WC_Product|int|null $product Produto, variação ou ID.
 */
function gilda_low_stock_qty( $product ): ?int {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product instanceof WC_Product || ! $product->is_in_stock() || ! $product->managing_stock() || $product->backorders_allowed() ) {
		return null;
	}
	// Variação sem estoque próprio usa o do pai (managing_stock() já considera isso).
	$qty       = (int) $product->get_stock_quantity();
	$threshold = gilda_low_stock_threshold();
	if ( $qty < 1 || 0 === $threshold || $qty > $threshold ) {
		return null;
	}
	return $qty;
}

/**
 * Marcação "Restam só N" (string vazia se não estiver nas últimas unidades).
 *
 * @param WC_Product|int|null $product Produto, variação ou ID.
 */
function gilda_low_stock_html( $product ): string {
	$qty = gilda_low_stock_qty( $product );
	if ( null === $qty ) {
		return '';
	}
	/* translators: %d: unidades restantes */
	$text = sprintf( _n( 'Resta só %d', 'Restam só %d', $qty, 'gilda' ), $qty );
	$text = (string) apply_filters( 'gilda_low_stock_text', $text, $qty, $product );
	return sprintf(
		'<p class="stock in-stock gilda-low-stock" data-gilda-low-stock="%d">%s</p>',
		$qty,
		esc_html( $text )
	);
}

// Substitui o "Em estoque" do WooCommerce (produto simples e availability_html da variação).
add_filter(
	'woocommerce_get_stock_html',
	static function ( $html, $product ) {
		$low = gilda_low_stock_html( $product );
		return '' !== $low ? $low : $html;
	},
	20,
	2
);

// Dado bruto nas variações (data-product_variations / found_variation).
add_filter(
	'woocommerce_available_variation',
	static function ( $data, $product, $variation ) {
		$data['gilda_low_stock'] = gilda_low_stock_qty( $variation );
		return $data;
	},
	10,
	3
);

// Store API: extensions.gilda.low_stock (o low_stock_remaining nativo usa outro limite, o do aviso ao admin).
add_action(
	'woocommerce_blocks_loaded',
	static function () {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema::IDENTIFIER,
				'namespace'       => 'gilda',
				'data_callback'   => static function ( $product ) {
					return array( 'low_stock' => gilda_low_stock_qty( $product ) );
				},
				'schema_callback' => static function () {
					return array(
						'low_stock' => array(
							'description' => 'Unidades restantes quando estão no limite de "últimas unidades"; null caso contrário.',
							'type'        => array( 'integer', 'null' ),
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					);
				},
				'schema_type'     => ARRAY_A,
			)
		);
	}
);

// Configuração: WooCommerce → Configurações → Produtos → Inventário.
add_filter(
	'woocommerce_inventory_settings',
	static function ( $settings ) {
		$field = array(
			'title'             => __( 'Últimas unidades (vitrine)', 'gilda' ),
			'desc'              => __( 'Mostra "Restam só N" na página do produto quando o estoque estiver neste número ou abaixo. 0 desliga.', 'gilda' ),
			'id'                => GILDA_LOW_STOCK_OPTION,
			'type'              => 'number',
			'default'           => (string) GILDA_LOW_STOCK_DEFAULT,
			'css'               => 'width:60px;',
			'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
			'desc_tip'          => false,
			'autoload'          => true,
		);
		// Logo depois do "Limite de estoque baixo" (notificação ao admin), se existir.
		$out = array();
		$done = false;
		foreach ( $settings as $setting ) {
			$out[] = $setting;
			if ( ! $done && isset( $setting['id'] ) && 'woocommerce_notify_no_stock_amount' === $setting['id'] ) {
				$out[] = $field;
				$done  = true;
			}
		}
		if ( ! $done ) {
			array_splice( $out, max( 0, count( $out ) - 1 ), 0, array( $field ) );
		}
		return $out;
	}
);
