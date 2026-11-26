#!/usr/bin/env node
// Servidor estático mínimo para os protótipos (sem dependências).
//   node prototypes/serve.mjs            → http://localhost:5500/prototypes/zoom-mask/
// Serve só /prototypes/ e as fontes/imagens do tema; o resto do repositório (ex.: .env) fica de fora.
// As fotos vêm do WordPress local (http://localhost:8080), então o Docker precisa estar no ar
// e as imagens do site importadas (IMPORT_IMAGES=1 ./scripts/setup.sh).
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PORT = Number(process.env.PORT || 5500);
const ALLOWED = ['/prototypes/', '/wp-content/themes/gilda/assets/'];
const TYPES = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.mjs': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2' };

http.createServer((req, res) => {
  let url = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  if (url === '/') { res.writeHead(302, { Location: '/prototypes/zoom-mask/' }); return res.end(); }
  if (url.endsWith('/')) url += 'index.html';
  const file = path.join(ROOT, url);
  if (!ALLOWED.some((p) => url.startsWith(p)) || !file.startsWith(ROOT)) { res.writeHead(404); return res.end('404'); }
  fs.readFile(file, (err, data) => {
    if (err) { res.writeHead(404); return res.end('404'); }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
    res.end(data);
  });
}).listen(PORT, () => console.log(`Protótipos em http://localhost:${PORT}/prototypes/zoom-mask/`));
