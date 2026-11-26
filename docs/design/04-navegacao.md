# 04 · Navegação e footer

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Navegacao) · Epic 1 · Epic 10

## Skip to content (1.7)

- Primeiro elemento focável da página: "Ir para o conteúdo principal".
- Oculto até receber foco via Tab; leva o foco para `<main id="conteudo">`.

## Header (1.1 · 1.3 · 1.4 · 1.5)

```
[ INÍCIO  PRODUTOS  QUEM CRIA  CAPÍTULOS ]   [ GILDA; ]   [ 👤 Entrar  🛍 ]
```

| Propriedade | Valor |
|-------------|-------|
| Altura | 72 px desktop · 56 px mobile |
| Fundo | `--color-terracota` · sobre o hero da home: transparente com véu |
| Posição | `position: sticky; top: 0; z-index: 100` |
| Menu | Archivo Black 14, tracking 1.2px, creme |
| Logo | Centralizado, link para `/`, `alt="GILDA;"` |

### Estados do link de menu

| Estado | Visual |
|--------|--------|
| Padrão | creme |
| Hover | sublinhado, offset 6px |
| Ativo | borda inferior 2 px creme + `aria-current="page"` |
| Foco | outline 2 px creme, offset 4px |

### Login (1.4)

| Estado | Visual | aria-label |
|--------|--------|------------|
| Deslogado | Ícone de usuário + "Entrar" | "Entrar na minha conta" |
| Logado | Círculo creme com inicial + "Olá, {nome}" | "Minha conta, {nome} conectada" |

### Carrinho (1.5)

- Badge numérico creme/terracota, oculto quando 0.
- Animação pulse 300 ms ao mudar.
- `aria-label="Carrinho com {n} itens"` · link para `/cart-page`.

## Breadcrumb (1.6)

`GILDA; › All Products › Camisa Grão`

- Só na PDP. Último item é `<span aria-current="page">`, não link.
- Jost 13; links terracota sublinhados; separador `›` com `aria-hidden`.
- Mobile: mostra só "‹ All Products".

## Divisor de marca

Linha 1 px areia + `;` em Fraunces terracota no centro. Separa seções editoriais.

## Footer (10.1 · 10.2 · 10.3)

**Bloco terracota**

| Coluna | Conteúdo |
|--------|----------|
| CONTATO | 41.991008303 (`tel:`) · mundogilda@gmail.com (`mailto:`) · @gildapontoevirgula (nova aba) |
| Empresa | GILDA ALFAIATARIA LTDA · CNPJ 45.822.638/0001-09 · Curitiba/PR · CEP 82.015-384 |
| Marca | `;` grande decorativo |

**Faixa creme**

- "Aceitamos os seguintes métodos de pagamento:" + Visa, Mastercard, Pix (SVGs oficiais com `alt`).
- "© 2006 by GILDA;" (+ crédito da plataforma, se aplicável).

Footer idêntico em todas as páginas — implementar como um único componente.
