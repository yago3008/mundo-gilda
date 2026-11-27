import { expect, type Locator, type Page } from '@playwright/test';

/** Entra no wp-login.php (o da GILDA;) e espera o Painel GILDA;. */
export async function loginAsOwner(page: Page, owner: { username: string; password: string }) {
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

/** Abre o seletor de mídia de uma lista de fotos do painel e escolhe as fotos pelo ID. */
export async function pickPhotos(page: Page, box: Locator, ids: number[]) {
  await box.locator('.gilda-photos__add').first().click();
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

export interface PieceInput {
  name: string;
  price: string; // "199,90"
  category: string; // rótulo, ex.: "Camisas"
  chapter?: string; // rótulo, ex.: "Capítulo 1 — Borboleta 88"
  photos: number[];
  colors?: string[]; // nomes existentes, ex.: ["Marrom", "Preto"]
  sizes?: string[]; // ex.: ["P", "M"]
  stock: Record<string, number>; // "Marrom P" (ou "Estoque" para peça simples) → quantidade
}

/** Cadastra uma peça pela tela "Nova peça" (dona logada) e devolve o ID do produto. */
export async function createPiece(page: Page, piece: PieceInput): Promise<number> {
  await page.goto('/wp-admin/admin.php?page=gilda-peca');
  await page.locator('#gilda-peca-name').fill(piece.name);
  await page.locator('#gilda-peca-price').fill(piece.price);
  await page.locator('#gilda-peca-category').selectOption({ label: piece.category });
  await page.locator('#gilda-peca-chapter').selectOption({ label: piece.chapter ?? 'Sem capítulo' });
  await pickPhotos(page, page.locator('.gilda-photos--cover'), piece.photos);
  for (const color of piece.colors ?? []) {
    await page.locator('[data-gilda-colors] label', { hasText: new RegExp(`^${color}$`) }).click();
  }
  for (const size of piece.sizes ?? []) {
    await page.locator('[data-gilda-sizes] label', { hasText: new RegExp(`^${size}$`) }).click();
  }
  for (const [label, qty] of Object.entries(piece.stock)) {
    await page.getByLabel(label, { exact: true }).fill(String(qty));
  }
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toContainText('Peça publicada');
  return Number(new URL(page.url()).searchParams.get('product'));
}

/** Roda a fila de tarefas do WordPress (WP-Cron → Action Scheduler) como um visitante faria. */
export async function runCron(page: Page): Promise<void> {
  // Sem parâmetro: o wp-cron.php pega a trava e roda o que estiver vencido (inclui a fila do WooCommerce).
  await page.request.get('/wp-cron.php').catch(() => undefined);
}
