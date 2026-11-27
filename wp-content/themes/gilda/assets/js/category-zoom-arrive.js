/*
 * Segunda metade da transição dos cards de categoria (a primeira é assets/js/category-zoom.js).
 * Impresso inline logo no começo do <body> (inc/setup.php), para a tela marrom com o nome vazado
 * já sair na primeira pintura do catálogo. Depois o zoom entra numa letra e revela a página.
 * Quando o mergulho inteiro já rodou na home ({hold: true}), aqui só se carregam as fotos de uma vez.
 * No <head>, gilda_category_zoom_hold() segura a pintura até a página chegar inteira e adianta a fonte.
 */
(() => {
	let s = null;
	try {
		s = JSON.parse(sessionStorage.getItem('gilda-czoom') || 'null');
		sessionStorage.removeItem('gilda-czoom');
	} catch (e) {
		return;
	}
	if (!s || (!s.text && !s.hold) || Date.now() - s.t > 10000) return;

	const domReady = document.readyState === 'loading'
		? new Promise((r) => document.addEventListener('DOMContentLoaded', r, { once: true }))
		: Promise.resolve();

	// As fotos do catálogo são "lazy" (só carregam depois da primeira pintura); aqui elas aparecem pelas
	// letras desde o começo, então carregam já (a abertura na home deixou elas no cache).
	const eager = (img) => {
		// decoding=sync: a primeira pintura já sai com a foto (senão as letras ficam creme por uns quadros)
		img.setAttribute('decoding', 'sync');
		if (img.getAttribute('loading') !== 'lazy') return;
		img.setAttribute('loading', 'eager');
		const sizes = img.getAttribute('sizes');
		if (sizes) img.setAttribute('sizes', sizes.replace(/^\s*auto\s*,\s*/, ''));
	};
	try {
		document.querySelectorAll('img').forEach(eager);
		const mo = new MutationObserver((list) => {
			for (const m of list) {
				for (const n of m.addedNodes) {
					if (n.nodeType !== 1) continue;
					if (n.tagName === 'IMG') eager(n);
					else n.querySelectorAll('img').forEach(eager);
				}
			}
		});
		mo.observe(document.documentElement, { childList: true, subtree: true });
		domReady.then(() => mo.disconnect());
	} catch (e) { /* segue com o lazy */ }

	// O mergulho já aconteceu na home (o catálogo estava montado por trás das letras e ocupou a tela):
	// aqui só chega a página real, igual à que estava na tela. Nada de overlay.
	if (s.hold) return;
	if (!s.force && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	// Login/cadastro: só mergulha se deu certo (a página seguinte já está logada).
	if (s.requireLogin && !(document.body && document.body.classList.contains('logged-in'))) return;

	const DIVE_MS = 1200;
	const NS = 'http://www.w3.org/2000/svg';
	const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
	const lerp = (a, b, t) => a + (b - a) * t;
	const smooth = (t) => t * t * (3 - 2 * t);

	// Mesma tela da home; se a janela mudou de tamanho no caminho, acompanha.
	const vw = document.documentElement.clientWidth || window.innerWidth;
	const vh = window.innerHeight;
	const kx = vw / s.vw, ky = vh / s.vh, k = Math.min(kx, ky);
	const size = s.size * k;
	const pos = { x: s.x * kx, y: s.y * ky };
	const center = { x: vw / 2, y: vh / 2 };
	const origin = { x: s.origin.x * kx, y: s.origin.y * ky };

	const overlay = document.createElement('div');
	overlay.className = 'gilda-czoom';
	overlay.setAttribute('aria-hidden', 'true');
	const svg = document.createElementNS(NS, 'svg');
	svg.setAttribute('class', 'gilda-czoom__svg');
	svg.setAttribute('viewBox', `0 0 ${vw} ${vh}`);
	svg.innerHTML = `<defs><mask id="gilda-czoom-in" maskUnits="userSpaceOnUse" x="0" y="0" width="${vw}" height="${vh}">`
		+ `<rect width="${vw}" height="${vh}" fill="#fff"></rect><g class="gilda-czoom__zoom"><text fill="#000" text-anchor="middle"></text></g>`
		+ `</mask></defs><rect class="gilda-czoom__cover" width="${vw}" height="${vh}" mask="url(#gilda-czoom-in)"></rect><g class="gilda-czoom__solid"></g>`;
	const zoom = svg.querySelector('.gilda-czoom__zoom');
	const word = svg.querySelector('text');
	word.textContent = s.text;
	Object.assign(word.style, {
		fontFamily: s.font.family,
		fontWeight: s.font.weight,
		fontStyle: s.font.style,
		letterSpacing: `${s.font.spacingEm}em`,
	});
	word.setAttribute('font-size', size);
	word.setAttribute('x', pos.x);
	word.setAttribute('y', pos.y);
	// Login/cadastro: as letras chegam sólidas (creme, iguais às da tela anterior) e vão ficando
	// transparentes enquanto o ";" cresce, revelando a página (sem corte seco no fim).
	const solidLayer = svg.querySelector('.gilda-czoom__solid');
	let solidWord = null;
	if (s.solid) {
		solidWord = word.cloneNode(true);
		solidWord.removeAttribute('fill');
		solidWord.classList.add('gilda-czoom__solid-word');
		solidLayer.appendChild(solidWord);
	}
	overlay.appendChild(svg);
	document.body.appendChild(overlay);

	// Enquanto a fonte do nome não carregou, a tela fica só marrom (sem a letra "errada" piscando).
	const fontSpec = `${s.font.style} ${s.font.weight} ${Math.round(size)}px ${s.font.family}`;
	let fontReady = Promise.resolve();
	try {
		if (document.fonts && !document.fonts.check(fontSpec)) {
			word.style.visibility = 'hidden';
			if (solidWord) solidWord.style.visibility = 'hidden';
			fontReady = document.fonts.load(fontSpec).catch(() => {});
		}
	} catch (e) { /* segue com a fonte que tiver */ }
	// O nome volta assim que a fonte chega (não espera o resto da página).
	Promise.race([fontReady, new Promise((r) => setTimeout(r, 1500))]).then(() => {
		word.style.visibility = '';
		if (solidWord) solidWord.style.visibility = '';
	});


	const timeout = (ms) => new Promise((r) => setTimeout(r, ms));

	const dive = (p) => {
		const sc = Math.pow(s.maxScale, p * p);
		const slide = smooth(p);
		const px = lerp(origin.x, center.x, slide);
		const py = lerp(origin.y, center.y, slide);
		zoom.setAttribute('transform', `translate(${px} ${py}) scale(${sc}) translate(${-origin.x} ${-origin.y})`);
		if (solidWord) {
			solidLayer.setAttribute('transform', zoom.getAttribute('transform'));
			solidLayer.style.opacity = 1 - smooth(clamp((p - 0.3) / 0.4));
		}
	};
	dive(0);
	// A letra já cobre a tela toda quando o círculo inscrito (raio r, o ponto mais grosso) passa da metade
	// da diagonal: termina ali. Mais perto de maxScale a letra fica enorme e o navegador pode deixar de
	// desenhar (a tela marrom voltava por um quadro no fim).
	const r = (Math.hypot(vw, vh) * 0.7) / s.maxScale;
	const pEnd = clamp(Math.sqrt(Math.log(Math.max(1.0001, (Math.hypot(vw, vh) * 0.56) / r)) / Math.log(s.maxScale)), 0.5, 1);

	// O gilda.js marca <html class="js"> no fim do carregamento; como o mergulho começa antes, marca já
	// (senão o layout que depende dela, como os filtros no celular, mudaria no meio do mergulho).
	document.documentElement.classList.add('js');

	// Começa logo depois do primeiro quadro da página (não espera os scripts do fim: era mais uma pausa
	// parada). O <head> segura a pintura até o HTML chegar inteiro (readyState "interactive"); dois
	// requestAnimationFrame garantem que esse primeiro quadro, igual ao último da home, já foi mostrado.
	const parsed = document.readyState === 'loading'
		? new Promise((r) => document.addEventListener('readystatechange', r, { once: true }))
		: Promise.resolve();
	const painted = () => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
	Promise.race([Promise.all([fontReady, parsed]).then(painted), timeout(1500)]).then(() => {
		word.style.visibility = '';
		if (solidWord) solidWord.style.visibility = '';
		let started = 0;
		const frame = (now) => {
			if (!started) started = now;
			const p = clamp((now - started) / DIVE_MS);
			dive(Math.min(p, pEnd));
			if (p < pEnd) {
				requestAnimationFrame(frame);
			} else {
				overlay.remove();
			}
		};
		// começa no próximo quadro: a página já chegou inteira (o <head> segurou a pintura até ela chegar)
		requestAnimationFrame(frame);
		// aba em segundo plano (sem requestAnimationFrame): não deixa a tela marrom presa
		setTimeout(() => overlay.remove(), DIVE_MS + 1500);
	});

	// Voltar/avançar pelo navegador não pode reaparecer com a tela marrom.
	window.addEventListener('pageshow', (e) => { if (e.persisted) overlay.remove(); });
})();
