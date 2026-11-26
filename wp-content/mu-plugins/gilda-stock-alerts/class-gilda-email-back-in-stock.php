<?php
/**
 * E-mail "Avise-me: voltou ao estoque" (WooCommerce → Configurações → E-mails).
 *
 * Templates: gilda-stock-alerts/templates/emails/gilda-back-in-stock.php (+ plain/).
 * O tema pode sobrescrever em wp-content/themes/gilda/woocommerce/emails/gilda-back-in-stock.php.
 */

defined( 'ABSPATH' ) || exit;

class Gilda_Email_Back_In_Stock extends WC_Email {

	/** @var object|null Pedido de aviso (linha da tabela) sendo enviado. */
	public $alert = null;

	public function __construct() {
		$this->id             = 'gilda_back_in_stock';
		$this->customer_email = true;
		$this->title          = __( 'Avise-me: voltou ao estoque', 'gilda' );
		$this->description    = __( 'Enviado a quem pediu "avise-me" quando o produto/tamanho esgotado volta ao estoque. Um único e-mail por pedido, com link para a peça e para cancelar.', 'gilda' );
		$this->template_html  = 'emails/gilda-back-in-stock.php';
		$this->template_plain = 'emails/plain/gilda-back-in-stock.php';
		$this->template_base  = GILDA_STOCK_ALERTS_DIR . 'templates/';
		$this->placeholders   = array(
			'{product_name}' => '',
		);
		parent::__construct();
	}

	public function get_default_subject() {
		return __( '{product_name} voltou ao estoque', 'gilda' );
	}

	public function get_default_heading() {
		return __( 'Voltou!', 'gilda' );
	}

	public function get_default_additional_content() {
		return __( 'São poucas peças de cada tamanho, então se gostou, não deixa pra depois.', 'gilda' );
	}

	/**
	 * Envia o aviso de um pedido.
	 *
	 * @param object $alert Linha de {prefix}gilda_stock_alerts.
	 */
	public function trigger( $alert ): bool {
		$this->setup_locale();
		$this->alert     = $alert;
		$this->object    = wc_get_product( $alert->variation_id ?: $alert->product_id );
		$this->recipient = $alert->email;
		$sent            = false;

		if ( $this->object && $this->is_enabled() && $this->get_recipient() ) {
			$this->placeholders['{product_name}'] = wp_strip_all_tags( $this->product_title() );
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
		return $sent;
	}

	/**
	 * Nome do produto sem cor/tamanho (o nome da variação já inclui os atributos).
	 */
	protected function product_title(): string {
		$product = $this->object;
		if ( $product instanceof WC_Product_Variation ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return $parent ? $parent->get_name() : $product->get_name();
		}
		return $product instanceof WC_Product ? $product->get_name() : '';
	}

	protected function template_args( bool $plain ): array {
		$product = $this->object instanceof WC_Product ? $this->object : null;
		return array(
			'product'            => $product,
			'product_title'      => $this->product_title(),
			'variation_text'     => $product instanceof WC_Product_Variation ? wc_get_formatted_variation( $product, true, true, false ) : '',
			'product_url'        => $product ? $product->get_permalink() : home_url( '/' ),
			'cancel_url'         => $this->alert ? gilda_stock_alerts_cancel_url( $this->alert ) : home_url( '/' ),
			'requested_at'       => $this->alert ? gilda_stock_alerts_format_date( $this->alert->created_at ) : '',
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}
