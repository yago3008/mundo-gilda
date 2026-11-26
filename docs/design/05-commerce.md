# 05 · Commerce

[← Índice](README.md) · [Artboard (interativo)](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Produto) · Epics 3, 4, 5 · Task 11.2

## Card de produto (3.2 · 4.6 · 11.2)

```
┌──────────────┐
│              │  imagem 3:4 (link → PDP)
│   [ 👁 ]     │  quick view (hover/foco; sempre visível no touch)
│ [ADICIONAR]  │  ação AJAX, não navega
└──────────────┘
CAMISA GRÃO       Fraunces 600 14, caixa alta, tracking 2px
R$ 429,00         Jost 14, tracking 1px
```

| Estado | Visual |
|--------|--------|
| Padrão | Só foto, nome, preço |
| Hover / foco | Revela "Adicionar ao carrinho" (faixa terracota na base) + botão quick view |
| Esgotado | Badge "ESGOTADO" creme no topo, foto com véu creme 35%, botão "Esgotado" desabilitado, nome e preço em grafite |

Preço: `Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })`.

## Botões

| Nível | Exemplo | Estilo |
|-------|---------|--------|
| Primário | Comprar, Finalizar compra | Sólido terracota, texto creme, 52 px · hover `#4E1A08` |
| Secundário | Adicionar ao carrinho, Calcular, Aplicar | Contorno 1.5 px terracota, texto terracota |
| Desabilitado | Esgotado | Fundo linho, texto `#555`, `disabled` |
| CTA editorial | "disponível" | Pill, EB Garamond itálico 20 |
| Link editorial | "peças disponíveis" | EB Garamond itálico, sublinhado |

Uma única ação primária por tela.

## Carrossel (3.1 · 5.12)

- Setas circulares 44 px: "Produto anterior" (contorno) · "Próximo produto" (sólido).
- Navegação cíclica, transição 300 ms, swipe no touch.
- Desktop 3–4 · tablet 2 · mobile 1.

## Filtros — All Products (Epic 4)

Sidebar branca, 300 px, seções separadas por linha areia.

| Seção | Controle | Detalhes |
|-------|----------|----------|
| Buscar por (4.1) | Lista de links | Todos, Bermudas, Calças, Camisas, Lenços · ativo = terracota 600 sublinhado |
| Tipo de produto (4.2) | Checkbox (acordeão) | Múltipla seleção |
| Preço (4.3) | Range slider duplo | R$ 97 – R$ 569 · handles 18 px com `role="slider"` e `aria-valuenow` |
| Cor (4.4) | Checkbox + swatch + nome | 7 cores (ver [Cores](01-cores.md)) |
| Tamanho (4.5) | Botões toggle em grid 5 col | 36–44, PP–GG · indisponível = tracejado + riscado + `disabled` |

- Contagem de resultados com `role="status"` ("7 produtos").
- **Estado vazio:** `;` grande + "Nenhum produto encontrado" + "Essa história ainda não foi escrita. Tente outros filtros." + botão "Limpar filtros".
- Mobile: bottom sheet "Filtrar" (ver [Responsivo](09-responsivo.md)).

## PDP — Galeria (5.1)

- Imagem principal 4:5 · botão "Ampliar" (zoom) · seta "Próxima imagem".
- Thumbs 72×90, ativa com borda 2 px terracota.
- Lazy loading; full-width no mobile com swipe.

## PDP — Buy box (5.2 – 5.11)

Ordem dos blocos (coluna de 440 px, gap 22):

1. **Nome** — `<h1>` Fraunces 36/42
2. **Preço** — Jost 20, com label "Preço" acessível (sr-only)
3. **Descrição** — EB Garamond 18/28, editável no CMS
4. **Composição** — destaque em fundo linho ("100% algodão egípcio")
5. **Tamanhos em centímetros** — tabela PP→GG com busto e cintura + "Modelo veste tamanho PP."
6. **COR** — `role="radio"`, botões 44 px com swatch + nome; selecionado = borda 2 px terracota
7. **TAMANHO** — `<select>` com placeholder "Selecionar"; esgotados `disabled` com sufixo "— Esgotado"
8. **Quantidade + Adicionar** — stepper (− desabilitado em 1, máx. = estoque) ao lado do botão secundário
9. **Comprar** — primário, full-width
10. **Frete** — ver abaixo
11. **Compartilhar** — WhatsApp, Facebook, X, Pinterest (ícones 44 px com `aria-label`)

### Validação

| Situação | Mensagem (`role="alert"`, `#A31621` + ícone "!") |
|----------|-----------------------------------------------|
| Sem cor e sem tamanho | "Selecione a cor e o tamanho antes de continuar." |
| Sem cor | "Selecione uma cor." |
| Sem tamanho | "Selecione um tamanho." (borda do select fica vermelha) |

Sucesso: toast tinta "✓ Produto adicionado ao carrinho · ver carrinho" + badge do header pulsa.

### Simulador de frete (5.10)

- Título "Calcule seu frete" · input "CEP" com máscara `00000-000` · botão "Calcular".
- Link "Não sei meu CEP" → `https://buscacepinter.correios.com.br` (nova aba).
- Estados: carregando (botão com spinner) · erro "CEP inválido. Digite os 8 números" · resultado em lista (PAC / SEDEX com prazo e valor).
