<?php
/**
 * Plugin Name: GILDA; Consentimento de cookies
 * Description: Banner de consentimento (LGPD): Aceitar / Recusar / Preferências. Cookies necessários sempre ligados; marketing (Meta Pixel) só com consentimento.
 *
 * Como funciona
 *   - A escolha fica no cookie de primeira parte `gilda_consent` (JSON: {"v":1,"c":{"marketing":true},"t":<unix>}),
 *     válido por 6 meses. Mudou a política ou entrou categoria nova? Suba VERSION: todo mundo é perguntado de novo.
 *   - Tudo é decidido no navegador (window.gildaConsent), então funciona mesmo com cache de página.
 *     No PHP, use gilda_consent_has( 'marketing' ) só onde a página não é cacheada (ex.: pedido recebido).
 *   - Scripts de marketing esperam o consentimento com gildaConsent.onChange() / whenGranted() (ver gilda-meta-pixel.php).
 *   - Também publica o "consent mode" do Google (padrão negado), para qualquer tag do Google que entrar no futuro.
 *
 * Para o front (K1venn): marcação com classes gilda-consent__*, CSS neutro mínimo e o shortcode
 * [gilda_consent_preferences] para reabrir as preferências (ex.: no rodapé). Detalhes e hooks em
 * docs/development/backend-para-frontend.md.
 */

defined( 'ABSPATH' ) || exit;

final class Gilda_Consent {

	public const COOKIE  = 'gilda_consent';
	public const VERSION = 1;

	private static bool $banner_done    = false;
	private static bool $shortcode_used = false;

