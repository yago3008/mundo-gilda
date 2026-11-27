/*
 * Cards de categoria (pattern gilda/categories) e de capítulo (vitrine da home): ao clicar, a tela "entra"
 * no nome do card (CAMISAS, CAPÍTULO 1…) como no mergulho do "GILDA;" (assets/js/transitions.js) e cai
 * no catálogo.
 *
 * 1. abertura (aqui): o card vira marrom com o nome vazado (a foto do card some das letras logo no
 *    começo e elas mostram o próprio catálogo, carregado num iframe por baixo desde que o mouse/dedo
 *    chegou no card), cresce até ocupar a tela e o nome vai para o centro;
 * 2. navega para o link do card. Enquanto o catálogo carrega, o navegador segura esse último quadro;
 * 3. mergulho (no catálogo, assets/js/category-zoom-arrive.js): a mesma tela marrom com o nome aparece
 *    já na primeira pintura e o zoom entra numa letra revelando o próprio catálogo.
 *    Os dados do quadro passam pelo sessionStorage ('gilda-czoom').
 *
 * Login e cadastro (Minha conta): o formulário é enviado em segundo plano; se a resposta já vier logada, o
 * "GILDA;" do topo cresce até o centro e a página seguinte mergulha no ";", como na abertura do Quem Cria.
 * Se falhar (senha errada, bloqueio por tentativas), só aparece a mensagem de erro, sem animação.
 *
 * Com "menos movimento" no sistema, ctrl/cmd/shift/clique do meio ou nova aba, o link funciona normal.
 * Sem dependências.
 */
