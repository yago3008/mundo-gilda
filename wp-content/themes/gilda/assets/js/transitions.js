/* Transições de scroll — ver inc/transitions.php. Sem dependências. */
(() => {
	// ?anima=1 força a animação mesmo com "menos movimento" ligado no sistema (só para testar)
	const force = /[?&]anima=1\b/.test(window.location.search);
	if (force) document.documentElement.classList.add('gilda-force-motion');
	if (!force && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
	const smooth = (t) => t * t * (3 - 2 * t);
	const easeOut = (t) => 1 - Math.pow(1 - t, 3);
	const progress = (el) => {
		const r = el.getBoundingClientRect();
		return clamp(-r.top / (r.height - window.innerHeight));
	};

	const effects = [];

	/* Zoom Parallax */
	document.querySelectorAll('.gilda-zp').forEach((zp) => {
		const layers = [...zp.querySelectorAll('.gilda-zp__layer')].map((el) => ({ el, scale: Number(el.dataset.scale) || 4 }));
		const title = zp.querySelector('.gilda-zp__title');
		effects.push({
			el: zp,
			update(p) {
				const t = smooth(clamp((p - 0.04) / 0.82));
				for (const { el, scale } of layers) el.style.transform = `scale(${1 + (scale - 1) * t})`;
				if (title) title.classList.toggle('is-on', p > 0.82);
				zp.classList.toggle('is-full', p > 0.8);
			},
		});
	});

	/* História do capítulo: um parágrafo por passo; a foto troca junto */
	document.querySelectorAll('.gilda-story').forEach((story) => {
		const steps = [...story.querySelectorAll('.gilda-story__step')];
		const imgs = [...story.querySelectorAll('.gilda-story__img')];
		const bar = story.querySelector('.gilda-story__bar');
		let current = -1;
		effects.push({
			el: story,
			update(p) {
				const n = steps.length;
				const idx = Math.min(n - 1, Math.floor(p * n));
				if (idx !== current) {
					steps.forEach((s, i) => {
						s.classList.toggle('is-on', i === idx);
						s.classList.toggle('is-past', i < idx);
					});
					imgs.forEach((im, i) => im.classList.toggle('is-on', imgs.length && i === idx % imgs.length));
					current = idx;
				}
				// zoom lento na foto ativa durante o passo
				const local = p * n - idx;
				const on = imgs[idx % (imgs.length || 1)];
				if (on) on.style.setProperty('--kb', (1.04 + 0.06 * clamp(local)).toFixed(4));
				if (bar) bar.style.setProperty('--p', p.toFixed(4));
			},
		});
	});

	/* Mask Section Transition */
	document.querySelectorAll('.gilda-mask').forEach((mask) => {
		const svg = mask.querySelector('.gilda-mask__svg');
		const rects = mask.querySelectorAll('.gilda-mask__white, .gilda-mask__cover');
		const zoom = mask.querySelector('.gilda-mask__zoom');
		const word = mask.querySelector('.gilda-mask__word');
		const photo = mask.querySelector('.gilda-mask__photo');
		const band = mask.querySelector('.gilda-mask__band');
		const headline = mask.querySelector('.gilda-mask__headline');
		const semi = mask.querySelector('.gilda-mask__semi');
		const shade = mask.querySelector('.gilda-mask__shade');
		let origin = { x: 0, y: 0 }, center = { x: 0, y: 0 }, maxScale = 60;

		const layout = () => {
			const w = svg.clientWidth || window.innerWidth, h = svg.clientHeight || window.innerHeight;
			svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
			rects.forEach((r) => { r.setAttribute('width', w); r.setAttribute('height', h); });
			word.setAttribute('x', w / 2);
			word.setAttribute('y', h / 2);
			word.setAttribute('font-size', Math.min(w * 0.24, h * 0.5));
			center = { x: w / 2, y: h / 2 };
			try {
				const last = word.getNumberOfChars() - 1; // o ";"
				const box = word.getExtentOfChar(last);
				origin = { x: box.x + box.width * 0.52, y: box.y + box.height * 0.42 };
				maxScale = (Math.hypot(w, h) * 1.2) / Math.max(4, box.width * 0.42);
			} catch (e) {
				origin = { x: w * 0.8, y: h / 2 };
			}
		};

		effects.push({
			el: mask,
			layout,
			update(p) {
				const t = clamp(p / 0.7);
				const s = Math.pow(maxScale, t * t);
				const slide = smooth(t);
				const px = origin.x + (center.x - origin.x) * slide;
				const py = origin.y + (center.y - origin.y) * slide;
				zoom.setAttribute('transform', `translate(${px} ${py}) scale(${s}) translate(${-origin.x} ${-origin.y})`);
				photo.style.transform = `scale(${1.25 - 0.25 * easeOut(t)})`;
				svg.style.visibility = t >= 0.999 ? 'hidden' : 'visible';
				band.classList.toggle('is-on', p > 0.74);
				headline.classList.toggle('is-on', p > 0.8);
				if (shade) shade.classList.toggle('is-on', p > 0.76);
				semi.classList.toggle('is-on', p > 0.86);
			},
		});
	});

	/* Quem Cria: os nomes das criadoras vão sendo pintados de marrom, de baixo pra cima, com o scroll.
	   Começa quando o nome entra pela base da tela e termina quando ele chega perto do meio. */
	document.querySelectorAll('.gilda-founder__signature').forEach((name) => {
		name.classList.add('is-paintable');
		effects.push({
			el: name,
			update() {
				const r = name.getBoundingClientRect();
				const vh = window.innerHeight;
				const t = clamp((vh - r.bottom) / (vh * 0.45));
				name.style.setProperty('--paint', `${(t * 100).toFixed(2)}%`);
			},
		});
	});

	if (!effects.length) return;

	let ticking = false;
	const frame = () => {
		ticking = false;
		for (const fx of effects) {
			const r = fx.el.getBoundingClientRect();
			if (r.bottom < -100 || r.top > window.innerHeight + 100) continue; // fora da tela
			fx.update(progress(fx.el));
		}
	};
	const request = () => { if (!ticking) { ticking = true; requestAnimationFrame(frame); } };
	const layoutAll = () => { effects.forEach((fx) => fx.layout && fx.layout()); frame(); };

	/* Leitura automática da história dos capítulos: rola sozinha, devagar.
	   A pessoa pode rolar quando quiser (a automática pausa e volta depois de alguns segundos parada)
	   e tem um botão para pausar/continuar. */
	(function autoRead() {
		const stories = [...document.querySelectorAll('.gilda-story')];
		if (!document.querySelector('.gilda-zp--chapter') || !stories.length) return;
		if (window.location.hash || window.scrollY > 40) return; // chegou por link direto ou já rolou

		const SPEED = { zoom: 190, story: 75, other: 130 }; // px por segundo (~7 s por parágrafo)
		const IDLE_RESUME_MS = 4000;
		const zooms = [...document.querySelectorAll('.gilda-zp--chapter')];
		const last = stories[stories.length - 1];
		const endY = () => last.getBoundingClientRect().bottom + window.scrollY - window.innerHeight * 0.35;
		const inside = (els) => els.some((el) => { const r = el.getBoundingClientRect(); return r.top <= 1 && r.bottom >= window.innerHeight - 1; });

		let playing = false, manualPause = false, done = false, y = window.scrollY, lastT = 0, resumeAt = 0, ownScroll = false;

		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'gilda-autoscroll';
		btn.innerHTML = '<span class="gilda-autoscroll__icon" aria-hidden="true"></span><span class="gilda-autoscroll__label"></span>';
		const label = btn.querySelector('.gilda-autoscroll__label');
		const render = () => {
			const on = playing && !manualPause;
			btn.classList.toggle('is-paused', !on);
			label.textContent = on ? 'Pausar leitura' : 'Continuar leitura';
			btn.setAttribute('aria-pressed', String(!on));
		};
		btn.addEventListener('click', () => {
			manualPause = !manualPause;
			if (!manualPause) { resumeAt = 0; y = window.scrollY; playing = true; }
			render();
		});
		document.body.appendChild(btn);

		// interação da pessoa: pausa e agenda a retomada
		const userActed = () => { if (done) return; resumeAt = performance.now() + IDLE_RESUME_MS; y = window.scrollY; };
		window.addEventListener('wheel', userActed, { passive: true });
		window.addEventListener('touchmove', userActed, { passive: true });
		window.addEventListener('keydown', (e) => {
			if (['ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', 'Home', 'End', ' ', 'Spacebar'].includes(e.key) && e.target === document.body) userActed();
		});
		// rolagem que não foi nossa (barra de rolagem, âncora...)
		window.addEventListener('scroll', () => {
			if (ownScroll) { ownScroll = false; return; }
			if (Math.abs(window.scrollY - y) > 3) userActed();
		}, { passive: true });

		const tick = (t) => {
			if (done) return;
			const dt = lastT ? Math.min(0.05, (t - lastT) / 1000) : 0;
			lastT = t;
			const waiting = manualPause || t < resumeAt;
			if (playing && !waiting) {
				const speed = inside(zooms) ? SPEED.zoom : inside(stories) ? SPEED.story : SPEED.other;
				y += speed * dt;
				const target = endY();
				if (y >= target) { y = target; done = true; btn.remove(); }
				ownScroll = true;
				window.scrollTo({ top: y, behavior: 'instant' });
			} else {
				y = window.scrollY;
			}
			if (!done) requestAnimationFrame(tick);
		};

		// começa depois de um respiro, para a pessoa ver a colagem
		setTimeout(() => { playing = true; render(); requestAnimationFrame(tick); }, 1500);
		render();
	})();

	window.addEventListener('scroll', request, { passive: true });
	window.addEventListener('resize', () => { layoutAll(); });
	(document.fonts ? document.fonts.ready : Promise.resolve()).then(layoutAll);
	layoutAll();
})();
