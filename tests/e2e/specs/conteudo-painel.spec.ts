import fs from 'node:fs';
import path from 'node:path';
import { test, expect, type Browser, type Page } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { admin, strongPassword, uniqueEmail } from '../helpers/env';
import { deleteMessages, deleteMessagesTo, searchMessages } from '../helpers/mailpit';
import { buyNowByUrl, choosePaymentAndPlaceOrder, fillCheckout, shippingOptions, waitForCheckout } from '../helpers/shop';
import { createChapter, createPiece, deleteChapter, loginAsOwner, refuseCookies, setChapterLive } from '../helpers/painel';

/**
 * Conteúdo do painel com o tempo:
 * - capítulos: números permanentes (tirar o do meio não renumera), 6º capítulo → home com os 5 mais
 *   recentes, capítulo com peças tirado do site ou apagado deixa as peças sem capítulo, sem erro;
 * - peça simples (sem cor nem tamanho) pela tela "Nova peça": na loja e comprada;
 * - cache do catálogo: o que a dona muda aparece na hora para quem não está logado;
 * - página editada pela dona (Dúvidas): o editor funciona, os dados da loja continuam no texto.
 */

const IMAGES = path.resolve(__dirname, '..', '..', '..', 'data', 'images');
const stamp = Date.now().toString(36);
const owner = { username: `dona-conteudo-${stamp}`, password: strongPassword() };
const buyer = uniqueEmail('simples');
const created = { userId: 0, media: [] as number[], chapters: [] as number[], products: [] as number[] };
const orderIds: number[] = [];

test.describe.configure({ mode: 'serial' });
test.setTimeout(420_000);

test.beforeAll(async () => {
  const api = await AdminApi.login();
  try {
    const files = fs.readdirSync(IMAGES).filter((f) => /\.(webp|jpe?g|png)$/i.test(f)).slice(0, 7);
    for (const file of files) created.media.push((await api.uploadImage(path.join(IMAGES, file))).id);
    created.userId = (await api.createUser({ ...owner, email: uniqueEmail('dona'), role: 'gilda_dona', firstName: 'Giulia' })).id;
  } finally {
    await api.dispose();
  }
});

test.afterAll(async ({ browser }) => {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await loginAsOwner(page, owner);
  for (const id of created.chapters) await deleteChapter(page, id).catch(() => undefined);
  await ctx.close();
  const api = await AdminApi.login();
  try {
    await api.cleanup([buyer]);
    for (const id of created.products) await api.deleteProduct(id).catch(() => undefined);
    for (const id of created.media) await api.deleteMedia(id);
    if (created.userId) await api.deleteUser(created.userId);
  } finally {
    await api.dispose();
  }
  await deleteMessagesTo([buyer]);
  const adminMails = (await searchMessages(`to:"${admin.email}"`)).filter((m) => orderIds.some((id) => m.Subject.includes(`#${id}`)));
  await deleteMessages(adminMails.map((m) => m.ID));
});

async function visitor(browser: Browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  return { page, close: () => context.close() };
}


/** Números dos capítulos nos cards da home, na ordem em que aparecem. */
async function homeCards(page: Page): Promise<number[]> {
  await page.goto('/');
  return (await page.locator('.gilda-chapter-card__title').allTextContents()).map((t) => Number(t.match(/\d+/)![0]));
}

