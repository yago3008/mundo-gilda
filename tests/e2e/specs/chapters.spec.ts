import fs from 'node:fs';
import path from 'node:path';
import { test, expect, type Browser, type Page, type Locator } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { strongPassword, uniqueEmail } from '../helpers/env';

/**
 * Painel das donas (docs/painel-gilda.md): uma conta "Dona da loja" cria o próximo capítulo com fotos,
 * textos e peças, e ele aparece sozinho na página Capítulos (abertura com zoom + narrativa), no Livro
 * GILDA;, nos cards da página inicial, no filtro da loja e na página da peça. Tirado do site, some de
 * todos esses lugares. Nada de código muda no caminho.
 */

const IMAGES = path.resolve(__dirname, '..', '..', '..', 'data', 'images');
const stamp = Date.now().toString(36);
const chapterName = `Capítulo de teste ${stamp}`;
const productName = `Peça de teste ${stamp}`;
const mainAlt = `Foto principal do teste ${stamp}`;

const created = { userId: 0, productId: 0, media: [] as number[], chapterId: 0 };
let owner = { username: '', password: '' };
let productUrl = '';

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
  const api = await AdminApi.login();
  try {
    const files = fs.readdirSync(IMAGES).filter((f) => /\.(webp|jpe?g|png)$/i.test(f)).slice(0, 7);
    expect(files.length, 'data/images precisa de 7 fotos para a colagem').toBe(7);
    for (const file of files) created.media.push((await api.uploadImage(path.join(IMAGES, file))).id);

    const product = await api.createProduct({ name: productName, price: '123' });
    created.productId = product.id;
    productUrl = product.permalink;

    owner = { username: `dona-e2e-${stamp}`, password: strongPassword() };
    created.userId = (await api.createUser({ ...owner, email: uniqueEmail('dona'), role: 'gilda_dona', firstName: 'Dona' })).id;
  } finally {
    await api.dispose();
  }
});

test.afterAll(async ({ browser }) => {
  if (created.chapterId) await deleteChapterForever(browser, created.chapterId);
  const api = await AdminApi.login();
  try {
    if (created.productId) await api.deleteProduct(created.productId);
    for (const id of created.media) await api.deleteMedia(id);
    if (created.userId) await api.deleteUser(created.userId);
  } finally {
    await api.dispose();
  }
});

async function loginAsOwner(page: Page) {
  await page.goto('/wp-login.php', { waitUntil: 'load' });
  // O wp-login foca e seleciona o campo de usuário ~200 ms depois de carregar: confere os dois campos.
  await expect(async () => {
    await page.locator('#user_login').fill(owner.username);
    await page.locator('#user_pass').fill(owner.password);
    await expect(page.locator('#user_login')).toHaveValue(owner.username, { timeout: 500 });
    await expect(page.locator('#user_pass')).toHaveValue(owner.password, { timeout: 500 });
  }).toPass({ timeout: 10_000 });
  await page.locator('#wp-submit').click();
  await page.waitForURL(/page=gilda-painel/);
}

/** Abre o seletor de mídia da lista e escolhe as fotos pelo ID. */
async function pickPhotos(page: Page, box: Locator, ids: number[]) {
  await box.locator('.gilda-photos__add').click();
  const modal = page.locator('.media-modal').filter({ visible: true });
  await expect(modal).toBeVisible();
  const browseTab = modal.getByRole('tab', { name: /Biblioteca de mídia|Media Library/ });
  if (await browseTab.isVisible()) await browseTab.click();
  for (const id of ids) {
    const item = modal.locator(`li.attachment[data-id="${id}"]`);
    await expect(item).toBeVisible();
    await item.click();
  }
  await modal.locator('.media-button-select').click();
  await expect(modal).toBeHidden();
}

async function deleteChapterForever(browser: Browser, id: number) {
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    await loginAsOwner(page);
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);
    if (await page.locator('#delete-action a').isVisible()) {
      await page.locator('#delete-action a').click();
      await page.waitForURL(/edit\.php/);
    }
    await page.goto('/wp-admin/edit.php?post_status=trash&post_type=gilda_capitulo');
    const row = page.locator(`#post-${id}`);
    if (await row.count()) {
      await row.hover();
      await row.locator('.row-actions .delete a').click();
      await expect(page.locator(`#post-${id}`)).toHaveCount(0);
    }
  } finally {
    await context.close();
  }
}