	public static function boot(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 0 );
		add_action( 'wp_body_open', array( self::class, 'banner' ), 5 );
		add_action( 'wp_footer', array( self::class, 'footer' ), 5 );
		add_shortcode( 'gilda_consent_preferences', array( self::class, 'shortcode' ) );
		// Atribuição de pedidos do WooCommerce (cookies sbjs_*): o WooCommerce trata como "marketing".
		// O JS (consent-ui.js) liga/desliga na hora, inclusive em página cacheada.
		add_filter( 'wc_order_attribution_allow_tracking', static fn() => self::has( 'marketing' ) );
	}

	/** Categorias de cookies. "necessary" é sempre ligada; as outras são opcionais. */
	public static function categories(): array {
		/**
		 * Filtra as categorias opcionais. Ex.: para um Google Analytics futuro,
		 * adicione 'analytics' => [ 'label' => 'Estatísticas', 'description' => '...' ].
		 */
		$cats = apply_filters(
			'gilda_consent_categories',
			array(
				'marketing' => array(
					'label'       => __( 'Marketing', 'gilda' ),
					'description' => __( 'Meta Pixel (Facebook e Instagram): mede as visitas e compras que vêm dos nossos anúncios e ajuda a mostrar anúncios mais relevantes. Os dados são tratados pela Meta.', 'gilda' ),
				),
			)
		);
		unset( $cats['necessary'] );
		return $cats;
	}

	/** Escolha salva no cookie (ou null se a pessoa ainda não escolheu / versão antiga). */
	public static function get(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$data = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $data ) || ( $data['v'] ?? null ) !== self::VERSION || ! is_array( $data['c'] ?? null ) ) {
			return null;
		}
		return $data;
	}

	public static function has( string $category ): bool {
		if ( 'necessary' === $category ) {
			return true;
		}
		$data = self::get();
		return $data && true === ( $data['c'][ $category ] ?? false );
	}

	public static function privacy_url(): string {
		return (string) apply_filters( 'gilda_consent_privacy_url', get_privacy_policy_url() );
	}

	private static function texts(): array {
		return apply_filters(
			'gilda_consent_texts',
			array(
				'title'       => __( 'Cookies e privacidade', 'gilda' ),
				'text'        => __( 'Usamos cookies necessários para a loja funcionar (carrinho, login e checkout). Com a sua permissão, também usamos cookies de marketing para medir nossos anúncios no Instagram e no Facebook. Você pode mudar de ideia quando quiser.', 'gilda' ),
				'privacy'     => __( 'Política de privacidade', 'gilda' ),
				'accept'      => __( 'Aceitar', 'gilda' ),
				'reject'      => __( 'Recusar', 'gilda' ),
				'preferences' => __( 'Preferências', 'gilda' ),
				'prefs_title' => __( 'Preferências de cookies', 'gilda' ),
				'prefs_text'  => __( 'Escolha quais cookies você permite. Os necessários não podem ser desligados, porque sem eles a loja não funciona.', 'gilda' ),
				'categories'  => __( 'Categorias de cookies', 'gilda' ),
				'necessary'   => __( 'Necessários', 'gilda' ),
				'always_on'   => __( 'sempre ativos', 'gilda' ),
				'necessary_d' => __( 'Carrinho, login, checkout, segurança e a sua escolha de cookies. Não são usados para publicidade.', 'gilda' ),
				'save'        => __( 'Salvar escolhas', 'gilda' ),
				'accept_all'  => __( 'Aceitar todos', 'gilda' ),
				'reject_all'  => __( 'Recusar todos', 'gilda' ),
				'close'       => __( 'Fechar', 'gilda' ),
				'reopen'      => __( 'Preferências de cookies', 'gilda' ),
			)
		);
	}

	private static function asset( string $file ): string {
		$path = __DIR__ . '/gilda-consent/' . $file;
		return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** Config + API (window.gildaConsent) antes de qualquer outro script; CSS. */
	public static function head(): void {
		$config = array(
			'cookie'     => self::COOKIE,
			'version'    => self::VERSION,
			'maxAge'     => (int) apply_filters( 'gilda_consent_max_age', 180 * DAY_IN_SECONDS ),
			'path'       => COOKIEPATH ? COOKIEPATH : '/',
			'categories' => array_keys( self::categories() ),
			'google'     => (bool) apply_filters( 'gilda_consent_google_consent_mode', true ),
		);
		echo "<script id=\"gilda-consent-api\">\nwindow.gildaConsentConfig=" . wp_json_encode( $config ) . ";\n" . self::asset( 'consent-api.js' ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput

		// Regras essenciais (esconder, leitor de tela) sempre; o visual padrão pode ser desligado pelo tema.
		$css = '.gilda-consent[hidden],.gilda-consent__prefs:not([open]){display:none!important}'
			. '.gilda-consent__sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}';
		if ( apply_filters( 'gilda_consent_default_css', true ) ) {
			$css .= self::asset( 'consent.css' );
		}
		echo '<style id="gilda-consent-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Banner + painel de preferências. Logo no início do <body>, para ser o primeiro no foco/leitor de tela. */
	public static function banner(): void {
		if ( self::$banner_done || is_embed() ) {
			return;
		}
		self::$banner_done = true;
		$t       = self::texts();
		$privacy = self::privacy_url();
		$link    = $privacy ? sprintf( ' <a class="gilda-consent__link" href="%s">%s</a>', esc_url( $privacy ), esc_html( $t['privacy'] ) ) : '';
		?>
<div id="gilda-consent" class="gilda-consent" role="dialog" aria-modal="false" aria-labelledby="gilda-consent-title" aria-describedby="gilda-consent-text" hidden>
	<div class="gilda-consent__inner">
		<div class="gilda-consent__body">
			<h2 id="gilda-consent-title" class="gilda-consent__title"><?php echo esc_html( $t['title'] ); ?></h2>
			<p id="gilda-consent-text" class="gilda-consent__text"><?php echo esc_html( $t['text'] ) . $link; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		</div>
		<div class="gilda-consent__actions">
			<button type="button" class="gilda-consent__button gilda-consent__button--accept" data-gilda-consent="accept"><?php echo esc_html( $t['accept'] ); ?></button>
			<button type="button" class="gilda-consent__button gilda-consent__button--reject" data-gilda-consent="reject"><?php echo esc_html( $t['reject'] ); ?></button>
			<button type="button" class="gilda-consent__button gilda-consent__button--preferences" data-gilda-consent="preferences" aria-haspopup="dialog" aria-controls="gilda-consent-prefs"><?php echo esc_html( $t['preferences'] ); ?></button>
		</div>
	</div>
</div>
<script>(function(b){if(b&&window.gildaConsent&&!window.gildaConsent.get()){b.hidden=false;}})(document.getElementById('gilda-consent'));</script>
<dialog id="gilda-consent-prefs" class="gilda-consent__prefs" aria-labelledby="gilda-consent-prefs-title" aria-describedby="gilda-consent-prefs-text">
	<div class="gilda-consent__prefs-header">
		<h2 id="gilda-consent-prefs-title" class="gilda-consent__title"><?php echo esc_html( $t['prefs_title'] ); ?></h2>
		<button type="button" class="gilda-consent__close" data-gilda-consent="close" aria-label="<?php echo esc_attr( $t['close'] ); ?>"><span aria-hidden="true">&times;</span></button>
	</div>
	<p id="gilda-consent-prefs-text" class="gilda-consent__text"><?php echo esc_html( $t['prefs_text'] ) . $link; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<fieldset class="gilda-consent__categories">
		<legend class="gilda-consent__sr"><?php echo esc_html( $t['categories'] ); ?></legend>
		<div class="gilda-consent__category gilda-consent__category--necessary">
			<input type="checkbox" id="gilda-consent-cat-necessary" class="gilda-consent__checkbox" checked disabled aria-describedby="gilda-consent-cat-necessary-desc">
			<label for="gilda-consent-cat-necessary" class="gilda-consent__label"><?php echo esc_html( $t['necessary'] ); ?> <span class="gilda-consent__badge">(<?php echo esc_html( $t['always_on'] ); ?>)</span></label>
			<p id="gilda-consent-cat-necessary-desc" class="gilda-consent__category-desc"><?php echo esc_html( $t['necessary_d'] ); ?></p>
		</div>
		<?php foreach ( self::categories() as $key => $cat ) : $id = 'gilda-consent-cat-' . sanitize_html_class( $key ); ?>
		<div class="gilda-consent__category gilda-consent__category--<?php echo esc_attr( sanitize_html_class( $key ) ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="gilda-consent__checkbox" data-gilda-consent-category="<?php echo esc_attr( $key ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-desc">
			<label for="<?php echo esc_attr( $id ); ?>" class="gilda-consent__label"><?php echo esc_html( $cat['label'] ?? $key ); ?></label>
			<p id="<?php echo esc_attr( $id ); ?>-desc" class="gilda-consent__category-desc"><?php echo esc_html( $cat['description'] ?? '' ); ?></p>
		</div>
		<?php endforeach; ?>
	</fieldset>
	<div class="gilda-consent__actions">
		<button type="button" class="gilda-consent__button gilda-consent__button--save" data-gilda-consent="save"><?php echo esc_html( $t['save'] ); ?></button>
		<button type="button" class="gilda-consent__button gilda-consent__button--accept" data-gilda-consent="accept"><?php echo esc_html( $t['accept_all'] ); ?></button>
		<button type="button" class="gilda-consent__button gilda-consent__button--reject" data-gilda-consent="reject"><?php echo esc_html( $t['reject_all'] ); ?></button>
	</div>
</dialog>
		<?php
	}

	/** Fallback (tema sem wp_body_open), link de reabrir se o tema não usou o shortcode, e o JS da interface. */
	public static function footer(): void {
		self::banner();
		/**
		 * Se nenhuma página usou [gilda_consent_preferences], desenha um link simples no fim da página,
		 * para sempre existir um jeito de mudar a escolha. Retorne false quando o tema tiver o link dele.
		 */
		if ( ! self::$shortcode_used && apply_filters( 'gilda_consent_auto_reopen_link', true ) ) {
			echo '<p class="gilda-consent__reopen">' . self::shortcode() . "</p>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo "<script id=\"gilda-consent-ui\">\n" . self::asset( 'consent-ui.js' ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** [gilda_consent_preferences label="Cookies"] → botão (com cara de link) que reabre as preferências. */
	public static function shortcode( $atts = array() ): string {
		self::$shortcode_used = true;
		$atts = shortcode_atts( array( 'label' => self::texts()['reopen'] ), (array) $atts, 'gilda_consent_preferences' );
		return sprintf(
			'<button type="button" class="gilda-consent-open" data-gilda-consent-open aria-haspopup="dialog" aria-controls="gilda-consent-prefs">%s</button>',
			esc_html( $atts['label'] )
		);
	}
}

/** Atalho para outros mu-plugins. Só confie nele em páginas sem cache (ver cabeçalho). */
function gilda_consent_has( string $category ): bool {
	return Gilda_Consent::has( $category );
}

Gilda_Consent::boot();
