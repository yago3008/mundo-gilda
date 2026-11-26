<?php
/**
 * Avise-me: formulário na página do produto, envio (AJAX ou POST sem JS) e link de cancelamento.
 *
 * Marcação (ver docs/development/backend-para-frontend.md):
 * - Produto variável: o bloco .gilda-notify fica DENTRO de form.variations_form (logo antes do
 *   botão de compra, em .single_variation_wrap) e começa com [hidden]. O JS mostra quando a
 *   variação escolhida está esgotada (found_variation) e esconde em reset_data/hide_variation.
 *   Como formulário dentro de formulário não é HTML válido, os campos apontam com o atributo
 *   form="gilda-notify-form-<id>" para um <form> vazio impresso depois do variations_form.
 * - Produto simples esgotado: o mesmo bloco, já visível, dentro do próprio <form>, depois do
 *   bloco de compra.
 */

defined( 'ABSPATH' ) || exit;

const GILDA_STOCK_ALERTS_NONCE        = 'gilda_stock_alert';
const GILDA_STOCK_ALERTS_RATE_PER_IP  = 10; // tentativas por hora por IP
const GILDA_STOCK_ALERTS_MAX_PER_MAIL = 20; // pedidos pendentes por e-mail

/** @var array<int,bool> Produtos cujo formulário já foi impresso nesta página. */
$GLOBALS['gilda_stock_alerts_rendered'] = array();

/**
 * O produto aceita "avise-me"? (simples ou variável, publicado e com compra normal)
 */
function gilda_stock_alerts_supports( $product ): bool {
	$supports = $product instanceof WC_Product
		&& $product->is_type( array( 'simple', 'variable' ) )
		&& 'publish' === $product->get_status()
		&& ! $product->is_on_backorder();
	return (bool) apply_filters( 'gilda_stock_alerts_supports', $supports, $product );
}

/**
 * Mensagens para o cliente, por código.
 */
function gilda_stock_alerts_messages(): array {
	return array(
		'ok'               => __( 'Pronto! Assim que voltar, você recebe um e-mail.', 'gilda' ),
		'expired'          => __( 'A página ficou aberta por muito tempo. Recarregue e tente de novo.', 'gilda' ),
		'rate_limited'     => __( 'Muitas tentativas seguidas. Tente de novo daqui a pouco.', 'gilda' ),
		'invalid_email'    => __( 'Confira o e-mail: ele parece incompleto.', 'gilda' ),
		'consent'          => __( 'Marque a caixa autorizando o aviso por e-mail.', 'gilda' ),
		'choose_variation' => __( 'Escolha a cor e o tamanho primeiro.', 'gilda' ),
		'invalid_product'  => __( 'Não encontramos esse produto. Recarregue a página.', 'gilda' ),
		'in_stock'         => __( 'Boa notícia: esse item está disponível. É só comprar.', 'gilda' ),
		'too_many'         => __( 'Esse e-mail já tem muitos avisos pendentes.', 'gilda' ),
		'error'            => __( 'Não deu certo agora. Tente de novo em instantes.', 'gilda' ),
	);
}

/**
 * HTML do formulário.
 *
 * @param WC_Product $product  Produto (simples ou variável).
 * @param bool       $detached Campos ficam dentro de outro form (variável): usa form="…" e começa escondido.
 */
