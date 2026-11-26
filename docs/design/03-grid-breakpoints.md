# 03 · Grid, breakpoints e formas

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Layout) · Task 11.1

## Breakpoints

| Nome | Faixa | Margem | Gutter | Menu |
|------|-------|--------|--------|------|
| `mobile` | < 768 px | 16 | 8 | Hambúrguer |
| `tablet` | 768–1023 px | 32 | 24 | Hambúrguer |
| `desktop` | ≥ 1024 px | container 1170 centralizado | 30 | Completo |

```css
@media (min-width: 768px)  { /* tablet */ }
@media (min-width: 1024px) { /* desktop */ }
```

## Matriz de componentes

| Componente | Desktop | Tablet | Mobile |
|------------|---------|--------|--------|
| Destaques da home | 3 cards | Carrossel 2 | Carrossel 1 |
| Categorias visuais | 4 col | 2 col | 1 col |
| Carrossel Produtos; | 3–4 cards + setas | 2 | 1 + swipe |
| All Products | Sidebar 240 + grid 4 | Painel deslizante + grid 3 | Bottom sheet + grid 2 |
| PDP | Galeria 7 col · info 5 col | Empilhado | Galeria full-width, CTAs fixos |
| Carrinho | Itens 8 col · resumo 4 col | Resumo abaixo | Resumo fixo no rodapé |

## Espaçamento (base 4)

| Token | px | Uso |
|-------|----|-----|
| `--space-1` | 4 | Ícone ↔ texto |
| `--space-2` | 8 | Gap mobile, swatches |
| `--space-3` | 12 | Label ↔ campo |
| `--space-4` | 16 | Margem mobile |
| `--space-6` | 24 | Blocos da buy box |
| `--space-grid` | 30 | Gap do grid desktop |
| `--space-12` | 48 | Entre grupos |
| `--space-24` | 96 | Entre seções |

## Formas

| Elemento | Raio | Borda |
|----------|------|-------|
| Padrão (botões, inputs, imagens) | 0 | — |
| CTA "disponível" | pill (`999px`) | 1.5 px terracota |
| Swatch, badge, setas de carrossel | 50% | 1 px taupe / 1.5 px terracota |
| Divisores | — | 1 px `--color-areia` |
| Botões secundários, campo em foco | 0 | 1.5 px `--color-terracota` |

Sem sombras, exceto drawer e modal (`8px 0 24px rgba(36,26,22,.25)`).

## Camadas (z-index)

| Camada | z |
|--------|---|
| Header sticky | 100 |
| Dropdown / filtro | 200 |
| Overlay + drawer | 300 |
| Modal / quick view / lightbox | 400 |
| Toast | 500 |

## Proporções de imagem

| Uso | Proporção |
|-----|-----------|
| Card de produto | 3:4 |
| Galeria PDP | 4:5 |
| Categoria visual | 4:5 |
| Hero | full-bleed, altura 480–720 |

Sempre `srcset`/`<picture>` e `loading="lazy"` fora da primeira dobra.

## Toque

Todo elemento clicável ≥ **44×44 px** (ícones, setas, +/−, swatches no mobile = 32 px visuais dentro de área de 44).
