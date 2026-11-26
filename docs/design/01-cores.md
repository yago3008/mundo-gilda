# 01 · Cores

[← Índice](README.md) · [Artboard](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Cores)

Paleta quase neutra: a cor forte vem da fotografia. Creme substitui o branco, terracota substitui o preto.

## Principais

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-terracota` | `#77270C` | Header, footer, CTA primário, manchetes |
| `--color-creme` | `#F7F5E9` | Fundo base de todas as páginas |
| `--color-tinta` | `#241A16` | Texto principal |

## Apoio

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-terracota-900` | `#4E1A08` | Hover / pressed do CTA |
| `--color-marrom` | `#75230D` | Bloco editorial (Quem Cria) |
| `--color-linho` | `#E3DFD4` | Seções alternadas, fundo de desabilitado |
| `--color-areia` | `#D9D3C2` | Bordas, divisores |
| `--color-taupe` | `#A69F88` | **Só decorativo** — reprova como texto |
| `--color-grafite` | `#676767` | Texto secundário |
| `--color-branco` | `#FFFFFF` | Cards, inputs, modais |

## Feedback

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-sucesso` | `#4B5320` (texto `#3E4519` sobre `#EDF0E4`) | Toast, cupom aplicado |
| `--color-erro` | `#A31621` (fundo `#FBECEB`) | Validação — sempre com ícone + texto |
| `--color-desabilitado-texto` | `#555555` sobre `--color-linho` | Esgotado |
| Foco | outline 2 px `--color-terracota`, offset 2 px | Todos os interativos |

## Contraste (WCAG AA)

| Par | Razão | OK |
|-----|-------|----|
| Creme sobre terracota | 9.3:1 | ✅ |
| Tinta sobre creme | 15.6:1 | ✅ |
| Grafite sobre creme | 5.2:1 | ✅ |
| `#555` sobre linho | 5.1:1 | ✅ |
| Taupe sobre creme | < 3:1 | ❌ não usar em texto |

Texto creme sobre foto (hero): aplicar véu `rgba(36,26,22,.35)`.

## Cores de produto (filtro e seletor)

| Nome | Swatch |
|------|--------|
| Off White | `#F2EEE3` |
| Marrom | `#5C3A21` |
| Azul Claro | `#A9C4D6` |
| Branco | `#FFFFFF` |
| Laranja | `#D9792B` |
| Verde Musgo | `#4B5320` |
| Preto | `#111111` |

Swatches sempre com borda 1 px `--color-taupe` (o branco some no creme sem ela) e **nome escrito ao lado** — cor nunca é a única informação.

## Proporção de uso

Creme ~55% · Linho ~20% · Terracota ~15% · Tinta ~8% · Taupe ~2%
