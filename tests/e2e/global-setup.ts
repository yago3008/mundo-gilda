import { BASE_URL, MAILPIT_URL } from './helpers/env';

// Falha cedo, com mensagem clara, se o ambiente local não estiver de pé.
export default async function globalSetup() {
  for (const [name, url] of [['Loja', `${BASE_URL}/`], ['Mailpit', `${MAILPIT_URL}/api/v1/info`]]) {
    try {
      const res = await fetch(url, { redirect: 'manual' });
      if (res.status >= 500) throw new Error(`HTTP ${res.status}`);
    } catch (err) {
      throw new Error(`${name} não responde em ${url} (${(err as Error).message}). Rode ./scripts/setup.sh antes dos testes.`);
    }
  }
}
