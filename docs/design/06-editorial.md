# 06 · Editorial

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Conteudo) · Epics 2, 8, 9

## Hero com cupom (2.1)

- Coluna creme à esquerda com `;` terracota gigante + foto com desfoque artístico nas bordas.
- Faixa terracota em forma de bandeirola (ponta recortada à direita), EB Garamond 21:
  "Use o cupom para 10% off na sua primeira compra pelo site: **primeiracompra**" — texto editável no CMS.
- CTA pill "disponível" → `/category/all-products`.
- Mobile: faixa vira bloco acima da foto; `;` lateral some; CTA centralizado.

## Destaques (2.2)

3 cards (imagem 3:4 + nome em Fraunces 600, sem preço), configuráveis no admin, lazy loading.

## Categorias visuais (2.3)

- 4 tiles 4:5 (Camisas, Calças, Bermudas, Lenços) com nome em Fraunces 22 creme, tracking .15em, na base.
- Véu inferior para garantir 4.5:1.
- Hover: foto escurece 15% + zoom 1.03 + nome sublinhado.
- Abaixo: "ALFAIATARIA CRIATIVA" em Archivo Black 28 terracota.
- Grid 4 / 2 / 1.

## Loja (2.4) e Instagram (2.5)

- Bloco branco centralizado: "LOJA" (Archivo Black, tracking .3em) → ícone borboleta 88 (link para Produtos;) → logo.
- "@gildapontoevirgula ↗" abre o perfil em nova aba.

## Headings temáticos (3.3)

"produtos;" · "capítulos" · "histórias" · "palavras" · "ALFAIATARIA" — mistura de Fraunces itálico, EB Garamond espaçado e Archivo Black, em terracota.
**Decorativos:** `aria-hidden="true"`, nunca `<h*>`.

## Quem Cria (8.1)

1. Manchete "ALFAIATARIA CRIATIVA"
2. Bloco `--color-marrom`, texto creme:
   - "quem **CRIA**" (Fraunces itálico + Archivo Black)
   - Duas colunas: foto 3:4 + nome manuscrito-like em Fraunces itálico ("giulia", "maria clara") + bio em EB Garamond 17/26
3. Encerramento: "VOCÊ CONTA A HISTÓRIA" + logo

Todo o conteúdo editável no CMS.

## Livro GILDA; (9.1 – 9.4)

- Título "LIVRO GILDA;" em `display/book`.
- Cada capítulo = bloco linho com:
  - Rótulo vertical "CAPÍTULO N" (Fraunces 34, `writing-mode: vertical-rl`)
  - Galeria 4×2 de miniaturas 3:4 (botões "Abrir imagem N de 8")
  - Link "peças disponíveis" → produto(s) da coleção
  - Narrativa em EB Garamond 16/25
- Cap. 1 Borboleta 88 · Cap. 2 Ritual brasileiro · Cap. 3 Coleção atual (links: Camisa Grão, Bermuda Envolto, Camisa Casulo).

## Lightbox

- Overlay `rgba(36,26,22,.92)`, imagem centralizada, contador "3 / 8".
- Botões 44 px: fechar (×), anterior, próxima.
- `role="dialog"` · Esc fecha · setas do teclado navegam · foco preso no diálogo e devolvido à miniatura ao fechar.
