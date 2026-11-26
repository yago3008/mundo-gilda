# 07 · Carrinho e conta

[← Índice](README.md) · [Artboard (interativo)](https://claude.ai/artifact/5D1iyqErytZns6jf8QNRax#artboard-Carrinho) · Epics 6, 7 · Task 11.5

## Página do carrinho (6.1)

Título: **"próximos capítulos:"** (`display/editorial`).

| Coluna | Conteúdo |
|--------|----------|
| Peça | Miniatura 3:4 (100 px) |
| Detalhes | Nome (Fraunces 600 caixa alta) · "Cor: Off White · Tamanho: PP" · "R$ 429,00 cada" |
| Quantidade | Stepper − / n / + (44 px), `aria-live="polite"` |
| Subtotal | Alinhado à direita, atualiza em tempo real |
| Remover | × 44 px, `aria-label="Remover {produto} do carrinho"` |

## Resumo do pedido (6.2 · 6.4)

Painel branco, 380 px (fixo no rodapé no mobile).

1. "Resumo do pedido" (Fraunces 24)
2. Subtotal
3. Frete ("calcular no checkout" ou valor)
4. Linha de desconto em verde musgo quando cupom aplicado
5. Campo "Cupom de desconto" + botão "Aplicar"
6. Total (Fraunces 28)
7. **Finalizar compra** (primário)
8. "Pagamento seguro · Visa · Mastercard · Pix"

| Cupom | Feedback |
|-------|----------|
| `primeiracompra` válido | `role="status"` verde: "✓ Cupom aplicado: 10% off na primeira compra." |
| Inválido / expirado / já usado | `role="alert"` vermelho: "Cupom inválido ou expirado." + borda do campo vermelha |

Regra: `primeiracompra` = 10%, uso único por cliente.

## Carrinho vazio (6.3)

- `;` terracota 96 px
- Fraunces 22, tracking .08em, caixa alta:
  "DEPOIS DO PONTO E VÍRGULA, VOCÊ CONTA A HISTÓRIA. QUAIS SEUS PRÓXIMOS CAPÍTULOS? QUAL SUA PRÓXIMA HISTÓRIA?"
- Botão secundário "Voltar à loja".

## Autenticação (Epic 7)

Modal 440 px ou página dedicada, fundo branco sobre creme.

| Tela | Campos | Ação | Links |
|------|--------|------|-------|
| Entrar (7.1) | E-mail, senha | Entrar | Esqueci minha senha · Criar conta |
| Criar conta (7.2) | Nome, e-mail, senha (+ medidor de força), confirmar senha | Criar conta | Já tem conta? Entrar |
| Recuperar senha (7.3) | E-mail | Enviar link de recuperação | — |

- Erros inline abaixo do campo (`#A31621`, borda 1.5 px), ligados via `aria-describedby`.
- Medidor de força: 4 segmentos, verde musgo quando forte.
- Sucesso de redefinição: caixa verde "✓ Senha redefinida…".

## Pagamento e confirmação (11.5)

- Radio cards 52 px: **Pix** (QR code / copia-e-cola) · **Cartão de crédito** (Visa, Mastercard). Selecionado = borda 2 px terracota.
- Página de sucesso: bloco terracota com `;`, "pedido confirmado" (Fraunces itálico) e "Um novo capítulo começa. Enviamos a confirmação para o seu e-mail. Pedido nº …".
