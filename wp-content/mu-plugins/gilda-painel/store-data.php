<?php
/**
 * "Dados da loja": empresa, contatos, redes e logo, numa opção só (`gilda_store`).
 * O rodapé, a faixa de avisos e o logo do tema leem daqui (gilda_store()).
 *
 * Os valores padrão são os que estavam escritos no rodapé (semente): enquanto as donas não salvarem
 * a tela, o site mostra exatamente o que mostrava antes.
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

const GILDA_STORE_OPTION = 'gilda_store';

/**
 * Campos da tela, na ordem em que aparecem.
 *
 * @return array<string,array{label:string,help?:string,type?:string,placeholder?:string,default:mixed}>
 */
function gilda_store_fields(): array {
	return array(
		'razao_social' => array( 'label' => 'Razão social', 'default' => 'GILDA ALFAIATARIA LTDA' ),
		'cnpj'         => array( 'label' => 'CNPJ', 'placeholder' => '00.000.000/0000-00', 'default' => '45.822.638/0001-09' ),
		'endereco'     => array( 'label' => 'Endereço', 'help' => 'Rua, número e complemento. Deixe em branco para mostrar só cidade e CEP no rodapé.', 'default' => '' ),
		'cep'          => array( 'label' => 'CEP', 'placeholder' => '00.000-000', 'default' => '82.015-384' ),
		'cidade'       => array( 'label' => 'Cidade', 'default' => 'Curitiba' ),
		'uf'           => array( 'label' => 'Estado (UF)', 'type' => 'uf', 'default' => 'PR' ),
		'telefone'     => array( 'label' => 'Telefone', 'help' => 'Do jeito que deve aparecer no rodapé. O link de ligar é montado sozinho.', 'placeholder' => '(41) 99100-8303', 'default' => '41.991008303' ),
		'whatsapp'     => array( 'label' => 'WhatsApp', 'help' => 'Número com DDD. Usado no botão de WhatsApp do rodapé.', 'placeholder' => '(41) 99100-8303', 'default' => '41991008303' ),
		'email'        => array( 'label' => 'E-mail de contato', 'type' => 'email', 'default' => 'mundogilda@gmail.com' ),
		'instagram'    => array( 'label' => 'Instagram', 'help' => 'Só o nome do perfil, sem o @ (ou cole o link do perfil).', 'placeholder' => 'gildapontoevirgula', 'default' => 'gildapontoevirgula' ),
		'logo'         => array( 'label' => 'Logo', 'type' => 'image', 'help' => 'Aparece no cabeçalho e no rodapé. PNG com fundo transparente, pelo menos 600 px de largura.', 'default' => 0 ),
	);
}

/**
 * Dados da loja (todos, ou um campo).
 *
 * @param string|null $field Campo (ver gilda_store_fields()).
 * @return mixed
 */
function gilda_store( ?string $field = null ) {
	$defaults = wp_list_pluck( gilda_store_fields(), 'default' );
	$data     = array_merge( $defaults, array_intersect_key( (array) get_option( GILDA_STORE_OPTION, array() ), $defaults ) );
	$data     = (array) apply_filters( 'gilda_store', $data );
	return null === $field ? $data : ( $data[ $field ] ?? '' );
}

/** Só os dígitos. */
function gilda_store_digits( string $value ): string {
	return (string) preg_replace( '/\D+/', '', $value );
}

/**
 * Número brasileiro em formato internacional (55 + DDD + número), para tel: e wa.me.
 *
 * @param string $value Telefone como foi digitado.
 * @return string Só dígitos ('' quando vazio).
 */
function gilda_store_e164( string $value ): string {
	$digits = gilda_store_digits( $value );
	if ( '' === $digits ) {
		return '';
	}
	if ( in_array( strlen( $digits ), array( 10, 11 ), true ) ) {
		$digits = '55' . $digits;
	}
	return $digits;
}

/** Link de ligar ('' sem telefone). */
function gilda_store_phone_url(): string {
	$number = gilda_store_e164( (string) gilda_store( 'telefone' ) );
	return $number ? 'tel:+' . $number : '';
}

/** Link do WhatsApp ('' sem número). */
function gilda_store_whatsapp_url(): string {
	$number = gilda_store_e164( (string) gilda_store( 'whatsapp' ) );
	return $number ? 'https://wa.me/' . $number : '';
}

