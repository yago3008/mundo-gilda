# 08 · Templates de página

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Templates)

Toda página = **Skip link → Header sticky → `<main id="conteudo">` → Footer**.

| Template | URL atual | URL sugerida (11.3) | Estrutura do `<main>` |
|----------|-----------|---------------------|-----------------------|
| Início | `/` | `/` | Hero + cupom → 3 destaques → 4 categorias → ALFAIATARIA CRIATIVA → Loja → Instagram |
| Produtos; | `/contact-1` | `/produtos` | Headings temáticos + carrossel cíclico |
| All Products | `/category/all-products` | `/produtos/todos` | Título → sidebar de filtros + grid 4 col |
| Produto | `/product-page/{slug}` | `/produto/{slug}` | Breadcrumb → galeria + buy box → relacionados |
| Carrinho | `/cart-page` | `/carrinho` | "próximos capítulos:" → itens + resumo (ou estado vazio) |
| Quem Cria | `/contact` | `/quem-cria` | Manchete → bloco marrom com 2 bios → encerramento |
| Livro GILDA; | `/contact-2` | `/livro-gilda` | Título → Capítulos 1, 2, 3 (galeria + narrativa + links) |
| Conta | — | `/entrar`, `/cadastro`, `/recuperar-senha` | Formulário único centralizado |
| Checkout | — | `/checkout`, `/pedido/{id}` | Header reduzido → dados + pagamento + resumo → confirmação |

> Ao trocar URLs, criar redirects 301 das antigas.

## SEO por template (11.3)

| Template | `<title>` | Schema |
|----------|-----------|--------|
| Início | GILDA; — Alfaiataria criativa | `Organization` |
| All Products | Todos os produtos · GILDA; | `ItemList` |
| Produto | {Produto} · GILDA; | `Product` + `Offer` |
| Livro GILDA; | Livro GILDA; · capítulos | `Article` |
| Quem Cria | Quem cria · GILDA; | `AboutPage` |

Meta description única por página; `alt` descritivo em toda imagem; sitemap.xml automático.
