<?php
/**
 * Perfil "Dona da loja" (`gilda_dona`): o gerente de loja do WooCommerce, sem tema/editor do site,
 * com um menu só com o que as donas usam (docs/development/painel-conteudo.md §14):
 *
 *   Painel GILDA; · Pedidos (e Clientes) · Peças · Capítulos · Categorias, Cores e Tamanhos · Cupons ·
 *   Dados da loja · Páginas
 *
 * Plugins, temas, configurações técnicas e o editor do site ficam escondidos (sem a permissão, ou com
 * a tela bloqueada para esse perfil). O administrador continua vendo tudo.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

const GILDA_OWNER_ROLE          = 'gilda_dona';
const GILDA_OWNER_ROLE_VERSION  = 1;
const GILDA_PAINEL_PAGE         = 'gilda-painel';

/** Permissões dos capítulos (capability_type gilda_capitulo). */
function gilda_chapter_caps(): array {
	return array(
		'edit_gilda_capitulos',
		'edit_others_gilda_capitulos',
		'edit_published_gilda_capitulos',
		'edit_private_gilda_capitulos',
		'publish_gilda_capitulos',
		'read_private_gilda_capitulos',
		'delete_gilda_capitulos',
		'delete_others_gilda_capitulos',
		'delete_published_gilda_capitulos',
		'delete_private_gilda_capitulos',
	);
}

/**
 * Cria/atualiza o perfil (uma vez por versão). Base: shop_manager do WooCommerce.
 */
function gilda_owner_role_sync(): void {
	if ( (int) get_option( 'gilda_owner_role_version' ) >= GILDA_OWNER_ROLE_VERSION ) {
		return;
	}
	$base = get_role( 'shop_manager' );
	if ( ! $base ) {
		return; // WooCommerce ainda não instalado: tenta de novo na próxima carga
	}
	$caps = $base->capabilities;
	// Sem tema, editor do site, menus, widgets e personalizar.
	unset( $caps['edit_theme_options'], $caps['customize'] );
	foreach ( array_merge( gilda_chapter_caps(), array( 'gilda_painel' ) ) as $cap ) {
		$caps[ $cap ] = true;
	}

	remove_role( GILDA_OWNER_ROLE );
	add_role( GILDA_OWNER_ROLE, 'Dona da loja', $caps );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array_merge( gilda_chapter_caps(), array( 'gilda_painel' ) ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}
	update_option( 'gilda_owner_role_version', GILDA_OWNER_ROLE_VERSION );
}
add_action( 'init', 'gilda_owner_role_sync', 1 );

/*
 * O WooCommerce só deixa o gerente de loja editar contas de clientes (wc_modify_editable_roles e
 * wc_modify_map_meta_cap olham o perfil shop_manager). A dona segue a mesma regra: nunca promove
 * ninguém nem mexe em contas de administradores.
 */
add_filter(
	'user_has_cap',
	static function ( $allcaps, $caps, $args, $user ) {
		if ( $user instanceof WP_User && in_array( GILDA_OWNER_ROLE, (array) $user->roles, true ) ) {
			$allcaps['edit_users'] = true; // limitado a clientes logo abaixo (como wc_shop_manager_has_capability)
		}
		return $allcaps;
	},
	10,
	4
);
add_filter(
	'editable_roles',
	static fn( $roles ) => gilda_is_owner() ? array_intersect_key( $roles, array( 'customer' => true ) ) : $roles
);
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_user', 'remove_user', 'promote_user', 'delete_user' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! gilda_is_owner( $user ) || (int) $args[0] === (int) $user_id ) {
			return $caps;
		}
		$target = get_userdata( (int) $args[0] );
		if ( $target && array_diff( (array) $target->roles, array( 'customer' ) ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	},
	10,
	4
);

/** A pessoa logada é dona da loja (e não administradora)? */
function gilda_is_owner( $user = null ): bool {
	$user = $user ? $user : wp_get_current_user();
	return $user instanceof WP_User && in_array( GILDA_OWNER_ROLE, (array) $user->roles, true ) && ! user_can( $user, 'manage_options' );
}

/** Tela de pedidos (com ou sem a tabela nova do WooCommerce). */
function gilda_orders_menu_slug(): string {
	$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	return $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order';
}

/* ---------- Painel GILDA; (página inicial) ---------- */

