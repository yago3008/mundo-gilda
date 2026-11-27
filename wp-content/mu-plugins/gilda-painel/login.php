<?php
/**
 * Tela de login (wp-login.php) com a cara da GILDA;, para todo mundo: foto de um lado,
 * "GILDA; · o painel da loja", campos e "ENTRAR;" do outro (protótipo painel-proto/Main.dc.html).
 *
 * Só aparência e textos: os campos, ids (#user_login, #user_pass, #wp-submit) e o fluxo do WordPress
 * (e o limite de tentativas do gilda-hardening) continuam os mesmos.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/**
 * @font-face das fontes do tema (Fraunces, EB Garamond, Jost), servidas pelo próprio site.
 *
 * @return string CSS
 */
function gilda_painel_fonts_css(): string {
	$base  = trailingslashit( get_theme_root_uri() ) . 'gilda/assets/fonts/';
	$faces = array(
		array( 'Fraunces', 'normal', '300 700', 'fraunces-latin-wght-normal.woff2' ),
		array( 'Fraunces', 'italic', '300 700', 'fraunces-latin-wght-italic.woff2' ),
		array( 'EB Garamond', 'normal', '400 800', 'eb-garamond-latin-wght-normal.woff2' ),
		array( 'EB Garamond', 'italic', '400 800', 'eb-garamond-latin-wght-italic.woff2' ),
		array( 'Jost', 'normal', '300 700', 'jost-latin-wght-normal.woff2' ),
	);
	$css = '';
	foreach ( $faces as $face ) {
		$css .= sprintf(
			"@font-face{font-family:'%s';font-style:%s;font-weight:%s;font-display:swap;src:url('%s') format('woff2')}",
			$face[0],
			$face[1],
			$face[2],
			esc_url_raw( $base . $face[3] )
		);
	}
	return $css;
}

/** URL da foto do login: a foto do banner da home (papel "hero" das fotos do site). */
function gilda_login_photo_url(): string {
	$images = (array) get_option( 'gilda_site_images', array() );
	$id     = absint( $images['hero'] ?? 0 );
	$url    = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
	return $url ? $url : '';
}

add_action(
	'login_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'gilda-login', GILDA_PAINEL_URL . 'assets/login.css', array( 'login' ), gilda_painel_ver( 'assets/login.css' ) );
		wp_add_inline_style( 'gilda-login', gilda_painel_fonts_css() );
	}
);

add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
add_filter( 'login_headertext', static fn() => 'GILDA;' );
add_filter( 'login_display_language_dropdown', '__return_false' );

/** Foto da coleção (lado esquerdo). Fica antes do #login no HTML. */
add_action(
	'login_header',
	static function () {
		$url = gilda_login_photo_url();
		?>
		<section class="gilda-login-photo" aria-label="Foto da coleção">
			<?php if ( $url ) : ?>
				<img src="<?php echo esc_url( $url ); ?>" alt="" />
			<?php endif; ?>
			<div class="gilda-login-photo__text">
				<span class="gilda-login-photo__kicker">Capítulo 3 · Ritual Brasileiro</span>
				<span class="gilda-login-photo__quote">uma pausa, uma conversa, um café.</span>
			</div>
		</section>
		<?php
	}
);

/** Título e texto de abertura, acima do formulário (só na tela de entrar). */
add_filter(
	'login_message',
	static function ( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $action, array( 'login', '' ), true ) || isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $message;
		}
		$intro = '<div class="gilda-login-intro"><span class="gilda-login-intro__brand">GILDA;</span><h2 class="gilda-login-intro__title">o painel da loja</h2><p class="gilda-login-intro__text">Peças, capítulos, pedidos e cupons: tudo o que você muda aqui aparece sozinho no site.</p></div>';
		return $intro . $message;
	}
);

/** Textos do formulário no tom do protótipo. */
add_filter(
	'gettext',
	static function ( $translation, $text, $domain ) {
		if ( 'default' !== $domain || 'wp-login.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
			return $translation;
		}
		$map = array(
			'Username or Email Address' => 'E-mail ou usuário',
			'Password'                  => 'Senha',
			'Remember Me'               => 'Continuar conectada neste computador',
			'Log In'                    => 'Entrar;',
			'Lost your password?'       => 'Esqueci minha senha',
		);
		return $map[ $text ] ?? $translation;
	},
	10,
	3
);

add_filter( 'login_body_class', static fn( $classes ) => array_merge( (array) $classes, array( 'gilda-login' ) ) );
