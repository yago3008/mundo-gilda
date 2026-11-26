# GILDA; — Design System

> Alfaiataria criativa de Curitiba. Estrutura limpa e fotográfica (inspirada em janafavoreto.com.br) com a voz da GILDA;: creme, terracota, serifas editoriais e o ponto e vírgula como marca de "a história continua".

**Fonte da verdade visual:** [Canvas GILDA; — System Design](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax)
Os arquivos `.md` desta pasta traduzem o canvas em regras para o código. Se houver conflito, abra uma issue com a label `design`.

## Índice

| # | Documento | Epics / tasks |
|---|-----------|---------------|
| 01 | [Cores](01-cores.md) | 11.4 |
| 02 | [Tipografia](02-tipografia.md) | 11.3 |
| 03 | [Grid, breakpoints e formas](03-grid-breakpoints.md) | 11.1 |
| 04 | [Navegação e footer](04-navegacao.md) | Epic 1, Epic 10 |
| 05 | [Commerce](05-commerce.md) | Epics 3, 4, 5 · 11.2 |
| 06 | [Editorial](06-editorial.md) | Epics 2, 8, 9 |
| 07 | [Carrinho e conta](07-carrinho-conta.md) | Epics 6, 7 · 11.5 |
| 08 | [Templates de página](08-templates.md) | Todas |
| 09 | [Responsivo](09-responsivo.md) | 1.2 · 11.1 |

## Princípios

1. **Foto primeiro.** A interface some; a cor vem das imagens. Sem cards com sombra.
2. **Creme e terracota.** Creme domina, terracota marca momentos (header, CTA, footer).
3. **Serifa conta a história.** Fraunces e EB Garamond para títulos e narrativa; Jost para interface.
4. **Ângulos retos.** Raio 0 por padrão. Pill só no CTA "disponível"; círculo só em swatch e badge.
5. **O `;` é a marca.** Logo, headings temáticos ("produtos;"), divisores, estados vazios.
6. **Acessível por padrão.** Contraste ≥ 4.5:1, alvos ≥ 44×44 px, foco visível, labels em tudo.

## Herança da Jana Favoreto

| Mantido | Alterado para GILDA; |
|---------|----------------------|
| Header em 3 zonas (menu · logo · ações) | Faixa terracota fixa no lugar do branco |
| Imagens em proporção vertical, sem moldura | 3:4 no lugar de 2:3 |
| Nome de produto em caixa alta espaçada | Fraunces 600 no lugar de Quicksand |
| Container 1170 px, gap 30 px | Breakpoints do backlog (768 / 1024) |
| Botão de contorno | Hierarquia: Comprar (sólido) › Adicionar (contorno) |

## Como usar

- Tokens em código: `src/styles/tokens.css` (nunca digite hex à mão).
- Cada issue linka o artboard correspondente, ex.: `…#artboard-Produto`.
- PRs que mudam UI anexam print desktop + mobile.
