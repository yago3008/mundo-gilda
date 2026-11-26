#!/usr/bin/env node
// Lê as páginas públicas da loja Wix (mundogilda.com.br) e gera data/products.json
// com nome, preço, descrição, categorias, atributos (cor/tamanho), variações com
// estoque e URLs das imagens. Uso: node scripts/export-wix-products.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = 'https://www.mundogilda.com.br';
const OUT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'data', 'products.json');

const get = async (url) => {
  const res = await fetch(url, { headers: { 'User-Agent': 'Mozilla/5.0 (product export)' } });
  if (!res.ok) throw new Error(`${res.status} ${url}`);
  return res.text();
};

// Extrai o objeto JSON que contém a posição `pos` (dados embutidos pelo Wix na página).
function enclosingObject(src, pos) {
  let depth = 0;
  for (let i = pos; i >= 0; i--) {
    const c = src[i];
    if (c === '}') depth++;
    else if (c === '{') {
      if (depth > 0) { depth--; continue; }
      let d = 0, inStr = false, esc = false;
      for (let j = i; j < src.length; j++) {
        const ch = src[j];
        if (inStr) {
          if (esc) esc = false; else if (ch === '\\') esc = true; else if (ch === '"') inStr = false;
          continue;
        }
        if (ch === '"') inStr = true;
        else if (ch === '{') d++;
        else if (ch === '}' && --d === 0) return JSON.parse(src.slice(i, j + 1));
      }
    }
  }
  throw new Error('objeto não encontrado');
}

// Descrição do Wix vem em "rich content" (JSON) ou HTML: converte para parágrafos simples.
function descriptionToHtml(desc) {
  if (!desc) return '';
  try {
    const doc = JSON.parse(desc);
    const text = (n) => (n.textData?.text ?? '') + (n.nodes || []).map(text).join('');
    return doc.nodes
      .map((p) => text(p).trim())
      .filter(Boolean)
      .map((t) => `<p>${t.replace(/\n+/g, '<br>')}</p>`)
      .join('\n');
  } catch {
    return desc;
  }
}

const listing = await get(`${BASE}/category/all-products`);
const slugs = [...new Set([...listing.matchAll(/product-page\/([^"\\?]+)/g)].map((m) => m[1]))];

const products = [];
for (const slug of slugs) {
  const html = await get(`${BASE}/product-page/${slug}`);
  const p = enclosingObject(html, html.indexOf('"productItems"'));
  const options = (p.options || []).map((o) => ({
    name: o.title.trim().toLowerCase() === 'cor' ? 'Cor' : o.title.trim().toLowerCase() === 'tamanho' ? 'Tamanho' : o.title.trim(),
    type: o.optionType,
    values: o.selections.map((s) => ({ id: s.id, label: s.description.trim(), hex: o.optionType === 'COLOR' ? s.value : undefined })),
  }));
  const labelOf = (id) => {
    for (const o of options) { const v = o.values.find((x) => x.id === id); if (v) return [o.name, v.label]; }
    return [null, null];
  };
  const items = (p.productItems || []).map((it) => ({
    attributes: Object.fromEntries(it.optionsSelections.map(labelOf)),
    price: it.price,
    stock_status: it.inventory?.status === 'in_stock' ? 'instock' : 'outofstock',
    stock_quantity: it.inventory?.quantity ?? null,
  }));
  products.push({
    slug: decodeURIComponent(slug),
    name: p.name.trim(),
    price: p.price,
    description: descriptionToHtml(p.description),
    categories: (p.categories || []).map((c) => c.name).filter((n) => n !== 'All Products'),
    attributes: options.map(({ name, values }) => ({ name, values: values.map(({ label, hex }) => (hex ? { label, hex } : { label })) })),
    variations: options.length ? items : [],
    stock_status: options.length ? undefined : items[0]?.stock_status,
    stock_quantity: options.length ? undefined : items[0]?.stock_quantity,
    images: (p.media || []).filter((m) => m.mediaType === 'PHOTO' || !m.mediaType).map((m) => `https://static.wixstatic.com/media/${m.url}`),
  });
  console.log(`✓ ${p.name} (${items.length} itens)`);
}

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify({ source: BASE, exported_at: new Date().toISOString(), products }, null, 2) + '\n');
console.log(`\n${products.length} produtos → ${OUT}`);
