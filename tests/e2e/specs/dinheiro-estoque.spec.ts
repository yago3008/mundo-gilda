import fs from 'node:fs';
import path from 'node:path';
import { test, expect, type Browser, type Page } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { admin, strongPassword, uniqueEmail } from '../helpers/env';
import { deleteMessages, deleteMessagesTo, searchMessages, waitForMessage } from '../helpers/mailpit';
import { applyCoupon, brl, buyNowByUrl, choosePaymentAndPlaceOrder, fillCheckout, readVariations, selectVariation, shippingOptions, waitForCheckout } from '../helpers/shop';
import { createPiece, loginAsOwner, runCron } from '../helpers/painel';

/**
 * Dinheiro e estoque com uma peça criada pelo painel (não importada do Wix):
 * compra de ponta a ponta, "Restam só N", número "Acabando" do Início, SKU e cor/tamanho no pedido,
 * "Avise-me" ao repor pelo painel e pela tela do WooCommerce, pedido antigo intacto depois de tirar a
 * cor vendida e mudar o preço, frete que acompanha o peso, cupom novo com "só na primeira compra" e a
 * "Promoção em destaque".
 */

const IMAGES = path.resolve(__dirname, '..', '..', '..', 'data', 'images');
const stamp = Date.now().toString(36);
const pieceName = `Camisa Estoque ${stamp}`;
const buyer = uniqueEmail('estoque');
const watcher = uniqueEmail('aviseme');
const watcher2 = uniqueEmail('aviseme2');
const couponBuyer = uniqueEmail('cupom');
const couponCode = `teste${stamp}`;
const owner = { username: `dona-estoque-${stamp}`, password: strongPassword() };
const CEP = '01310-100';

const created = { userId: 0, productId: 0, media: [] as number[], couponId: 0 };
let promoBefore = '';
const orderIds: number[] = [];

test.describe.configure({ mode: 'serial' });
test.setTimeout(420_000); // compras, e-mails e a fila de tarefas do WordPress

test.beforeAll(async () => {
  const api = await AdminApi.login();
  try {
    const file = fs.readdirSync(IMAGES).find((f) => /\.(webp|jpe?g|png)$/i.test(f))!;
    created.media.push((await api.uploadImage(path.join(IMAGES, file))).id);
    created.userId = (await api.createUser({ ...owner, email: uniqueEmail('dona'), role: 'gilda_dona', firstName: 'Giulia' })).id;
  } finally {
    await api.dispose();
  }
});

test.afterAll(async ({ browser }) => {
  // Promoção volta para o que era.
  if (promoBefore) {
    const ctx = await browser.newContext();
    const p = await ctx.newPage();
    await loginAsOwner(p, owner);
    await p.goto('/wp-admin/admin.php?page=gilda-promocao');
    await p.locator('#gilda-promo').selectOption(promoBefore);
    await p.getByRole('button', { name: 'Salvar promoção' }).click();
    await ctx.close();
  }
  const api = await AdminApi.login();
  try {
    await api.cleanup([buyer, couponBuyer]);
    if (created.productId) await api.deleteProduct(created.productId);
    for (const id of created.media) await api.deleteMedia(id);
    if (created.couponId) await api.deleteCoupon(created.couponId);
    if (created.userId) await api.deleteUser(created.userId);
  } finally {
    await api.dispose();
  }
  await deleteMessagesTo([buyer, watcher, watcher2, couponBuyer]);
  const adminMails = (await searchMessages(`to:"${admin.email}"`)).filter((m) => m.Subject.includes(pieceName) || orderIds.some((id) => m.Subject.includes(`#${id}`)));
  await deleteMessages(adminMails.map((m) => m.ID));
});

async function visitor(browser: Browser) {
  const context = await browser.newContext();
  return { page: await context.newPage(), close: () => context.close() };
}

/** Recusa o banner de cookies (ele abre como diálogo e bloqueia o resto da página até a resposta). */
async function refuseCookies(page: Page) {
  const refuse = page.getByRole('dialog', { name: /cookies/i }).getByRole('button', { name: 'Recusar' });
  if (await refuse.isVisible().catch(() => false)) await refuse.click();
}

/** ID da variação pela cor e tamanho (slugs). */
async function variationId(page: Page, color: string, size: string): Promise<number> {
  const v = (await readVariations(page)).find((x) => x.attributes.attribute_pa_cor === color && x.attributes.attribute_pa_tamanho === size);
  expect(v, `variação ${color}/${size}`).toBeTruthy();
  return v!.variation_id;
}

