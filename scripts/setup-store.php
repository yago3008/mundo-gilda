<?php
/**
 * Configuração da loja GILDA; (WooCommerce). Idempotente: pode rodar quantas vezes quiser.
 * Uso: docker compose run --rm wpcli wp eval-file /scripts/setup-store.php
 *
 * Endereço de origem da loja (usado no cálculo de frete) vem das variáveis
 * STORE_ADDRESS, STORE_CITY, STORE_STATE e STORE_CEP do .env.
 */

defined( 'ABSPATH' ) || exit;

$env = static fn( $k, $default = '' ) => getenv( $k ) !== false && getenv( $k ) !== '' ? getenv( $k ) : $default;
$log = static fn( $msg ) => WP_CLI::log( "  • $msg" );

WP_CLI::log( 'Loja:' );

/* ---------------------------------------------------------------------------
 * Geral: Brasil, Real, formato de preço brasileiro
 * ------------------------------------------------------------------------ */
$state = strtoupper( $env( 'STORE_STATE', 'SP' ) );
$options = array(
	'woocommerce_store_address'           => $env( 'STORE_ADDRESS', 'Endereço a definir' ),
	'woocommerce_store_city'              => $env( 'STORE_CITY', 'São Paulo' ),
	'woocommerce_default_country'         => "BR:$state",
	'woocommerce_store_postcode'          => $env( 'STORE_CEP', '01001-000' ),
	'woocommerce_allowed_countries'       => 'specific',
	'woocommerce_specific_allowed_countries' => array( 'BR' ),
	'woocommerce_ship_to_countries'       => 'specific',
	'woocommerce_specific_ship_to_countries' => array( 'BR' ),
	'woocommerce_default_customer_address' => 'base',
	'woocommerce_currency'                => 'BRL',
	'woocommerce_currency_pos'            => 'left_space',
	'woocommerce_price_thousand_sep'      => '.',
	'woocommerce_price_decimal_sep'       => ',',
	'woocommerce_price_num_decimals'      => 2,
	'woocommerce_weight_unit'             => 'kg',
	'woocommerce_dimension_unit'          => 'cm',
	'woocommerce_calc_taxes'              => 'no',
	'woocommerce_enable_coupons'          => 'yes',
	'woocommerce_enable_reviews'          => 'no', // o site atual não tem avaliações
	'woocommerce_calc_discounts_sequentially' => 'no',

	// Estoque
	'woocommerce_manage_stock'            => 'yes',
	'woocommerce_hold_stock_minutes'      => 60,
	'woocommerce_notify_low_stock_amount' => 1,
	'woocommerce_notify_no_stock_amount'  => 0,
	'woocommerce_hide_out_of_stock_items' => 'no', // mantém "Esgotado" visível, como no site atual
	'woocommerce_stock_format'            => 'no_amount',

	// Frete
	'woocommerce_enable_shipping_calc'    => 'yes',
	'woocommerce_shipping_cost_requires_address' => 'no',
	'woocommerce_ship_to_destination'     => 'billing',

	// Contas de cliente
	'woocommerce_enable_guest_checkout'               => 'yes',
	'woocommerce_enable_checkout_login_reminder'      => 'yes',
	'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
	'woocommerce_enable_myaccount_registration'       => 'yes',
	'woocommerce_registration_generate_username'      => 'yes',
	'woocommerce_registration_generate_password'      => 'no', // cliente escolhe a própria senha
	'woocommerce_erasure_request_removes_order_data'  => 'no',
	'woocommerce_allow_bulk_remove_personal_data'     => 'no',

	// E-mails
	'woocommerce_email_from_name'         => 'GILDA;',
	'woocommerce_email_from_address'      => $env( 'MAIL_FROM', get_option( 'admin_email' ) ),

	// Sem assistente de configuração / marketplace / rastreamento
	'woocommerce_onboarding_profile'      => array( 'skipped' => true ),
	'woocommerce_task_list_hidden'        => 'yes',
	'woocommerce_task_list_complete'      => 'yes',
	'woocommerce_show_marketplace_suggestions' => 'no',
	'woocommerce_allow_tracking'          => 'no',
	'woocommerce_analytics_enabled'       => 'yes',
	'woocommerce_coming_soon'             => 'no',
);
foreach ( $options as $key => $value ) {
	update_option( $key, $value );
}
$log( 'Brasil / R$ / kg-cm / sem impostos / estoque ativo' );
$log( 'Contas: cadastro em Minha Conta e no checkout, compra como visitante liberada' );

