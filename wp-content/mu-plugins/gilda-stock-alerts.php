<?php
/**
 * Plugin Name: GILDA; Avise-me quando chegar
 * Description: Formulário "avise-me" para produto/variação esgotada, lista de espera por tamanho no admin (WooCommerce → Avise-me) e e-mail automático (WooCommerce → Configurações → E-mails → "Avise-me: voltou ao estoque") quando o estoque volta.
 *
 * Código em wp-content/mu-plugins/gilda-stock-alerts/. Marcação, classes e hooks para o tema:
 * docs/development/backend-para-frontend.md (seção "Avise-me").
 */

defined( 'ABSPATH' ) || exit;

define( 'GILDA_STOCK_ALERTS_DIR', __DIR__ . '/gilda-stock-alerts/' );
define( 'GILDA_STOCK_ALERTS_URL', plugins_url( 'gilda-stock-alerts/', __FILE__ ) );
const GILDA_STOCK_ALERTS_VERSION = '1.1.1';

require GILDA_STOCK_ALERTS_DIR . 'store.php';
require GILDA_STOCK_ALERTS_DIR . 'frontend.php';
require GILDA_STOCK_ALERTS_DIR . 'sender.php';
require GILDA_STOCK_ALERTS_DIR . 'privacy.php';

if ( is_admin() ) {
	require GILDA_STOCK_ALERTS_DIR . 'admin.php';
}