test('capítulos: números permanentes, home com os 5 mais recentes, peças sem capítulo sem erro', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  // Até ter 6 capítulos publicados (a loja começa com 3).
  const v = await visitor(browser);
  const live = async () => {
    await v.page.goto('/capitulos/');
    return (await v.page.locator('.gilda-story').count());
  };
  let count = await live();
  const mine: { id: number; number: number }[] = [];
  // Pelo menos 3 nossos: o do meio é tirado do site e publicado de novo. Os da carga inicial podem estar
  // sem fotos (CI, sem importar imagens) e aí nem estão no ar nem podem ser publicados.
  while (count < 6 || mine.length < 3) {
    const ch = await createChapter(page, { name: `Capítulo teste ${stamp} ${mine.length + 1}`, photos: created.media });
    created.chapters.push(ch.id);
    mine.push(ch);
    count = await live();
  }
  const newest = mine[mine.length - 1].number;
  const cards = await homeCards(v.page);
  expect(cards, 'home: os 5 mais recentes, do mais antigo ao mais novo').toEqual([newest - 4, newest - 3, newest - 2, newest - 1, newest]);

  // Tira um capítulo do meio do site: ninguém é renumerado.
  const [before, middle, after] = mine.slice(-3);
  const afterName = `Capítulo teste ${stamp} ${mine.indexOf(after) + 1}`;
  await setChapterLive(page, middle.id, false);
  try {
    await v.page.goto('/capitulos/');
    await expect(v.page.locator(`#capitulo-${middle.number}`)).toHaveCount(0);
    await expect(v.page.locator(`#capitulo-${before.number}`)).toHaveCount(1);
    await expect(v.page.locator(`#capitulo-${after.number} .gilda-vertical-heading`)).toHaveText(`CAPÍTULO ${after.number}`);
    await expect(v.page.locator(`section.gilda-story[aria-label="CAPÍTULO ${after.number}"] .gilda-story__eyebrow`)).toContainText(`Capítulo ${after.number}`);
    await v.page.goto('/loja/');
    const options = await v.page.locator('#gilda-filter-chapter option').allTextContents();
    expect(options.some((o) => new RegExp(`^Capítulo ${middle.number}\\b`).test(o))).toBe(false);
    expect(options).toContain(`Capítulo ${after.number} — ${afterName}`);
    const cardsWithout = await homeCards(v.page);
    expect(cardsWithout).not.toContain(middle.number);
    expect(cardsWithout).toContain(after.number);
  } finally {
    await setChapterLive(page, middle.id, true);
  }

  // Capítulo com uma peça: tirado do site e depois apagado, a peça fica sem capítulo e tudo funciona.
  const withPiece = mine[mine.length - 1];
  const pieceId = await createPiece(page, {
    name: `Peça do capítulo ${stamp}`,
    price: '150,00',
    category: 'Camisas',
    chapter: `Capítulo ${withPiece.number} — Capítulo teste ${stamp} ${mine.length}`,
    photos: [created.media[0]],
    stock: { Estoque: 2 },
  });
  created.products.push(pieceId);
  const api = await AdminApi.login();
  const permalink = (await api.product(pieceId)).permalink;
  await api.dispose();
  await v.page.goto(permalink);
  await expect(v.page.locator('.gilda-pdp-chapter')).toContainText(`Capítulo ${withPiece.number}`);

  await setChapterLive(page, withPiece.id, false);
  const res = await v.page.goto(permalink);
  expect(res?.status()).toBe(200);
  await expect(v.page.locator('.gilda-pdp-chapter')).toHaveCount(0);
  const filtered = await v.page.goto(`/loja/?capitulo=capitulo-${withPiece.number}`);
  expect(filtered?.status()).toBe(200);
  await expect(v.page.getByRole('main').locator(`a[href="${permalink}"]`)).toHaveCount(0);

  await deleteChapter(page, withPiece.id);
  created.chapters = created.chapters.filter((id) => id !== withPiece.id);
  expect((await v.page.goto(permalink))?.status()).toBe(200);
  await expect(v.page.locator('.gilda-pdp-chapter')).toHaveCount(0);
  // Na tela da peça, o campo Capítulo volta para "Sem capítulo".
  await page.goto(`/wp-admin/admin.php?page=gilda-peca&product=${pieceId}`);
  await expect(page.locator('#gilda-peca-chapter')).toHaveValue('0');
  await v.close();
});

test('peça simples (sem cor nem tamanho) pela tela Nova peça: na loja e comprada', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  const name = `Lenço Único ${stamp}`;
  const id = await createPiece(page, { name, price: '120,00', category: 'Lenços', photos: [created.media[2]], stock: { Estoque: 3 } });
  created.products.push(id);
  const api = await AdminApi.login();
  const product = await api.product(id);
  expect(product.type).toBe('simple');
  await api.dispose();

  const v = await visitor(browser);
  await v.page.goto('/loja/');
  await expect(v.page.getByRole('main').locator(`a[href="${product.permalink}"]`).first()).toBeVisible();
  await buyNowByUrl(v.page, id);
  await waitForCheckout(v.page);
  await fillCheckout(v.page, buyer);
  const { pac } = await shippingOptions(v.page);
  await pac.check();
  await choosePaymentAndPlaceOrder(v.page);
  await expect(v.page).toHaveURL(/\/pedido-recebido\/\d+/, { timeout: 45_000 });
  orderIds.push(Number(v.page.url().match(/pedido-recebido\/(\d+)/)![1]));
  await v.close();

  const api2 = await AdminApi.login();
  try {
    const after = await api2.product(id);
    expect((after as unknown as { stock_quantity: number }).stock_quantity).toBe(2);
  } finally {
    await api2.dispose();
  }
});

