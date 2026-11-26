import { test, expect } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { admin, uniqueEmail } from '../helpers/env';
import { deleteMessages, deleteMessagesTo, searchMessages, waitForMessage } from '../helpers/mailpit';
import {
  PRODUCT_SLUG,
  PRODUCT_URL,
  addToCartButton,
  applyCoupon,
  brl,
  buyNowButton,
  buyNowByUrl,
  cartItems,
  choosePaymentAndPlaceOrder,
  fillCheckout,
  pickInStockVariation,
  selectVariation,
  shippingOptions,
  waitForCheckout,
  type Variation,
} from '../helpers/shop';

// Compra de ponta a ponta (Epics 5 e 6) e regra do cupom de primeira compra.
test.describe.configure({ mode: 'serial' });

const COUPON = 'primeiracompra';
const email = uniqueEmail('compra');

let api: AdminApi;
let productId: number;
let variation: Variation;
let originalStock: number | null = null;
const orderNumbers: string[] = [];

test.beforeAll(async () => {
  api = await AdminApi.login();
  productId = (await api.productBySlug(PRODUCT_SLUG)).id;
});

test.afterAll(async () => {
  // Devolve o estoque e apaga pedidos/e-mails criados.
  if (variation && originalStock !== null) {
    await api.setVariationStock(productId, variation.variation_id, originalStock);
  }
  await api.cleanup([email]);
  await api.dispose();
  const adminMails = (await searchMessages(`to:"${admin.email}"`)).filter((m) => orderNumbers.some((n) => m.Subject.includes(n)));
  await deleteMessages(adminMails.map((m) => m.ID));
  await deleteMessagesTo([email]);
});

test('produto variável: escolher cor e tamanho e adicionar ao carrinho', async ({ page }) => {
  await page.goto(PRODUCT_URL);
  variation = await pickInStockVariation(page);
  await selectVariation(page, variation);
  await addToCartButton(page).click();

  await expect
    .poll(async () => (await cartItems(page)).map((i) => i.id), { message: 'variação no carrinho', timeout: 15_000 })
    .toContain(variation.variation_id);

  await page.goto('/carrinho/');
  await expect(page.getByRole('main').getByText(/camisa gr[ãa]o/i).first()).toBeVisible();
});

test('botão de compra direta leva para o checkout com o produto', async ({ page }) => {
  await page.goto(PRODUCT_URL);
  variation ??= await pickInStockVariation(page);
  await selectVariation(page, variation);
  await buyNowButton(page).click();

  await waitForCheckout(page);
  await expect(page.getByRole('main').getByText(/camisa gr[ãa]o/i).first()).toBeVisible();
  const items = await cartItems(page);
  expect(items.map((i) => i.id)).toEqual([variation.variation_id]);
});

test('checkout completo: CEP, frete, cupom de 10%, pagamento de teste, e-mails e estoque', async ({ page }) => {
  await page.goto(PRODUCT_URL);
  variation ??= await pickInStockVariation(page);
  const before = await api.variation(productId, variation.variation_id);
  expect(before.manage_stock, 'estoque controlado por variação').toBe(true);
  originalStock = before.stock_quantity;
  expect(originalStock ?? 0).toBeGreaterThan(0);

  await selectVariation(page, variation);
  await buyNowButton(page).click();
  await waitForCheckout(page);

  await fillCheckout(page, email);

  const { pac, sedex } = await shippingOptions(page);
  await expect(pac).toHaveAccessibleName(/R\$\s?\d+,\d{2}/);
  await expect(sedex).toHaveAccessibleName(/R\$\s?\d+,\d{2}/);
  await pac.check();

  await applyCoupon(page, COUPON);
  const discount = brl(variation.display_price * 0.1);
  await expect(page.getByText(new RegExp(COUPON, 'i')).first(), 'cupom aplicado').toBeVisible();
  await expect(page.getByText(discount).first(), `desconto de 10% (R$ ${discount})`).toBeVisible();

  await choosePaymentAndPlaceOrder(page);

  await expect(page).toHaveURL(/\/pedido-recebido\/\d+/, { timeout: 45_000 });
  await expect(page.getByRole('main')).toContainText(/pedido (foi )?recebido|obrigad/i);

  // Pedido gravado com o cupom e o total certo.
  const [order] = await api.ordersByEmail(email);
  expect(order, 'pedido criado').toBeTruthy();
  orderNumbers.push(order.number);
  expect(order.coupon_lines.map((c) => c.code.toLowerCase())).toContain(COUPON);
  expect(['processing', 'on-hold', 'completed']).toContain(order.status);

  // E-mails: confirmação para a cliente e aviso de novo pedido para a loja.
  await waitForMessage(email, /pedido|recebid/i);
  await waitForMessage(admin.email, new RegExp(order.number));

  // Estoque da variação baixou 1.
  const after = await api.variation(productId, variation.variation_id);
  expect(after.stock_quantity).toBe((originalStock ?? 0) - 1);
});

test('cupom de primeira compra é recusado na segunda compra do mesmo e-mail', async ({ page }) => {
  test.skip(!orderNumbers.length, 'depende da primeira compra');
  await buyNowByUrl(page, variation.variation_id);
  await waitForCheckout(page);
  await fillCheckout(page, email);
  await shippingOptions(page);

  const refusal = page.getByText(/v[áa]lido apenas na primeira compra/i).first();
  await applyCoupon(page, COUPON);
  // O aviso pode vir ao aplicar (e-mail já conhecido) ou ao finalizar (e-mail só no envio do formulário).
  if (!(await refusal.isVisible())) {
    await choosePaymentAndPlaceOrder(page);
  }
  await expect(refusal).toBeVisible();
  await expect(page).not.toHaveURL(/pedido-recebido/);
  expect((await api.ordersByEmail(email)).length, 'nenhum pedido novo').toBe(1);
});
