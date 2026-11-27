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
	wp_enqueue_style( 'gilda-painel-admin', GILDA_PAINEL_URL . 'assets/painel-admin.css', array(), GILDA_PAINEL_VERSION );
	wp_enqueue_script( 'gilda-painel-media', GILDA_PAINEL_URL . 'assets/painel-media.js', array( 'jquery', 'jquery-ui-sortable' ), GILDA_PAINEL_VERSION, true );
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
		<h1>Dados da loja</h1>
		<p class="gilda-panel__intro">Estes dados aparecem no rodapé de todas as páginas, na faixa de avisos (Instagram) e no cabeçalho (logo). Mudou aqui, muda no site inteiro.</p>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Dados salvos. O site já mostra as informações novas.</p></div>
		<?php endif; ?>
		<form method="post" class="gilda-panel__form">
			<?php wp_nonce_field( 'gilda_store_save' ); ?>
			<div class="gilda-panel__card">
				<?php
				foreach ( gilda_store_fields() as $key => $field ) :
					$id    = 'gilda-store-' . $key;
					$name  = 'gilda_store[' . $key . ']';
					$value = $data[ $key ] ?? '';
					$type  = $field['type'] ?? 'text';
					?>
					<div class="gilda-field gilda-field--<?php echo esc_attr( $type ); ?>">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php if ( 'uf' === $type ) : ?>
							<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
								<option value="">—</option>
								<?php foreach ( gilda_store_states() as $uf => $state ) : ?>
									<option value="<?php echo esc_attr( $uf ); ?>" <?php selected( $value, $uf ); ?>><?php echo esc_html( $uf . ' — ' . $state ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'image' === $type ) : ?>
							<?php $logo = gilda_store_logo_id(); ?>
							<div class="gilda-photos gilda-photos--single" data-gilda-photos data-name="<?php echo esc_attr( $name ); ?>" data-max="1" data-title="Escolher o logo" data-button="Usar este logo" data-alt="0">
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="" /><?php // vazio = logo importado do site antigo ?>
								<ul class="gilda-photos__list">
									<?php if ( $logo ) : ?>
										<li class="gilda-photo" data-id="<?php echo esc_attr( $logo ); ?>">
											<img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $logo, 'medium' ) ); ?>" alt="" />
											<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ? $logo : '' ); ?>" />
											<button type="button" class="gilda-photo__remove" aria-label="Tirar o logo">×</button>
										</li>
									<?php endif; ?>
								</ul>
								<button type="button" class="button gilda-photos__add" id="<?php echo esc_attr( $id ); ?>"><?php echo $logo ? 'Trocar logo' : 'Escolher logo'; ?></button>
							</div>
						<?php else : ?>
							<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo 'email' === $type ? 'email' : 'text'; ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" class="regular-text" />
						<?php endif; ?>
						<?php if ( ! empty( $field['help'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="gilda-panel__actions"><button type="submit" class="button button-primary button-hero">Salvar dados da loja</button></p>
		</form>
	</div>
	<?php
}
