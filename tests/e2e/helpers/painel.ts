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
