<?php
/**
 * Cupons no painel:
 * - caixinha "Só na primeira compra" na tela do cupom (meta _gilda_first_purchase_only, regra em
 *   wp-content/mu-plugins/gilda-first-purchase-coupon.php);
 * - "Promoção em destaque" (Cupons → Promoção em destaque): a dona escolhe qual cupom anunciar (ou
 *   nenhum). A faixa de avisos e a fita do banner da home leem o código e o desconto desse cupom, ao
 *   vivo (gilda_promo()). Sem cupom escolhido, ou cupom vencido/esgotado/fora do ar, não anunciam nada.
 *   Semente: o primeiracompra, uma vez só.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

const GILDA_PROMO_OPTION = 'gilda_promo_coupon';
const GILDA_PROMO_PAGE   = 'gilda-promocao';

/**
 * Cupom anunciado agora, ou null.
 *
 * @return array{id:int,code:string,label:string,first:bool}|null label: "10%" ou "R$ 20"
 */
function gilda_promo(): ?array {
	$id = absint( get_option( GILDA_PROMO_OPTION, 0 ) );
	if ( ! $id || 'shop_coupon' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ! class_exists( 'WC_Coupon' ) ) {
		return null;
	}
	$coupon  = new WC_Coupon( $id );
	$expires = $coupon->get_date_expires();
	if ( $expires && $expires->getTimestamp() < time() ) {
		return null;
	}
	if ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
		return null;
	}
	$amount = (float) $coupon->get_amount();
	if ( $amount <= 0 ) {
		return null;
	}
	$number = rtrim( rtrim( number_format( $amount, 2, ',', '.' ), '0' ), ',' );
	return array(
		'id'    => $id,
		'code'  => $coupon->get_code(),
		'label' => 'percent' === $coupon->get_discount_type() ? $number . '%' : 'R$ ' . $number,
		'first' => 'yes' === $coupon->get_meta( '_gilda_first_purchase_only' ),
	);
}

/** Semente: anuncia o primeiracompra (uma vez; "nenhum" escolhido pelas donas fica). */
add_action(
	'init',
	static function () {
		if ( null !== get_option( GILDA_PROMO_OPTION, null ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return;
		}
		$id = (int) wc_get_coupon_id_by_code( 'primeiracompra' );
		if ( $id ) {
			add_option( GILDA_PROMO_OPTION, $id, '', false );
		}
	},
	50
);

// Faixa e banner aparecem nas páginas guardadas no cache do catálogo: mudou o cupom ou a escolha, limpa.
$gilda_promo_flush = static function () {
	if ( function_exists( 'gilda_catalog_cache_flush' ) ) {
		gilda_catalog_cache_flush();
	}
};
add_action( 'update_option_' . GILDA_PROMO_OPTION, $gilda_promo_flush );
add_action( 'add_option_' . GILDA_PROMO_OPTION, $gilda_promo_flush );
add_action( 'save_post_shop_coupon', $gilda_promo_flush );
add_action( 'woocommerce_coupon_options_save', $gilda_promo_flush );
unset( $gilda_promo_flush );

/* ---------- "Só na primeira compra" na tela do cupom ---------- */

add_action(
	'woocommerce_coupon_options',
	static function ( $coupon_id, $coupon ) {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_gilda_first_purchase_only',
				'label'       => 'Só na primeira compra',
				'description' => 'Vale só para quem ainda não fez nenhum pedido (confere pela conta e pelo e-mail).',
				'value'       => $coupon instanceof WC_Coupon && 'yes' === $coupon->get_meta( '_gilda_first_purchase_only' ) ? 'yes' : 'no',
			)
		);
	},
	10,
	2
);

add_action(
	'woocommerce_coupon_options_save',
	static function ( $post_id, $coupon ) {
		if ( ! $coupon instanceof WC_Coupon ) {
			$coupon = new WC_Coupon( $post_id );
		}
		// phpcs:ignore WordPress.Security.NonceVerification -- o WooCommerce já conferiu o nonce da tela do cupom.
		$coupon->update_meta_data( '_gilda_first_purchase_only', empty( $_POST['_gilda_first_purchase_only'] ) ? 'no' : 'yes' );
		$coupon->save();
	},
	10,
	2
);

/* ---------- Cupons → Promoção em destaque ---------- */

add_action(
	'admin_menu',
	static function () {
		// Sob "Marketing" do WooCommerce (onde ficam os Cupons para o administrador). Para a dona, o menu
		// Cupons do painel aponta para cá (role.php).
		$hook = add_submenu_page( 'woocommerce-marketing', 'Promoção em destaque', 'Promoção em destaque', 'edit_shop_coupons', GILDA_PROMO_PAGE, 'gilda_promo_screen' );
		add_action( 'load-' . $hook, 'gilda_promo_load' );
	},
	20
);