/**
 * Perfil do Instagram sem @ nem link.
 *
 * @param string $value Como foi digitado ("@perfil", "perfil" ou o link).
 * @return string
 */
function gilda_store_instagram_handle( string $value ): string {
	$value = trim( $value );
	if ( preg_match( '~instagram\.com/([A-Za-z0-9._]+)~i', $value, $m ) ) {
		$value = $m[1];
	}
	return (string) preg_replace( '/[^A-Za-z0-9._]/', '', ltrim( $value, '@' ) );
}

/** Link do perfil do Instagram ('' sem perfil). */
function gilda_store_instagram_url(): string {
	$handle = gilda_store_instagram_handle( (string) gilda_store( 'instagram' ) );
	return $handle ? 'https://www.instagram.com/' . $handle . '/' : '';
}

/**
 * Valida o que veio da tela.
 *
 * @param array $input Valores do formulário.
 * @return array
 */
function gilda_store_sanitize( array $input ): array {
	$out = array();
	foreach ( gilda_store_fields() as $key => $field ) {
		$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
		switch ( $field['type'] ?? 'text' ) {
			case 'email':
				$value = sanitize_email( (string) $value );
				break;
			case 'image':
				$value = absint( $value );
				$value = ( $value && wp_attachment_is_image( $value ) ) ? $value : 0;
				break;
			case 'uf':
				$value = strtoupper( sanitize_text_field( (string) $value ) );
				$value = array_key_exists( $value, gilda_store_states() ) ? $value : '';
				break;
			default:
				$value = sanitize_text_field( (string) $value );
		}
		if ( 'instagram' === $key ) {
			$value = gilda_store_instagram_handle( $value );
		}
		$out[ $key ] = $value;
	}
	return $out;
}

/** Estados brasileiros (UF => nome). */
function gilda_store_states(): array {
	return array(
		'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
		'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
		'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
		'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
		'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
		'SE' => 'Sergipe', 'TO' => 'Tocantins',
	);
}

// Rodapé e cabeçalho entram no HTML guardado do catálogo: mudou o dado, limpa.
add_action( 'update_option_' . GILDA_STORE_OPTION, static fn() => function_exists( 'gilda_catalog_cache_flush' ) && gilda_catalog_cache_flush() );
add_action( 'add_option_' . GILDA_STORE_OPTION, static fn() => function_exists( 'gilda_catalog_cache_flush' ) && gilda_catalog_cache_flush() );

/* ---------- Tela ---------- */

add_action(
	'admin_menu',
	static function () {
		$hook = add_menu_page(
			'Dados da loja',
			'Dados da loja',
			'gilda_painel',
			'gilda-dados-da-loja',
			'gilda_store_screen',
			'dashicons-store',
			58
		);
		add_action( 'load-' . $hook, 'gilda_store_screen_load' );
	}
);

