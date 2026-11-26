/*
 * Segunda metade da transição dos cards de categoria (a primeira é assets/js/category-zoom.js).
 * Impresso inline logo no começo do <body> (inc/setup.php), para a tela creme com o nome vazado
 * já sair na primeira pintura do catálogo. Depois o zoom entra numa letra e revela a página.
 */
(() => {
	let s = null;
	try {
		s = JSON.parse(sessionStorage.getItem('gilda-czoom') || 'null');
		sessionStorage.removeItem('gilda-czoom');
	} catch (e) {
		return;
	}
	if (!s || !s.text || Date.now() - s.t > 10000) return;
	if (!s.force && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

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
		+ `</mask></defs><rect class="gilda-czoom__cover" width="${vw}" height="${vh}" mask="url(#gilda-czoom-in)"></rect>`;
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
	overlay.appendChild(svg);
	document.body.appendChild(overlay);

	// Enquanto a fonte do nome não carregou, a tela fica só creme (sem a letra "errada" piscando).
	const fontSpec = `${s.font.style} ${s.font.weight} ${Math.round(size)}px ${s.font.family}`;
	let fontReady = Promise.resolve();
	try {
		if (document.fonts && !document.fonts.check(fontSpec)) {
			word.style.visibility = 'hidden';
			fontReady = document.fonts.load(fontSpec).catch(() => {});
		}
	} catch (e) { /* segue com a fonte que tiver */ }

	const domReady = document.readyState === 'loading'
		? new Promise((r) => document.addEventListener('DOMContentLoaded', r, { once: true }))
		: Promise.resolve();
	const timeout = (ms) => new Promise((r) => setTimeout(r, ms));

	const dive = (p) => {
		const sc = Math.pow(s.maxScale, p * p);
		const slide = smooth(p);
		const px = lerp(origin.x, center.x, slide);
		const py = lerp(origin.y, center.y, slide);
		zoom.setAttribute('transform', `translate(${px} ${py}) scale(${sc}) translate(${-origin.x} ${-origin.y})`);
	};
	dive(0);

	Promise.race([Promise.all([fontReady, domReady]), timeout(1500)]).then(() => {
		word.style.visibility = '';
		let started = 0;
		const frame = (now) => {
			if (!started) started = now;
			const p = clamp((now - started) / DIVE_MS);
			dive(p);
			if (p < 1) {
				requestAnimationFrame(frame);
			} else {
				overlay.remove();
			}
		};
		// um respiro curto: a página por baixo termina de montar antes do mergulho
		setTimeout(() => requestAnimationFrame(frame), 150);
	});

	// Voltar/avançar pelo navegador não pode reaparecer com a tela creme.
	window.addEventListener('pageshow', (e) => { if (e.persisted) overlay.remove(); });
})();
