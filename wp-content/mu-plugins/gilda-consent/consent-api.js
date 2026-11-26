/* GILDA; consentimento de cookies: API (window.gildaConsent). Impressa no <head> antes de qualquer tag de marketing. */
(function (w, d) {
	'use strict';
	var cfg = w.gildaConsentConfig || {};
	var NAME = cfg.cookie || 'gilda_consent';
	var listeners = [];
	var state = read();

	function read() {
		var m = d.cookie.match(new RegExp('(?:^|;\\s*)' + NAME + '=([^;]*)'));
		if (!m) { return null; }
		try {
			var v = JSON.parse(decodeURIComponent(m[1]));
			return v && v.v === cfg.version && v.c && typeof v.c === 'object' ? v : null;
		} catch (e) {
			return null;
		}
	}

	function has(cat) {
		return cat === 'necessary' || !!(state && state.c[cat] === true);
	}

	// Consent mode do Google (ad_* = marketing, analytics_storage = categoria "analytics", se existir).
	function googleMap() {
		var m = has('marketing') ? 'granted' : 'denied';
		return {
			ad_storage: m,
			ad_user_data: m,
			ad_personalization: m,
			analytics_storage: has('analytics') ? 'granted' : 'denied',
			functionality_storage: 'granted',
			security_storage: 'granted'
		};
	}
	if (cfg.google) {
		w.dataLayer = w.dataLayer || [];
		if (typeof w.gtag !== 'function') {
			w.gtag = function () { w.dataLayer.push(arguments); };
		}
		var defaults = googleMap();
		if (!state) { defaults.wait_for_update = 500; }
		w.gtag('consent', 'default', defaults);
	}

	/** Apaga cookies (ex.: _fbp) no host atual e em todos os domínios-pai (.mundogilda.com.br etc.). */
	function deleteCookies(names) {
		var parts = location.hostname.split('.');
		var domains = [''];
		for (var i = 0; i < parts.length - 1; i++) {
			domains.push('; domain=.' + parts.slice(i).join('.'));
		}
		names.forEach(function (n) {
			if (!new RegExp('(?:^|;\\s*)' + n + '=').test(d.cookie)) { return; }
			domains.forEach(function (dom) {
				d.cookie = n + '=; Max-Age=0; path=/' + dom;
			});
		});
	}

	function set(choices) {
		var c = {};
		(cfg.categories || []).forEach(function (k) { c[k] = !!(choices && choices[k]); });
		var previous = state;
		state = { v: cfg.version, c: c, t: Math.floor(Date.now() / 1000) };
		d.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(state)) +
			'; Max-Age=' + cfg.maxAge + '; path=' + (cfg.path || '/') + '; SameSite=Lax' +
			(location.protocol === 'https:' ? '; Secure' : '');
		if (cfg.google) { w.gtag('consent', 'update', googleMap()); }
		listeners.slice().forEach(function (fn) {
			try { fn(state, previous); } catch (e) { if (w.console) { console.error(e); } }
		});
		try {
			d.dispatchEvent(new CustomEvent('gilda:consent', { detail: { consent: state, previous: previous } }));
		} catch (e) { /* navegador antigo */ }
		return state;
	}

	function all(value) {
		var c = {};
		(cfg.categories || []).forEach(function (k) { c[k] = value; });
		return c;
	}

	w.gildaConsent = {
		/** Escolha salva ({v, c: {marketing: bool}, t}) ou null se a pessoa ainda não escolheu. */
		get: function () { return state; },
		/** true se a categoria está liberada ("necessary" sempre). */
		has: has,
		/** Salva uma escolha: gildaConsent.set({ marketing: true }). */
		set: set,
		acceptAll: function () { return set(all(true)); },
		rejectAll: function () { return set(all(false)); },
		/** fn(state, previous) a cada mudança de escolha. */
		onChange: function (fn) { listeners.push(fn); },
		/** Roda fn uma vez, agora (se já liberado) ou quando a categoria for liberada. */
		whenGranted: function (cat, fn) {
			if (has(cat)) { fn(); return; }
			var done = false;
			listeners.push(function () {
				if (!done && has(cat)) { done = true; fn(); }
			});
		},
		deleteCookies: deleteCookies,
		/** Abre o painel de preferências (definido pela interface, no rodapé). */
		open: function () { d.addEventListener('DOMContentLoaded', function () { w.gildaConsent.open(); }); }
	};
})(window, document);
