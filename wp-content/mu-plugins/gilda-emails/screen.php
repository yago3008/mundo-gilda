<?php
/**
 * Tela "E-mails" do painel (gilda-emails.php): lista de todos os e-mails e a edição de cada um, com prévia.
 *
 * @package gilda
 */

defined( 'ABSPATH' ) || exit;

$gilda_catalog = gilda_emails_catalog();
$gilda_id      = isset( $_GET['email'] ) ? sanitize_key( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$gilda_base    = admin_url( 'admin.php?page=' . GILDA_EMAILS_PAGE );

// Só aparecem os e-mails que existem nesta instalação (os dos Correios dependem do plugin).
$gilda_available = array_filter(
	$gilda_catalog,
	static fn( $meta, $id ) => gilda_emails_is_wp( $id ) || gilda_emails_wc( $id ),
	ARRAY_FILTER_USE_BOTH
);

if ( '' === $gilda_id || ! isset( $gilda_available[ $gilda_id ] ) ) :
	?>
	<div class="wrap gilda-panel gilda-emails">
		<header class="gilda-page-head">
			<h1 class="gilda-display">e-mails;</h1>
			<p class="gilda-lead">Tudo o que o site manda por e-mail. Escolha um para mudar o assunto, o título e o texto, ou para desligar. Todos saem com o logo e as cores da GILDA; e com os contatos dos Dados da loja no rodapé.</p>
		</header>
		<?php
		foreach ( array( 'cliente' => 'Para a cliente', 'loja' => 'Para vocês (a loja)' ) as $gilda_group => $gilda_title ) :
			?>
			<section class="gilda-card gilda-emails__group" aria-labelledby="gilda-emails-<?php echo esc_attr( $gilda_group ); ?>">
				<h2 id="gilda-emails-<?php echo esc_attr( $gilda_group ); ?>" class="gilda-h2"><?php echo esc_html( $gilda_title ); ?></h2>
				<ul class="gilda-emails__list">
					<?php
					foreach ( $gilda_available as $gilda_eid => $gilda_meta ) :
						if ( $gilda_meta[0] !== $gilda_group ) {
							continue;
						}
						$gilda_v  = gilda_emails_values( $gilda_eid );
						$gilda_on = 'no' !== ( $gilda_v['enabled'] ?? 'yes' );
						?>
						<li>
							<a class="gilda-emails__item" href="<?php echo esc_url( add_query_arg( 'email', $gilda_eid, $gilda_base ) ); ?>">
								<span class="gilda-emails__name"><?php echo esc_html( $gilda_meta[1] ); ?></span>
								<span class="gilda-emails__when"><?php echo esc_html( $gilda_meta[2] ); ?></span>
								<span class="gilda-emails__status <?php echo $gilda_on ? 'is-on' : 'is-off'; ?>"><?php echo $gilda_on ? 'Ligado' : 'Desligado'; ?></span>
								<span class="gilda-emails__edit" aria-hidden="true">Editar →</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	return;
endif;

// ---------------- Edição de um e-mail ----------------
$gilda_meta   = $gilda_available[ $gilda_id ];
$gilda_v      = gilda_emails_values( $gilda_id );
$gilda_d      = $gilda_v['defaults'] ?? array();
$gilda_locked = ! empty( $gilda_meta[3] );
$gilda_store  = 'loja' === $gilda_meta[0];
$gilda_wp     = gilda_emails_is_wp( $gilda_id );
$gilda_prev   = wp_nonce_url( add_query_arg( array( 'action' => 'gilda_email_preview', 'email' => $gilda_id ), admin_url( 'admin-post.php' ) ), 'gilda_email_preview' );
$gilda_reset  = wp_nonce_url( add_query_arg( array( 'action' => 'gilda_email_reset', 'email' => $gilda_id ), admin_url( 'admin-post.php' ) ), 'gilda_email_reset' );
$gilda_me     = wp_get_current_user()->user_email;
?>
<div class="wrap gilda-panel gilda-emails">
	<a class="gilda-back" href="<?php echo esc_url( $gilda_base ); ?>">← Todos os e-mails</a>
	<header class="gilda-page-head">
		<p class="gilda-eyebrow"><?php echo $gilda_store ? 'Para vocês (a loja)' : 'Para a cliente'; ?></p>
		<h1 class="gilda-display"><?php echo esc_html( mb_strtolower( $gilda_meta[1] ) ); ?>;</h1>
		<p class="gilda-lead"><?php echo esc_html( $gilda_meta[2] ); ?></p>
	</header>

	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="gilda-notice gilda-notice--ok notice-success" role="status"><p>E-mail salvo. Os próximos já saem assim.</p></div>
	<?php elseif ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="gilda-notice gilda-notice--ok notice-success" role="status"><p>Textos voltaram ao padrão.</p></div>
	<?php elseif ( isset( $_GET['test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php if ( 'ok' === $_GET['test'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="gilda-notice gilda-notice--ok notice-success" role="status"><p>Teste enviado para <?php echo esc_html( $gilda_me ); ?>.</p></div>
		<?php else : ?>
			<div class="gilda-notice gilda-notice--err notice-error" role="alert"><p>Não consegui enviar o teste agora.</p></div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gilda-edit gilda-emails__form" data-preview="<?php echo esc_url( $gilda_prev ); ?>">
		<?php wp_nonce_field( 'gilda_email_form' ); ?>
		<input type="hidden" name="action" value="gilda_email_save">
		<input type="hidden" name="email" value="<?php echo esc_attr( $gilda_id ); ?>">

		<div class="gilda-edit__main">
			<section class="gilda-card" aria-labelledby="gilda-email-texts">
				<h2 id="gilda-email-texts" class="gilda-h2">Textos</h2>

				<div class="gilda-field">
					<label class="gilda-check gilda-emails__toggle">
						<input type="checkbox" name="enabled" value="1" <?php checked( 'no' !== ( $gilda_v['enabled'] ?? 'yes' ) || $gilda_locked ); ?> <?php disabled( $gilda_locked ); ?>>
						<span><?php echo $gilda_locked ? 'Sempre ligado (a cliente precisa dele para entrar na conta)' : 'Ligado: o site manda este e-mail'; ?></span>
					</label>
				</div>

				<?php if ( $gilda_store ) : ?>
					<div class="gilda-field">
						<label for="gilda-email-recipient">Quem recebe</label>
						<input type="text" id="gilda-email-recipient" name="recipient" value="<?php echo esc_attr( $gilda_v['recipient'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" data-live>
						<p class="gilda-muted">Mais de um e-mail: separe com vírgula.</p>
					</div>
				<?php endif; ?>

				<div class="gilda-field">
					<label for="gilda-email-subject">Assunto</label>
					<input type="text" id="gilda-email-subject" name="subject" value="<?php echo esc_attr( $gilda_v['subject'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $gilda_d['subject'] ?? '' ); ?>" data-live>
				</div>
				<div class="gilda-field">
					<label for="gilda-email-heading">Título (o texto grande no topo)</label>
					<input type="text" id="gilda-email-heading" name="heading" value="<?php echo esc_attr( $gilda_v['heading'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $gilda_d['heading'] ?? '' ); ?>" data-live>
				</div>
				<div class="gilda-field">
					<label for="gilda-email-content"><?php echo $gilda_wp ? 'Texto do e-mail' : 'Texto extra (aparece depois das informações do pedido)'; ?></label>
					<textarea id="gilda-email-content" name="content" rows="<?php echo $gilda_wp ? 8 : 5; ?>" placeholder="<?php echo esc_attr( $gilda_d['content'] ?? '' ); ?>" data-live><?php echo esc_textarea( $gilda_v['content'] ?? '' ); ?></textarea>
					<?php if ( ! $gilda_wp ) : ?>
						<p class="gilda-muted">O resumo do pedido (peças, valores, endereço) entra sozinho; aqui vai o recado de vocês.</p>
					<?php endif; ?>
				</div>

				<?php $gilda_ph = gilda_emails_placeholders( $gilda_id ); ?>
				<?php if ( $gilda_ph ) : ?>
					<div class="gilda-emails__placeholders">
						<p class="gilda-label">Dá para usar (o site troca na hora de enviar):</p>
						<p>
							<?php foreach ( $gilda_ph as $gilda_tag ) : ?>
								<code><?php echo esc_html( $gilda_tag ); ?></code>
							<?php endforeach; ?>
						</p>
					</div>
				<?php endif; ?>
				<p class="gilda-muted">Campo vazio = usa o texto padrão (o que aparece clarinho dentro do campo).</p>
			</section>

			<section class="gilda-card gilda-emails__preview" aria-labelledby="gilda-email-preview-t">
				<div class="gilda-card__head">
					<h2 id="gilda-email-preview-t" class="gilda-h2">Prévia</h2>
					<span class="gilda-muted">com um pedido de exemplo · atualiza enquanto você digita</span>
				</div>
				<iframe class="gilda-emails__frame" title="Prévia do e-mail" src="<?php echo esc_url( $gilda_prev ); ?>"></iframe>
			</section>
		</div>

		<aside class="gilda-edit__side">
			<section class="gilda-card gilda-publish" aria-labelledby="gilda-email-save-t">
				<h2 id="gilda-email-save-t" class="gilda-h2">Salvar</h2>
				<button type="submit" class="gilda-btn gilda-btn--big gilda-btn--block">Salvar e-mail</button>
				<button type="submit" name="action" value="gilda_email_test" class="gilda-btn gilda-btn--ghost gilda-btn--block">Enviar teste para mim</button>
				<p class="gilda-publish__note">O teste vai para <?php echo esc_html( $gilda_me ); ?>, com os textos que estão na tela (mesmo sem salvar).</p>
				<p><a class="gilda-link" href="<?php echo esc_url( $gilda_reset ); ?>" onclick="return confirm('Voltar o assunto, o título e o texto deste e-mail para o padrão?');">Voltar aos textos padrão</a></p>
			</section>
		</aside>
	</form>
</div>
