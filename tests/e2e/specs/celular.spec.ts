import { test, expect, type Page } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { admin, strongPassword, uniqueEmail } from '../helpers/env';
import { deleteMessages, deleteMessagesTo, searchMessages } from '../helpers/mailpit';
import { loginAsOwner } from '../helpers/painel';
import {
  PRODUCT_URL,
  addToCartButton,
  cartItems,
  choosePaymentAndPlaceOrder,
  fillCheckout,
  pickInStockVariation,
  selectVariation,
  shippingOptions,
  waitForCheckout,
} from '../helpers/shop';

/**
 * Celular (390 px): compra completa, gaveta de filtros, telas do painel (Nova peça e capítulo) e as
 * animações de zoom (card de categoria e login). Em todas: nada passa da largura da tela.
 */

test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
test.describe.configure({ mode: 'serial' });
test.setTimeout(180_000);

const buyer = uniqueEmail('celular');
const stamp = Date.now().toString(36);
const owner = { username: `dona-celular-${stamp}`, password: strongPassword() };
const customer = { username: `cliente-celular-${stamp}`, password: strongPassword(), email: uniqueEmail('celular-login') };
const users: number[] = [];
const orderIds: number[] = [];

test.beforeAll(async () => {
  const api = await AdminApi.login();
  try {
    users.push((await api.createUser({ ...owner, email: uniqueEmail('dona'), role: 'gilda_dona', firstName: 'Giulia' })).id);
    users.push((await api.createUser({ ...customer, role: 'customer', firstName: 'Cliente' })).id);
  } finally {
    await api.dispose();
  }
});

test.afterAll(async () => {
  const api = await AdminApi.login();
  try {
    await api.cleanup([buyer]);
    for (const id of users) await api.deleteUser(id);
  } finally {
    await api.dispose();
  }
  await deleteMessagesTo([buyer, customer.email]);
  const adminMails = (await searchMessages(`to:"${admin.email}"`)).filter((m) => orderIds.some((id) => m.Subject.includes(`#${id}`)));
  await deleteMessages(adminMails.map((m) => m.ID));
});

/** A página não rola para o lado (nada mais largo que a tela). */
async function expectFits(page: Page, where: string) {
  const { scroll, width } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, width: document.documentElement.clientWidth }));
  expect(scroll, `${where}: sem rolagem lateral`).toBeLessThanOrEqual(width);
}

/** Recusa o banner de cookies (é modal e bloqueia toques). */
async function refuseCookies(page: Page) {
  const reject = page.locator('#gilda-consent [data-gilda-consent="reject"]');
  if (await reject.isVisible().catch(() => false)) await reject.click();
}

function errors(page: Page): string[] {
  const list: string[] = [];
  page.on('pageerror', (e) => list.push(e.message));
  return list;
}

test('compra completa no celular: produto → carrinho → checkout → pedido recebido', async ({ page }) => {
  const js = errors(page);
  await page.goto(PRODUCT_URL);
  await refuseCookies(page);
  await expectFits(page, 'produto');
  const variation = await pickInStockVariation(page);
  await selectVariation(page, variation);
  await addToCartButton(page).click();
  await expect.poll(async () => (await cartItems(page)).length, { message: 'peça no carrinho' }).toBeGreaterThan(0);
  await page.goto('/carrinho/');
  await expectFits(page, 'carrinho');
  await page.getByRole('link', { name: /finalizar compra|ir para o checkout/i }).filter({ visible: true }).first().click();
  await waitForCheckout(page);
  await expectFits(page, 'checkout');
  await fillCheckout(page, buyer);
  const { pac } = await shippingOptions(page);
  await pac.check();
  await expectFits(page, 'checkout preenchido');
  await choosePaymentAndPlaceOrder(page);
  await expect(page).toHaveURL(/\/pedido-recebido\/\d+/, { timeout: 45_000 });
  orderIds.push(Number(page.url().match(/pedido-recebido\/(\d+)/)![1]));
  await expectFits(page, 'pedido recebido');
  expect(js, 'sem erro de JavaScript').toEqual([]);
});

