<?php
/**
 * Textos do WordPress que o pacote pt_BR ainda não traduz (WordPress 7.1 mudou vários rótulos:
 * "Add New Page" virou "Add Page", "Page updated." etc.). Sem isto, o painel das donas mostrava
 * avisos e botões em inglês: "Page updated.", "Add Page", "1 item", ajuda do formulário de
 * categorias/cores/tamanhos, filtros da biblioteca de mídia, erro de foto HEIC.
 *
 * Só traduz quando o WordPress devolveu o texto em inglês (sem tradução). Se o pacote oficial passar
 * a traduzir, a tradução oficial vale e esta lista não faz nada. Domínio "default" (núcleo).
 *
 * @package gilda-painel
 */

defined( 'ABSPATH' ) || exit;

/** Inglês → português. */
function gilda_core_translations(): array {
	return array(
		// Páginas e posts (títulos, botões, avisos de "salvo", biblioteca de mídia).
		'Add Page'                   => 'Adicionar página',
		'Add Post'                   => 'Adicionar post',
		'New Page'                   => 'Nova página',
		'New Post'                   => 'Novo post',
		'Edit Page'                  => 'Editar página',
		'Edit Post'                  => 'Editar post',
		'View Page'                  => 'Ver página',
		'View Pages'                 => 'Ver páginas',
		'View Post'                  => 'Ver post',
		'View Posts'                 => 'Ver posts',
		'All Pages'                  => 'Todas as páginas',
		'All Posts'                  => 'Todos os posts',
		'Search Pages'               => 'Pesquisar páginas',
		'Search Posts'               => 'Pesquisar posts',
		'No pages found.'            => 'Nenhuma página encontrada.',
		'No pages found in Trash.'   => 'Nenhuma página encontrada na lixeira.',
		'No posts found.'            => 'Nenhum post encontrado.',
		'No posts found in Trash.'   => 'Nenhum post encontrado na lixeira.',
		'Parent Page:'               => 'Página ascendente:',
		'Page Attributes'            => 'Atributos da página',
		'Post Attributes'            => 'Atributos do post',
		'Page Archives'              => 'Arquivo de páginas',
		'Post Archives'              => 'Arquivo de posts',
		'Insert into page'           => 'Inserir na página',
		'Insert into post'           => 'Inserir no post',
		'Uploaded to this page'      => 'Enviados para esta página',
		'Uploaded to this post'      => 'Enviados para este post',
		'Filter pages list'          => 'Filtrar a lista de páginas',
		'Filter posts list'          => 'Filtrar a lista de posts',
		'Pages list'                 => 'Lista de páginas',
		'Pages list navigation'      => 'Navegação da lista de páginas',
		'Posts list'                 => 'Lista de posts',
		'Posts list navigation'      => 'Navegação da lista de posts',
		'Page published.'            => 'Página publicada.',
		'Page published privately.'  => 'Página publicada como privada.',
		'Page reverted to draft.'    => 'A página voltou a ser rascunho.',
		'Page scheduled.'            => 'Página agendada.',
		'Page trashed.'              => 'Página movida para a lixeira.',
		'Page updated.'              => 'Página atualizada.',
		'Post published.'            => 'Post publicado.',
		'Post published privately.'  => 'Post publicado como privado.',
		'Post reverted to draft.'    => 'O post voltou a ser rascunho.',
		'Post scheduled.'            => 'Post agendado.',
		'Post trashed.'              => 'Post movido para a lixeira.',
		'Post updated.'              => 'Post atualizado.',
		'Revision'                   => 'Revisão',
		'Revisions'                  => 'Revisões',
		'Filter by date'             => 'Filtrar por data',
		'Filter by category'         => 'Filtrar por categoria',
		'Items list navigation'      => 'Navegação da lista de itens',
		'Single item: %s'            => 'Item: %s',
		'Show or hide panel'         => 'Mostrar ou ocultar o painel',
		'Short Description'          => 'Descrição curta',
		'Theme'                      => 'Tema',
		'Themes'                     => 'Temas',
		'Templates'                  => 'Modelos',
		'Template Parts'             => 'Partes de modelo',
		'Edit Template'              => 'Editar modelo',
		'Edit Template Part'         => 'Editar parte de modelo',

		// Mídia.
		'Add Media File'             => 'Adicionar arquivo de mídia',
		'Edit Media'                 => 'Editar mídia',
		'View Media File'            => 'Ver arquivo de mídia',
		'Attachment Attributes'      => 'Atributos do anexo',
		'Image Caption'              => 'Legenda da imagem',
		'Viewing media item: %s'     => 'Vendo o item de mídia: %s',
		'The server cannot process HEIC images. Convert it to JPEG before uploading.' => 'O servidor não consegue processar fotos HEIC (formato do iPhone). Converta para JPEG antes de enviar.',

		// Categorias, cores e tamanhos (formulário e lista de termos).
		'Add Category'               => 'Adicionar categoria',
		'Add Tag'                    => 'Adicionar tag',
		'All Categories'             => 'Todas as categorias',
		'All Tags'                   => 'Todas as tags',
		'Edit Category'              => 'Editar categoria',
		'Edit Tag'                   => 'Editar tag',
		'New Category Name'          => 'Nome da nova categoria',
		'New Tag Name'               => 'Nome da nova tag',
		'No categories'              => 'Nenhuma categoria',
		'No categories found.'       => 'Nenhuma categoria encontrada.',
		'No tags'                    => 'Nenhuma tag',
		'No tags found.'             => 'Nenhuma tag encontrada.',
		'Parent Category'            => 'Categoria ascendente',
		'Parent Category:'           => 'Categoria ascendente:',
		'Popular Tags'               => 'Tags populares',
		'Search Categories'          => 'Pesquisar categorias',
		'Search Tags'                => 'Pesquisar tags',
		'Separate tags with commas'  => 'Separe as tags com vírgulas',
		'Add or remove tags'         => 'Adicionar ou remover tags',
		'Choose from the most used tags' => 'Escolha entre as tags mais usadas',
		'Update Category'            => 'Atualizar categoria',
		'Update Tag'                 => 'Atualizar tag',
		'View Category'              => 'Ver categoria',
		'View Tag'                   => 'Ver tag',
		'Categories list'            => 'Lista de categorias',
		'Categories list navigation' => 'Navegação da lista de categorias',
		'Tags list'                  => 'Lista de tags',
		'Tags list navigation'       => 'Navegação da lista de tags',
		'&larr; Go to Categories'    => '&larr; Ir para as categorias',
		'&larr; Go to Tags'          => '&larr; Ir para as tags',
		'The name is how it appears on your site.' => 'O nome é como ele aparece no site.',
		'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' => 'O &#8220;slug&#8221; é o nome do jeito que vai no endereço (URL): normalmente só letras minúsculas, números e hifens.',
		'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.' => 'Escolha um item acima deste para criar uma hierarquia. Por exemplo: "Camisas" acima de "Camisas de linho".',
		'The description is not prominent by default; however, some themes may show it.' => 'A descrição normalmente não aparece no site, mas alguns temas podem mostrá-la.',
	);
}