/* ---------------------------------------------------------------------------
 * Campos brasileiros no checkout (plugin "Campos Checkout Brasileiro")
 * ------------------------------------------------------------------------ */
$br = array(
	'woo_better_calc_person_type_select'        => 'physical', // CPF (necessário p/ nota fiscal e etiqueta de envio)
	'woo_better_calc_apply_cpf_mask'            => 'yes',
	'woo_better_calc_number_required'           => 'yes',
	'woo_better_calc_enable_neighborhood_field' => 'yes',
	'woo_better_calc_enable_auto_address_fill'  => 'yes', // preenche rua/bairro/cidade pelo CEP
	'woo_better_calc_cep_field_position'        => 'yes', // CEP primeiro
	'woo_better_calc_contact_required'          => 'yes', // telefone obrigatório
	'woo_better_calc_apply_phone_mask'          => 'yes',
	'woo_better_calc_validate_ddd'              => 'yes',
	'woo_better_calc_enable_order_details'      => 'yes',
);
foreach ( $br as $key => $value ) {
	update_option( $key, $value );
}
$log( 'Checkout BR: CPF, número, bairro, telefone e autopreenchimento por CEP' );

/* ---------------------------------------------------------------------------
 * Páginas do WooCommerce (Loja, Carrinho, Finalizar compra, Minha conta)
 * ------------------------------------------------------------------------ */
WC_Install::create_pages();
$pt_pages = array(
	'woocommerce_shop_page_id'      => array( 'Loja', 'loja' ),
	'woocommerce_cart_page_id'      => array( 'Carrinho', 'carrinho' ),
	'woocommerce_checkout_page_id'  => array( 'Finalizar compra', 'finalizar-compra' ),
	'woocommerce_myaccount_page_id' => array( 'Minha conta', 'minha-conta' ),
	'woocommerce_refund_returns_page_id' => array( 'Trocas e devoluções', 'trocas-e-devolucoes' ),
	'wp_page_for_privacy_policy'    => array( 'Política de privacidade', 'politica-de-privacidade' ),
);
foreach ( $pt_pages as $opt => [ $title, $slug ] ) {
	$id = (int) get_option( $opt );
	if ( $id && get_post( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title, 'post_name' => $slug ) );
	}
}
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) {
	wp_delete_post( $sample->ID, true );
}

// URLs em português: /produto/<slug>, /categoria/<slug> e endpoints de "Minha conta".
update_option( 'woocommerce_permalinks', array_merge( (array) get_option( 'woocommerce_permalinks', array() ), array( 'product_base' => '/produto', 'category_base' => 'categoria', 'tag_base' => 'tag-produto', 'attribute_base' => '', 'use_verbose_page_rules' => false ) ) );
$endpoints = array(
	'woocommerce_myaccount_orders_endpoint'          => 'pedidos',
	'woocommerce_myaccount_view_order_endpoint'      => 'ver-pedido',
	'woocommerce_myaccount_downloads_endpoint'       => 'downloads',
	'woocommerce_myaccount_edit_account_endpoint'    => 'editar-conta',
	'woocommerce_myaccount_edit_address_endpoint'    => 'enderecos',
	'woocommerce_myaccount_payment_methods_endpoint' => 'formas-de-pagamento',
	'woocommerce_myaccount_lost_password_endpoint'   => 'recuperar-senha',
	'woocommerce_logout_endpoint'                    => 'sair',
	'woocommerce_checkout_order_received_endpoint'   => 'pedido-recebido',
	'woocommerce_checkout_pay_endpoint'              => 'pagar-pedido',
);
foreach ( $endpoints as $key => $value ) {
	update_option( $key, $value );
}
update_option( 'show_on_front', 'page' );
$front = get_page_by_path( 'inicio' );
if ( ! $front ) {
	$front_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Início', 'post_name' => 'inicio' ) );
} else {
	$front_id = $front->ID;
}
update_option( 'page_on_front', $front_id );
$log( 'Páginas: Início, Loja, Carrinho, Finalizar compra, Minha conta' );