// Dona: acende "Cupons" no menu quando está na Promoção em destaque.
add_filter(
	'parent_file',
	static function ( $parent_file ) {
		global $plugin_page, $submenu_file;
		if ( GILDA_PROMO_PAGE === $plugin_page && function_exists( 'gilda_is_owner' ) && gilda_is_owner() ) {
			$submenu_file = 'admin.php?page=' . GILDA_PROMO_PAGE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'edit.php?post_type=shop_coupon';
		}
		return $parent_file;
	},
	100
);

/** Grava a escolha. */
function gilda_promo_load(): void {
	gilda_painel_enqueue_ui();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	check_admin_referer( 'gilda_promo_save' );
	if ( ! current_user_can( 'edit_shop_coupons' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$id = absint( $_POST['gilda_promo'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && 'shop_coupon' !== get_post_type( $id ) ) {
		$id = 0;
	}
	update_option( GILDA_PROMO_OPTION, $id, false );
	wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=' . GILDA_PROMO_PAGE ) ) );
	exit;
}

/** Tela. */
function gilda_promo_screen(): void {
	$current = absint( get_option( GILDA_PROMO_OPTION, 0 ) );
	$coupons = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$promo   = gilda_promo();
	?>
	<div class="wrap gilda-panel">
		<header class="gilda-page-head">
			<a class="gilda-back" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>">← Cupons</a>
			<h1 class="gilda-display">promoção em destaque;</h1>
			<p class="gilda-lead">O cupom escolhido aparece na faixa de avisos do topo e na fita do banner da página inicial, com o código e o desconto lidos do próprio cupom.</p>
		</header>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="gilda-notice gilda-notice--ok notice-success" role="status"><p>Promoção salva. O site já mostra a escolha nova.</p></div>
		<?php endif; ?>
		<form method="post" class="gilda-edit">
			<?php wp_nonce_field( 'gilda_promo_save' ); ?>
			<div class="gilda-edit__main">
				<section class="gilda-card" aria-labelledby="gilda-promo-t">
					<h2 id="gilda-promo-t" class="gilda-h2">Cupom anunciado</h2>
					<label class="gilda-label">Qual cupom anunciar
						<select name="gilda_promo" id="gilda-promo" class="gilda-input">
							<option value="0" <?php selected( $current, 0 ); ?>>Nenhum (não anunciar cupom)</option>
							<?php foreach ( $coupons as $coupon_post ) : ?>
								<?php $coupon = new WC_Coupon( $coupon_post->ID ); ?>
								<option value="<?php echo (int) $coupon_post->ID; ?>" <?php selected( $current, $coupon_post->ID ); ?>><?php echo esc_html( $coupon->get_code() . ' — ' . ( 'percent' === $coupon->get_discount_type() ? rtrim( rtrim( number_format( (float) $coupon->get_amount(), 2, ',', '.' ), '0' ), ',' ) . '%' : 'R$ ' . number_format( (float) $coupon->get_amount(), 2, ',', '.' ) ) . ( 'yes' === $coupon->get_meta( '_gilda_first_purchase_only' ) ? ' · só na primeira compra' : '' ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<p class="gilda-help">Para mudar o desconto, edite o próprio cupom em Cupons: a faixa e o banner acompanham. Cupom vencido ou esgotado deixa de ser anunciado sozinho.</p>
					<p class="gilda-help"><strong>Hoje o site mostra:</strong> <?php echo $promo ? esc_html( gilda_promo_message() ) : 'nenhum cupom (a faixa e o banner ficam sem a mensagem de cupom).'; ?></p>
				</section>
			</div>
			<aside class="gilda-edit__side">
				<section class="gilda-card gilda-publish">
					<h2 class="gilda-h2">Salvar</h2>
					<button type="submit" class="gilda-btn gilda-btn--big">Salvar promoção</button>
				</section>
			</aside>
		</form>
	</div>
	<?php
}

/** Frase da faixa de avisos ("10% OFF na primeira compra com o cupom primeiracompra"), '' sem promoção. */
function gilda_promo_message(): string {
	$promo = gilda_promo();
	if ( ! $promo ) {
		return '';
	}
	return sprintf( '%1$s OFF%2$s com o cupom %3$s', $promo['label'], $promo['first'] ? ' na primeira compra' : '', $promo['code'] );
}