async function lowStockKpi(page: Page): Promise<number> {
  await page.goto('/wp-admin/admin.php?page=gilda-painel');
  return Number(await page.locator('[data-kpi="low-stock"] .gilda-kpi__value').innerText());
}

async function buy(page: Page, variation: number, email: string, coupon?: string): Promise<number> {
  await buyNowByUrl(page, variation);
  await waitForCheckout(page);
  await fillCheckout(page, email);
  const { pac } = await shippingOptions(page);
  await pac.check();
  if (coupon) await applyCoupon(page, coupon);
  await choosePaymentAndPlaceOrder(page);
  await expect(page).toHaveURL(/\/pedido-recebido\/\d+/, { timeout: 45_000 });
  const id = Number(page.url().match(/pedido-recebido\/(\d+)/)![1]);
  orderIds.push(id);
  return id;
}

/** Espera o "voltou" chegar, rodando a fila de tarefas como um visitante faria. */
async function waitForBackInStock(page: Page, email: string) {
  await expect
    .poll(
      async () => {
        await runCron(page);
        return (await searchMessages(`to:"${email}"`)).filter((m) => /voltou ao estoque/i.test(m.Subject)).length;
      },
      { timeout: 150_000, intervals: [3_000] }, // a fila do WooCommerce roda mais ou menos a cada minuto
    )
    .toBeGreaterThan(0);
}

let permalink = '';
let marromP = 0;
let pretoM = 0;
let marromM = 0;
let firstOrder = 0;

test('peça criada no painel: compra de ponta a ponta, estoque, "Restam só N", Acabando e SKU no pedido', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  created.productId = await createPiece(page, {
    name: pieceName,
    price: '199,90',
    category: 'Camisas',
    photos: created.media,
    colors: ['Marrom', 'Preto'],
    sizes: ['P', 'M'],
    stock: { 'Marrom P': 3, 'Marrom M': 1, 'Preto P': 2, 'Preto M': 0 },
  });
  const api = await AdminApi.login();
  try {
    permalink = (await api.product(created.productId)).permalink;
  } finally {
    await api.dispose();
  }
  const kpiBefore = await lowStockKpi(page);

  const v = await visitor(browser);
  await v.page.goto(permalink);
  marromP = await variationId(v.page, 'marrom', 'p');
  pretoM = await variationId(v.page, 'preto', 'm');
  marromM = await variationId(v.page, 'marrom', 'm');
  firstOrder = await buy(v.page, marromP, buyer);
  await waitForMessage(buyer, /pedido|recebid/i);

  const api2 = await AdminApi.login();
  try {
    const variations = await api2.variations(created.productId);
    const sold = variations.find((x) => x.id === marromP)!;
    expect(sold.stock_quantity, 'estoque baixou 1').toBe(2);
    const order = await api2.order(firstOrder);
    const item = order.line_items[0];
    expect(item.variation_id).toBe(marromP);
    expect(item.sku, 'SKU da variação no pedido').toBe(sold.sku);
    expect(item.sku).toMatch(/-MR-P$/);
    const meta = item.meta_data.map((m) => `${m.display_key}: ${m.display_value}`).join(' | ');
    expect(meta).toMatch(/Marrom/);
    expect(meta).toMatch(/\bP\b/);
  } finally {
    await api2.dispose();
  }

  // "Restam só 2" na página da peça, na combinação comprada.
  await v.page.goto(permalink);
  await refuseCookies(v.page);
  const variations = await readVariations(v.page);
  await selectVariation(v.page, variations.find((x) => x.variation_id === marromP)!);
  await expect(v.page.locator('.gilda-low-stock').filter({ visible: true })).toHaveText(/Restam só 2/);
  await v.close();

  // Início: mais uma combinação "acabando" (3 → 2).
  expect(await lowStockKpi(page)).toBe(kpiBefore + 1);
});