/** Salva o formulário e carrega o seletor de mídia. */
function gilda_store_screen_load(): void {
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		check_admin_referer( 'gilda_store_save' );
		if ( ! current_user_can( 'gilda_painel' ) ) {
			wp_die( 'Sem permissão.' );
		}
		update_option( GILDA_STORE_OPTION, gilda_store_sanitize( (array) ( $_POST['gilda_store'] ?? array() ) ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( add_query_arg( 'saved', '1', menu_page_url( 'gilda-dados-da-loja', false ) ) );
		exit;
	}
	wp_enqueue_media();
	gilda_painel_enqueue_ui();
	wp_enqueue_script( 'gilda-painel-media', GILDA_PAINEL_URL . 'assets/painel-media.js', array( 'jquery', 'jquery-ui-sortable' ), gilda_painel_ver( 'assets/painel-media.js' ), true );
}

/** Logo que o site usa hoje (o salvo aqui ou, sem ele, o importado do site antigo). */
function gilda_store_logo_id(): int {
	$id = absint( gilda_store( 'logo' ) );
	if ( $id && wp_attachment_is_image( $id ) ) {
		return $id;
	}
	$images = (array) get_option( 'gilda_site_images', array() );
	$id     = absint( $images['logo'] ?? 0 );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/** Renderiza a tela. */
function gilda_store_screen(): void {
	$data = gilda_store();
	?>
	<div class="wrap gilda-panel">
		<header class="gilda-page-head">
			<h1 class="gilda-display">dados da loja;</h1>
			<p class="gilda-lead">Aparecem no rodapé de todas as páginas, na faixa de avisos (Instagram) e no cabeçalho (logo). Mudou aqui, muda no site inteiro.</p>
		</header>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="gilda-notice gilda-notice--ok notice-success" role="status"><p>Dados salvos. O site já mostra as informações novas.</p></div>
		<?php endif; ?>
		<form method="post" class="gilda-edit">
			<?php wp_nonce_field( 'gilda_store_save' ); ?>
			<div class="gilda-edit__main">
				<section class="gilda-card" aria-labelledby="gilda-store-t">
					<h2 id="gilda-store-t" class="gilda-h2">A loja</h2>
					<div class="gilda-store-form">
					<?php
					foreach ( gilda_store_fields() as $key => $field ) :
						$id    = 'gilda-store-' . $key;
						$name  = 'gilda_store[' . $key . ']';
						$value = $data[ $key ] ?? '';
						$type  = $field['type'] ?? 'text';
						?>
						<div class="gilda-field gilda-field--<?php echo esc_attr( $type ); ?>">
							<?php if ( 'image' === $type ) : ?>
								<span class="gilda-label"><?php echo esc_html( $field['label'] ); ?></span>
								<?php
								$logo = gilda_store_logo_id();
								// Vazio = continua o logo importado do site antigo.
								gilda_photo_field(
									$name,
									$logo ? array( $logo ) : array(),
									1,
									array( 'title' => 'Escolher o logo', 'button' => 'Usar este logo', 'style' => 'logo', 'alt' => false, 'saved' => (bool) $value, 'add' => '+ Escolher logo' )
								);
								?>
							<?php else : ?>
								<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
								<?php if ( 'uf' === $type ) : ?>
									<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
										<option value="">—</option>
										<?php foreach ( gilda_store_states() as $uf => $state ) : ?>
											<option value="<?php echo esc_attr( $uf ); ?>" <?php selected( $value, $uf ); ?>><?php echo esc_html( $uf . ' — ' . $state ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo 'email' === $type ? 'email' : 'text'; ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" />
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( ! empty( $field['help'] ) ) : ?>
								<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					</div>
				</section>
			</div>
			<aside class="gilda-edit__side">
				<section class="gilda-card gilda-publish" aria-labelledby="gilda-store-save-t">
					<h2 id="gilda-store-save-t" class="gilda-h2">Salvar</h2>
					<button type="submit" class="gilda-btn gilda-btn--big">Salvar dados da loja</button>
					<p class="gilda-publish__note">O rodapé, a faixa de avisos e o logo mudam na hora, em todas as páginas.</p>
				</section>
			</aside>
		</form>
	</div>
	<?php
}

/* ---------- Dados da loja dentro dos textos das páginas ---------- */

/**
 * Telefone brasileiro para mostrar: (41) 99100-8303.
 *
 * @param string $value Como foi digitado.
 * @return string
 */
function gilda_store_format_phone( string $value ): string {
	$digits = gilda_store_digits( $value );
	if ( 13 === strlen( $digits ) || 12 === strlen( $digits ) ) {
		$digits = substr( $digits, 2 ); // tira o 55
	}
	if ( 11 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 5 ), substr( $digits, 7 ) );
	}
	if ( 10 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 4 ), substr( $digits, 6 ) );
	}
	return trim( $value );
}

/**
 * [gilda_loja campo=whatsapp link=1] — um dado da tela "Dados da loja" no meio de um texto
 * (políticas, dúvidas, cuidados). Mudou no painel, muda em todas as páginas.
 * Campos: whatsapp, telefone, email, instagram, razao_social, cnpj, endereco, cep, cidade, uf.
 * link=1 vira link (WhatsApp, ligar, e-mail, Instagram).
 *
 * @param array|string $atts Atributos.
 * @return string
 */