/** Visitante sem login (passa pelo cache do catálogo). */
async function visitor(browser: Browser) {
  const context = await browser.newContext();
  return { page: await context.newPage(), close: () => context.close() };
}

test('a dona vê só o menu do painel e não abre as telas técnicas', async ({ page }) => {
  await loginAsOwner(page);
  const menu = page.locator('#adminmenu > li.menu-top .wp-menu-name');
  const labels = (await menu.allInnerTexts()).map((t) => t.replace(/\s*\d+\s*$/, '').trim());
  expect(labels).toEqual([
    'Painel GILDA;',
    'Pedidos',
    'Peças',
    'Capítulos',
    'Categorias, Cores e Tamanhos',
    'Cupons',
    'Dados da loja',
    'Páginas',
  ]);

  await page.goto('/wp-admin/admin.php?page=wc-settings');
  await expect(page).toHaveURL(/page=gilda-painel/);
  const editor = await page.goto('/wp-admin/site-editor.php');
  expect(editor?.status()).toBe(403);
  const plugins = await page.goto('/wp-admin/plugins.php');
  expect(plugins?.status()).toBe(403);
});

test('a dona cria o próximo capítulo e ele aparece em todo o site; tirado do site, some', async ({ page, browser }) => {
  // Antes: o filtro do capítulo novo (ainda inexistente) fica guardado no cache do catálogo, vazio.
  {
    const v = await visitor(browser);
    await v.page.goto('/loja/?capitulo=capitulo-999');
    await v.close();
  }

  await loginAsOwner(page);
  await page.goto('/wp-admin/post-new.php?post_type=gilda_capitulo');
  const numberText = await page.locator('.gilda-chapter-number strong').innerText();
  const number = Number(numberText.match(/\d+/)![0]);
  const anchor = `capitulo-${number}`;

  // Cache do filtro deste número ainda vazio (tem que ser limpo quando o capítulo for publicado).
  {
    const v = await visitor(browser);
    await v.page.goto(`/loja/?capitulo=${anchor}`);
    await expect(v.page.getByRole('main').locator(`a[href="${productUrl}"]`)).toHaveCount(0);
    await v.close();
  }

  await page.locator('#title').fill(chapterName);
  await page.locator('.gilda-paragraph textarea').first().fill('Um capítulo criado pelo teste, com *itálico* no meio.');
  await page.locator('.gilda-paragraphs__add').click();
  const closing = page.locator('.gilda-paragraph').nth(1);
  await closing.locator('textarea').fill('Uma frase de fechamento.');
  await closing.locator('.gilda-paragraph__italic-box').check();

  const [main, ...others] = created.media;
  await pickPhotos(page, page.locator('.gilda-opening__main [data-gilda-photos]'), [main]);
  await page.locator(`.gilda-opening__main .gilda-photo[data-id="${main}"] .gilda-photo__alt input`).fill(mainAlt);
  // Só 5 das 6: o painel não deixa publicar.
  await pickPhotos(page, page.locator('.gilda-opening__others [data-gilda-photos]'), others.slice(0, 5));
  await expect(page.locator('.gilda-opening__others .gilda-photos__count strong')).toHaveText('5');
  await pickPhotos(page, page.locator('#gilda-chapter-story [data-gilda-photos]'), [others[0], others[1]]);
  await pickPhotos(page, page.locator('#gilda-chapter-gallery [data-gilda-photos]'), [others[2], others[3]]);
  await page.locator('.gilda-products label', { hasText: productName }).locator('input').check();

  await page.locator('#publish').click();
  await expect(page.locator('.gilda-notice-incomplete')).toContainText('Falta');
  await expect(page.locator('.gilda-notice-incomplete')).toContainText('1 foto');
  created.chapterId = Number(new URL(page.url()).searchParams.get('post'));
  expect(created.chapterId).toBeGreaterThan(0);

  // Completa a colagem e publica.
  await pickPhotos(page, page.locator('.gilda-opening__others [data-gilda-photos]'), [others[5]]);
  await expect(page.locator('.gilda-opening__others .gilda-photos__count strong')).toHaveText('6');
  await page.locator('#publish').click();
  await expect(page.locator('#message')).toContainText('publicado');

  // Aparece em todos os lugares.
  const v = await visitor(browser);
  const p = v.page;

  await p.goto('/capitulos/');
  const opening = p.locator(`section.gilda-zp--chapter[aria-label="CAPÍTULO ${number} — ${chapterName}"]`);
  await expect(opening).toHaveCount(1);
  await expect(opening.locator('.gilda-zp__layer')).toHaveCount(7);
  await expect(opening.locator(`img[alt="${mainAlt}"]`)).toHaveCount(1);
  const story = p.locator(`section.gilda-story[aria-label="CAPÍTULO ${number}"]`);
  await expect(story.locator('.gilda-story__step em')).toHaveCount(2);
  await expect(story.locator('.gilda-story__eyebrow')).toHaveText(`Capítulo ${number} · ${chapterName}`);
  const book = p.locator(`#${anchor}`);
  await expect(book.locator('.gilda-vertical-heading')).toHaveText(`CAPÍTULO ${number}`);
  await expect(book.locator('.gilda-chapter-gallery img')).toHaveCount(2);
  await expect(book.getByRole('link', { name: `peças disponíveis: ${productName}` })).toBeVisible();

  await p.goto('/');
  const card = p.locator(`.gilda-chapter-card[href*="capitulo=${anchor}"]`);
  await expect(card).toHaveCount(1);
  await expect(card.locator('.gilda-chapter-card__title')).toHaveText(`CAPÍTULO ${number}`);
  expect(await p.locator('.gilda-chapter-card').count()).toBeLessThanOrEqual(5);

  await p.goto('/loja/');
  await expect(p.locator(`#gilda-filter-chapter option[value="${anchor}"]`)).toHaveText(`Capítulo ${number} — ${chapterName}`);
  await p.goto(`/loja/?capitulo=${anchor}`);
  await expect(p.getByRole('main').locator(`a[href="${productUrl}"]`).first()).toBeVisible();

  await p.goto(productUrl);
  await expect(p.locator('.gilda-pdp-chapter')).toContainText(`Capítulo ${number} — ${chapterName}`);

  // Tirar do site: some de tudo (nada é apagado).
  await page.goto(`/wp-admin/post.php?post=${created.chapterId}&action=edit`);
  await page.getByRole('button', { name: 'Tirar do site' }).click();
  await expect(page.locator('.notice-info')).toContainText('tirado do site');

  await p.goto('/capitulos/');
  await expect(p.locator(`#${anchor}`)).toHaveCount(0);
  await expect(p.getByText(chapterName)).toHaveCount(0);
  await p.goto('/');
  await expect(p.locator(`.gilda-chapter-card[href*="capitulo=${anchor}"]`)).toHaveCount(0);
  await p.goto('/loja/');
  await expect(p.locator(`#gilda-filter-chapter option[value="${anchor}"]`)).toHaveCount(0);
  await p.goto(`/loja/?capitulo=${anchor}`);
  await expect(p.getByRole('main').locator(`a[href="${productUrl}"]`)).toHaveCount(0);
  await p.goto(productUrl);
  await expect(p.locator('.gilda-pdp-chapter')).toHaveCount(0);
  await v.close();
});