test('"Avise-me": repor pelo painel e pela tela do WooCommerce manda o "voltou ao estoque"', async ({ page, browser }) => {
  const v = await visitor(browser);
  const askFor = async (variation: number, email: string) => {
    await v.page.goto(permalink);
    await refuseCookies(v.page);
    const variations = await readVariations(v.page);
    await selectVariation(v.page, variations.find((x) => x.variation_id === variation)!).catch(() => undefined); // esgotado: o botão de comprar fica desligado
    await v.page.locator('.gilda-notify__open').filter({ visible: true }).first().click();
    await v.page.locator('.gilda-notify__email').filter({ visible: true }).fill(email);
    await v.page.locator('.gilda-notify__submit').filter({ visible: true }).click();
    // Só vale a mensagem de sucesso (o plugin limita 10 pedidos por hora por IP e responde com outra mensagem).
    await expect(v.page.locator('.gilda-notify__message').filter({ visible: true }).first()).toContainText('Assim que voltar');
  };

  // Preto M está zerado: pede o aviso; a dona repõe pela grade da tela "Nova peça".
  await askFor(pretoM, watcher);
  await loginAsOwner(page, owner);
  await page.goto(`/wp-admin/admin.php?page=gilda-peca&product=${created.productId}`);
  await page.getByLabel('Preto M', { exact: true }).fill('2');
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toBeVisible();
  await waitForBackInStock(page, watcher);

  // Marrom M: zera e repõe pela API do WooCommerce (o mesmo caminho da tela de produto do WooCommerce).
  const api = await AdminApi.login();
  try {
    await api.setVariationStock(created.productId, marromM, 0);
    await askFor(marromM, watcher2);
    await api.setVariationStock(created.productId, marromM, 4);
  } finally {
    await api.dispose();
  }
  await waitForBackInStock(page, watcher2);
  await v.close();
});

test('editar uma peça vendida: tirar a cor e mudar o preço não mexem no pedido antigo', async ({ page }) => {
  const api = await AdminApi.login();
  let before: Awaited<ReturnType<AdminApi['order']>>;
  try {
    before = await api.order(firstOrder);
  } finally {
    await api.dispose();
  }

  await loginAsOwner(page, owner);
  await page.goto(`/wp-admin/admin.php?page=gilda-peca&product=${created.productId}`);
  await page.locator('[data-gilda-colors] label', { hasText: /^Marrom$/ }).click(); // desmarca a cor vendida
  await page.locator('#gilda-peca-price').fill('249,90');
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toBeVisible();

  const api2 = await AdminApi.login();
  try {
    const after = await api2.order(firstOrder);
    expect(after.total).toBe(before.total);
    expect(after.line_items[0].name).toBe(before.line_items[0].name);
    expect(after.line_items[0].sku).toBe(before.line_items[0].sku);
    expect(after.line_items[0].total).toBe(before.line_items[0].total);
    expect(after.line_items[0].meta_data.map((m) => m.display_value)).toEqual(before.line_items[0].meta_data.map((m) => m.display_value));
    // A variação vendida não foi apagada: só saiu da loja.
    const sold = (await api2.variations(created.productId)).find((x) => x.id === marromP);
    expect(sold, 'variação vendida continua existindo').toBeTruthy();
    expect(sold!.status).toBe('private');
  } finally {
    await api2.dispose();
  }
});

test('frete acompanha o peso: simulador da página e carrinho', async ({ page, browser }) => {
  const quote = async () => {
    const res = await page.request.get(`/wp-json/gilda/v1/shipping?product_id=${created.productId}&variation_id=${pretoM}&cep=${CEP}`);
    expect(res.ok()).toBeTruthy();
    const rates = (await res.json()) as { label?: string; name?: string; cost?: number; price?: number | string }[];
    return JSON.stringify(rates);
  };
  const v = await visitor(browser);
  const cartRates = async () => {
    const cart = await v.page.request.get('/wp-json/wc/store/v1/cart');
    const nonce = cart.headers()['nonce'];
    const res = await v.page.request.post('/wp-json/wc/store/v1/cart/update-customer', {
      headers: { Nonce: nonce },
      data: { shipping_address: { postcode: CEP, country: 'BR', state: 'SP', city: 'São Paulo', address_1: 'Av. Paulista, 1000' } },
    });
    expect(res.ok(), await res.text()).toBeTruthy();
    const body = await res.json();
    return JSON.stringify((body.shipping_rates?.[0]?.shipping_rates || []).map((r: { name: string; price: string }) => [r.name, r.price]));
  };

  const before = await quote();
  {
    const cart = await v.page.request.get('/wp-json/wc/store/v1/cart');
    const add = await v.page.request.post('/wp-json/wc/store/v1/cart/add-item', { headers: { Nonce: cart.headers()['nonce'] }, data: { id: pretoM, quantity: 1 } });
    expect(add.ok(), await add.text()).toBeTruthy();
  }
  const cartBefore = await cartRates();
  expect(cartBefore).not.toBe('[]');

  await loginAsOwner(page, owner);
  await page.goto(`/wp-admin/admin.php?page=gilda-peca&product=${created.productId}`);
  await page.locator('input[name="weight"]').fill('6');
  await page.locator('input[name="length"]').fill('60');
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toBeVisible();

  expect(await quote(), 'cotação da página mudou com o peso').not.toBe(before);
  expect(await cartRates(), 'frete do carrinho mudou com o peso').not.toBe(cartBefore);
  await v.close();
});