test('cache do catálogo: nome, preço, estoque, foto e destaque mudam na hora para o visitante', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  const name = `Camisa Cache ${stamp}`;
  const id = await createPiece(page, { name, price: '210,00', category: 'Camisas', photos: [created.media[3], created.media[4]], stock: { Estoque: 5 } });
  created.products.push(id);
  const api = await AdminApi.login();
  const permalink = (await api.product(id)).permalink;
  await api.dispose();

  // Visitante abre a loja e a categoria duas vezes: a 2ª já vem do cache.
  const v = await visitor(browser);
  for (const url of ['/loja/', '/categoria/camisas/']) {
    await v.page.goto(url);
    const second = await v.page.goto(url);
    expect(second?.headers()['x-gilda-cache'], `${url} guardada no cache`).toBe('HIT');
    await expect(v.page.getByRole('main')).toContainText(name);
  }

  // A dona muda nome, preço, estoque, ordem das fotos e o destaque.
  const renamed = `Camisa Mudou ${stamp}`;
  await page.goto(`/wp-admin/admin.php?page=gilda-peca&product=${id}`);
  await page.locator('#gilda-peca-name').fill(renamed);
  await page.locator('#gilda-peca-price').fill('333,00');
  await page.getByLabel('Estoque', { exact: true }).fill('0');
  await page.locator('input[name="featured"]').check();
  const photos = page.locator('.gilda-photos--cover .gilda-photo');
  // Arrasta a 2ª foto para a frente (com movimentos intermediários, como a mão faz).
  await photos.nth(0).scrollIntoViewIfNeeded();
  const from = (await photos.nth(1).boundingBox())!;
  const to = (await photos.nth(0).boundingBox())!;
  await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
  await page.mouse.down();
  for (let i = 1; i <= 10; i++) {
    // Só para o lado, sem subir nem descer: era o caso que o jQuery UI não reordenava.
    await page.mouse.move(from.x + from.width / 2 + ((to.x - from.x) * i) / 10 - 5, from.y + from.height / 2, { steps: 2 });
  }
  await page.mouse.up();
  await expect(photos.first()).toHaveAttribute('data-id', String(created.media[4]));
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toBeVisible();

  for (const url of ['/loja/', '/categoria/camisas/']) {
    await v.page.goto(url);
    await expect(v.page.getByRole('main'), `${url} com o nome novo`).toContainText(renamed);
    await expect(v.page.getByRole('main')).not.toContainText(name);
    await expect(v.page.getByRole('main').locator('.wc-block-product', { hasText: renamed })).toContainText('333,00');
  }
  await v.page.goto(permalink);
  await expect(v.page.locator('h1')).toContainText(renamed);
  await expect(v.page.getByRole('main')).toContainText('333,00');
  await expect(v.page.getByRole('main')).toContainText(/esgotad/i);

  const api2 = await AdminApi.login();
  try {
    const product = await api2.product(id);
    expect(product.featured).toBe(true);
    expect(product.images.map((i) => i.id)).toEqual([created.media[4], created.media[3]]);
  } finally {
    await api2.dispose();
  }
  await v.close();
});

test('página editada pela dona: o editor funciona e os dados da loja continuam no texto', async ({ page, browser }) => {
  const api = await AdminApi.login();
  const pages = await (await api['ctx'].fetch('/wp-json/wp/v2/pages?slug=duvidas-frequentes&context=edit', { headers: { 'X-WP-Nonce': api['nonce'] } })).json();
  const pageId: number = pages[0].id;
  const original: string = pages[0].content.raw;
  await api.dispose();
  const marker = `Teste da dona ${stamp}.`;

  try {
    await loginAsOwner(page, owner);
    await page.goto(`/wp-admin/post.php?post=${pageId}&action=edit`);
    const close = page.getByRole('button', { name: /fechar|close/i }).filter({ visible: true });
    await page.waitForTimeout(1500);
    if (await close.first().isVisible().catch(() => false)) await close.first().click(); // guia de boas-vindas
    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    const firstParagraph = canvas.locator('p.block-editor-rich-text__editable').first();
    await firstParagraph.click();
    await page.keyboard.press('End');
    await page.keyboard.type(` ${marker}`);
    await page.getByRole('button', { name: /^(salvar|atualizar)$/i }).first().click();
    // Gravou? (o botão do WordPress muda de nome conforme a versão; o conteúdo salvo é o que importa)
    await expect
      .poll(async () => {
        const check = await AdminApi.login();
        try {
          const res = await check['ctx'].fetch(`/wp-json/wp/v2/pages/${pageId}?context=edit`, { headers: { 'X-WP-Nonce': check['nonce'] } });
          return ((await res.json()).content.raw as string).includes(marker);
        } finally {
          await check.dispose();
        }
      }, { timeout: 30_000 })
      .toBe(true);

    const v = await visitor(browser);
    await v.page.goto('/duvidas-frequentes/');
    await refuseCookies(v.page);
    expect(await v.page.locator('main').innerHTML()).toContain(marker);
    // Os [gilda_loja …] continuam virando os dados da loja.
    await expect(v.page.locator('main details a[href^="https://wa.me/"]').first()).toHaveCount(1);
    expect(await v.page.locator('main').innerHTML()).not.toContain('[gilda_loja');
    await v.close();
  } finally {
    const api2 = await AdminApi.login();
    await api2['ctx'].fetch(`/wp-json/wp/v2/pages/${pageId}`, { method: 'POST', headers: { 'X-WP-Nonce': api2['nonce'] }, data: { content: original } });
    await api2.dispose();
  }
});
