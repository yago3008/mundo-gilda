/*
 * Páginas de atendimento (templates/page-info.html): nas dúvidas frequentes, cada pergunta (título H3)
 * e o texto até a próxima pergunta viram um <details> que abre e fecha. O texto continua sendo o da
 * página, editado no painel (título + parágrafos); sem JavaScript, tudo aparece aberto.
 * Um link para #pergunta abre a resposta certa.
 */
(() => {
	const content = document.querySelector('.gilda-info__content');
	if (!content) return;

	const questions = Array.from(content.children).filter((el) => el.tagName === 'H3');
	if (!questions.length) return;

	questions.forEach((h3) => {
		const details = document.createElement('details');
		details.className = 'gilda-faq';
		if (h3.id) {
			details.id = h3.id;
			h3.removeAttribute('id');
		}
		const summary = document.createElement('summary');
		const answer = document.createElement('div');
		answer.className = 'gilda-faq__answer';
		h3.before(details);
		// tudo até o próximo título vai para a resposta
		let next = h3.nextElementSibling;
		while (next && !/^H[1-3]$/.test(next.tagName) && !next.matches('hr, .wp-block-separator')) {
			const move = next;
			next = next.nextElementSibling;
			answer.appendChild(move);
		}
		summary.appendChild(h3);
		details.append(summary, answer);
	});

	const openFromHash = () => {
		const id = decodeURIComponent(window.location.hash.slice(1));
		const target = id && document.getElementById(id);
		if (target && target.matches('details.gilda-faq')) {
			target.open = true;
			target.scrollIntoView({ block: 'start' });
		}
	};
	openFromHash();
	window.addEventListener('hashchange', openFromHash);
})();