add_action(
	'admin_menu',
	static function () {
		$hook = add_menu_page( 'Painel GILDA;', gilda_is_owner() ? 'Início' : 'Painel GILDA;', 'gilda_painel', GILDA_PAINEL_PAGE, 'gilda_painel_screen', 'dashicons-heart', 2 );
		add_action( 'load-' . $hook, 'gilda_painel_enqueue_ui' );
	}
);

// A página em si: home.php (gilda_painel_screen).

/* ---------- Menu enxuto da dona ---------- */

add_action(
	'admin_menu',
	static function () {
		if ( ! gilda_is_owner() ) {
			return;
		}
		global $menu, $submenu;

		// Mesmo com a tabela nova de pedidos, o WordPress agrupa a tela sob este endereço (e o WooCommerce
		// redireciona para admin.php?page=wc-orders).
		$orders  = 'edit.php?post_type=shop_order';
		$catalog = 'edit-tags.php?taxonomy=product_cat&post_type=product';

		$to_ship = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'processing' ) : 0;
		$badge   = $to_ship ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $to_ship ) : '';
		add_menu_page( 'Pedidos', 'Pedidos' . $badge, 'edit_shop_orders', $orders, '', 'dashicons-cart' );
		add_submenu_page( $orders, 'Pedidos', 'Pedidos', 'edit_shop_orders', $orders );
		add_submenu_page( $orders, 'Clientes', 'Clientes', 'view_woocommerce_reports', 'admin.php?page=wc-admin&path=/customers' );

		add_menu_page( 'Categorias e cores', 'Categorias e cores', 'manage_product_terms', $catalog, '', 'dashicons-category' );
		add_submenu_page( $catalog, 'Categorias', 'Categorias', 'manage_product_terms', $catalog );
		foreach ( array( 'pa_cor' => 'Cores', 'pa_tamanho' => 'Tamanhos' ) as $taxonomy => $label ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				add_submenu_page( $catalog, $label, $label, 'manage_product_terms', 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=product' );
			}
		}

		add_menu_page( 'Cupons', 'Cupons', 'edit_shop_coupons', 'edit.php?post_type=shop_coupon', '', 'dashicons-tickets-alt' );

		// Ordem final; o resto some do menu (as telas continuam registradas para links internos do WooCommerce).
		$order = array(
			GILDA_PAINEL_PAGE,
			$orders,
			'edit.php?post_type=product',
			'edit.php?post_type=' . GILDA_CHAPTER_CPT,
			$catalog,
			'edit.php?post_type=shop_coupon',
			'gilda-dados-da-loja',
			'edit.php?post_type=page',
		);
		$by_slug = array();
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) ) {
				$by_slug[ $item[2] ] = $item;
			}
		}
		// Ícones do protótipo (assets/owner.css desenha pela classe).
		$icons = array( 'inicio', 'pedidos', 'pecas', 'capitulos', 'catalogo', 'cupons', 'dados', 'paginas' );
		$new   = array();
		foreach ( $order as $i => $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$item     = $by_slug[ $slug ];
				$item[4]  = trim( ( $item[4] ?? '' ) . ' gilda-ico gilda-ico--' . $icons[ $i ] );
				$new[ ( $i + 1 ) * 5 ] = $item;
			}
		}
		$menu = $new; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		// Peças: só "Todas as peças" e "Nova peça".
		if ( isset( $menu[15] ) ) {
			$menu[15][0] = 'Peças'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
		$submenu['edit.php?post_type=product'] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			array( 'Todas as peças', 'edit_products', 'edit.php?post_type=product', 'Peças' ),
			array( 'Nova peça', 'edit_products', GILDA_PECA_PAGE, 'Nova peça' ),
		);
		$submenu['edit.php?post_type=page'] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			array( 'Todas as páginas', 'edit_pages', 'edit.php?post_type=page', 'Páginas' ),
		);
	},
	9999
);

/** Destaque do menu certo nas telas que o WooCommerce põe em outro lugar. */
add_filter(
	'parent_file',
	static function ( $parent_file ) {
		if ( ! gilda_is_owner() || ! function_exists( 'get_current_screen' ) || ! get_current_screen() ) {
			return $parent_file;
		}
		global $submenu_file;
		$screen = get_current_screen();
		$tax    = $screen->taxonomy ?? '';
		if ( in_array( $tax, array( 'product_cat', 'pa_cor', 'pa_tamanho' ), true ) ) {
			$submenu_file = 'edit-tags.php?taxonomy=' . $tax . '&post_type=product'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'edit-tags.php?taxonomy=product_cat&post_type=product';
		}
		if ( 'shop_coupon' === $screen->post_type ) {
			return 'edit.php?post_type=shop_coupon';
		}
		if ( 'shop_order' === $screen->post_type || in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			$submenu_file = 'edit.php?post_type=shop_order'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'edit.php?post_type=shop_order';
		}
		return $parent_file;
	},
	99
);