test('cupom novo da dona: "só na primeira compra", Promoção em destaque na faixa e no banner, e na compra', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  // Cupom pela tela do WooCommerce, com a caixinha nova.
  await page.goto('/wp-admin/post-new.php?post_type=shop_coupon');
  await page.locator('#title').fill(couponCode);
  await page.locator('#discount_type').selectOption('percent');
  await page.locator('#coupon_amount').fill('15');
  await page.locator('#_gilda_first_purchase_only').check();
  // Sair do campo do código dispara o rascunho automático, que desliga "Publicar" por um instante.
  await expect(page.locator('#publish')).not.toHaveClass(/disabled/);
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/, { timeout: 60_000 }), page.locator('#publish').click()]);
  await expect(page.locator('#message')).toBeVisible();
  created.couponId = Number(new URL(page.url()).searchParams.get('post'));
  const api = await AdminApi.login();
  try {
    const [coupon] = await api.couponsByCode(couponCode);
    expect(coupon.meta_data.find((m) => m.key === '_gilda_first_purchase_only')?.value).toBe('yes');
  } finally {
    await api.dispose();
  }

  // Promoção em destaque: escolhe o cupom novo.
  await page.goto('/wp-admin/admin.php?page=gilda-promocao');
  promoBefore = await page.locator('#gilda-promo').inputValue();
  await page.locator('#gilda-promo').selectOption(String(created.couponId));
  await page.getByRole('button', { name: 'Salvar promoção' }).click();
  await expect(page.locator('.notice-success')).toBeVisible();

  const v = await visitor(browser);
  await v.page.goto('/');
  const message = `15% OFF na primeira compra com o cupom ${couponCode}`;
  await expect(v.page.locator('.gilda-marquee__track > .gilda-marquee__item', { hasText: message })).toHaveCount(1);
  await expect(v.page.locator('.gilda-hero__ribbon')).toContainText(couponCode);
  await expect(v.page.locator('.gilda-hero__ribbon')).toContainText('15% off na sua primeira compra');

  // Funciona na primeira compra...
  const variations = await (async () => {
    await v.page.goto(permalink);
    return readVariations(v.page);
  })();
  const inStock = variations.find((x) => x.is_in_stock)!;
  const orderId = await buy(v.page, inStock.variation_id, couponBuyer, couponCode);
  const api2 = await AdminApi.login();
  try {
    const order = await api2.order(orderId);
    expect(Number(order.total)).toBeGreaterThan(0);
    const [summary] = await api2.ordersByEmail(couponBuyer);
    expect(summary.coupon_lines.map((c) => c.code.toLowerCase())).toContain(couponCode);
  } finally {
    await api2.dispose();
  }

  // ...e é recusado na segunda (outra visita, como uma compra feita depois).
  const second = await visitor(browser);
  await buyNowByUrl(second.page, inStock.variation_id);
  await waitForCheckout(second.page);
  await fillCheckout(second.page, couponBuyer);
  await shippingOptions(second.page);
  const refusal = second.page.getByText(/v[áa]lido apenas na primeira compra/i).filter({ visible: true }).first();
  await applyCoupon(second.page, couponCode);
  await refusal.waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {});
  if (!(await refusal.isVisible())) await choosePaymentAndPlaceOrder(second.page);
  await expect(refusal).toBeVisible();
  await second.close();

  // Nenhum cupom anunciado: a faixa e o banner ficam sem a mensagem de cupom.
  await page.goto('/wp-admin/admin.php?page=gilda-promocao');
  await page.locator('#gilda-promo').selectOption('0');
  await page.getByRole('button', { name: 'Salvar promoção' }).click();
  await v.page.goto('/');
  await expect(v.page.locator('.gilda-marquee__item', { hasText: /OFF/ })).toHaveCount(0);
  await expect(v.page.locator('.gilda-hero__ribbon')).toHaveCount(0);
  await v.close();
  expect(brl(1)).toBe('1,00');
});
