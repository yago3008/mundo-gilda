<?php
/**
 * Semente das páginas legais e institucionais a partir de docs/legal/*.md. Roda no setup.sh.
 *
 *   docker compose run --rm wpcli wp eval-file /scripts/import-legal-pages.php --user=admin
 *
 * - Cada .md tem um cabeçalho com titulo, slug e opcao_wp (a opção do WordPress/WooCommerce que aponta
 *   para a página, ex.: wp_page_for_privacy_policy). A página é achada por essa opção ou pelo slug.
 * - Página PUBLICADA nunca é tocada: depois de publicada, quem manda é o painel (as donas editam lá).
 * - Página que não existe é criada; em rascunho, é atualizada com o .md. Com `status: publicar` no
 *   cabeçalho ela é publicada nessa hora (primeira publicação) e, daí em diante, não é mais sobrescrita.
 *   Sem `status: publicar`, fica em RASCUNHO (o texto do git ainda tem [PREENCHER]).
 * - Não define woocommerce_terms_page_id: com a página em rascunho, o checkout linkaria para um 404.
 *   Depois de publicar os termos, ligue em WooCommerce → Configurações → Avançado → Termos e condições.
 * - Converte só o Markdown que esses arquivos usam (títulos, parágrafos, listas, citação, negrito,
 *   `código` e links) para blocos do editor.
 */

defined( 'ABSPATH' ) || exit;

$dir   = '/legal';
$files = glob( $dir . '/*.md' ) ?: array();
if ( ! $files ) {
	WP_CLI::error( "Nenhum .md em $dir. O serviço wpcli monta ./docs/legal em /legal (docker-compose.yml)." );
}

$inline = static function ( string $text ): string {
	$text = esc_html( $text );
	$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
	$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
	return preg_replace_callback(
		'/\[([^\]]+)\]\(([^)\s]+)\)/',
		static function ( $m ) {
			$url = html_entity_decode( $m[2] );
			$url = str_starts_with( $url, '/' ) ? home_url( $url ) : $url;
			return '<a href="' . esc_url( $url ) . '">' . $m[1] . '</a>';
		},
		$text
	);
};

$block = static function ( string $name, string $html, array $attrs = array() ): string {
	$json = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
	return "<!-- wp:$name$json -->\n$html\n<!-- /wp:$name -->";
};

$to_blocks = static function ( string $md ) use ( $inline, $block ): string {
	$out    = array();
	$chunks = preg_split( '/\n\s*\n/', trim( str_replace( "\r", '', $md ) ) );
	foreach ( $chunks as $chunk ) {
		$lines = explode( "\n", trim( $chunk ) );
		if ( preg_match( '/^(#{1,4})\s+(.*)$/', $lines[0], $m ) && 1 === count( $lines ) ) {
			$level = strlen( $m[1] );
			if ( 1 === $level ) {
				continue; // o H1 é o título da página
			}
			$out[] = $block( 'heading', "<h$level class=\"wp-block-heading\">" . $inline( $m[2] ) . "</h$level>", 2 === $level ? array() : array( 'level' => $level ) );
		} elseif ( str_starts_with( $lines[0], '>' ) ) {
			$text  = implode( ' ', array_map( static fn( $l ) => ltrim( substr( $l, 1 ) ), $lines ) );
			$p     = $block( 'paragraph', '<p>' . $inline( $text ) . '</p>' );
			$out[] = $block( 'quote', "<blockquote class=\"wp-block-quote\">$p</blockquote>" );
		} elseif ( preg_match( '/^(-|\d+\.)\s/', $lines[0], $m ) ) {
			$ordered = '-' !== $m[1];
			$items   = array();
			foreach ( $lines as $line ) {
				if ( preg_match( '/^(?:-|\d+\.)\s+(.*)$/', $line, $im ) ) {
					$items[] = $im[1];
				} elseif ( $items ) {
					$items[ count( $items ) - 1 ] .= ' ' . trim( $line );
				}
			}
			$tag   = $ordered ? 'ol' : 'ul';
			$lis   = implode( '', array_map( static fn( $i ) => $block( 'list-item', '<li>' . $inline( $i ) . '</li>' ), $items ) );
			$out[] = $block( 'list', "<$tag class=\"wp-block-list\">$lis</$tag>", $ordered ? array( 'ordered' => true ) : array() );
		} else {
			$out[] = $block( 'paragraph', '<p>' . $inline( implode( ' ', array_map( 'trim', $lines ) ) ) . '</p>' );
		}
	}
	return implode( "\n\n", $out );
};

foreach ( $files as $file ) {
	if ( 'README.md' === basename( $file ) ) {
		continue;
	}
	$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! preg_match( '/^---\n(.*?)\n---\n(.*)$/s', str_replace( "\r", '', $raw ), $fm ) ) {
		WP_CLI::warning( basename( $file ) . ': sem cabeçalho (titulo/slug/opcao_wp); pulando.' );
		continue;
	}
	$meta = array();
	foreach ( explode( "\n", $fm[1] ) as $line ) {
		if ( preg_match( '/^(\w+):\s*(.*)$/', $line, $m ) ) {
			$meta[ $m[1] ] = trim( $m[2] );
		}
	}
	$title  = $meta['titulo'] ?? basename( $file, '.md' );
	$slug   = $meta['slug'] ?? sanitize_title( $title );
	$option  = $meta['opcao_wp'] ?? '';
	$publish = 'publicar' === ( $meta['status'] ?? '' );

	$page = null;
	if ( $option && ( $id = (int) get_option( $option ) ) ) {
		$page = get_post( $id );
	}
	if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
		$page = get_page_by_path( $slug );
	}

	if ( $page && 'publish' === $page->post_status ) {
		WP_CLI::log( "\"{$page->post_title}\" (#{$page->ID}) já está publicada: não sobrescrevo (edite pelo painel)." );
		if ( $option && 'woocommerce_terms_page_id' !== $option && (int) get_option( $option ) !== (int) $page->ID ) {
			update_option( $option, (int) $page->ID );
		}
		continue;
	}

	$postarr = array(
		'post_type'    => 'page',
		'post_status'  => $publish ? 'publish' : 'draft',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $to_blocks( $fm[2] ),
	);
	if ( $page ) {
		$postarr['ID'] = $page->ID;
		$id            = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$id = wp_insert_post( wp_slash( $postarr ), true );
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( basename( $file ) . ': ' . $id->get_error_message() );
		continue;
	}
	if ( $option && 'woocommerce_terms_page_id' !== $option && (int) get_option( $option ) !== (int) $id ) {
		update_option( $option, (int) $id );
	}
	WP_CLI::log( sprintf( '%s "%s" (#%d, %s): %s', $page ? 'Atualizada' : 'Criada', $title, $id, $publish ? 'publicada' : 'rascunho', $publish ? get_permalink( $id ) : admin_url( "post.php?post=$id&action=edit" ) ) );
}
WP_CLI::success( 'Páginas conferidas. Publicadas são editadas no painel; as em rascunho: revise, apague o aviso de RASCUNHO, preencha os [PREENCHER] e marque `status: publicar` no .md.' );