add_filter(
	'gettext',
	static function ( $translation, $text, $domain ) {
		if ( 'default' !== $domain || $translation !== $text ) {
			return $translation;
		}
		static $map = null;
		$map = $map ?? gilda_core_translations();
		return $map[ $text ] ?? $translation;
	},
	20,
	3
);

/** Contadores das listas ("1 item", "3 items"). */
add_filter(
	'ngettext',
	static function ( $translation, $single, $plural, $number, $domain ) {
		if ( 'default' !== $domain || ( $translation !== $single && $translation !== $plural ) ) {
			return $translation;
		}
		if ( '%s item' === $single && '%s items' === $plural ) {
			return 1 === (int) $number ? '%s item' : '%s itens';
		}
		return $translation;
	},
	20,
	5
);

/*
 * WooCommerce 11.3: textos do mini-carrinho (o carrinho que abre no cabeçalho) sem tradução pt_BR.
 */
const GILDA_TRADUCOES_VERSION = '1';

add_filter(
	'gettext_woocommerce',
	static function ( $translation, $text ) {
		$map = array( 'Your cart is empty' => 'Seu carrinho está vazio' );
		return ( $translation === $text && isset( $map[ $text ] ) ) ? $map[ $text ] : $translation;
	},
	20,
	2
);
add_filter(
	'gettext_with_context_woocommerce',
	static function ( $translation, $text, $context ) {
		if ( $translation === $text && 'Updating' === $text ) {
			return 'Atualizando';
		}
		return $translation;
	},
	20,
	3
);
// O botão de fechar o mini-carrinho vem com aria-label="Close" escrito direto no HTML do WooCommerce.
add_filter(
	'render_block_woocommerce/mini-cart-contents',
	static fn( $html ) => str_replace( 'aria-label="Close"', 'aria-label="Fechar"', (string) $html )
);

// As páginas da loja guardadas no cache foram geradas com os textos em inglês: limpa uma vez.
add_action(
	'init',
	static function () {
		if ( GILDA_TRADUCOES_VERSION !== get_option( 'gilda_traducoes_version' ) && function_exists( 'gilda_catalog_cache_flush' ) ) {
			gilda_catalog_cache_flush();
			update_option( 'gilda_traducoes_version', GILDA_TRADUCOES_VERSION, false );
		}
	}
);
