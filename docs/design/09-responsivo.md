# 09 · Responsivo

[← Índice](README.md) · [Artboard (interativo)](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Mobile) · Tasks 1.2 · 11.1

## Header mobile/tablet (< 1024 px)

`[☰]  GILDA;  [👤][🛍]` — altura 56 px, todos os ícones 44×44.

## Drawer (1.2)

| Propriedade | Valor |
|-------------|-------|
| Entrada | Pela esquerda, 250 ms ease-out |
| Largura | 300 px |
| Fundo | Creme, sombra `8px 0 24px rgba(36,26,22,.25)` |
| Overlay | `rgba(36,26,22,.55)` |
| Itens | GILDA; · PRODUTOS; · LIVRO GILDA; · CONTATO — EB Garamond 24, 56 px de altura, divisores areia |
| Rodapé | Telefone e Instagram |

Comportamento:
- `role="dialog"`, `aria-expanded` no botão hambúrguer.
- Fecha com ×, Esc, toque no overlay ou ao tocar num item (que também navega).
- Foco preso no drawer enquanto aberto; volta ao hambúrguer ao fechar.
- Scroll do body travado.

## Filtros mobile (4.1)

- Botão "Filtrar" abre **bottom sheet** branco com alça.
- Acordeões: Tipo de produto, Preço, Cor (swatches 32 px), Tamanho.
- Rodapé: "Limpar" (link) + "Ver N produtos" (primário).

## Ajustes por página

| Página | Mobile |
|--------|--------|
| Início | Faixa do cupom acima da foto; destaques em carrossel com espiada do próximo card |
| All Products | Grid 2 col, gap 8 |
| Produto | Galeria full-width com swipe; "Adicionar" + "Comprar" em barra fixa no rodapé |
| Carrinho | Resumo fixo no rodapé com total + "Finalizar compra" |
| Livro GILDA; | Rótulo do capítulo vira horizontal; galeria 2×4 |

## Tablet (768–1023 px)

Grid 3 col · categorias 2 col · filtros em painel lateral deslizante · carrinho com resumo abaixo dos itens.

## Checklist

- [ ] Sem scroll horizontal em 320 px
- [ ] Texto legível sem zoom (mín. 13 px; inputs 16 px para evitar zoom no iOS)
- [ ] Alvos ≥ 44×44
- [ ] Imagens com `srcset`
