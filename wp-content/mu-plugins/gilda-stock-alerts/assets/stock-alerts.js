/**
 * GILDA; Avise-me — mostra o formulário quando a variação escolhida está esgotada e envia por AJAX.
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

	function setMessage( box, text, ok ) {
		const msg = box.querySelector( '.gilda-notify__message' );
		if ( ! msg ) {
			return;
		}
		msg.textContent = text || '';
		msg.hidden = ! text;
		msg.classList.toggle( 'is-success', !! text && ok );
		msg.classList.toggle( 'is-error', !! text && ! ok );
	}

	// "Marrom / M" a partir dos selects do variations_form.
	function variationLabel( form ) {
		return Array.from( form.querySelectorAll( 'select[name^="attribute_"]' ) )
			.filter( ( s ) => s.value )
			.map( ( s ) => s.options[ s.selectedIndex ].text.trim() )
			.join( ' / ' );
	}

	function show( box, form, variation ) {
		const label = box.querySelector( '.gilda-notify__variation' );
		const text = variationLabel( form );
		if ( label ) {
			label.textContent = text ? `${ text }` : label.dataset.default || '';
		}
		const input = box.querySelector( '.gilda-notify__variation-id' );
		const changed = input && String( input.value ) !== String( variation.variation_id );
		if ( input ) {
			input.value = variation.variation_id;
		}
		if ( changed ) {
			box.classList.remove( 'is-done' );
			setMessage( box, '' );
		}
		if ( box.hidden ) {
			box.hidden = false;
			emit( box, 'gilda:notify-show', { variation } );
		}
	}

	function hide( box ) {
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
		setMessage( box, message, ok );
		box.classList.toggle( 'is-done', ok );
		box.classList.remove( 'is-loading' );
		button?.removeAttribute( 'aria-busy' );
		if ( button ) {
			button.disabled = false;
		}
		emit( box, ok ? 'gilda:notify-success' : 'gilda:notify-error', { code, message } );
	} );
} )( jQuery );
