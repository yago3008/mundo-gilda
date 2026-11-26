/* GILDA; consentimento de cookies: interface (banner, painel de preferências, links de reabrir). */
(function (w, d) {
	'use strict';
	var api = w.gildaConsent;
	var banner = d.getElementById('gilda-consent');
	var dialog = d.getElementById('gilda-consent-prefs');
	if (!api || !banner || !dialog) { return; }
	var opener = null;

	function boxes() {
		return Array.prototype.slice.call(dialog.querySelectorAll('[data-gilda-consent-category]'));
	}

	// O foco estava num botão que sumiu: leva para o conteúdo principal.
	function focusMain() {
		var main = d.querySelector('main') || d.body;
		if (!main.hasAttribute('tabindex')) { main.setAttribute('tabindex', '-1'); }
		main.focus({ preventScroll: true });
	}

	function hideBanner() {
		var hadFocus = banner.contains(d.activeElement);
		banner.hidden = true;
		if (hadFocus) { focusMain(); }
	}

	function openPrefs(trigger) {
		var s = api.get();
		boxes().forEach(function (b) { b.checked = !!(s && s.c[b.getAttribute('data-gilda-consent-category')]); });
		opener = trigger || d.activeElement;
		if (typeof dialog.showModal === 'function') {
			if (!dialog.open) { dialog.showModal(); }
		} else {
			dialog.setAttribute('open', '');
		}
		var first = dialog.querySelector('[data-gilda-consent-category]') || dialog.querySelector('button');
		if (first) { first.focus(); }
	}

	function closePrefs() {
		if (typeof dialog.close === 'function' && dialog.open) {
			dialog.close();
		} else {
			dialog.removeAttribute('open');
		}
	}

	dialog.addEventListener('close', function () {
		var target = opener && d.contains(opener) && opener.offsetParent !== null ? opener : null;
		opener = null;
		if (target) { target.focus(); } else { focusMain(); }
	});

	function choose(action) {
		if (action === 'accept') {
			api.acceptAll();
		} else if (action === 'reject') {
			api.rejectAll();
		} else if (action === 'save') {
			var c = {};
			boxes().forEach(function (b) { c[b.getAttribute('data-gilda-consent-category')] = b.checked; });
			api.set(c);
		}
		closePrefs();
		hideBanner();
	}

	d.addEventListener('click', function (e) {
		var el = e.target.closest ? e.target.closest('[data-gilda-consent], [data-gilda-consent-open], a[href$="#gilda-consent"]') : null;
		if (!el) { return; }
		var action = el.getAttribute('data-gilda-consent');
		if (!action) {
			e.preventDefault();
			openPrefs(el);
		} else if (action === 'preferences') {
			openPrefs(el);
		} else if (action === 'close') {
			closePrefs();
		} else {
			choose(action);
		}
	});

	api.open = function () { openPrefs(null); };

	if (!api.get()) { banner.hidden = false; }

	// Atribuição de pedidos do WooCommerce (cookies sbjs_*: origem/UTM da visita) = marketing, como o próprio WooCommerce classifica.
	function syncOrderAttribution() {
		var oa = w.wc_order_attribution;
		if (oa && typeof oa.setOrderTracking === 'function') { oa.setOrderTracking(api.has('marketing')); }
	}
	api.onChange(syncOrderAttribution);
	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', syncOrderAttribution);
	} else {
		syncOrderAttribution();
	}
})(window, document);
