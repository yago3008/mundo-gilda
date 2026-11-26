# 02 · Tipografia

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Tipografia)

O site atual (Wix) usa 8 famílias. O novo usa 4.

## Famílias

| Família | Papel | Pesos | Substitui |
|---------|-------|-------|-----------|
| **Fraunces** | Display: títulos, nome de produto, logo | 300–600 + itálico | Fraunces, Cormorant, fontes Wix |
| **EB Garamond** | Leitura: descrições, capítulos, cupom | 400–500 + itálico | EB Garamond |
| **Jost** | Interface: labels, preço, inputs, botões | 300–500 | Avenir, Poppins (Jana) |
| **Archivo Black** | Manchete: menu, "ALFAIATARIA CRIATIVA", "LOJA" | 400 | Arial Black |

```html
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=EB+Garamond:ital,wght@0,400..700;1,400..700&family=Jost:wght@300..600&family=Archivo+Black&display=swap" rel="stylesheet">
```

## Escala

| Token | Fonte | Tamanho / altura | Extras | Onde |
|-------|-------|------------------|--------|------|
| `display/book` | Fraunces 300 | 80/80 | caixa alta, tracking .08em | "LIVRO GILDA;" |
| `display/editorial` | Fraunces 300 itálico | 56/56 | caixa baixa | "quem cria", "produtos;", "próximos capítulos:" |
| `h1/produto` | Fraunces 400 | 36/42 | — | Nome na PDP |
| `h2/manchete` | Archivo Black | 28/32 | tracking .03em, terracota | "ALFAIATARIA CRIATIVA" |
| `h2/secao` | Fraunces 400 | 20/24 | caixa alta, tracking .2em | "CAPÍTULO 1", "CONTATO" |
| `nav/primary` | Archivo Black | 14 | tracking 1.2px | Menu desktop |
| `body/editorial` | EB Garamond 400 | 19/30 | — | Descrição, narrativa |
| `produto/nome` | Fraunces 600 | 14/18 | caixa alta, tracking 2px | Cards |
| `produto/preco` | Jost 400 | 14/18 | tracking 1px | Cards |
| `ui/label` | Jost 500 | 11 | caixa alta, tracking 2px | "COR", "TAMANHO" |
| `ui/botao` | Jost 500 | 13–14 | caixa alta, tracking 1.5px | Botões |
| `ui/small` | Jost 300 | 13/20 | — | Legendas, ajuda |
| `link/editorial` | EB Garamond itálico | 20 | sublinhado, offset 5px | "peças disponíveis" |

## Regras

- **Mínimo 13 px** para qualquer texto (a Jana usava 8 px nas legendas da Press — não repetir).
- **Hierarquia semântica:** um `<h1>` por página; headings decorativos (3.3) levam `aria-hidden="true"` e não são `<h*>`.
- Caixa alta sempre via `text-transform`, nunca digitada — leitores de tela soletram.
- Itálico do Fraunces = voz da marca; não usar para ênfase em corpo de texto.