test('gaveta de filtros no celular: abre, escolhe, aplica e fecha', async ({ page }) => {
  await page.goto('/loja/');
  await refuseCookies(page);
  const sheet = page.locator('#gilda-filters');
  const inView = async () => {
    const box = await sheet.boundingBox();
    return !!box && box.y < 844 && box.y + box.height > 0 && box.x >= -1 && box.x + box.width <= 391;
  };
  await expect.poll(inView, { message: 'fechada: fora da tela' }).toBe(false);
  await page.getByRole('button', { name: /^filtrar/i }).filter({ visible: true }).first().click();
  await expect.poll(inView, { message: 'aberta: dentro da tela' }).toBe(true);
  await expectFits(page, 'gaveta aberta');

  // Fecha no X…
  await sheet.getByRole('button', { name: 'Fechar filtros' }).click();
  await expect.poll(inView).toBe(false);

  // …reabre, escolhe uma cor e aplica.
  await page.getByRole('button', { name: /^filtrar/i }).filter({ visible: true }).first().click();
  await expect.poll(inView).toBe(true);
  await sheet.locator('label', { hasText: 'Marrom' }).first().click();
  await sheet.getByRole('button', { name: 'Aplicar filtros' }).click();
  await expect(page).toHaveURL(/cor(%5B%5D|\[\])=marrom/);
  await expect(page.getByRole('main').locator('a[href*="/product-page/"]').first()).toBeVisible();
  await expect.poll(inView, { message: 'depois de aplicar, a gaveta fecha' }).toBe(false);
  await expectFits(page, 'loja filtrada');
});

test('painel no celular: Nova peça e tela do capítulo cabem e funcionam', async ({ page }) => {
  await loginAsOwner(page, owner);
  await expectFits(page, 'início do painel');

  await page.goto('/wp-admin/admin.php?page=gilda-peca');
  await expectFits(page, 'Nova peça');
  for (const loc of [page.locator('#gilda-peca-name'), page.locator('#gilda-peca-price'), page.locator('#gilda-peca-publish')]) {
    await loc.scrollIntoViewIfNeeded();
    const box = (await loc.boundingBox())!;
    expect(box.x, 'campo dentro da tela').toBeGreaterThanOrEqual(0);
    expect(box.x + box.width, 'campo dentro da tela').toBeLessThanOrEqual(391);
  }
  // Escolher cor e tamanho monta a grade de estoque, que também tem que caber.
  await page.locator('label', { hasText: 'Marrom' }).first().click();
  await page.locator('label', { hasText: /^P$/ }).first().click();
  await expect(page.getByLabel('Marrom P').first()).toBeVisible();
  await expectFits(page, 'Nova peça com estoque');

  await page.goto('/wp-admin/post-new.php?post_type=gilda_capitulo');
  await expectFits(page, 'novo capítulo');
  for (const loc of [page.locator('#title'), page.locator('.gilda-paragraph textarea').first(), page.locator('#publish')]) {
    await loc.scrollIntoViewIfNeeded();
    const box = (await loc.boundingBox())!;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(391);
  }
});

test('animações no celular: zoom do card de categoria e do login terminam na página certa', async ({ page }) => {
  const js = errors(page);
  await page.goto('/');
  await refuseCookies(page);
  const card = page.locator('.gilda-category a[href]').first();
  await card.scrollIntoViewIfNeeded();
  const href = (await card.getAttribute('href'))!;
  await card.tap();
  const target = new URL(href, 'http://x');
  await page.waitForURL((u) => u.pathname + u.search === target.pathname + target.search, { timeout: 20_000 });
  // O mergulho termina e solta a página: o primeiro produto recebe o toque.
  const product = page.getByRole('main').locator('a[href*="/product-page/"]').first();
  await expect(product).toBeVisible({ timeout: 15_000 });
  await expect.poll(() => page.locator('[class*="czoom"], .gilda-czoom').filter({ visible: true }).count(), { timeout: 15_000 }).toBe(0);
  await expectFits(page, 'categoria depois do zoom');

  await page.context().clearCookies();
  await page.goto('/minha-conta/');
  await refuseCookies(page);
  const form = page.locator('form.woocommerce-form-login');
  await form.locator('#username').fill(customer.username);
  await form.locator('#password').fill(customer.password);
  await form.getByRole('button', { name: /^(acessar|entrar|login)$/i }).click();
  await expect(page.getByRole('link', { name: /^sair$/i }).first()).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('body')).toHaveClass(/\blogged-in\b/);
  await expectFits(page, 'Minha conta depois do login');
  expect(js, 'sem erro de JavaScript').toEqual([]);
});