(() => {
	const force = /[?&]anima=1\b/.test(window.location.search);
	if (!force && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	const AUTH_FORMS = 'form.woocommerce-form-login, form.woocommerce-form-register';
	// Cards que abrem com o zoom: tipos de roupa (pattern gilda/categories: o link é o nome) e capítulos
	// (vitrine de capítulos da home: o card inteiro é o link, o nome é o título dentro dele).
	const CARD_LINKS = '.gilda-category a[href], a.gilda-chapter-card[href]';
	if (!document.querySelector(`${CARD_LINKS}, ${AUTH_FORMS}`)) return;

	/** Link, card, nome e foto (a que está na tela) de um card clicável, ou null. */
	const cardParts = (el) => {
		const link = el && el.closest && el.closest(CARD_LINKS);
		if (!link) return null;
		if (link.matches('.gilda-chapter-card')) {
			// a vitrine troca as fotos sozinha (crossfade): usa a que está mais visível agora
			const imgs = Array.from(link.querySelectorAll('.gilda-chapter-card__image'));
			const img = imgs.reduce((best, el) => (!best || parseFloat(getComputedStyle(el).opacity) > parseFloat(getComputedStyle(best).opacity) ? el : best), null);
			return { link, card: link, label: link.querySelector('.gilda-chapter-card__title') || link, img };
		}
		const card = link.closest('.gilda-category');
		return { link, card, label: link, img: card && card.querySelector('img') };
	};

	const NS = 'http://www.w3.org/2000/svg';
	const OPEN_MS = 650;
	const KEY = 'gilda-czoom';
	const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
	const lerp = (a, b, t) => a + (b - a) * t;
	const smooth = (t) => t * t * (3 - 2 * t);
	const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

	let overlay = null;
	let preview = null; // catálogo carregado por baixo: { href, frame, ready, loaded }
	const dropPreview = () => {
		if (preview) preview.frame.remove();
		preview = null;
	};
	const cleanup = () => {
		if (overlay) overlay.remove();
		overlay = null;
		dropPreview();
	};
	// Voltar pelo navegador (bfcache) não pode mostrar a tela da transição.
	window.addEventListener('pageshow', (e) => { if (e.persisted) cleanup(); });

	/**
	 * Carrega o catálogo do card num iframe invisível, para as letras já mostrarem a página de destino
	 * durante a abertura, igual ao mergulho que continua lá (sem a tela creme piscando no meio).
	 * O HTML vem por fetch (catálogo em cache) e entra sem scripts nem <noscript>: nada de pixel, banner
	 * ou visita contada em dobro. As fotos carregam já (não "lazy"), e o navegador guarda para a página real.
	 */
	// HTML dos catálogos já buscado (href => Promise<texto>): no celular não há "mouse em cima" antes do toque.
	const pages = new Map();
	const fetchPage = (href) => {
		if (!pages.has(href)) {
			const req = fetch(href, { credentials: 'same-origin', priority: 'low' })
				.then((res) => (res.ok ? res.text() : Promise.reject(new Error(String(res.status)))));
			req.catch(() => pages.delete(href));
			pages.set(href, req);
		}
		return pages.get(href);
	};
	// Quando os cards aparecem na tela, já busca o HTML de cada catálogo (leve, vem do cache do catálogo).
	if ('IntersectionObserver' in window && window.fetch) {
		const io = new IntersectionObserver((entries) => {
			if (!entries.some((en) => en.isIntersecting)) return;
			io.disconnect();
			document.querySelectorAll(CARD_LINKS).forEach((a) => {
				if (a.origin === window.location.origin) {
					fetchPage(a.href);
					prefetch(a.href);
				}
			});
		}, { rootMargin: '200px 0px' });
		document.querySelectorAll('.gilda-category, .gilda-chapter-card').forEach((card) => io.observe(card));
	}

	const loadPreview = (href) => {
		if (preview && preview.href === href) return preview;
		if (overlay || !window.fetch || !window.DOMParser) return preview; // já animando: não troca
		dropPreview();
		const frame = document.createElement('iframe');
		frame.className = 'gilda-czoom__page';
		frame.setAttribute('sandbox', 'allow-same-origin');
		frame.setAttribute('aria-hidden', 'true');
		frame.setAttribute('tabindex', '-1');
		frame.setAttribute('title', '');
		document.body.appendChild(frame);
		const p = { href, frame, ready: false, loaded: null };
		p.loaded = fetchPage(href)
			.then((html) => {
				if (preview !== p) return Promise.reject(new Error('trocou'));
				const doc = new DOMParser().parseFromString(html, 'text/html');
				doc.querySelectorAll('script, noscript, iframe, link[rel="modulepreload"], link[as="script"]').forEach((n) => n.remove());
				doc.querySelectorAll('img[loading="lazy"]').forEach((img) => {
					img.setAttribute('loading', 'eager');
					const sizes = img.getAttribute('sizes');
					if (sizes) img.setAttribute('sizes', sizes.replace(/^\s*auto\s*,\s*/, ''));
				});
				// a página real marca <html class="js"> (gilda.js); sem isso o layout muda (filtros no celular)
				doc.documentElement.classList.add('js');
				const base = doc.createElement('base');
				base.href = href;
				doc.head.prepend(base);
				return new Promise((resolve) => {
					frame.addEventListener('load', resolve, { once: true });
					frame.srcdoc = `<!DOCTYPE html>${doc.documentElement.outerHTML}`;
				});
			})
			.then(() => {
				p.ready = true;
				return true;
			}, () => false); // falhou: as letras ficam creme, como antes
		preview = p;
		return p;
	};

	// Adianta o carregamento do catálogo quando a pessoa chega perto do card. Com Speculation Rules
	// (Chrome/Edge), a navegação usa a resposta já buscada: o clique não espera o servidor (antes eram
	// ~0,5 s parados no fim da abertura). Sem suporte, fica o <link rel=prefetch> comum.
	const prefetched = new Set();
	const canSpeculate = !!(window.HTMLScriptElement && HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules'));
	const prefetch = (href) => {
		if (prefetched.has(href)) return;
		prefetched.add(href);
		if (canSpeculate) {
			const rules = document.createElement('script');
			rules.type = 'speculationrules';
			rules.textContent = JSON.stringify({ prefetch: [{ source: 'list', urls: [href] }] });
			document.head.appendChild(rules);
			return;
		}
		const link = document.createElement('link');
		link.rel = 'prefetch';
		link.href = href;
		document.head.appendChild(link);
	};

	/**
	 * Ponto mais "grosso" de uma letra (o mais longe da borda), relativo à posição da caneta
	 * (início da letra, na linha de base). O zoom entra por ali sem cair num vão da letra.
	 */
	// Cópia da foto do card já decodificada (no mouse em cima): o clique não espera decodificar.
	const decodedPics = new Map();
	const predecode = (img) => {
		const src = img && (img.currentSrc || img.src);
		if (!src || decodedPics.has(src)) return;
		const pic = new Image();
		pic.decoding = 'sync';
		pic.src = src;
		decodedPics.set(src, pic);
		if (pic.decode) pic.decode().catch(() => {});
	};

	const thickCache = new Map();
	function thickestPoint(char, style, size) {
		const key = `${char}|${style.fontStyle}|${style.fontWeight}|${style.fontFamily}|${size}`;
		if (!thickCache.has(key)) thickCache.set(key, measureThickest(char, style, size));
		return thickCache.get(key);
	}
	function measureThickest(char, style, size) {
		const canvas = document.createElement('canvas');
		const pad = Math.ceil(size * 0.4);
		canvas.width = Math.ceil(size * 1.6) + pad * 2;
		canvas.height = Math.ceil(size * 1.6) + pad * 2;
		const ctx = canvas.getContext('2d', { willReadFrequently: true });
		ctx.font = `${style.fontStyle} ${style.fontWeight} ${size}px ${style.fontFamily}`;
		const penX = pad;
		const baseY = pad + size;
		ctx.fillText(char, penX, baseY);
		const { width: W, height: H } = canvas;
		const alpha = ctx.getImageData(0, 0, W, H).data;
		// distância (chanfro 3-4) até o pixel vazio mais próximo
		const d = new Float32Array(W * H);
		const BIG = 1e9;
		for (let i = 0; i < W * H; i++) d[i] = alpha[i * 4 + 3] > 127 ? BIG : 0;
		for (let y = 1; y < H - 1; y++) {
			for (let x = 1; x < W - 1; x++) {
				const i = y * W + x;
				if (!d[i]) continue;
				d[i] = Math.min(d[i], d[i - 1] + 3, d[i - W] + 3, d[i - W - 1] + 4, d[i - W + 1] + 4);
			}
		}
		let best = 0, bx = 0, by = 0;
		for (let y = H - 2; y > 0; y--) {
			for (let x = W - 2; x > 0; x--) {
				const i = y * W + x;
				if (!d[i]) continue;
				d[i] = Math.min(d[i], d[i + 1] + 3, d[i + W] + 3, d[i + W + 1] + 4, d[i + W - 1] + 4);
				if (d[i] > best) { best = d[i]; bx = x; by = y; }
			}
		}
		return { dx: bx - penX, dy: by - baseY, r: best / 3 };
	}

	/**
	 * Abertura da transição.
	 * @param {Element}  label  Elemento com o nome (texto, fonte e posição inicial).
	 * @param {Object}   opts   card/img: card de origem (a foto aparece pelas letras); sem eles a tela creme
	 *                          cobre a página inteira. zoomInto: 'middle' (letra mais grossa do meio) ou 'last'
	 *                          (a última, o ";"). requireLogin: só mergulha se a próxima página estiver logada.
	 *                          go: o que fazer no fim (navegar, enviar o formulário).
	 */
	function play(label, opts) {
		const { card = null, img = null, page = null, zoomInto = 'middle', requireLogin = false, go } = opts;
		// Sem card (login/cadastro): letras sólidas (creme), sem a página aparecendo por dentro.
		const solid = opts.solid ?? !card;
		const text = (label.textContent || '').trim();
		if (!text || (card && !img)) return false;

		const vw = document.documentElement.clientWidth;
		const vh = window.innerHeight;
		const cardBox = card ? card.getBoundingClientRect() : { left: 0, top: 0, width: vw, height: vh };
		const range = document.createRange();
		range.selectNodeContents(label);
		const labelBox = range.getBoundingClientRect();
		const labelStyle = getComputedStyle(label);
		const labelSize = parseFloat(labelStyle.fontSize) || 22;
		const dim = card && card.querySelector('.wp-block-cover__background');
		const dimStyle = dim ? getComputedStyle(dim) : null;

		// ---- camadas: foto (no lugar do card) + creme com o nome vazado
		overlay = document.createElement('div');
		overlay.className = 'gilda-czoom';
		overlay.setAttribute('aria-hidden', 'true');

		// Sem card (login), as letras mostram a própria página por baixo.
		const photo = document.createElement('div');
		photo.className = 'gilda-czoom__photo';
		const src = img ? img.currentSrc || img.src : '';
		const pic = (src && decodedPics.get(src)) || document.createElement('img');
		const shade = document.createElement('div');
		if (img) {
			if (pic.src !== src) pic.src = src;
			pic.alt = '';
			pic.style.objectPosition = getComputedStyle(img).objectPosition;
			photo.appendChild(pic);
			shade.className = 'gilda-czoom__shade';
			if (dimStyle) shade.style.background = dimStyle.backgroundColor;
			photo.appendChild(shade);
		}
		const shadeFrom = dimStyle ? parseFloat(dimStyle.opacity) || 0 : 0;

		const svg = document.createElementNS(NS, 'svg');
		svg.setAttribute('class', 'gilda-czoom__svg');
		svg.setAttribute('viewBox', `0 0 ${vw} ${vh}`);
		const id = `gilda-czoom-${Date.now()}`;
		svg.innerHTML = `<defs><mask id="${id}" maskUnits="userSpaceOnUse" x="0" y="0" width="${vw}" height="${vh}">`
			+ `<rect width="${vw}" height="${vh}" fill="#fff"></rect><g class="gilda-czoom__zoom"><text fill="#000" text-anchor="middle"></text></g>`
			+ `</mask></defs><rect class="gilda-czoom__cover" mask="url(#${id})"></rect><g class="gilda-czoom__solid"></g>`;
		const zoom = svg.querySelector('.gilda-czoom__zoom');
		const word = svg.querySelector('text');
		const cover = svg.querySelector('.gilda-czoom__cover');
		word.textContent = text;
		word.style.fontFamily = labelStyle.fontFamily;
		word.style.fontWeight = labelStyle.fontWeight;
		word.style.fontStyle = labelStyle.fontStyle;
		const spacingEm = (parseFloat(labelStyle.letterSpacing) || 0) / labelSize;
		word.style.letterSpacing = `${spacingEm}em`;

		if (img) overlay.append(photo);
		overlay.append(svg);
		document.body.appendChild(overlay);

		// Catálogo por baixo: a área do card mostra o iframe (recortado no tamanho do card) e a foto
		// some revelando ele. Se ainda não carregou, as letras ficam creme até ele chegar.
		const fw = window.innerWidth;
		const seeThrough = () => {
			if (!page || !overlay) return;
			page.frame.classList.add('is-playing');
			photo.classList.add('is-see-through');
		};
		if (page) {
			if (page.ready) seeThrough();
			else page.loaded.then((ok) => { if (ok) seeThrough(); });
		}

		// ---- tamanho final do nome: ~86% da largura, sem passar de ~40% da altura
		const probe = 100;
		word.setAttribute('font-size', probe);
		const probeLen = word.getComputedTextLength() || text.length * probe * 0.7;
		const size = Math.min((probe * vw * 0.86) / probeLen, vh * 0.55);
		const center = { x: vw / 2, y: vh / 2 };
		word.setAttribute('font-size', size);
		word.setAttribute('x', center.x);
		word.setAttribute('y', center.y + size * 0.35); // linha de base ≈ centro + metade da altura das maiúsculas (= baseline abaixo)
		const solidLayer = svg.querySelector('.gilda-czoom__solid');
		if (solid) {
			const solidWord = word.cloneNode(true);
			solidWord.removeAttribute('fill');
			solidWord.classList.add('gilda-czoom__solid-word');
			solidLayer.appendChild(solidWord);
		}

		// ---- por onde o zoom entra: a letra mais grossa entre as do meio (ou o ";" do GILDA;)
		let origin = { x: center.x, y: center.y }, radius = size * 0.08;
		try {
			const n = word.getNumberOfChars();
			const mid = Math.floor(n / 2);
			const chars = 'last' === zoomInto ? [n - 1] : [...new Set([mid, mid - 1, mid + 1].filter((i) => i >= 0 && i < n))];
			const sample = 120;
			let best = null;
			for (const i of chars) {
				const ch = text.charAt(i);
				if (!ch.trim()) continue;
				const t = thickestPoint(ch, labelStyle, sample);
				if (!best || t.r > best.r) best = { ...t, i };
			}
			if (best && best.r > 0) {
				const pen = word.getStartPositionOfChar(best.i);
				const k = size / sample;
				origin = { x: pen.x + best.dx * k, y: pen.y + best.dy * k };
				radius = Math.max(2, best.r * k);
			}
		} catch (e) { /* fica no centro */ }
		const maxScale = Math.max(8, (Math.hypot(vw, vh) * 0.7) / radius);
		const baseline = center.y + size * 0.35;

		const labelCenter = { x: labelBox.left + labelBox.width / 2, y: labelBox.top + labelBox.height / 2 };
		const startScale = labelSize / size;

		// A foto não muda de tamanho de verdade (cada tamanho novo obrigava o navegador a decodificar a imagem
		// de novo, 30-50 ms por quadro: era a travada no começo). Ela fica no tamanho do card e só cresce por
		// transform; a camada da foto ocupa a tela e é recortada (clip-path) no retângulo que cresce.
		const from = { x: cardBox.left, y: cardBox.top, w: cardBox.width, h: cardBox.height };
		const to = { x: 0, y: 0, w: vw, h: vh };
		Object.assign(photo.style, { left: '0px', top: '0px', width: `${vw}px`, height: `${vh}px` });
		Object.assign(pic.style, { left: `${from.x}px`, top: `${from.y}px`, width: `${from.w}px`, height: `${from.h}px` });
		const setBox = (b, t) => {
			photo.style.clipPath = `inset(${b.y}px ${vw - b.x - b.w}px ${vh - b.y - b.h}px ${b.x}px)`;
			const k = Math.max(b.w / from.w, b.h / from.h) * lerp(1, 1.2, t);
			const dx = b.x + b.w / 2 - (from.x + from.w / 2);
			const dy = b.y + b.h / 2 - (from.y + from.h / 2);
			pic.style.transform = `translate(${dx}px, ${dy}px) scale(${k})`;
			cover.setAttribute('x', b.x);
			cover.setAttribute('y', b.y);
			cover.setAttribute('width', b.w);
			cover.setAttribute('height', b.h);
			if (page) page.frame.style.clipPath = `inset(${b.y}px ${fw - b.x - b.w}px ${vh - b.y - b.h}px ${b.x}px)`;
		};

		const open = (p) => {
			const t = easeInOut(p);
			setBox({ x: lerp(from.x, to.x, t), y: lerp(from.y, to.y, t), w: lerp(from.w, to.w, t), h: lerp(from.h, to.h, t) }, t);
			cover.style.opacity = smooth(clamp(p / 0.35));
			const cx = lerp(labelCenter.x, center.x, t);
			const cy = lerp(labelCenter.y, center.y, t);
			const s = lerp(startScale, 1, t);
			zoom.setAttribute('transform', `translate(${cx} ${cy}) scale(${s}) translate(${-center.x} ${-center.y})`);
			if (solid) {
				solidLayer.setAttribute('transform', zoom.getAttribute('transform'));
				solidLayer.style.opacity = cover.style.opacity;
			}
			// A foto do card some logo no começo: as letras passam a mostrar o catálogo (iframe por baixo),
			// iguais às do mergulho que continua lá (category-zoom-arrive.js).
			// Começa a sumir quando o marrom já cobriu o card (senão o catálogo aparece "fantasma" no card todo).
			const fade = 1 - smooth(clamp((p - 0.28) / 0.27));
			pic.style.opacity = fade;
			shade.style.opacity = shadeFrom * fade;
		};

		// O catálogo continua daqui: mesmo nome, mesma posição, mesmo ponto de entrada do zoom.
		const handOff = () => {
			try {
				sessionStorage.setItem(KEY, JSON.stringify({
					t: Date.now(),
					force,
					text,
					font: { family: labelStyle.fontFamily, weight: labelStyle.fontWeight, style: labelStyle.fontStyle, spacingEm },
					vw, vh, size,
					x: center.x, y: baseline,
					origin, maxScale,
					requireLogin,
					solid,
				}));
			} catch (e) { /* sem sessionStorage: o catálogo abre sem o mergulho */ }
		};

		open(0);
		let started = 0, finished = false;
		const finish = () => {
			if (finished || !overlay) return;
			finished = true;
			open(1);
			const leave = () => {
				handOff();
				go();
			};
			if (!page || page.ready) {
				leave();
				return;
			}
			// O catálogo do iframe ainda não chegou: espera um pouco (a navegação ia esperar parte disso).
			// Se chegar, deixa o creme sair das letras antes de ir; se não, vai assim mesmo.
			let gone = false;
			const once = (ms) => { setTimeout(() => { if (!gone) { gone = true; leave(); } }, ms); };
			page.loaded.then((ok) => once(ok ? 260 : 0));
			once(600);
		};
		const frame = (now) => {
			if (finished || !overlay) return;
			if (!started) started = now;
			const ms = now - started;
			open(clamp(ms / OPEN_MS));
			if (ms < OPEN_MS) requestAnimationFrame(frame);
			else finish();
		};
		requestAnimationFrame(frame);
		// Aba em segundo plano não roda requestAnimationFrame: segue mesmo assim.
		setTimeout(finish, OPEN_MS + 400);
		return true;
	}

	// Mouse chegando no card, toque começando ou foco pelo teclado: já busca o catálogo e monta ele por baixo.
	const warm = (e) => {
		const parts = cardParts(e.target);
		if (!parts) return;
		const { link, label, img } = parts;
		prefetch(link.href);
		loadPreview(link.href);
		predecode(img);
		// e já mede por onde o zoom vai entrar (o clique começa a animar na hora)
		const text = (label.textContent || '').trim();
		if (text && !overlay) {
			const st = getComputedStyle(label);
			const n = text.length, mid = Math.floor(n / 2);
			const run = () => [mid - 1, mid, mid + 1].forEach((i) => { const ch = text.charAt(i); if (ch && ch.trim()) thickestPoint(ch, st, 120); });
			(window.requestIdleCallback || ((f) => setTimeout(f, 1)))(run);
		}
	};
	document.addEventListener('pointerover', warm);
	document.addEventListener('pointerdown', warm);
	document.addEventListener('focusin', warm);

	document.addEventListener('click', (e) => {
		const parts = cardParts(e.target);
		if (!parts) return;
		const { link } = parts;
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		if (link.target && link.target !== '_self') return;
		if (overlay) { e.preventDefault(); return; } // já está animando
		prefetch(link.href);
		let ok = false;
		try {
			ok = play(parts.label, { card: parts.card, img: parts.img, page: loadPreview(link.href), go: () => window.location.assign(link.href) });
		} catch (err) {
			cleanup();
		}
		if (ok) e.preventDefault();
	});

	// Login e cadastro: o formulário vai antes, em segundo plano. Só se a resposta já vier logada o "GILDA;"
	// cresce até o centro e a página seguinte mergulha no ";". Se der erro (senha errada, "Muitas tentativas"…),
	// a mensagem aparece aqui mesmo, sem animação começar e parar no meio.
	let authBusy = false;
	const setAuthBusy = (form, button, on) => {
		form.classList.toggle('is-loading', on);
		form.setAttribute('aria-busy', on ? 'true' : 'false');
		if (button) button.disabled = on;
	};
	const showAuthNotices = (form, doc) => {
		const source = Array.from(doc.querySelectorAll('.woocommerce-notices-wrapper')).find((w) => w.textContent.trim())
			|| doc.querySelector('.woocommerce-error');
		let target = document.querySelector('.woocommerce-notices-wrapper');
		if (!target) {
			target = document.createElement('div');
			target.className = 'woocommerce-notices-wrapper';
			form.before(target);
		}
		if (source) {
			target.innerHTML = source.classList.contains('woocommerce-notices-wrapper') ? source.innerHTML : source.outerHTML;
		} else {
			target.innerHTML = '<ul class="woocommerce-error" role="alert"><li>Não deu certo agora. Tente de novo.</li></ul>';
		}
		const notice = target.firstElementChild;
		if (notice) {
			notice.setAttribute('tabindex', '-1');
			notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
			notice.focus({ preventScroll: true });
		}
	};
	document.addEventListener('submit', async (e) => {
		const form = e.target.closest && e.target.closest(AUTH_FORMS);
		if (!form || e.defaultPrevented) return;
		if (authBusy || overlay) { e.preventDefault(); return; }
		const brand = document.querySelector('.gilda-account-auth__brand');
		if (!brand || !brand.getClientRects().length || !window.fetch || !window.DOMParser) return; // envio normal
		e.preventDefault();
		authBusy = true;
		const button = (e.submitter && e.submitter.form === form) ? e.submitter : form.querySelector('[type="submit"][name]');
		const data = new FormData(form);
		// o WooCommerce precisa do name="login"/"register" do botão no POST
		if (button && button.name && !data.has(button.name)) data.append(button.name, button.value);
		setAuthBusy(form, button, true);

		let res = null;
		let doc = null;
		try {
			res = await fetch(form.action || window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
			doc = new DOMParser().parseFromString(await res.text(), 'text/html');
		} catch (err) {
			// sem rede/resposta estranha: envio normal, sem animação
			authBusy = false;
			setAuthBusy(form, button, false);
			HTMLFormElement.prototype.submit.call(form);
			return;
		}

		if (doc.body && doc.body.classList.contains('logged-in')) {
			const next = res.url || window.location.href;
			// já busca a página da conta enquanto o "GILDA;" cresce (já logado): o mergulho não espera o servidor
			prefetch(next);
			let ok = false;
			try {
				ok = play(brand, { zoomInto: 'last', requireLogin: true, go: () => window.location.assign(next) });
			} catch (err) {
				cleanup();
			}
			if (!ok) window.location.assign(next);
			return;
		}

		showAuthNotices(form, doc);
		form.querySelectorAll('input[type="password"]').forEach((input) => { input.value = ''; });
		authBusy = false;
		setAuthBusy(form, button, false);
	}, true);
})();
