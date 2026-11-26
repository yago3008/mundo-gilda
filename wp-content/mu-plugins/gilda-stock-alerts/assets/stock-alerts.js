/**
 * GILDA; Avise-me — mostra o aviso quando a variação escolhida está esgotada e envia por AJAX.
 * Logado: o botão envia direto (e-mail da conta). Sem login: o botão abre o modal que pede o e-mail.
 *
 * Eventos (no elemento .gilda-notify), para o tema reagir se quiser:
 *   gilda:notify-show  (detail: { variation })   gilda:notify-hide
 *   gilda:notify-success / gilda:notify-error   (detail: { code, message })
 */
( function ( $ ) {
	'use strict';

	const cfg = window.gildaStockAlerts || {};

	function emit( el, name, detail ) {
		el.dispatchEvent( new CustomEvent( name, { bubbles: true, detail: detail || {} } ) );
	}

	// where: 'modal' (dentro do modal), 'inline' (no bloco da página) ou 'all'.
	function setMessage( box, text, ok, where ) {
		box.querySelectorAll( '.gilda-notify__message' ).forEach( ( msg ) => {
			const inModal = !! msg.closest( '.gilda-notify-modal' );
			if ( 'all' !== where && ( 'modal' === where ) !== inModal ) {
				return; // só mexe nas mensagens do lugar pedido
			}
			const show = !! text;
			msg.textContent = show ? text : '';
			msg.hidden = ! show;
			msg.classList.toggle( 'is-success', show && ok );
			msg.classList.toggle( 'is-error', show && ! ok );
		} );
	}

	function modalOf( box ) {
		return box.querySelector( '.gilda-notify-modal' );
	}

	function closeModal( box ) {
		const dialog = modalOf( box );
		if ( dialog && dialog.open ) {
			dialog.close();
		}
	}

	// "Marrom / M" a partir dos selects do variations_form.
	function variationLabel( form ) {
		return Array.from( form.querySelectorAll( 'select[name^="attribute_"]' ) )
			.filter( ( s ) => s.value )
			.map( ( s ) => s.options[ s.selectedIndex ].text.trim() )
			.join( ' / ' );
	}

	function show( box, form, variation ) {
		const text = variationLabel( form );
		box.querySelectorAll( '.gilda-notify__variation' ).forEach( ( label ) => {
			label.textContent = text || label.dataset.default || '';
		} );
		const input = box.querySelector( '.gilda-notify__variation-id' );
		const changed = input && String( input.value ) !== String( variation.variation_id );
		if ( input ) {
			input.value = variation.variation_id;
		}
		if ( changed ) {
			box.classList.remove( 'is-done' );
			setMessage( box, '', false, 'all' );
		}
		if ( box.hidden ) {
			box.hidden = false;
			emit( box, 'gilda:notify-show', { variation } );
		}
	}

	function hide( box ) {
		closeModal( box );
		if ( ! box.hidden ) {
			box.hidden = true;
			emit( box, 'gilda:notify-hide' );
		}
	}

	$( document ).on( 'found_variation', 'form.variations_form', function ( e, variation ) {
		const box = this.querySelector( '.gilda-notify' );
		if ( ! box ) {
			return;
		}
		if ( variation && ! variation.is_in_stock && variation.gilda_stock_alert !== false ) {
			show( box, this, variation );
		} else {
			hide( box );
		}
	} );

	$( document ).on( 'reset_data hide_variation', 'form.variations_form', function () {
		const box = this.querySelector( '.gilda-notify' );
		if ( box ) {
			hide( box );
		}
	} );

	// Sem login: abre o modal que pede o e-mail.
	document.addEventListener( 'click', ( e ) => {
		const opener = e.target.closest && e.target.closest( '.gilda-notify__open[aria-haspopup="dialog"]' );
		if ( opener ) {
			const box = opener.closest( '.gilda-notify' );
			const dialog = box && modalOf( box );
			if ( dialog && dialog.showModal ) {
				e.preventDefault();
				setMessage( box, '', false, 'modal' );
				dialog.showModal();
				dialog.querySelector( '.gilda-notify__email' )?.focus();
			}
			return;
		}
		const closer = e.target.closest && e.target.closest( '[data-gilda-notify-close]' );
		if ( closer ) {
			closer.closest( 'dialog' )?.close();
			return;
		}
		// clique fora do cartão (no fundo escuro) fecha
		if ( e.target.matches && e.target.matches( 'dialog.gilda-notify-modal' ) ) {
			const r = e.target.getBoundingClientRect();
			if ( e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom ) {
				e.target.close();
			}
		}
	} );

	document.addEventListener( 'submit', async ( e ) => {
		const form = e.target;
		if ( ! form.matches || ! form.matches( 'form.gilda-notify__form' ) || ! window.fetch || ! cfg.ajaxUrl ) {
			return; // sem fetch: envio normal (admin-post.php), volta com ?gilda_stock_alert=…
		}
		e.preventDefault();
		const box = document.querySelector( `.gilda-notify[data-form-id="${ form.id }"]` );
		if ( ! box || box.classList.contains( 'is-loading' ) ) {
			return;
		}
		const button = box.querySelector( '.gilda-notify__submit' );
		box.classList.add( 'is-loading' );
		button?.setAttribute( 'aria-busy', 'true' );
		if ( button ) {
			button.disabled = true;
		}

		let code = 'error';
		let message = cfg.error || '';
		try {
			const res = await fetch( cfg.ajaxUrl, { method: 'POST', body: new FormData( form ), credentials: 'same-origin' } );
			const json = await res.json();
			if ( json && json.data ) {
				code = json.data.code || code;
				message = json.data.message || message;
			}
		} catch ( err ) {
			// mantém a mensagem genérica
		}

		const ok = 'ok' === code;
		const dialog = modalOf( box );
		if ( dialog && dialog.open && ! ok ) {
			setMessage( box, message, false, 'modal' ); // erro: fica no modal para corrigir o e-mail
		} else {
			if ( dialog && dialog.open ) {
				setMessage( box, message, true, 'modal' );
				setTimeout( () => closeModal( box ), 1400 );
			}
			setMessage( box, message, ok, 'inline' );
		}
		box.classList.toggle( 'is-done', ok );
		box.classList.remove( 'is-loading' );
		button?.removeAttribute( 'aria-busy' );
		if ( button ) {
			button.disabled = false;
		}
		emit( box, ok ? 'gilda:notify-success' : 'gilda:notify-error', { code, message } );
	} );
} )( jQuery );