/* ---------------------------------------------------------------------------
 * Frete: zona Brasil com tarifa provisória (troca pelo Melhor Envio/Correios)
 * ------------------------------------------------------------------------ */
$zone = null;
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	if ( 'Brasil' === $z['zone_name'] ) {
		$zone = new WC_Shipping_Zone( $z['id'] );
	}
}
if ( ! $zone ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'Brasil' );
	$zone->add_location( 'BR', 'country' );
	$zone->save();
}
$has_flat = false;
foreach ( $zone->get_shipping_methods() as $method ) {
	if ( 'flat_rate' === $method->id ) {
		$has_flat = true;
	}
}
if ( ! $has_flat ) {
	$instance_id = $zone->add_shipping_method( 'flat_rate' );
	update_option(
		"woocommerce_flat_rate_{$instance_id}_settings",
		array(
			'title'      => 'Frete fixo (provisório)',
			'tax_status' => 'none',
			'cost'       => '25,00',
		)
	);
}
$log( 'Frete: zona Brasil com frete fixo provisório de R$ 25 (até integrar a transportadora)' );

/* ---------------------------------------------------------------------------
 * Pagamento: só o de teste no ambiente local (o gateway real entra depois)
 * ------------------------------------------------------------------------ */
if ( 'production' !== wp_get_environment_type() ) {
	update_option(
		'woocommerce_cheque_settings',
		array(
			'enabled'      => 'yes',
			'title'        => 'Pagamento de teste (ambiente local)',
			'description'  => 'Simula um pagamento. Não existe no site de produção.',
			'instructions' => '',
		)
	);
	$log( 'Pagamento: gateway de teste ativo (só no ambiente local)' );
}
foreach ( array( 'bacs', 'cod' ) as $gw ) {
	$s            = get_option( "woocommerce_{$gw}_settings", array() );
	$s['enabled'] = 'no';
	update_option( "woocommerce_{$gw}_settings", $s );
}

/* ---------------------------------------------------------------------------
 * Cupom de primeira compra (regra "só 1ª compra" no mu-plugin gilda-first-purchase)
 * ------------------------------------------------------------------------ */
$code      = 'primeiracompra';
$coupon_id = wc_get_coupon_id_by_code( $code );
$coupon    = new WC_Coupon( $coupon_id ?: 0 );
$coupon->set_code( $code );
$coupon->set_description( '10% off na primeira compra pelo site' );
$coupon->set_discount_type( 'percent' );
$coupon->set_amount( 10 );
$coupon->set_individual_use( true );
$coupon->set_usage_limit_per_user( 1 );
$coupon->update_meta_data( '_gilda_first_purchase_only', 'yes' );
$coupon->save();
$log( 'Cupom: primeiracompra (10%, 1 uso por cliente, só na primeira compra)' );

/* ---------------------------------------------------------------------------
 * Atributos globais de produto: Cor e Tamanho
 * ------------------------------------------------------------------------ */
foreach ( array( 'cor' => 'Cor', 'tamanho' => 'Tamanho' ) as $slug => $label ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		wc_create_attribute( array( 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
	}
}
delete_transient( 'wc_attribute_taxonomies' );
$log( 'Atributos: Cor e Tamanho' );

flush_rewrite_rules();
WP_CLI::success( 'Loja configurada.' );
