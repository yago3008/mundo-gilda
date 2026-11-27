<?php
/**
 * Plugin Name: GILDA; Painel das donas
 * Description: Perfil "Dona da loja" com menu enxuto (Painel GILDA;), tela "Dados da loja" (empresa, contatos, redes, logo) e Capítulos (tipo de conteúdo gilda_capitulo, com tela de edição própria e o campo "Capítulo" nas peças).
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
const GILDA_PAINEL_VERSION = '1.0.0';

require GILDA_PAINEL_DIR . 'store-data.php';
require GILDA_PAINEL_DIR . 'chapters.php';
require GILDA_PAINEL_DIR . 'role.php';

if ( is_admin() ) {
	require GILDA_PAINEL_DIR . 'chapters-admin.php';
}
