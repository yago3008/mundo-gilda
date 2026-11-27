<?php
/**
 * Plugin Name: GILDA; SEO básico
 * Description: Meta description, Open Graph/Twitter (prévia no WhatsApp/Instagram), sitemap limpo e bloqueio de indexação fora de produção (requisito 11.3).
 *
 * A descrição pode ser escrita à mão em cada página/produto (caixa "Descrição para o Google" no editor)
 * e em cada categoria (campo na tela da categoria). Sem ela, é gerada a partir do texto.
 * Título, URL canônica, sitemap.xml e schema de Produto/Oferta/Breadcrumb já vêm do WordPress/WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

final class Gilda_SEO {

	private const META_KEY = '_gilda_meta_description';
	private const MAX_LEN  = 158;

	public static function boot(): void {
		$self = new self();

		// Fora de produção: não indexar (local, staging).
		if ( 'production' !== wp_get_environment_type() ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
			add_filter( 'robots_txt', static fn() => "User-agent: *\nDisallow: /\n", 99 );
		}

		add_action( 'wp_head', array( $self, 'head_tags' ), 2 );

		// Sitemap: sem usuários (expunha logins), sem blog/categorias de post (não usamos), sem páginas de sistema.
		add_filter(
			'wp_sitemaps_add_provider',
			static fn( $provider, $name ) => 'users' === $name ? false : $provider,
			10,
			2
		);
		add_filter( 'wp_sitemaps_post_types', static function ( $types ) {
			unset( $types['post'] );
			return $types;
		} );
		add_filter( 'wp_sitemaps_taxonomies', static function ( $taxes ) {
			unset( $taxes['category'], $taxes['post_tag'], $taxes['product_tag'] );
			return $taxes;
		} );
		add_filter( 'wp_sitemaps_posts_query_args', array( $self, 'exclude_system_pages' ), 10, 2 );
		add_filter( 'wp_robots', array( $self, 'noindex_system_pages' ) );

		// Filtros da loja geram infinitas combinações de URL: não deixar o Google rastrear.
		add_filter( 'robots_txt', static fn( $txt ) => $txt . "Disallow: /*?*filter_\nDisallow: /*?*min_price=\nDisallow: /*?*orderby=\n", 20 );

		// Campos de edição no painel.
		add_action( 'add_meta_boxes', array( $self, 'add_meta_box' ) );
		add_action( 'save_post', array( $self, 'save_meta_box' ) );
		add_action( 'product_cat_edit_form_fields', array( $self, 'term_field' ) );
		add_action( 'edited_product_cat', array( $self, 'save_term_field' ) );
	}

	/* ---------------------------------------------------------------- head */

	public function head_tags(): void {
		$data = $this->current();
		if ( ! $data ) {
			return;
		}
		$tags = array(
			array( 'name', 'description', $data['description'] ),
			array( 'property', 'og:locale', 'pt_BR' ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'property', 'og:type', $data['type'] ),
			array( 'property', 'og:title', $data['title'] ),
			array( 'property', 'og:description', $data['description'] ),
			array( 'property', 'og:url', $data['url'] ),
			array( 'name', 'twitter:card', $data['image'] ? 'summary_large_image' : 'summary' ),
		);
		if ( $data['image'] ) {
			$tags[] = array( 'property', 'og:image', $data['image'] );
		}
		if ( ! empty( $data['price'] ) ) {
			$tags[] = array( 'property', 'product:price:amount', $data['price'] );
			$tags[] = array( 'property', 'product:price:currency', get_woocommerce_currency() );
			$tags[] = array( 'property', 'product:availability', $data['availability'] );
		}
		echo "\n<!-- GILDA; SEO -->\n";
		foreach ( $tags as [ $attr, $key, $value ] ) {
			if ( '' !== (string) $value ) {
				printf( '<meta %s="%s" content="%s" />' . "\n", $attr, esc_attr( $key ), esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	}

	/** Dados da página atual: título, descrição, URL, imagem, tipo e preço. */
	private function current(): ?array {
		$site = get_bloginfo( 'name' );

		if ( is_front_page() ) {
			$desc = $this->manual( get_option( 'page_on_front' ) )
				?: 'GILDA; — alfaiataria criativa feita em Curitiba. Camisas, calças, bermudas e lenços autorais em capítulos.';
			return $this->pack( $site . ' — ' . get_bloginfo( 'description' ), $desc, home_url( '/' ), $this->image( (int) get_option( 'page_on_front' ) ) );
		}

		// Livro (/capitulos/): um capítulo é compartilhado como /capitulos/#capitulo-N e o "#" não chega ao
		// servidor. A prévia mostra o capítulo mais novo: foto principal e o começo do texto.
		$chapter = is_page( 'capitulos' ) ? $this->latest_chapter() : null;
		if ( $chapter ) {
			$id   = get_queried_object_id();
			$desc = $this->manual( $id )
				?: $this->excerpt( sprintf( 'Capítulo %d — %s. %s', $chapter['number'], $chapter['name'], (string) ( $chapter['text'][0] ?? '' ) ) );
			return $this->pack( get_the_title( $id ) . ' — ' . $site, $desc, get_permalink( $id ), $this->image( $id ) );
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( ! $product ) {
				return null;
			}
			$desc = $this->manual( $product->get_id() ) ?: $this->excerpt( $product->get_short_description() ?: $product->get_description() );
			$data = $this->pack( $product->get_name() . ' — ' . $site, $desc, get_permalink( $product->get_id() ), $this->image( $product->get_id() ), 'product' );
			$data['price']        = wc_format_decimal( $product->get_price(), 2 );
			$data['availability'] = $product->is_in_stock() ? 'instock' : 'oos';
			return $data;
		}

		if ( is_tax( 'product_cat' ) ) {
			$term = get_queried_object();
			$desc = get_term_meta( $term->term_id, self::META_KEY, true )
				?: ( $this->excerpt( term_description( $term ) ) ?: sprintf( '%s da GILDA; — alfaiataria criativa feita em Curitiba.', $term->name ) );
			$thumb = get_term_meta( $term->term_id, 'thumbnail_id', true );
			return $this->pack( $term->name . ' — ' . $site, $desc, get_term_link( $term ), $thumb ? wp_get_attachment_image_url( (int) $thumb, 'large' ) : '' );
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop = wc_get_page_id( 'shop' );
			$desc = $this->manual( $shop ) ?: 'Loja GILDA; — camisas, calças, bermudas e lenços de alfaiataria criativa. Frete para todo o Brasil.';
			return $this->pack( get_the_title( $shop ) . ' — ' . $site, $desc, get_permalink( $shop ), $this->image( $shop ) );
		}

		if ( is_singular() ) {
			$id   = get_queried_object_id();
			$desc = $this->manual( $id ) ?: $this->excerpt( get_post_field( 'post_excerpt', $id ) ?: get_post_field( 'post_content', $id ) );
			return $this->pack( get_the_title( $id ) . ' — ' . $site, $desc ?: get_bloginfo( 'description' ), get_permalink( $id ), $this->image( $id ) );
		}

		return null;
	}

	private function pack( string $title, string $desc, string $url, string $image, string $type = 'website' ): array {
		return array( 'title' => wp_strip_all_tags( $title ), 'description' => $desc, 'url' => $url, 'image' => $image, 'type' => $type );
	}

	private function manual( $post_id ): string {
		return $post_id ? trim( (string) get_post_meta( (int) $post_id, self::META_KEY, true ) ) : '';
	}

	/**
	 * Foto da prévia: a imagem destacada; sem ela, a foto principal do capítulo mais novo (a cara da
	 * coleção atual); sem capítulo, o ícone do site.
	 */
	private function image( int $post_id ): string {
		$url = $post_id ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
		if ( ! $url ) {
			$chapter = $this->latest_chapter();
			$url     = $chapter && $chapter['main'] ? wp_get_attachment_image_url( (int) $chapter['main'], 'large' ) : '';
		}
		if ( ! $url && has_site_icon() ) {
			$url = get_site_icon_url( 512 );
		}
		return (string) $url;
	}

	/** Capítulo publicado mais novo (painel das donas), ou null. */
	private function latest_chapter(): ?array {
		$chapters = function_exists( 'gilda_chapters' ) ? gilda_chapters() : array();
		return $chapters ? end( $chapters ) : null;
	}

	/** Texto limpo, cortado em ~158 caracteres sem quebrar palavra. */
	private function excerpt( string $html ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( mb_strlen( $text ) <= self::MAX_LEN ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, self::MAX_LEN - 1 );
		$cut = preg_replace( '/\s+\S*$/u', '', $cut );
		return rtrim( $cut, ' ,.;:' ) . '…';
	}

	/* ---------------------------------------------------------- sitemap/robots */

	private function system_page_ids(): array {
		$ids = array();
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$ids[] = wc_get_page_id( $page );
		}
		return array_filter( $ids, static fn( $id ) => $id > 0 );
	}

	public function exclude_system_pages( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $this->system_page_ids() );
		}
		return $args;
	}

	public function noindex_system_pages( $robots ) {
		if ( is_page( $this->system_page_ids() ) || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) || is_search() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/* ------------------------------------------------------------ admin UI */

	public function add_meta_box(): void {
		foreach ( array( 'page', 'product' ) as $type ) {
			add_meta_box( 'gilda_seo', 'Descrição para o Google', array( $this, 'render_meta_box' ), $type, 'side', 'low' );
		}
	}

	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'gilda_seo', 'gilda_seo_nonce' );
		$value = get_post_meta( $post->ID, self::META_KEY, true );
		printf(
			'<textarea name="gilda_meta_description" rows="4" style="width:100%%" maxlength="%d" placeholder="Opcional. Se vazio, usamos o começo do texto.">%s</textarea><p class="description">Até %d caracteres. Aparece no Google e na prévia do WhatsApp.</p>',
			(int) self::MAX_LEN,
			esc_textarea( (string) $value ),
			(int) self::MAX_LEN
		);
	}

	public function save_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['gilda_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gilda_seo_nonce'] ) ), 'gilda_seo' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$value = isset( $_POST['gilda_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gilda_meta_description'] ) ) : '';
		'' === $value ? delete_post_meta( $post_id, self::META_KEY ) : update_post_meta( $post_id, self::META_KEY, mb_substr( $value, 0, self::MAX_LEN ) );
	}

	public function term_field( WP_Term $term ): void {
		wp_nonce_field( 'gilda_seo_term', 'gilda_seo_term_nonce' );
		printf(
			'<tr class="form-field"><th scope="row"><label for="gilda_meta_description">Descrição para o Google</label></th><td><textarea name="gilda_meta_description" id="gilda_meta_description" rows="3" maxlength="%d">%s</textarea><p class="description">Opcional, até %d caracteres.</p></td></tr>',
			(int) self::MAX_LEN,
			esc_textarea( (string) get_term_meta( $term->term_id, self::META_KEY, true ) ),
			(int) self::MAX_LEN
		);
	}

	public function save_term_field( int $term_id ): void {
		if ( ! isset( $_POST['gilda_seo_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gilda_seo_term_nonce'] ) ), 'gilda_seo_term' ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		$value = isset( $_POST['gilda_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gilda_meta_description'] ) ) : '';
		'' === $value ? delete_term_meta( $term_id, self::META_KEY ) : update_term_meta( $term_id, self::META_KEY, mb_substr( $value, 0, self::MAX_LEN ) );
	}
}

add_action( 'plugins_loaded', array( 'Gilda_SEO', 'boot' ) );