test('a dona edita os Dados da loja e o rodapé acompanha', async ({ page, browser }) => {
  await loginAsOwner(page);
  await page.goto('/wp-admin/admin.php?page=gilda-dados-da-loja');
  const instagram = page.locator('#gilda-store-instagram');
  const original = await instagram.inputValue();
  try {
    await instagram.fill(`@gilda_teste_${stamp}`);
    await page.getByRole('button', { name: 'Salvar dados da loja' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();

    const v = await visitor(browser);
    await v.page.goto('/loja/');
    const footer = v.page.locator('footer.gilda-footer');
    await expect(footer.getByRole('link', { name: `@gilda_teste_${stamp}` })).toHaveAttribute('href', `https://www.instagram.com/gilda_teste_${stamp}/`);
    await expect(v.page.locator('.gilda-footer__copyright')).toContainText(`© ${new Date().getFullYear()}`);
    await expect(v.page.locator('.gilda-marquee__track > .gilda-marquee__item', { hasText: `@gilda_teste_${stamp}` })).toHaveCount(1);
    await v.close();
  } finally {
    await page.goto('/wp-admin/admin.php?page=gilda-dados-da-loja');
    await page.locator('#gilda-store-instagram').fill(original);
    await page.getByRole('button', { name: 'Salvar dados da loja' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();
  }
});
