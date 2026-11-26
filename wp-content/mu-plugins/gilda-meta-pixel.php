<?php
/**
 * Plugin Name: GILDA; Meta Pixel
 * Description: Pixel da Meta (Facebook/Instagram) com eventos de e-commerce: PageView, ViewContent, AddToCart, InitiateCheckout e Purchase.
 *
 * Configuração (variáveis de ambiente):
 *   META_PIXEL_ID       ID do pixel (o mesmo do site Wix: 2143468076438110).
 *   META_PIXEL_DEBUG=1  Fora de produção, mostra os eventos no console do navegador em vez de enviar à Meta.
 *
 * O pixel só envia eventos de verdade quando WP_ENVIRONMENT_TYPE = production, para que testes
 * locais/staging não sujem os dados de anúncios. Conversions API (servidor) fica para depois:
 * precisa de um token gerado no Gerenciador de Eventos da Meta.
 */

defined( 'ABSPATH' ) || exit;

final class Gilda_Meta_Pixel {

	private const SESSION_KEY = 'gilda_pixel_queue';

	public static function boot(): void {
		$id = (string) getenv( 'META_PIXEL_ID' );
		if ( '' === $id || ! ctype_digit( $id ) ) {
			return;
		}
		$live  = 'production' === wp_get_environment_type();
		$debug = ! $live && (bool) getenv( 'META_PIXEL_DEBUG' );
		if ( ! $live && ! $debug ) {
			return;
		}

		$self = new self( $id, $live );
		add_action( 'wp_head', array( $self, 'base_code' ), 1 );
		add_action( 'wp_footer', array( $self, 'page_events' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( $self, 'queue_add_to_cart' ), 10, 6 );
	}

	private function __construct( private string $id, private bool $live ) {}

	/** Código base do pixel (ou um stub que loga no console, em modo debug). */
	public function base_code(): void {
		if ( $this->live ) {
			?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?php echo wp_json_encode( $this->id ); ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $this->id ); ?>&ev=PageView&noscript=1"/></noscript>
<!-- /Meta Pixel -->
			<?php
		} else {
			?>
<script>window.fbq=function(){console.info('[Meta Pixel debug]',...arguments);(window.__gildaPixel=window.__gildaPixel||[]).push([...arguments]);};fbq('init', <?php echo wp_json_encode( $this->id ); ?>);fbq('track','PageView');</script>
			<?php
		}
	}

	/** Eventos da página atual + eventos enfileirados (ex.: AddToCart feito via AJAX/Store API). */
	public function page_events(): void {
		$events = array();

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$events[] = array( 'ViewContent', $this->product_payload( $product, 1 ) );
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$ids = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				$ids[] = $this->content_id( $item['data'] );
			}
			$events[] = array(
				'InitiateCheckout',
				array(
					'content_ids'  => $ids,
					'content_type' => 'product',
					'num_items'    => WC()->cart->get_cart_contents_count(),
					'value'        => (float) WC()->cart->get_total( 'edit' ),
					'currency'     => get_woocommerce_currency(),
				),
			);
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			$order_id = absint( get_query_var( 'order-received' ) );
			$order    = $order_id ? wc_get_order( $order_id ) : null;
			$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $order && hash_equals( $order->get_order_key(), $key ) && ! $order->get_meta( '_gilda_pixel_purchase_sent' ) ) {
				$ids = array();
				foreach ( $order->get_items() as $item ) {
					$ids[] = $this->content_id( $item->get_product() );
				}
				$events[] = array(
					'Purchase',
					array(
						'content_ids'  => array_values( array_filter( $ids ) ),
						'content_type' => 'product',
						'num_items'    => $order->get_item_count(),
						'value'        => (float) $order->get_total(),
						'currency'     => $order->get_currency(),
					),
					array( 'eventID' => 'order-' . $order->get_id() ), // deduplicação com a futura Conversions API
				);
				$order->update_meta_data( '_gilda_pixel_purchase_sent', time() );
				$order->save();
			}
		}

		if ( WC()->session ) {
			foreach ( (array) WC()->session->get( self::SESSION_KEY, array() ) as $queued ) {
				$events[] = $queued;
			}
			WC()->session->set( self::SESSION_KEY, array() );
		}

		if ( ! $events ) {
			return;
		}
		echo "<script>\n";
		foreach ( $events as $e ) {
			$args = array( 'track', $e[0], $e[1] );
			if ( ! empty( $e[2] ) ) {
				$args[] = $e[2];
			}
			echo 'fbq(' . implode( ',', array_map( 'wp_json_encode', $args ) ) . ");\n";
		}
		echo "</script>\n";
	}

	/** AddToCart: enfileira na sessão e dispara no próximo carregamento de página. */
	public function queue_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0 ): void {
		if ( ! WC()->session ) {
			return;
		}
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) {
			return;
		}
		$queue   = (array) WC()->session->get( self::SESSION_KEY, array() );
		$queue[] = array( 'AddToCart', $this->product_payload( $product, (int) $quantity ) );
		WC()->session->set( self::SESSION_KEY, $queue );
	}

	private function product_payload( WC_Product $product, int $qty ): array {
		return array(
			'content_ids'  => array( $this->content_id( $product ) ),
			'content_name' => $product->get_name(),
			'content_type' => 'product',
			'value'        => (float) wc_get_price_to_display( $product ) * max( 1, $qty ),
			'currency'     => get_woocommerce_currency(),
		);
	}

	private function content_id( $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		// Sempre o produto "pai" (a variação vai no content_name), para casar com o catálogo da Meta.
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		return $parent->get_sku() ?: (string) $parent->get_id();
	}
}

add_action( 'init', array( 'Gilda_Meta_Pixel', 'boot' ) );