function gilda_stock_alerts_fields_html( WC_Product $product, bool $detached ): string {
	$pid     = $product->get_id();
	$form_id = 'gilda-notify-form-' . $pid;
	$f       = $detached ? ' form="' . esc_attr( $form_id ) . '"' : '';
	$user    = wp_get_current_user();
	$email   = $user && $user->exists() ? $user->user_email : '';
	$privacy = get_privacy_policy_url();

	// Retorno do envio sem JavaScript (?gilda_stock_alert=<código>).
	$result   = isset( $_GET['gilda_stock_alert'] ) ? sanitize_key( wp_unslash( $_GET['gilda_stock_alert'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$messages = gilda_stock_alerts_messages();
	$message  = isset( $messages[ $result ] ) && ( empty( $_GET['gilda_stock_alert_product'] ) || (int) $_GET['gilda_stock_alert_product'] === $pid ) ? $messages[ $result ] : ''; // phpcs:ignore WordPress.Security.NonceVerification

	$consent = $privacy
		? sprintf(
			/* translators: %s: link para a política de privacidade */
			__( 'Quero receber um único e-mail quando este item voltar ao estoque. Meu e-mail só será usado para isso (%s).', 'gilda' ),
			'<a href="' . esc_url( $privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'política de privacidade', 'gilda' ) . '</a>'
		)
		: esc_html__( 'Quero receber um único e-mail quando este item voltar ao estoque. Meu e-mail só será usado para isso.', 'gilda' );

	$classes = 'gilda-notify' . ( $detached ? ' gilda-notify--variation' : ' gilda-notify--simple' ) . ( 'ok' === $result && $message ? ' is-done' : '' );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" id="gilda-notify-<?php echo esc_attr( $pid ); ?>" data-product-id="<?php echo esc_attr( $pid ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>"<?php echo $detached && ! $message ? ' hidden' : ''; ?>>
		<p class="gilda-notify__title"><?php esc_html_e( 'Esgotado. Quer saber quando voltar?', 'gilda' ); ?></p>
		<p class="gilda-notify__text">
			<?php esc_html_e( 'Deixe seu e-mail e avisamos assim que', 'gilda' ); ?>
			<span class="gilda-notify__variation" data-default="<?php esc_attr_e( 'este item', 'gilda' ); ?>"><?php esc_html_e( 'este item', 'gilda' ); ?></span>
			<?php esc_html_e( 'chegar.', 'gilda' ); ?>
		</p>
		<div class="gilda-notify__fields">
			<label class="gilda-notify__label" for="gilda-notify-email-<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Seu e-mail', 'gilda' ); ?></label>
			<input class="gilda-notify__email" type="email" name="email" id="gilda-notify-email-<?php echo esc_attr( $pid ); ?>" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" inputmode="email" maxlength="190" required<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<label class="gilda-notify__consent">
				<input class="gilda-notify__consent-input" type="checkbox" name="consent" value="1" required<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<span><?php echo wp_kses( $consent, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ); ?></span>
			</label>
			<span class="gilda-notify__hp" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
				<label><?php esc_html_e( 'Deixe em branco', 'gilda' ); ?> <input type="text" name="website" value="" tabindex="-1" autocomplete="off"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>></label>
			</span>
			<input type="hidden" name="action" value="gilda_stock_alert"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $pid ); ?>"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<input type="hidden" name="variation_id" value="0" class="gilda-notify__variation-id"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<input type="hidden" name="_gilda_nonce" value="<?php echo esc_attr( wp_create_nonce( GILDA_STOCK_ALERTS_NONCE ) ); ?>"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<button type="submit" class="gilda-notify__submit button wp-element-button"<?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Avise-me', 'gilda' ); ?></button>
		</div>
		<p class="gilda-notify__message<?php echo $message ? ( 'ok' === $result ? ' is-success' : ' is-error' ) : ''; ?>" role="status" aria-live="polite"<?php echo $message ? '' : ' hidden'; ?>><?php echo esc_html( $message ); ?></p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * <form> que recebe os campos (vazio no produto variável, envolvendo os campos no simples).
 */
function gilda_stock_alerts_form_open( int $product_id ): string {
	return sprintf(
		'<form id="gilda-notify-form-%1$d" class="gilda-notify__form" method="post" action="%2$s" data-product-id="%1$d">',
		$product_id,
		esc_url( admin_url( 'admin-post.php' ) )
	);
}

/**
 * Formulário completo para produto simples esgotado (também serve para o tema chamar onde quiser).
 * Em produto variável, devolve só o <form> vazio (os campos vão dentro do variations_form).
 *
 * @param WC_Product|int|null $product Produto; padrão: o global.
 */
function gilda_stock_alert_form( $product = null ): string {
	$product = $product ? wc_get_product( $product ) : ( $GLOBALS['product'] ?? null );
	if ( ! gilda_stock_alerts_supports( $product ) ) {
		return '';
	}
	$pid = $product->get_id();
	if ( ! empty( $GLOBALS['gilda_stock_alerts_rendered'][ $pid ] ) ) {
		return '';
	}
	if ( $product->is_type( 'variable' ) ) {
		$GLOBALS['gilda_stock_alerts_rendered'][ $pid ] = true;
		return gilda_stock_alerts_form_open( $pid ) . '</form>';
	}
	if ( $product->is_in_stock() ) {
		return '';
	}
	$GLOBALS['gilda_stock_alerts_rendered'][ $pid ] = true;
	return gilda_stock_alerts_form_open( $pid ) . gilda_stock_alerts_fields_html( $product, false ) . '</form>';
}

// Variável: campos dentro do variations_form, entre a disponibilidade e o botão de compra.
add_action(
	'woocommerce_single_variation',
	static function () {
		global $product;
		if ( gilda_stock_alerts_supports( $product ) && $product->is_type( 'variable' ) ) {
			echo gilda_stock_alerts_fields_html( $product, true ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	15
);

// Variável: o <form> vazio, depois do variations_form.
add_action(
	'woocommerce_after_add_to_cart_form',
	static function () {
		global $product;
		if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
			echo gilda_stock_alert_form( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

// Simples esgotado (o template do WooCommerce não chama os hooks do form quando está sem estoque):
// tema de blocos → depois do bloco "Adicionar ao carrinho"; tema clássico → no resumo do produto.
add_filter(
	'render_block_woocommerce/add-to-cart-form',
	static function ( $content ) {
		global $product;
		if ( is_product() && $product instanceof WC_Product && ! $product->is_type( 'variable' ) ) {
			$content .= gilda_stock_alert_form( $product );
		}
		return $content;
	}
);
add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		if ( $product instanceof WC_Product && ! $product->is_type( 'variable' ) ) {
			echo gilda_stock_alert_form( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	31
);

// Dado na variação: o "avise-me" vale para ela? (data-product_variations / found_variation)
add_filter(
	'woocommerce_available_variation',
	static function ( $data, $product, $variation ) {
		$data['gilda_stock_alert'] = gilda_stock_alerts_supports( $product ) && ! $variation->is_in_stock();
		return $data;
	},
	10,
	3
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_script(
			'gilda-stock-alerts',
			GILDA_STOCK_ALERTS_URL . 'assets/stock-alerts.js',
			array( 'jquery' ),
			GILDA_STOCK_ALERTS_VERSION,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
		wp_localize_script(
			'gilda-stock-alerts',
			'gildaStockAlerts',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'error'   => gilda_stock_alerts_messages()['error'],
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Envio
 * ---------------------------------------------------------------------- */

function gilda_stock_alerts_client_ip(): string {
	$ip = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return (string) $ip;
}

/**
 * Valida e grava um pedido de aviso.
 *
 * @param array $req Campos do formulário (já sem slashes).
 * @return string Código do resultado (chave de gilda_stock_alerts_messages()).
 */
function gilda_stock_alerts_subscribe( array $req ): string {
	// Robôs preenchem o campo escondido: responde "ok" sem gravar nada.
	if ( ! empty( $req['website'] ) ) {
		return 'ok';
	}
	if ( ! wp_verify_nonce( (string) ( $req['_gilda_nonce'] ?? '' ), GILDA_STOCK_ALERTS_NONCE ) ) {
		return 'expired';
	}

	// Limite por IP (o IP não é gravado, só um hash temporário).
	$rl_key = 'gilda_sa_rl_' . substr( wp_hash( gilda_stock_alerts_client_ip() ), 0, 20 );
	$tries  = (int) get_transient( $rl_key );
	if ( $tries >= (int) apply_filters( 'gilda_stock_alerts_rate_limit', GILDA_STOCK_ALERTS_RATE_PER_IP ) ) {
		return 'rate_limited';
	}
	set_transient( $rl_key, $tries + 1, HOUR_IN_SECONDS );

	$email = sanitize_email( (string) ( $req['email'] ?? '' ) );
	if ( ! $email || ! is_email( $email ) || strlen( $email ) > 190 ) {
		return 'invalid_email';
	}
	if ( empty( $req['consent'] ) ) {
		return 'consent';
	}

	$product = wc_get_product( absint( $req['product_id'] ?? 0 ) );
	if ( ! gilda_stock_alerts_supports( $product ) ) {
		return 'invalid_product';
	}
	$variation_id = absint( $req['variation_id'] ?? 0 );
	$target       = $product;
	if ( $product->is_type( 'variable' ) ) {
		if ( ! $variation_id ) {
			return 'choose_variation';
		}
		$target = wc_get_product( $variation_id );
		if ( ! $target instanceof WC_Product_Variation || $target->get_parent_id() !== $product->get_id() || 'publish' !== $target->get_status() ) {
			return 'invalid_product';
		}
	} else {
		$variation_id = 0;
	}
	if ( $target->is_in_stock() ) {
		return 'in_stock';
	}
	if ( gilda_stock_alerts_count_pending_for_email( $email ) >= GILDA_STOCK_ALERTS_MAX_PER_MAIL ) {
		return 'too_many';
	}

	$id = gilda_stock_alerts_add( $product->get_id(), $variation_id, $email );
	if ( ! $id ) {
		return 'error';
	}
	do_action( 'gilda_stock_alert_subscribed', $id, $product->get_id(), $variation_id, $email );
	return 'ok';
}

/**
 * Handler único: admin-ajax (JSON) e admin-post (sem JS, redireciona de volta).
 */
function gilda_stock_alerts_handle_request(): void {
	$req  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- verificado em gilda_stock_alerts_subscribe()
	$code = gilda_stock_alerts_subscribe( is_array( $req ) ? $req : array() );
	$msg  = gilda_stock_alerts_messages()[ $code ] ?? gilda_stock_alerts_messages()['error'];

	if ( wp_doing_ajax() ) {
		$payload = array( 'code' => $code, 'message' => $msg );
		'ok' === $code ? wp_send_json_success( $payload ) : wp_send_json_error( $payload, 'rate_limited' === $code ? 429 : 400 );
	}

	$pid      = absint( $req['product_id'] ?? 0 );
	$fallback = $pid ? get_permalink( $pid ) : home_url( '/' );
	$back     = wp_validate_redirect( wp_get_referer() ?: $fallback, $fallback );
	$back     = remove_query_arg( array( 'gilda_stock_alert', 'gilda_stock_alert_product' ), $back );
	wp_safe_redirect( add_query_arg( array( 'gilda_stock_alert' => $code, 'gilda_stock_alert_product' => $pid ), $back ) . '#gilda-notify-' . $pid, 303 );
	exit;
}
add_action( 'wp_ajax_gilda_stock_alert', 'gilda_stock_alerts_handle_request' );
add_action( 'wp_ajax_nopriv_gilda_stock_alert', 'gilda_stock_alerts_handle_request' );
add_action( 'admin_post_gilda_stock_alert', 'gilda_stock_alerts_handle_request' );
add_action( 'admin_post_nopriv_gilda_stock_alert', 'gilda_stock_alerts_handle_request' );

/* -------------------------------------------------------------------------
 * Cancelamento (link do e-mail): GET mostra a confirmação, POST apaga.
 * Pedir confirmação evita que antivírus/prévia de e-mail que "clicam" no link cancelem sozinhos.
 * ---------------------------------------------------------------------- */
add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_REQUEST['gilda_stock_alert_cancel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$id    = absint( $_REQUEST['gilda_stock_alert_cancel'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$alert = $id ? gilda_stock_alerts_get( $id ) : null;
		$title = __( 'Avise-me', 'gilda' ) . ' | ' . get_bloginfo( 'name' );
		$back  = '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Voltar para a loja', 'gilda' ) . '</a></p>';

		if ( ! $alert || '' === $token || ! hash_equals( $alert->token, $token ) ) {
			wp_die( '<p>' . esc_html__( 'Este aviso já foi cancelado ou não existe mais.', 'gilda' ) . '</p>' . $back, esc_html( $title ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		$target = wc_get_product( $alert->variation_id ?: $alert->product_id );
		$name   = $target ? wp_strip_all_tags( $target->get_name() ) : __( 'este produto', 'gilda' );

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			gilda_stock_alerts_delete( $id );
			do_action( 'gilda_stock_alert_cancelled', $alert );
			/* translators: %s: produto */
			wp_die( '<p>' . esc_html( sprintf( __( 'Pronto: apagamos seu e-mail da lista de aviso de %s.', 'gilda' ), $name ) ) . '</p>' . $back, esc_html( $title ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		$form = sprintf(
			'<p>%1$s</p><form method="post" action="%2$s"><input type="hidden" name="gilda_stock_alert_cancel" value="%3$d"><input type="hidden" name="token" value="%4$s"><p><button type="submit" class="button">%5$s</button></p></form>%6$s',
			/* translators: %s: produto */
			esc_html( sprintf( __( 'Cancelar o aviso de %s e apagar seu e-mail da lista?', 'gilda' ), $name ) ),
			esc_url( home_url( '/' ) ),
			$id,
			esc_attr( $token ),
			esc_html__( 'Sim, cancelar', 'gilda' ),
			$back
		);
		wp_die( $form, esc_html( $title ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	1
);