function gilda_store_shortcode( $atts ): string {
	$atts  = shortcode_atts( array( 'campo' => '', 'link' => '0' ), (array) $atts, 'gilda_loja' );
	$field = sanitize_key( $atts['campo'] );
	$link  = in_array( (string) $atts['link'], array( '1', 'sim', 'true' ), true );
	switch ( $field ) {
		case 'whatsapp':
			$text = gilda_store_format_phone( (string) gilda_store( 'whatsapp' ) );
			$url  = gilda_store_whatsapp_url();
			break;
		case 'telefone':
			$text = gilda_store_format_phone( (string) gilda_store( 'telefone' ) );
			$url  = gilda_store_phone_url();
			break;
		case 'email':
			$text = (string) gilda_store( 'email' );
			$url  = $text ? 'mailto:' . $text : '';
			break;
		case 'instagram':
			$handle = gilda_store_instagram_handle( (string) gilda_store( 'instagram' ) );
			$text   = $handle ? '@' . $handle : '';
			$url    = gilda_store_instagram_url();
			break;
		default:
			$fields = gilda_store_fields();
			if ( ! isset( $fields[ $field ] ) || 'image' === ( $fields[ $field ]['type'] ?? '' ) ) {
				return '';
			}
			$text = (string) gilda_store( $field );
			$url  = '';
	}
	if ( '' === $text ) {
		return '';
	}
	if ( $link && $url ) {
		$external = str_starts_with( $url, 'http' );
		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url, array( 'http', 'https', 'mailto', 'tel' ) ),
			$external ? ' target="_blank" rel="noopener"' : '',
			esc_html( $text )
		);
	}
	return esc_html( $text );
}
add_shortcode( 'gilda_loja', 'gilda_store_shortcode' );

/** Páginas que usam os dados da loja no texto (docs/legal/*.md). */
function gilda_store_text_pages(): array {
	return array( 'politica-de-privacidade', 'cuidados-com-as-pecas', 'duvidas-frequentes', 'termos-de-uso', 'trocas-e-devolucoes' );
}

/**
 * Uma vez só: nas páginas que já foram criadas (e publicadas) com o telefone, o e-mail e o Instagram
 * escritos à mão, troca SÓ esses textos exatos pelo [gilda_loja …]. O resto do que as donas escreveram
 * não é tocado; um texto que já é link vira o dado sem link (não cria link dentro de link).
 *
 * @return int Páginas alteradas.
 */
function gilda_store_migrate_text_pages(): int {
	$changed = 0;
	foreach ( gilda_store_text_pages() as $slug ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			continue;
		}
		$content = gilda_store_replace_literals( (string) $page->post_content );
		if ( $content !== $page->post_content ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
			++$changed;
		}
	}
	return $changed;
}

/**
 * Troca os textos fixos antigos (fora de atributos HTML) pelos shortcodes.
 *
 * @param string $html Conteúdo da página.
 * @return string
 */
function gilda_store_replace_literals( string $html ): string {
	// O link do Instagram inteiro, como o import do .md gerou.
	$html = preg_replace(
		'~<a href="https://www\.instagram\.com/gildapontoevirgula/?">@gildapontoevirgula</a>~',
		'[gilda_loja campo=instagram link=1]',
		$html
	);
	$literals = array(
		'(41) 99100-8303'      => 'whatsapp',
		'(41) 991008303'       => 'whatsapp',
		'41 99100-8303'        => 'whatsapp',
		'mundogilda@gmail.com' => 'email',
	);
	$parts  = preg_split( '~(<[^>]+>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$in_a   = 0;
	foreach ( $parts as $i => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '~^<a\b~i', $part ) ) {
				++$in_a;
			} elseif ( preg_match( '~^</a>~i', $part ) ) {
				$in_a = max( 0, $in_a - 1 );
			}
			continue; // nunca mexe dentro de uma tag (href="mailto:…" etc.)
		}
		foreach ( $literals as $literal => $field ) {
			$part = str_replace( $literal, '[gilda_loja campo=' . $field . ( $in_a ? '' : ' link=1' ) . ']', $part );
		}
		$parts[ $i ] = $part;
	}
	return implode( '', $parts );
}

add_action(
	'init',
	static function () {
		if ( get_option( 'gilda_store_text_pages_v1' ) ) {
			return;
		}
		update_option( 'gilda_store_text_pages_v1', gmdate( 'c' ), false );
		gilda_store_migrate_text_pages();
	},
	30
);
