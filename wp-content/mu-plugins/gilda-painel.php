<?php
/**
 * Plugin Name: GILDA; Painel das donas
 * Description: Perfil "Dona da loja" com menu enxuto e o visual da GILDA; (login, Início com números da loja), tela "Dados da loja" (empresa, contatos, redes, logo), Capítulos (tipo de conteúdo gilda_capitulo, com tela de edição própria e o campo "Capítulo" nas peças) e a tela simplificada "Nova peça" (dados no WooCommerce).
 *
 * Código em wp-content/mu-plugins/gilda-painel/. Plano e decisões: docs/development/painel-conteudo.md.
 * Guia das donas: docs/painel-gilda.md.
 *
 * Para o tema (todas com function_exists, o tema funciona sem este plugin):
 *   gilda_store( 'campo' )            dados da loja (razao_social, cnpj, endereco, cep, cidade, uf, telefone,
 *                                     whatsapp, email, instagram, logo) — gilda-painel/store-data.php
 *   gilda_chapters()                  capítulos publicados, em ordem — gilda-painel/chapters.php
 *   gilda_chapter_by_anchor( 'capitulo-3' ), gilda_product_chapter( $product_id )
 */

defined( 'ABSPATH' ) || exit;

define( 'GILDA_PAINEL_DIR', __DIR__ . '/gilda-painel/' );
define( 'GILDA_PAINEL_URL', plugins_url( 'gilda-painel/', __FILE__ ) );
const GILDA_PAINEL_VERSION = '1.1.0';
const GILDA_PECA_PAGE      = 'gilda-peca';

/** Versão de um arquivo de assets/ (muda quando o arquivo muda: o navegador não usa o antigo). */
function gilda_painel_ver( string $file ): string {
	return GILDA_PAINEL_VERSION . '.' . (int) @filemtime( GILDA_PAINEL_DIR . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

require GILDA_PAINEL_DIR . 'store-data.php';
require GILDA_PAINEL_DIR . 'chapters.php';
require GILDA_PAINEL_DIR . 'sku.php';
require GILDA_PAINEL_DIR . 'categorias.php';
require GILDA_PAINEL_DIR . 'promocao.php';
require GILDA_PAINEL_DIR . 'role.php';
require GILDA_PAINEL_DIR . 'login.php';
require GILDA_PAINEL_DIR . 'traducoes.php';

if ( is_admin() ) {
	require GILDA_PAINEL_DIR . 'admin-skin.php';
	require GILDA_PAINEL_DIR . 'home.php';
	require GILDA_PAINEL_DIR . 'chapters-admin.php';
	require GILDA_PAINEL_DIR . 'peca.php';
}
