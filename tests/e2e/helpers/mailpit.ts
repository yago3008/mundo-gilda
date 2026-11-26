import { expect } from '@playwright/test';
import { MAILPIT_URL } from './env';

/** Cliente mínimo da API do Mailpit (https://mailpit.axllent.org/docs/api-v1/). */

export interface MailSummary {
  ID: string;
  Subject: string;
  To: { Name: string; Address: string }[];
  Created: string;
}

export interface MailMessage extends MailSummary {
  Text: string;
  HTML: string;
}

async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${MAILPIT_URL}/api/v1${path}`, init);
  if (!res.ok) {
    throw new Error(`Mailpit ${init?.method || 'GET'} ${path}: HTTP ${res.status}`);
  }
  const body = await res.text();
  // Alguns endpoints (ex.: DELETE) respondem só "ok".
  return (res.headers.get('content-type')?.includes('json') && body ? JSON.parse(body) : body) as T;
}

export async function searchMessages(query: string): Promise<MailSummary[]> {
  const data = await api<{ messages: MailSummary[] }>(`/search?limit=50&query=${encodeURIComponent(query)}`);
  return data.messages || [];
}

export async function getMessage(id: string): Promise<MailMessage> {
  return api<MailMessage>(`/message/${id}`);
}

/**
 * Espera chegar um e-mail para `to` cujo assunto bata com `subject`.
 * O WordPress manda os e-mails na mesma requisição, mas o Mailpit pode levar um instante para indexar.
 */
export async function waitForMessage(to: string, subject: RegExp, timeout = 30_000): Promise<MailMessage> {
  let found: MailSummary | undefined;
  await expect
    .poll(
      async () => {
        const messages = await searchMessages(`to:"${to}"`);
        found = messages.find((m) => subject.test(m.Subject));
        return found ? found.Subject : `(${messages.length} e-mail(s) para ${to}: ${messages.map((m) => m.Subject).join(' | ') || 'nenhum'})`;
      },
      { message: `e-mail para ${to} com assunto ${subject}`, timeout, intervals: [500, 1000, 2000] },
    )
    .toMatch(subject);
  return getMessage(found!.ID);
}

/** Apaga os e-mails enviados para os endereços de teste (limpeza). */
export async function deleteMessagesTo(addresses: string[]): Promise<void> {
  const ids: string[] = [];
  for (const address of addresses) {
    ids.push(...(await searchMessages(`to:"${address}"`)).map((m) => m.ID));
  }
  await deleteMessages(ids);
}

export async function deleteMessages(ids: string[]): Promise<void> {
  if (ids.length) {
    await api('/messages', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ IDs: ids }) });
  }
}

/** Primeira URL do texto do e-mail que bata com o padrão. */
export function findLink(message: MailMessage, pattern: RegExp): string {
  const decode = (s: string) => s.replace(/&amp;/g, '&');
  const urls = [...`${message.Text}\n${message.HTML}`.matchAll(/https?:\/\/[^\s"'<>)\]]+/g)].map((m) => decode(m[0]));
  const url = urls.find((u) => pattern.test(u));
  if (!url) {
    throw new Error(`Nenhum link ${pattern} no e-mail "${message.Subject}". Links: ${urls.join(', ')}`);
  }
  return url;
}
