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
body.woocommerce-account .gilda-account-side,
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

/* ----- Navegação: saudação + menu com ícones (coluna da esquerda; abas roláveis no celular) ----- */
body.woocommerce-account .gilda-account-side { grid-column: 1; position: sticky; top: calc(var(--gilda-header-h, 72px) + 24px); }
body.woocommerce-account .gilda-account-side__hello { border-bottom: 1px solid var(--gilda-acc-linen); margin: 0 0 14px; padding: 0 0 18px; }
body.woocommerce-account .gilda-account-side__name { color: var(--gilda-acc-ink); font: italic 300 32px/1.05 var(--gilda-acc-display); letter-spacing: -.01em; margin: 0; }
body.woocommerce-account .gilda-account-side__name span { color: var(--gilda-acc-terracotta); font-style: normal; font-weight: 400; margin-left: .03em; }
body.woocommerce-account .gilda-account-side__email { color: var(--gilda-acc-ink-soft); font: 400 13px/1.4 var(--gilda-acc-sans); margin: 8px 0 0; overflow-wrap: anywhere; }
body.woocommerce-account .woocommerce-MyAccount-navigation {
	background: none;
	border: 0;
	float: none !important;
	margin: 0;
	padding: 0;
	width: auto !important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation ul { display: flex; flex-direction: column; gap: 2px; list-style: none; margin: 0; padding: 0; }
body.woocommerce-account .woocommerce-MyAccount-navigation li { margin: 0; padding: 0; }
body.woocommerce-account .woocommerce-MyAccount-navigation li a {
	align-items: center;
	border: 0;
	color: var(--gilda-acc-ink);
	display: flex;
	font: 500 14px/1.2 var(--gilda-acc-sans);
	gap: 14px;
	letter-spacing: .02em;
	min-height: 48px;
	padding: 0 16px;
	text-decoration: none !important;
	text-transform: none;
	transition: background-color .18s ease, color .18s ease;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li a::before {
	background: currentColor;
	content: "";
	flex: 0 0 20px;
	height: 20px;
	-webkit-mask-position: center;
	mask-position: center;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	-webkit-mask-size: contain;
	mask-size: contain;
	opacity: .75;
	transition: transform .18s ease, opacity .18s ease;
	width: 20px;
}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--edit-account a::before { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='8' r='4'/%3E%3Cpath d='M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='8' r='4'/%3E%3Cpath d='M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8'/%3E%3C/svg%3E"); }
body.woocommerce-account .woocommerce-MyAccount-navigation-link--orders a::before { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 8h14l-1 13H6z'/%3E%3Cpath d='M9 8V6a3 3 0 0 1 6 0v2'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 8h14l-1 13H6z'/%3E%3Cpath d='M9 8V6a3 3 0 0 1 6 0v2'/%3E%3C/svg%3E"); }
body.woocommerce-account .woocommerce-MyAccount-navigation-link--edit-address a::before { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z'/%3E%3Ccircle cx='12' cy='9' r='2.5'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z'/%3E%3Ccircle cx='12' cy='9' r='2.5'/%3E%3C/svg%3E"); }
body.woocommerce-account .woocommerce-MyAccount-navigation-link--senha a::before { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='11' width='14' height='10'/%3E%3Cpath d='M8 11V7a4 4 0 0 1 8 0v4'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='11' width='14' height='10'/%3E%3Cpath d='M8 11V7a4 4 0 0 1 8 0v4'/%3E%3C/svg%3E"); }
body.woocommerce-account .woocommerce-MyAccount-navigation-link--customer-logout a::before { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 4H5v16h4'/%3E%3Cpath d='M14 8l4 4-4 4'/%3E%3Cpath d='M18 12H9'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 4H5v16h4'/%3E%3Cpath d='M14 8l4 4-4 4'/%3E%3Cpath d='M18 12H9'/%3E%3C/svg%3E"); }
body.woocommerce-account .woocommerce-MyAccount-navigation li:not(.is-active) a:hover { background: var(--gilda-acc-white); color: var(--gilda-acc-terracotta); }
body.woocommerce-account .woocommerce-MyAccount-navigation li:not(.is-active) a:hover::before { opacity: 1; transform: translateX(2px); }
body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a { background: var(--gilda-acc-terracotta); color: var(--gilda-acc-cream); }
body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a::before { opacity: 1; }
body.woocommerce-account .woocommerce-MyAccount-navigation li a:focus-visible { outline: 2px solid var(--gilda-acc-terracotta); outline-offset: 2px; }
/* "Sair" separado, mais discreto. */
body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout { border-top: 1px solid var(--gilda-acc-linen); margin-top: 12px; padding-top: 12px; }
body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a { color: var(--gilda-acc-ink-soft); }
body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a:hover { background: none; color: var(--gilda-acc-terracotta); }
@media (max-width: 782px) {
	body.woocommerce-account .gilda-account-side { min-width: 0; position: static; }
	body.woocommerce-account .gilda-account-side__hello { align-items: baseline; border: 0; display: flex; flex-wrap: wrap; gap: 4px 12px; margin: 0 0 12px; padding: 0; }
	body.woocommerce-account .gilda-account-side__name { font-size: 26px; }
	body.woocommerce-account .gilda-account-side__email { display: none; }
	/* Abas numa linha só, roláveis de lado; a borda de baixo marca a linha. */
	body.woocommerce-account .woocommerce-MyAccount-navigation { border-bottom: 1px solid var(--gilda-acc-linen); margin: 0 -16px; overflow-x: auto; padding: 0 16px; scrollbar-width: none; }
	body.woocommerce-account .woocommerce-MyAccount-navigation::-webkit-scrollbar { display: none; }
	/* Degradê na borda direita: sinal de que há mais abas para o lado. */
	body.woocommerce-account .woocommerce-MyAccount-navigation { -webkit-mask-image: linear-gradient(to right, #000 calc(100% - 40px), transparent); mask-image: linear-gradient(to right, #000 calc(100% - 40px), transparent); }
	body.woocommerce-account .woocommerce-MyAccount-navigation.is-scrolled-end { -webkit-mask-image: none; mask-image: none; }
	body.woocommerce-account .woocommerce-MyAccount-navigation ul { flex-direction: row; gap: 4px; width: max-content; }
	body.woocommerce-account .woocommerce-MyAccount-navigation li a { font-size: 13px; gap: 8px; min-height: 44px; padding: 0 14px; white-space: nowrap; }
	body.woocommerce-account .woocommerce-MyAccount-navigation li a::before { flex-basis: 17px; height: 17px; width: 17px; }
	body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a { background: none; box-shadow: inset 0 -2px 0 var(--gilda-acc-terracotta); color: var(--gilda-acc-terracotta); }
	body.woocommerce-account .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout { border: 0; margin: 0; padding: 0; }
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

/* Botão "Salvar alterações": animação no hover */
body.woocommerce-account .woocommerce-MyAccount-content button[type="submit"],
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-Button,
body.woocommerce-account .woocommerce-MyAccount-content button.button {
	transition: background-color .28s ease, transform .22s ease, box-shadow .28s ease, letter-spacing .28s ease;
	will-change: transform;
}
body.woocommerce-account .woocommerce-MyAccount-content button[type="submit"]:hover,
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-Button:hover,
body.woocommerce-account .woocommerce-MyAccount-content button.button:hover {
	box-shadow: 0 10px 22px -10px color-mix(in srgb, var(--gilda-acc-terracotta) 75%, transparent);
	letter-spacing: 1.8px;
	transform: translateY(-2px);
}
body.woocommerce-account .woocommerce-MyAccount-content button[type="submit"]:active,
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-Button:active,
body.woocommerce-account .woocommerce-MyAccount-content button.button:active {
	transform: translateY(0);
}

/* ----- Página de Endereços ----- */
body.woocommerce-account .woocommerce-Addresses {
	align-items: start;
	direction: ltr;
	display: grid;
	gap: clamp(20px, 2.5vw, 28px);
	grid-template-columns: repeat(2, minmax(0, 1fr));
	margin-top: 24px;
}
body.woocommerce-account .woocommerce-Addresses .col-1 { grid-column: 1; }
body.woocommerce-account .woocommerce-Addresses .col-2 { grid-column: 2; }
body.woocommerce-account .woocommerce-Address {
	background: var(--gilda-acc-cream) !important;
	border: 1px solid var(--gilda-acc-linen);
	float: none !important;
	margin: 0 !important;
	padding: 26px 28px !important;
	width: auto !important;
}
body.woocommerce-account .woocommerce-Address-title {
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	gap: 10px 16px;
	justify-content: space-between;
	margin: 0 0 16px;
}
body.woocommerce-account .woocommerce-Address-title h2 {
	font-family: var(--gilda-acc-display);
	font-size: 22px;
	font-weight: 400;
	margin: 0;
}
body.woocommerce-account .woocommerce-Address-title .edit {
	border: 1px solid var(--gilda-acc-terracotta);
	color: var(--gilda-acc-terracotta);
	font: 500 10px/1 var(--gilda-acc-sans);
	letter-spacing: 1.2px;
	padding: 9px 14px;
	text-decoration: none;
	text-transform: uppercase;
	transition: background-color .2s ease, color .2s ease;
	white-space: nowrap;
}
body.woocommerce-account .woocommerce-Address-title .edit:hover {
	background: var(--gilda-acc-terracotta);
	color: var(--gilda-acc-cream);
}
body.woocommerce-account .woocommerce-Address address {
	color: var(--gilda-acc-ink);
	font: 400 16px/1.7 var(--gilda-acc-serif);
	font-style: normal;
}
/* Esconde o País (Brasil é padrão) e o checkbox de CEP nos formulários de endereço. */
body.woocommerce-account #billing_country_field,
body.woocommerce-account #shipping_country_field,
body.woocommerce-account #wc_better_calc_checkbox_billing_field,
body.woocommerce-account #wc_better_calc_checkbox_shipping_field {
	display: none !important;
}
@media (max-width: 782px) {
	body.woocommerce-account .woocommerce-Addresses { grid-template-columns: minmax(0, 1fr); }
	body.woocommerce-account .woocommerce-Addresses .col-1,
	body.woocommerce-account .woocommerce-Addresses .col-2 { grid-column: 1; }
}

/* ----- Mobile: empilha menu acima do conteúdo ----- */
@media (max-width: 782px) {
	body.woocommerce-account .woocommerce { grid-template-columns: minmax(0, 1fr); gap: 24px; }
	body.woocommerce-account .gilda-account-side,
	body.woocommerce-account .woocommerce-MyAccount-navigation,
	body.woocommerce-account .woocommerce-MyAccount-content { grid-column: 1; }
}
</style>
	<?php
}
add_action( 'wp_head', 'gilda_account_dashboard_styles', 99 );

/*
 * Coluna da esquerda: "olá, {nome};" e o e-mail em cima do menu. O template de navegação do WooCommerce
 * continua o mesmo; os ganchos antes/depois dele abrem e fecham o <aside> que vira a coluna do grid.
 */
add_action(
	'woocommerce_before_account_navigation',
	static function () {
		$user = wp_get_current_user();
		$name = $user->first_name ? $user->first_name : $user->display_name;
		echo '<aside class="gilda-account-side">';
		if ( $user->exists() ) {
			echo '<div class="gilda-account-side__hello">';
			/* translators: %s: primeiro nome da cliente */
			echo '<p class="gilda-account-side__name">' . esc_html( sprintf( __( 'olá, %s', 'gilda' ), $name ) ) . '<span>;</span></p>';
			echo '<p class="gilda-account-side__email">' . esc_html( $user->user_email ) . '</p>';
			echo '</div>';
		}
	},
	1
);
add_action(
	'woocommerce_after_account_navigation',
	static function () {
		echo '</aside>';
		// No celular as abas rolam de lado: a ativa entra na tela, e o degradê some quando chega ao fim.
		?>
<script>
( function () {
	var nav = document.querySelector( '.woocommerce-MyAccount-navigation' );
	if ( ! nav ) { return; }
	var sync = function () { nav.classList.toggle( 'is-scrolled-end', nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 4 ); };
	var active = nav.querySelector( 'li.is-active' );
	if ( active && nav.scrollWidth > nav.clientWidth ) {
		nav.scrollLeft = active.offsetLeft - ( nav.clientWidth - active.offsetWidth ) / 2;
	}
	sync();
	nav.addEventListener( 'scroll', sync, { passive: true } );
	window.addEventListener( 'resize', sync );
} )();
</script>
		<?php
	},
	99
);

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
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
		return;
	}

	// Raiz da conta -> Detalhes da conta.
	if ( function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url() && function_exists( 'wc_get_account_endpoint_url' ) ) {
		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-account' ) );
		exit;
	}

	// Enderecos (visao geral) -> formulario de Endereco de entrega.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) && function_exists( 'wc_get_endpoint_url' ) ) {
		global $wp;
		$sub = isset( $wp->query_vars['edit-address'] ) ? $wp->query_vars['edit-address'] : '';
		if ( '' === $sub ) {
			wp_safe_redirect( wc_get_endpoint_url( 'edit-address', 'shipping', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
	}
}
add_action( 'template_redirect', 'gilda_account_default_endpoint' );

/**
 * Remove Nome e Sobrenome dos formularios de endereco (apenas em Minha conta;
 * o checkout nao e afetado). Sem nome/sobrenome o endereco continua salvando.
 */
function gilda_account_address_remove_name( $fields ) {
    if ( is_admin() ) {
        return $fields;
    }
    if ( function_exists( 'is_account_page' ) && is_account_page()
        && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) ) {
        unset(
            $fields['first_name'], $fields['last_name'],
            $fields['shipping_first_name'], $fields['shipping_last_name'],
            $fields['billing_first_name'], $fields['billing_last_name']
        );
    }
    return $fields;
}
add_filter( 'woocommerce_shipping_fields', 'gilda_account_address_remove_name' );
add_filter( 'woocommerce_billing_fields', 'gilda_account_address_remove_name' );
add_filter( 'woocommerce_default_address_fields', 'gilda_account_address_remove_name' );

/**
 * Consulta o CEP (ViaCEP) e preenche rua, bairro, cidade e estado nos
 * formularios de endereco de Minha conta. A consulta roda no navegador.
 */
function gilda_account_cep_autofill() {
    if ( is_admin() ) {
        return;
    }
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page()
        || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
        return;
    }
    ?>
<script>
(function () {
    function digits(s) { return (s || '').replace(/[^0-9]/g, ''); }
    // force: o CEP mudou, então rua/bairro/cidade antigos são de outro endereço e são trocados.
    function fill(id, val, force) {
        var el = document.getElementById(id);
        if (el && val && (force || !el.value)) {
            el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    function setState(prefix, uf) {
        var el = document.getElementById(prefix + 'state');
        if (!el || !uf) { return; }
        uf = uf.toUpperCase();
        if (window.jQuery) { window.jQuery(el).val(uf).trigger('change'); }
        else { el.value = uf; el.dispatchEvent(new Event('change', { bubbles: true })); }
    }
    function lookup(prefix) {
        var pc = document.getElementById(prefix + 'postcode');
        if (!pc) { return; }
        var cep = digits(pc.value);
        if (cep.length !== 8 || cep === pc.dataset.gildaCepDone) { return; }
        pc.dataset.gildaCepDone = cep;
        var force = cep !== pc.dataset.gildaCepFields;
        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.erro) { return; }
                if (digits(pc.value) !== cep) { return; } // digitou outro CEP enquanto a resposta vinha
                fill(prefix + 'address_1', d.logradouro, force);
                fill(prefix + 'neighborhood', d.bairro, force);
                fill(prefix + 'city', d.localidade, force);
                setState(prefix, d.uf);
                pc.dataset.gildaCepFields = cep;
                var num = document.getElementById(prefix + 'number');
                if (num && (force || !num.value)) { num.focus(); num.select(); }
            })
            .catch(function () {});
    }
    function bind() {
        ['shipping_', 'billing_'].forEach(function (prefix) {
            var pc = document.getElementById(prefix + 'postcode');
            if (pc && !pc.dataset.gildaCep) {
                pc.dataset.gildaCep = '1';
                pc.dataset.gildaCepFields = digits(pc.value); // CEP a que rua/bairro/cidade pertencem (o salvo, no início)
                pc.addEventListener('blur', function () { lookup(prefix); });
                pc.addEventListener('input', function () { if (digits(pc.value).length === 8) { lookup(prefix); } });
            }
        });
    }
    if (document.readyState !== 'loading') { bind(); }
    else { document.addEventListener('DOMContentLoaded', bind); }
})();
</script>
    <?php
}
add_action( 'wp_footer', 'gilda_account_cep_autofill', 99 );