/**
 * Telas técnicas que a dona não abre (nem pelo endereço direto). Vai para o Painel GILDA;.
 * Plugins, usuários, configurações do WordPress, temas e editor do site já ficam de fora pela permissão.
 */
add_action(
	'current_screen',
	static function ( $screen ) {
		if ( ! gilda_is_owner() ) {
			return;
		}
		$blocked_ids = array(
			'dashboard',
			'tools',
			'import',
			'export',
			'edit-comments',
			'edit-post',
			'post',
			'woocommerce_page_wc-settings',
			'woocommerce_page_wc-status',
			'woocommerce_page_wc-addons',
			'woocommerce_page_wc-reports',
			'product_page_product_attributes',
		);
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$path    = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$wcadmin = 'wc-admin' === $page && ! str_starts_with( $path, '/customers' );
		if ( in_array( $screen->id, $blocked_ids, true ) || $wcadmin ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . GILDA_PAINEL_PAGE ) );
			exit;
		}
	}
);

/** Depois do login, a dona cai no Painel GILDA;. */
function gilda_owner_login_redirect( $redirect, $request = '', $user = null ) {
	if ( $user instanceof WP_User && gilda_is_owner( $user ) ) {
		return admin_url( 'admin.php?page=' . GILDA_PAINEL_PAGE );
	}
	return $redirect;
}
add_filter( 'login_redirect', 'gilda_owner_login_redirect', 20, 3 );
add_filter( 'woocommerce_login_redirect', static fn( $redirect, $user ) => gilda_owner_login_redirect( $redirect, '', $user ), 20, 2 );

/** Barra de cima sem atalhos técnicos. */
add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		if ( ! gilda_is_owner() ) {
			return;
		}
		foreach ( array( 'wp-logo', 'comments', 'new-content', 'new-post', 'new-media', 'new-user', 'updates', 'customize', 'site-editor', 'edit-site', 'search', 'command-palette', 'woocommerce-site-visibility-badge' ) as $node ) {
			$bar->remove_node( $node );
		}
		$bar->add_node(
			array(
				'parent' => 'site-name',
				'id'     => 'gilda-painel',
				'title'  => 'Painel GILDA;',
				'href'   => admin_url( 'admin.php?page=' . GILDA_PAINEL_PAGE ),
			)
		);
	},
	999
);

/** Sem o "Painel" do WordPress no topo para a dona: o link do site vai ao Painel GILDA;. */
add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		if ( gilda_is_owner() && ! is_admin() ) {
			$bar->remove_node( 'dashboard' );
		}
	},
	1000
);

/** Avisos de atualização e propaganda de extensões não aparecem para a dona. */
add_action(
	'admin_init',
	static function () {
		if ( ! gilda_is_owner() ) {
			return;
		}
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
		add_filter( 'woocommerce_show_admin_notice', '__return_false' );
		add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
	}
);

/*
 * Nas telas do próprio WooCommerce (pedidos com a tabela nova, clientes) o WordPress recalcula o menu
 * "pai" depois do filtro parent_file. Aqui só acende "Pedidos" no menu da dona.
 */
add_action(
	'admin_footer',
	static function () {
		$screen = get_current_screen();
		if ( ! gilda_is_owner() || ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'woocommerce_page_wc-admin' ), true ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var item = document.querySelector( '#adminmenu a[href="edit.php?post_type=shop_order"]' );
			var li = item && item.closest( 'li.menu-top' );
			if ( ! li || document.querySelector( '#adminmenu li.wp-has-current-submenu' ) ) { return; }
			li.classList.remove( 'wp-not-current-submenu' );
			li.classList.add( 'wp-has-current-submenu', 'wp-menu-open' );
			item.classList.add( 'wp-has-current-submenu' );
			var sub = li.querySelector( '.wp-submenu a[href*="' + ( location.search.indexOf( 'customers' ) > -1 ? 'customers' : 'shop_order' ) + '"]' );
			if ( sub ) { sub.parentNode.classList.add( 'current' ); }
		} )();
		</script>
		<?php
	}
);
