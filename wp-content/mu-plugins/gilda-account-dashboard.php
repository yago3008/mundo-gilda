<?php
/**
 * Plugin Name: GILDA; — Painel do cliente (Minha conta)
 * Description: Dá a identidade GILDA; à área logada de "Minha conta" do WooCommerce
 *              (navegação, painel, pedidos, downloads, endereços, detalhes da conta).
 *              Estilo impresso só no front-end, na página da conta e com usuário logado,
 *              em arquivo próprio para sobreviver às reescritas do theme.css.
 *
 * Author: GILDA;
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imprime o CSS da área logada de Minha conta.
 * Só na página da conta, só logado — a tela de login/registro tem estilo próprio.
 */
function gilda_account_dashboard_styles() {
	if ( is_admin() ) {
		return;
	}
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
		return;
	}
	?>
<style id="gilda-account-dashboard">
/* ===== GILDA; — Minha conta (área logada) ===== */
body.woocommerce-account .woocommerce-MyAccount-content,
body.woocommerce-account .woocommerce-MyAccount-navigation {
	--gilda-acc-terracotta: var(--gilda-terracotta, var(--wp--preset--color--terracotta, #77270c));
	--gilda-acc-terracotta-900: var(--gilda-terracotta-900, var(--wp--preset--color--terracotta-900, #4e1a08));
	--gilda-acc-cream: var(--gilda-cream, var(--wp--preset--color--cream, #f7f5e9));
	--gilda-acc-linen: var(--gilda-linen, var(--wp--preset--color--linen, #e3dfd4));
	--gilda-acc-ink: var(--gilda-ink, var(--wp--preset--color--ink, #241a16));
	--gilda-acc-ink-soft: var(--gilda-ink-soft, var(--wp--preset--color--ink-soft, #6b5d54));
	--gilda-acc-white: var(--gilda-white, #fffdf7);
	--gilda-acc-sans: var(--gilda-sans, var(--wp--preset--font-family--sans, sans-serif));
	--gilda-acc-serif: var(--wp--preset--font-family--serif, Georgia, serif);
	--gilda-acc-display: var(--wp--preset--font-family--display, Georgia, serif);
}

/* Duas colunas: menu à esquerda, conteúdo à direita */
body.woocommerce-account .woocommerce {
	align-items: start;
	direction: ltr;
	display: grid;
	gap: clamp(28px, 4vw, 60px);
	grid-template-columns: 250px minmax(0, 1fr);
}
body.woocommerce-account .woocommerce-notices-wrapper { grid-column: 1 / -1; }

/* ----- Navegação (menu lateral) ----- */
body.woocommerce-account .woocommerce-MyAccount-navigation {
	background: var(--gilda-acc-white);
	border: 1px solid var(--gilda-acc-linen);
	float: none !important;
	grid-column: 1;
	margin: 0;
	padding: 8px;
	width: auto !important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li { margin: 0; padding: 0; }
body.woocommerce-account .woocommerce-MyAccount-navigation li a {
	border-left: 2px solid transparent;
	color: var(--gilda-acc-ink);
	display: block;
	font: 500 12px/1.3 var(--gilda-acc-sans);
	letter-spacing: 1.4px;
	padding: 14px 18px;
	text-decoration: none;
	text-transform: uppercase;
	transition: color .18s ease, background .18s ease, border-color .18s ease, padding .18s ease;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li + li a {
	border-top: 1px solid color-mix(in srgb, var(--gilda-acc-linen) 70%, transparent);
}
body.woocommerce-account .woocommerce-MyAccount-navigation li a:hover {
	background: var(--gilda-acc-cream);
	color: var(--gilda-acc-terracotta);
	padding-left: 22px;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a {
	background: var(--gilda-acc-cream);
	border-left-color: var(--gilda-acc-terracotta);
	color: var(--gilda-acc-terracotta);
	font-weight: 600;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a {
	color: var(--gilda-acc-ink-soft);
}
body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a:hover {
	color: var(--gilda-acc-terracotta);
}

/* ----- Conteúdo ----- */
body.woocommerce-account .woocommerce-MyAccount-content {
	color: var(--gilda-acc-ink);
	float: none !important;
	grid-column: 2;
	margin: 0;
	width: auto !important;
}
body.woocommerce-account .woocommerce-MyAccount-content p {
	color: var(--gilda-acc-ink);
	font: 400 17px/1.75 var(--gilda-acc-serif);
	margin: 0 0 18px;
}
body.woocommerce-account .woocommerce-MyAccount-content a:not(.button) {
	color: var(--gilda-acc-terracotta);
	text-decoration: underline;
	text-underline-offset: 3px;
}
body.woocommerce-account .woocommerce-MyAccount-content a:not(.button):hover {
	color: var(--gilda-acc-terracotta-900);
}
body.woocommerce-account .woocommerce-MyAccount-content h2,
body.woocommerce-account .woocommerce-MyAccount-content h3 {
	color: var(--gilda-acc-ink);
	font-family: var(--gilda-acc-display);
	font-weight: 400;
	letter-spacing: .2px;
	margin: 0 0 18px;
}
body.woocommerce-account .woocommerce-MyAccount-content h2 { font-size: clamp(22px, 3vw, 28px); }
body.woocommerce-account .woocommerce-MyAccount-content h3 { font-size: 19px; }

/* Tabelas (pedidos, downloads) */
body.woocommerce-account .woocommerce-MyAccount-content table.shop_table,
body.woocommerce-account .woocommerce-MyAccount-content table.woocommerce-orders-table,
body.woocommerce-account .woocommerce-MyAccount-content table.woocommerce-MyAccount-downloads {
	border: 1px solid var(--gilda-acc-linen);
	border-collapse: collapse;
	width: 100%;
}
body.woocommerce-account .woocommerce-MyAccount-content table th {
	background: var(--gilda-acc-cream);
	border-bottom: 1px solid var(--gilda-acc-linen);
	color: var(--gilda-acc-ink);
	font: 500 11px/1.3 var(--gilda-acc-sans);
	letter-spacing: 1.2px;
	padding: 14px 16px;
	text-align: left;
	text-transform: uppercase;
}
body.woocommerce-account .woocommerce-MyAccount-content table td {
	border-bottom: 1px solid color-mix(in srgb, var(--gilda-acc-linen) 70%, transparent);
	color: var(--gilda-acc-ink);
	font: 400 15px/1.5 var(--gilda-acc-serif);
	padding: 16px;
	vertical-align: middle;
}
body.woocommerce-account .woocommerce-MyAccount-content table tr:last-child td { border-bottom: 0; }

/* Painel vazio / mensagens informativas */
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-Message,
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-info {
	background: var(--gilda-acc-cream);
	border-top: 2px solid var(--gilda-acc-terracotta);
	color: var(--gilda-acc-ink);
	font-family: var(--gilda-acc-serif);
}

/* Formulários (endereços, detalhes da conta) */
body.woocommerce-account .woocommerce-MyAccount-content form label {
	color: var(--gilda-acc-ink);
	display: block;
	font: 500 12px/1.3 var(--gilda-acc-sans);
	letter-spacing: .6px;
	margin-bottom: 8px;
}
body.woocommerce-account .woocommerce-MyAccount-content form .woocommerce-input-wrapper input.input-text,
body.woocommerce-account .woocommerce-MyAccount-content form input.input-text,
body.woocommerce-account .woocommerce-MyAccount-content form textarea,
body.woocommerce-account .woocommerce-MyAccount-content form select,
body.woocommerce-account .woocommerce-MyAccount-content form .select2-selection {
	background: var(--gilda-acc-cream);
	border: 1px solid var(--gilda-acc-linen);
	box-sizing: border-box;
	color: var(--gilda-acc-ink);
	font: 400 15px/1.4 var(--gilda-acc-sans);
	min-height: 48px;
	padding: 0 14px;
	width: 100%;
}
body.woocommerce-account .woocommerce-MyAccount-content form textarea { padding: 12px 14px; }
body.woocommerce-account .woocommerce-MyAccount-content form input.input-text:focus,
body.woocommerce-account .woocommerce-MyAccount-content form textarea:focus,
body.woocommerce-account .woocommerce-MyAccount-content form select:focus {
	border-color: var(--gilda-acc-terracotta);
	outline: 2px solid color-mix(in srgb, var(--gilda-acc-terracotta) 30%, transparent);
	outline-offset: 1px;
}
body.woocommerce-account .woocommerce-MyAccount-content fieldset {
	border: 1px solid var(--gilda-acc-linen);
	margin: 28px 0 0;
	padding: 20px 22px 4px;
}
body.woocommerce-account .woocommerce-MyAccount-content fieldset legend {
	color: var(--gilda-acc-ink);
	font-family: var(--gilda-acc-display);
	font-size: 18px;
	padding: 0 8px;
}
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-form-row,
body.woocommerce-account .woocommerce-MyAccount-content .form-row { margin: 0 0 18px; }

/* ----- Mobile: empilha menu acima do conteúdo ----- */
@media (max-width: 782px) {
	body.woocommerce-account .woocommerce { grid-template-columns: minmax(0, 1fr); gap: 24px; }
	body.woocommerce-account .woocommerce-MyAccount-navigation,
	body.woocommerce-account .woocommerce-MyAccount-content { grid-column: 1; }
}
</style>
	<?php
}
add_action( 'wp_head', 'gilda_account_dashboard_styles', 99 );

/**
 * Menu da conta: remove "Painel" e "Downloads".
 *
 * @param array $items Itens do menu de Minha conta.
 * @return array
 */
function gilda_account_menu_items( $items ) {
	unset( $items['dashboard'], $items['downloads'] );

	// "Detalhes da conta" em primeiro; depois pedidos, endereços e sair.
	$order   = array( 'edit-account', 'orders', 'edit-address', 'customer-logout' );
	$ordered = array();
	foreach ( $order as $key ) {
		if ( isset( $items[ $key ] ) ) {
			$ordered[ $key ] = $items[ $key ];
		}
	}
	// Preserva quaisquer outros itens (de plugins) no fim.
	foreach ( $items as $key => $label ) {
		if ( ! isset( $ordered[ $key ] ) ) {
			$ordered[ $key ] = $label;
		}
	}
	return $ordered;
}
add_filter( 'woocommerce_account_menu_items', 'gilda_account_menu_items' );

/**
 * Página inicial da conta: abre em "Detalhes da conta" no lugar do painel.
 * A raiz /minha-conta/ (sem endpoint) redireciona para o endpoint edit-account.
 */
function gilda_account_default_endpoint() {
	if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
		return;
	}
	if ( function_exists( 'is_account_page' ) && is_account_page()
		&& function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url()
		&& is_user_logged_in()
		&& function_exists( 'wc_get_account_endpoint_url' ) ) {
		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-account' ) );
		exit;
	}
}
add_action( 'template_redirect', 'gilda_account_default_endpoint' );
