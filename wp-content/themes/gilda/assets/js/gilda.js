/**
 * GILDA; — progressive enhancements (no dependencies).
 *
 * Carousel (3.1, 5.12) · mobile drawer overlay (1.2) · cart counter (1.5)
 * quick view (3.2) · swatches, stepper, validation, AJAX add + toast (5.5–5.9)
 * shipping by CEP (5.10) · share popups (5.11) · catalog filters (4.1–4.6)
 */
( function () {
	'use strict';

	const S = window.gildaSettings || { i18n: {}, colors: {} };
	const t = ( key ) => ( S.i18n && S.i18n[ key ] ) || key;
	const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
	const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );
	const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	document.documentElement.classList.add( 'js' );

	const icon = ( d ) =>
		`<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ d }</svg>`;
	const ICONS = {
		left: icon( '<path d="M15 5l-7 7 7 7"/>' ),
		right: icon( '<path d="M9 5l7 7-7 7"/>' ),
		close: icon( '<path d="M6 6l12 12M18 6L6 18"/>' ),
		filter: icon( '<path d="M4 6h16M7 12h10M10 18h4"/>' ),
	};

	const formatBRL = ( minor, unit = 2 ) =>
		new Intl.NumberFormat( 'pt-BR', { style: 'currency', currency: 'BRL' } ).format( Number( minor ) / Math.pow( 10, unit ) );

	const escapeHTML = ( s ) =>
		String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	/* ------------------------------------------------------------------ */
	/* Store API                                                           */
	/* ------------------------------------------------------------------ */

	async function storeApi( path, options = {} ) {
		const res = await fetch( S.storeApi + path, {
			credentials: 'same-origin',
			...options,
			headers: {
				'Content-Type': 'application/json',
				Nonce: S.storeNonce || '',
				...( options.headers || {} ),
			},
		} );
		const nonce = res.headers.get( 'Nonce' );
		if ( nonce ) {
			S.storeNonce = nonce;
		}
		const data = await res.json().catch( () => ( {} ) );
		if ( ! res.ok ) {
			throw Object.assign( new Error( data.message || t( 'genericError' ) ), { data } );
		}
		return data;
	}

	/* ------------------------------------------------------------------ */
	/* Toast                                                               */
	/* ------------------------------------------------------------------ */

	let toastTimer;
	function toast( message, link ) {
		$( '.gilda-toast' )?.remove();
		const el = document.createElement( 'div' );
		el.className = 'gilda-toast';
		el.setAttribute( 'role', 'status' );
		el.innerHTML = `<span>✓ ${ escapeHTML( message ) }</span>${
			link ? `<a href="${ escapeHTML( link.href ) }">${ escapeHTML( link.label ) }</a>` : ''
		}`;
		document.body.appendChild( el );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( () => el.remove(), 5000 );
	}

	/* ------------------------------------------------------------------ */
	/* 1.5 Header cart counter                                             */
	/* ------------------------------------------------------------------ */

	function setCartCount( count ) {
		$$( '.gilda-cart-link' ).forEach( ( link ) => {
			const badge = $( '.gilda-cart-count', link );
			link.dataset.count = count;
			link.setAttribute( 'aria-label', t( 'cartLabel' ).replace( '%d', count ) );
			if ( badge ) {
				badge.textContent = count;
				badge.hidden = count < 1;
				if ( ! reduceMotion ) {
					badge.classList.remove( 'is-bumping' );
					void badge.offsetWidth; // Restart animation.
					badge.classList.add( 'is-bumping' );
				}
			}
		} );
	}

	async function refreshCartCount() {
		try {
			const cart = await storeApi( 'cart' );
			setCartCount( cart.items_count || 0 );
		} catch ( e ) {
			/* Header keeps the server-rendered value. */
		}
	}

	// Blocks (product button, cart block, mini-cart) dispatch these events.
	[ 'wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart' ].forEach( ( evt ) =>
		document.body.addEventListener( evt, refreshCartCount )
	);

	/* ------------------------------------------------------------------ */
	/* 3.1 / 5.12 Carousel                                                 */
	/* ------------------------------------------------------------------ */

	function initCarousel( root ) {
		const track = $( '.wc-block-product-template, .wp-block-post-template', root );
		if ( ! track || root.dataset.carousel ) {
			return;
		}
		root.dataset.carousel = '1';
		track.setAttribute( 'tabindex', '0' );
		track.setAttribute( 'aria-roledescription', 'carrossel' );

		const controls = document.createElement( 'div' );
		controls.className = 'gilda-carousel__controls';
		controls.innerHTML = `
			<button type="button" class="gilda-arrow gilda-arrow--prev" aria-label="${ escapeHTML( t( 'prev' ) ) }">${ ICONS.left }</button>
			<button type="button" class="gilda-arrow gilda-arrow--next" aria-label="${ escapeHTML( t( 'next' ) ) }">${ ICONS.right }</button>`;
		track.after( controls );

		const step = () => {
			const item = track.firstElementChild;
			if ( ! item ) {
				return track.clientWidth;
			}
			const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
			return item.getBoundingClientRect().width + gap;
		};
		const behavior = reduceMotion ? 'auto' : 'smooth';
		const max = () => track.scrollWidth - track.clientWidth - 2;

		$( '.gilda-arrow--next', controls ).addEventListener( 'click', () => {
			// Cyclic: from the end go back to the start.
			track.scrollTo( { left: track.scrollLeft >= max() ? 0 : track.scrollLeft + step(), behavior } );
		} );
		$( '.gilda-arrow--prev', controls ).addEventListener( 'click', () => {
			track.scrollTo( { left: track.scrollLeft <= 2 ? track.scrollWidth : track.scrollLeft - step(), behavior } );
		} );
		track.addEventListener( 'keydown', ( e ) => {
			if ( 'ArrowRight' === e.key ) {
				$( '.gilda-arrow--next', controls ).click();
			} else if ( 'ArrowLeft' === e.key ) {
				$( '.gilda-arrow--prev', controls ).click();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* 1.2 Drawer: close on overlay click and on same-page links           */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'click', ( e ) => {
		const container = e.target.closest( '.gilda-nav-mobile .wp-block-navigation__responsive-container.is-menu-open' );
		if ( ! container ) {
			return;
		}
		const panel = $( '.wp-block-navigation__responsive-close', container );
		const clickedOverlay = ! panel || ! panel.contains( e.target );
		const clickedLink = e.target.closest( '.wp-block-navigation-item__content' );
		if ( clickedOverlay || clickedLink ) {
			$( '.wp-block-navigation__responsive-container-close', container )?.click();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* 5.7 Quantity stepper                                                */
	/* ------------------------------------------------------------------ */

	function initStepper( wrap ) {
		const input = $( 'input.qty', wrap );
		if ( ! input || wrap.classList.contains( 'gilda-stepper' ) || 'hidden' === input.type ) {
			return;
		}
		wrap.classList.add( 'gilda-stepper' );
		const minus = document.createElement( 'button' );
		const plus = document.createElement( 'button' );
		minus.type = plus.type = 'button';
		minus.textContent = '−';
		plus.textContent = '+';
		minus.setAttribute( 'aria-label', t( 'decrease' ) );
		plus.setAttribute( 'aria-label', t( 'increase' ) );
		input.before( minus );
		input.after( plus );

		const bounds = () => ( {
			min: Math.max( 1, parseInt( input.min, 10 ) || 1 ),
			max: parseInt( input.max, 10 ) || Infinity,
			step: parseInt( input.step, 10 ) || 1,
		} );
		const clamp = ( v ) => {
			const b = bounds();
			v = parseInt( String( v ).replace( /\D/g, '' ), 10 );
			if ( ! Number.isFinite( v ) || v < b.min ) {
				v = b.min;
			}
			return Math.min( v, b.max );
		};
		const sync = () => {
			const b = bounds();
			const v = clamp( input.value );
			if ( String( v ) !== input.value ) {
				input.value = v;
			}
			minus.disabled = v <= b.min;
			plus.disabled = v >= b.max;
		};
		minus.addEventListener( 'click', () => {
			input.value = clamp( Number( input.value ) - bounds().step );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		plus.addEventListener( 'click', () => {
			input.value = clamp( Number( input.value ) + bounds().step );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		input.addEventListener( 'change', sync );
		input.addEventListener( 'blur', sync );
		new MutationObserver( sync ).observe( input, { attributes: true, attributeFilter: [ 'max', 'min' ] } );
		sync();
	}

	/* ------------------------------------------------------------------ */
	/* 5.5 Colour swatches (pa_cor select → radios)                        */
	/* ------------------------------------------------------------------ */

	function initSwatches( select ) {
		if ( select.dataset.swatches ) {
			return;
		}
		select.dataset.swatches = '1';
		const label = select.closest( 'tr' )?.querySelector( 'th label' );
		const group = document.createElement( 'div' );
		group.className = 'gilda-swatches';
		group.setAttribute( 'role', 'radiogroup' );
		if ( label ) {
			label.id = label.id || 'gilda-label-' + select.id;
			group.setAttribute( 'aria-labelledby', label.id );
		}
		select.classList.add( 'gilda-hidden-select' );
		select.setAttribute( 'aria-hidden', 'true' );
		select.tabIndex = -1;
		select.after( group );

		const render = () => {
			const options = Array.from( select.options ).filter( ( o ) => o.value );
			group.innerHTML = options
				.map(
					( o ) => `
				<label class="gilda-swatch-option">
					<input type="radio" name="gilda_${ escapeHTML( select.name ) }" value="${ escapeHTML( o.value ) }"${ o.selected ? ' checked' : '' }${ o.disabled ? ' disabled' : '' } />
					<span class="gilda-swatch" style="--swatch:${ escapeHTML( S.colors[ o.value ] || '#D9D3C2' ) }" aria-hidden="true"></span>
					${ escapeHTML( o.textContent.trim() ) }
				</label>`
				)
				.join( '' );
		};
		group.addEventListener( 'change', ( e ) => {
			if ( e.target.matches( 'input[type="radio"]' ) ) {
				select.value = e.target.value;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				clearFormError( select.form );
			}
		} );
		// WooCommerce rebuilds the options when other attributes change.
		new MutationObserver( render ).observe( select, { childList: true, subtree: true, attributes: true } );
		select.addEventListener( 'change', render );
		render();
	}

	/* ------------------------------------------------------------------ */
		function initSizeButtons( select ) {
		if ( select.dataset.sizeButtons ) return;
		select.dataset.sizeButtons = '1';
		const label = select.closest( 'tr' )?.querySelector( 'th label' );
		const group = document.createElement( 'div' );
		group.className = 'gilda-sizes';
		group.setAttribute( 'role', 'radiogroup' );
		if ( label ) {
			label.id = label.id || 'gilda-label-' + select.id;
			group.setAttribute( 'aria-labelledby', label.id );
		}
		select.classList.add( 'gilda-hidden-select' );
		select.setAttribute( 'aria-hidden', 'true' );
		select.tabIndex = -1;
		select.after( group );
		// Tamanho esgotado (ou que não existe) na cor escolhida: já aparece riscado, mas continua clicável
		// para a cliente poder pedir "Avise-me". Usa as variações que o WooCommerce põe no formulário.
		const form = select.form;
		let variations = [];
		try {
			variations = JSON.parse( form?.dataset.product_variations || '[]' ) || [];
		} catch ( e ) {
			variations = [];
		}
		const colorSelect = form && $( 'select[name*=cor], select[name*=color]', form );
		const soldOut = ( size ) => {
			if ( ! Array.isArray( variations ) || ! variations.length ) {
				return false; // muitas variações: o WooCommerce não manda a lista; fica como antes
			}
			const color = colorSelect ? colorSelect.value : '';
			return ! variations.some( ( v ) => {
				const a = v.attributes || {};
				const sizeOk = [ '', size ].includes( a[ select.name ] ?? '' );
				const colorOk = ! colorSelect || ! color || [ '', color ].includes( a[ colorSelect.name ] ?? '' );
				return sizeOk && colorOk && v.is_in_stock;
			} );
		};
		const render = () => {
			group.innerHTML = Array.from( select.options ).filter( ( option ) => option.value ).map( ( option ) => {
				const out = soldOut( option.value );
				const text = option.textContent.trim();
				return `
				<label class="gilda-size${ out ? ' is-soldout' : '' }"><input type="radio" name="gilda_${ escapeHTML( select.name ) }" value="${ escapeHTML( option.value ) }"${ option.selected ? ' checked' : '' }${ option.disabled ? ' disabled' : '' }${ out ? ` aria-label="${ escapeHTML( text ) } esgotado"` : '' } /><span>${ escapeHTML( text ) }</span></label>`;
			} ).join( '' );
		};
		colorSelect?.addEventListener( 'change', render );
		group.addEventListener( 'change', ( event ) => {
			if ( event.target.matches( 'input[type="radio"]' ) ) {
				select.value = event.target.value;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				clearFormError( select.form );
			}
		} );
		new MutationObserver( render ).observe( select, { childList: true, subtree: true, attributes: true } );
		select.addEventListener( 'change', render );
		render();
	}
/* 5.6 / 5.8 / 5.9 Validation + AJAX add to cart                       */
	/* ------------------------------------------------------------------ */

	function formErrorEl( form ) {
		let el = $( '.gilda-form-error', form );
		if ( ! el ) {
			el = document.createElement( 'p' );
			el.className = 'gilda-form-error';
			el.setAttribute( 'role', 'alert' );
			el.hidden = true;
			( $( '.woocommerce-variation-add-to-cart', form ) || form ).prepend( el );
		}
		return el;
	}

	function clearFormError( form ) {
		if ( ! form ) {
			return;
		}
		const el = $( '.gilda-form-error', form );
		if ( el ) {
			el.hidden = true;
		}
		$$( '.has-error', form ).forEach( ( n ) => n.classList.remove( 'has-error' ) );
	}

	function missingSelections( form ) {
		return $$( 'select[name^="attribute_"]', form ).filter( ( s ) => ! s.value );
	}

	function validate( form ) {
		const missing = missingSelections( form );
		if ( ! missing.length ) {
			clearFormError( form );
			return true;
		}
		const names = missing.map( ( s ) => s.name );
		const color = names.some( ( n ) => /cor|color/.test( n ) );
		const size = names.some( ( n ) => /tamanho|size/.test( n ) );
		let msg = t( 'selectOption' );
		if ( color && size ) {
			msg = t( 'selectBoth' );
		} else if ( color ) {
			msg = t( 'selectColor' );
		} else if ( size ) {
			msg = t( 'selectSize' );
		}
		const el = formErrorEl( form );
		el.textContent = msg;
		el.hidden = false;
		missing.forEach( ( s ) => {
			s.classList.add( 'has-error' );
			const options = s.nextElementSibling;
			if ( options?.classList.contains( 'gilda-swatches' ) ) {
				$$( '.gilda-swatch-option', options ).forEach( ( option ) => option.classList.add( 'has-error' ) );
			} else if ( options?.classList.contains( 'gilda-sizes' ) ) {
				$$( '.gilda-size', options ).forEach( ( option ) => option.classList.add( 'has-error' ) );
			}
		} );
		const first = missing[ 0 ];
		( first.classList.contains( 'gilda-hidden-select' ) ? $( 'input', first.nextElementSibling ) : first )?.focus();
		return false;
	}

	// Capture phase: runs before WooCommerce's alert() on disabled buttons.
	document.addEventListener(
		'click',
		( e ) => {
			const btn = e.target.closest( 'form.variations_form .single_add_to_cart_button, form.variations_form .gilda-buy-now' );
			if ( btn && ! validate( btn.form ) ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);

	document.addEventListener( 'submit', async ( e ) => {
		const form = e.target;
		if ( ! form.matches( 'form.cart' ) || form.matches( '.grouped_form' ) ) {
			return;
		}
		if ( form.matches( '.variations_form' ) && ! validate( form ) ) {
			e.preventDefault();
			return;
		}
		// "Comprar" keeps the native submit (server redirects to checkout).
		if ( e.submitter && e.submitter.classList.contains( 'gilda-buy-now' ) ) {
			return;
		}
		if ( ! S.storeApi || ! window.fetch ) {
			return;
		}
		e.preventDefault();

		const fd = new FormData( form );
		const variationId = Number( fd.get( 'variation_id' ) || 0 );
		const productId = Number( fd.get( 'add-to-cart' ) || fd.get( 'product_id' ) || e.submitter?.value || 0 );
		const variation = [];
		fd.forEach( ( value, key ) => {
			if ( key.startsWith( 'attribute_' ) && value ) {
				variation.push( { attribute: key.replace( /^attribute_/, '' ), value } );
			}
		} );
		const submit = $( '.single_add_to_cart_button', form );
		submit?.setAttribute( 'aria-busy', 'true' );

		try {
			const cart = await storeApi( 'cart/add-item', {
				method: 'POST',
				body: JSON.stringify( {
					id: variationId || productId,
					quantity: Number( fd.get( 'quantity' ) || 1 ),
					...( variation.length ? { variation } : {} ),
				} ),
			} );
			setCartCount( cart.items_count || 0 );
			document.body.dispatchEvent( new Event( 'wc-blocks_added_to_cart' ) );
			clearFormError( form );
			toast( t( 'added' ), { href: S.cartUrl, label: t( 'viewCart' ) } );
		} catch ( err ) {
			const code = ( err.data && err.data.code ) || '';
			if ( /stock|quantity|purchasable|sold/i.test( code ) && err.data.message ) {
				// Business rule (e.g. not enough stock): show it inline.
				const el = formErrorEl( form );
				el.textContent = err.data.message.replace( /<[^>]+>/g, '' );
				el.hidden = false;
			} else {
				// Anything else: fall back to WooCommerce's native submit.
				if ( productId && ! form.querySelector( 'input[name="add-to-cart"]' ) ) {
					const hidden = document.createElement( 'input' );
					hidden.type = 'hidden';
					hidden.name = 'add-to-cart';
					hidden.value = productId;
					form.appendChild( hidden );
				}
				HTMLFormElement.prototype.submit.call( form );
			}
		} finally {
			submit?.removeAttribute( 'aria-busy' );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* 5.10 Shipping calculator                                            */
	/* ------------------------------------------------------------------ */

	function maskCep( v ) {
		const d = String( v ).replace( /\D/g, '' ).slice( 0, 8 );
		return d.length > 5 ? `${ d.slice( 0, 5 ) }-${ d.slice( 5 ) }` : d;
	}

	function initShipping( form ) {
		const input = $( 'input[name="cep"]', form );
		const out = $( '.gilda-shipping__feedback', form );
		const btn = $( 'button[type="submit"]', form );
		input.addEventListener( 'input', () => {
			input.value = maskCep( input.value );
			input.classList.remove( 'has-error' );
			input.removeAttribute( 'aria-invalid' );
		} );
		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const cep = input.value.replace( /\D/g, '' );
			if ( 8 !== cep.length ) {
				input.classList.add( 'has-error' );
				input.setAttribute( 'aria-invalid', 'true' );
				out.innerHTML = `<p class="gilda-shipping__error">${ escapeHTML( t( 'cepInvalid' ) ) }</p>`;
				input.focus();
				return;
			}
			const cartForm = $( 'form.cart' );
			const params = new URLSearchParams( {
				product_id: form.dataset.productId,
				cep,
				quantity: $( 'input.qty', cartForm || document )?.value || 1,
				variation_id: $( 'input[name="variation_id"]', cartForm || document )?.value || 0,
			} );
			form.classList.add( 'is-loading' );
			btn.disabled = true;
			btn.dataset.label = btn.dataset.label || btn.textContent;
			btn.textContent = t( 'calculating' );
			out.innerHTML = '';
			try {
				const res = await fetch( `${ S.shippingApi }?${ params }`, { headers: { 'X-WP-Nonce': S.restNonce }, credentials: 'same-origin' } );
				const rates = await res.json();
				if ( ! res.ok ) {
					throw new Error( rates.message || t( 'cepError' ) );
				}
				out.innerHTML = rates.length
					? `<ul class="gilda-shipping__rates">${ rates
							.map(
								( r ) =>
									`<li><span>${ escapeHTML( r.label ) }${ r.delivery ? `<small>${ escapeHTML( r.delivery ) }</small>` : '' }</span><strong>${ escapeHTML( r.price ) }</strong></li>`
							)
							.join( '' ) }</ul>`
					: `<p class="gilda-shipping__error">${ escapeHTML( t( 'cepEmpty' ) ) }</p>`;
			} catch ( err ) {
				out.innerHTML = `<p class="gilda-shipping__error">${ escapeHTML( err.message || t( 'cepError' ) ) }</p>`;
			} finally {
				form.classList.remove( 'is-loading' );
				btn.disabled = false;
				btn.textContent = btn.dataset.label;
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* 5.11 Share popups                                                   */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( '[data-share-popup]' );
		if ( ! link || window.matchMedia( '(max-width: 767px)' ).matches ) {
			return;
		}
		const win = window.open( link.href, 'gilda-share', 'width=620,height=560,noopener' );
		if ( win ) {
			e.preventDefault();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* 4.1–4.6 Catalog filters                                             */
	/* ------------------------------------------------------------------ */

	function initRange( range ) {
		const [ lo, hi ] = $$( 'input[type="range"]', range );
		const outputs = $$( 'output', range.parentElement );
		const min = Number( range.dataset.min );
		const max = Number( range.dataset.max );
		const paint = ( changed ) => {
			if ( Number( lo.value ) > Number( hi.value ) ) {
				if ( changed === lo ) {
					lo.value = hi.value;
				} else {
					hi.value = lo.value;
				}
			}
			const span = max - min || 1;
			range.style.setProperty( '--from', `${ ( ( lo.value - min ) / span ) * 100 }%` );
			range.style.setProperty( '--to', `${ ( ( hi.value - min ) / span ) * 100 }%` );
			// Bolinha fora da ponta (filtro em uso) fica preenchida de marrom; na ponta, continua vazada.
			lo.classList.toggle( 'is-moved', Number( lo.value ) > min );
			hi.classList.toggle( 'is-moved', Number( hi.value ) < max );
			lo.setAttribute( 'aria-valuetext', `R$ ${ lo.value }` );
			hi.setAttribute( 'aria-valuetext', `R$ ${ hi.value }` );
			if ( outputs[ 0 ] ) {
				outputs[ 0 ].textContent = `R$ ${ lo.value }`;
			}
			if ( outputs[ 1 ] ) {
				outputs[ 1 ].textContent = `R$ ${ hi.value }`;
			}
		};
		lo.addEventListener( 'input', () => paint( lo ) );
		hi.addEventListener( 'input', () => paint( hi ) );
		paint();
	}

	/**
	 * Caixa de seleção personalizada (filtro de capítulo): botão + lista no visual da GILDA;.
	 * O <select> continua no formulário (escondido): é ele que vai no filtro e no "change".
	 * Teclado: setas, Home/End, Enter/Espaço escolhem, Esc fecha. Leitores de tela: listbox/option.
	 */
	function initCustomSelect( select ) {
		if ( ! select || select.dataset.gildaSelect ) {
			return;
		}
		select.dataset.gildaSelect = '1';
		const uid = `${ select.id || 'gilda-dropdown' }-ui`;
		const wrap = document.createElement( 'div' );
		wrap.className = 'gilda-dropdown';
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'gilda-dropdown__button';
		button.id = `${ uid }-button`;
		button.setAttribute( 'aria-haspopup', 'listbox' );
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', `${ uid }-list` );
		const list = document.createElement( 'ul' );
		list.className = 'gilda-dropdown__list';
		list.id = `${ uid }-list`;
		list.setAttribute( 'role', 'listbox' );
		list.tabIndex = -1;
		list.hidden = true;
		const label = select.id ? document.querySelector( `label[for="${ select.id }"]` ) : null;
		if ( label ) {
			label.id = label.id || `${ uid }-label`;
			label.setAttribute( 'for', button.id );
			list.setAttribute( 'aria-labelledby', label.id );
			button.setAttribute( 'aria-labelledby', `${ label.id } ${ button.id }` );
		}

		// "Capítulo 2 — Nome": número em cima, nome embaixo (no botão, numa linha só).
		const parts = ( text ) => {
			const i = text.indexOf( ' — ' );
			return i > 0 ? [ text.slice( 0, i ), text.slice( i + 3 ) ] : [ text, '' ];
		};
		const options = Array.from( select.options ).map( ( opt, index ) => {
			const li = document.createElement( 'li' );
			li.className = 'gilda-dropdown__option';
			li.id = `${ uid }-opt-${ index }`;
			li.setAttribute( 'role', 'option' );
			li.dataset.value = opt.value;
			const [ kicker, name ] = parts( opt.textContent.trim() );
			li.innerHTML = name
				? `<span class="gilda-dropdown__kicker">${ escapeHTML( kicker ) }</span><span class="gilda-dropdown__name">${ escapeHTML( name ) }</span>`
				: `<span class="gilda-dropdown__name">${ escapeHTML( kicker ) }</span>`;
			list.appendChild( li );
			return li;
		} );

		const render = () => {
			const opt = select.options[ select.selectedIndex ] || select.options[ 0 ];
			const [ kicker, name ] = parts( opt ? opt.textContent.trim() : '' );
			button.innerHTML = `<span class="gilda-dropdown__value">${ name ? `<span class="gilda-dropdown__kicker">${ escapeHTML( kicker ) }</span> ${ escapeHTML( name ) }` : escapeHTML( kicker ) }</span><svg class="gilda-dropdown__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>`;
			button.classList.toggle( 'is-placeholder', ! select.value );
			options.forEach( ( li, i ) => li.setAttribute( 'aria-selected', i === select.selectedIndex ? 'true' : 'false' ) );
		};
		let active = 0;
		const setActive = ( i ) => {
			active = Math.max( 0, Math.min( options.length - 1, i ) );
			options.forEach( ( li, k ) => li.classList.toggle( 'is-active', k === active ) );
			list.setAttribute( 'aria-activedescendant', options[ active ].id );
			options[ active ].scrollIntoView( { block: 'nearest' } );
		};
		const open = () => {
			if ( ! list.hidden ) {
				return;
			}
			list.hidden = false;
			wrap.classList.add( 'is-open' );
			button.setAttribute( 'aria-expanded', 'true' );
			setActive( Math.max( 0, select.selectedIndex ) );
			list.focus( { preventScroll: true } );
		};
		const close = ( focusButton = true ) => {
			if ( list.hidden ) {
				return;
			}
			list.hidden = true;
			wrap.classList.remove( 'is-open' );
			button.setAttribute( 'aria-expanded', 'false' );
			if ( focusButton ) {
				button.focus( { preventScroll: true } );
			}
		};
		const choose = ( i ) => {
			if ( select.selectedIndex !== i ) {
				select.selectedIndex = i;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			render();
			close();
		};

		button.addEventListener( 'click', () => ( list.hidden ? open() : close() ) );
		button.addEventListener( 'keydown', ( e ) => {
			if ( [ 'ArrowDown', 'ArrowUp', 'Enter', ' ' ].includes( e.key ) ) {
				e.preventDefault();
				open();
			}
		} );
		list.addEventListener( 'keydown', ( e ) => {
			const keys = { ArrowDown: active + 1, ArrowUp: active - 1, Home: 0, End: options.length - 1, PageDown: active + 5, PageUp: active - 5 };
			if ( e.key in keys ) {
				e.preventDefault();
				setActive( keys[ e.key ] );
			} else if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				choose( active );
			} else if ( 'Escape' === e.key ) {
				e.preventDefault();
				e.stopPropagation(); // não fecha a gaveta de filtros do celular junto
				close();
			} else if ( 'Tab' === e.key ) {
				close( false );
			}
		} );
		list.addEventListener( 'mousemove', ( e ) => {
			const li = e.target.closest( '.gilda-dropdown__option' );
			if ( li ) {
				setActive( options.indexOf( li ) );
			}
		} );
		list.addEventListener( 'click', ( e ) => {
			const li = e.target.closest( '.gilda-dropdown__option' );
			if ( li ) {
				choose( options.indexOf( li ) );
			}
		} );
		document.addEventListener( 'pointerdown', ( e ) => {
			if ( ! wrap.contains( e.target ) ) {
				close( false );
			}
		} );
		select.addEventListener( 'change', render );

		select.classList.add( 'gilda-dropdown__native' );
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );
		select.before( wrap );
		wrap.append( button, list, select );
		render();
	}

	function initFilters( aside ) {
		const form = $( 'form', aside );
		const results = $( '.gilda-shop-results' );
		$$( '.gilda-range', aside ).forEach( initRange );
		$$( 'select.gilda-filter-chapter', aside ).forEach( initCustomSelect );

		// Mobile bottom sheet.
		const toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'gilda-filter-toggle';
		toggle.setAttribute( 'aria-controls', aside.id );
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.innerHTML = `${ ICONS.filter }${ escapeHTML( t( 'filter' ) ) }`;
		( results || aside ).before( toggle );

		let backdrop;
		const close = () => {
			aside.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			backdrop?.remove();
			document.body.style.overflow = '';
			toggle.focus();
		};
		const open = () => {
			aside.classList.add( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			backdrop = document.createElement( 'div' );
			backdrop.className = 'gilda-backdrop';
			backdrop.addEventListener( 'click', close );
			document.body.appendChild( backdrop );
			document.body.style.overflow = 'hidden';
			$( '.gilda-filters__close', aside )?.focus();
		};
		toggle.addEventListener( 'click', open );
		$( '.gilda-filters__close', aside )?.addEventListener( 'click', close );
		aside.addEventListener( 'keydown', ( e ) => {
			if ( 'Escape' === e.key && aside.classList.contains( 'is-open' ) ) {
				close();
			}
		} );

		if ( ! form || ! results ) {
			return;
		}

		const buildUrl = () => {
			const url = new URL( form.action, window.location.origin );
			const fd = new FormData( form );
			const range = $( '.gilda-range', form );
			fd.forEach( ( value, key ) => {
				if ( range && 'min_price' === key && Number( value ) <= Number( range.dataset.min ) ) {
					return;
				}
				if ( range && 'max_price' === key && Number( value ) >= Number( range.dataset.max ) ) {
					return;
				}
				url.searchParams.append( key, value );
			} );
			const current = new URL( window.location.href );
			[ 'orderby', 's', 'post_type' ].forEach( ( k ) => current.searchParams.has( k ) && url.searchParams.set( k, current.searchParams.get( k ) ) );
			return url;
		};

		let controller;
		const update = async ( push = true ) => {
			// The mobile sheet applies with its button.
			if ( aside.classList.contains( 'is-open' ) && push === 'auto' ) {
				return;
			}
			const url = buildUrl();
			controller?.abort();
			controller = new AbortController();
			results.classList.add( 'is-loading' );
			results.setAttribute( 'aria-busy', 'true' );
			try {
				const res = await fetch( url, { signal: controller.signal, credentials: 'same-origin' } );
				const html = await res.text();
				const doc = new DOMParser().parseFromString( html, 'text/html' );
				const next = $( '.gilda-shop-results', doc );
				if ( ! next ) {
					throw new Error( 'no results region' );
				}
				results.innerHTML = next.innerHTML;
				$$( '.gilda-carousel', results ).forEach( initCarousel );
				history.pushState( {}, '', url );
			} catch ( err ) {
				if ( 'AbortError' !== err.name ) {
					window.location.href = url.toString();
				}
			} finally {
				results.classList.remove( 'is-loading' );
				results.removeAttribute( 'aria-busy' );
			}
		};

		let timer;
		form.addEventListener( 'change', () => {
			clearTimeout( timer );
			timer = setTimeout( () => update( 'auto' ), 250 );
		} );
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			update( true );
			if ( aside.classList.contains( 'is-open' ) ) {
				close();
			}
		} );
		window.addEventListener( 'popstate', () => window.location.reload() );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ */
	/* Direction-aware sticky header with a smooth reveal.                 */
	/* ------------------------------------------------------------------ */

	function initStickyHeader() {
		const header = $( '.wp-site-blocks > header' );
		if ( ! header ) {
			return;
		}
		const hasHero = document.body.classList.contains( 'gilda-has-hero' );
		let previousY = window.scrollY;
		let ticking = false;
		const sync = () => {
			const currentY = Math.max( 0, window.scrollY );
			if ( hasHero ) {
				header.classList.toggle( 'is-stuck', currentY > 40 );
			}
			if ( currentY <= 40 || currentY < previousY - 3 ) {
				header.classList.remove( 'is-scroll-hidden' );
			} else if ( currentY > previousY + 3 ) {
				header.classList.add( 'is-scroll-hidden' );
			}
			previousY = currentY;
			ticking = false;
		};
		sync();
		window.addEventListener(
			'scroll',
			() => {
				if ( ! ticking ) {
					ticking = true;
					window.requestAnimationFrame( sync );
				}
			},
			{ passive: true }
		);
	}

	function initAccountTabs() {
		const columns = $( '.woocommerce-account .u-columns.col2-set' );
		if ( ! columns || columns.dataset.gildaAccountTabs ) {
			return;
		}
		const loginPanel = $( '.u-column1', columns );
		const registerPanel = $( '.u-column2', columns );
		if ( ! loginPanel || ! registerPanel || ! $( 'form.login, form.woocommerce-form-login', loginPanel ) || ! $( 'form.register, form.woocommerce-form-register', registerPanel ) ) {
			return;
		}
		columns.dataset.gildaAccountTabs = '1';
		columns.classList.add( 'gilda-account-tabs' );
		const main = columns.closest( '#conteudo' ) || columns.closest( 'main' );
		if ( main ) {
			main.classList.add( 'gilda-account-auth' );
			document.body.classList.add( 'gilda-account-auth-page' );
			const brand = document.createElement( 'a' );
			brand.className = 'gilda-account-auth__brand';
			brand.href = S.homeUrl || '/';
			brand.setAttribute( 'aria-label', 'GILDA; — página inicial' );
			brand.textContent = 'GILDA;';
			main.prepend( brand );
			const visual = document.createElement( 'aside' );
			visual.className = 'gilda-account-auth__visual';
			visual.setAttribute( 'aria-hidden', 'true' );
			const productImages = Array.isArray( S.accountImages ) ? S.accountImages.slice( 0, 6 ) : [];
			if ( productImages.length ) {
				const frame = document.createElement( 'div' );
				frame.className = 'gilda-account-auth__frame';
				const imageLayers = productImages.length > 1 ? [ document.createElement( 'img' ), document.createElement( 'img' ) ] : [ document.createElement( 'img' ) ];
				imageLayers.forEach( ( image ) => {
					image.className = 'gilda-account-auth__product-image';
					image.alt = '';
					image.decoding = 'async';
					frame.append( image );
				} );
				visual.append( frame );
				let activeLayer = 0;
				let nextImageIndex = 1;
				const showInitialImage = () => imageLayers[ 0 ].classList.add( 'is-active' );
				imageLayers[ 0 ].addEventListener( 'load', showInitialImage, { once: true } );
				imageLayers[ 0 ].addEventListener( 'error', showInitialImage, { once: true } );
				imageLayers[ 0 ].src = productImages[ 0 ];
				if ( productImages.length > 1 ) {
					const cycleImages = () => {
						const incomingLayer = 1 - activeLayer;
						const incomingImage = imageLayers[ incomingLayer ];
						const finishFade = () => {
							incomingImage.classList.add( 'is-active' );
							window.setTimeout( () => {
								imageLayers[ activeLayer ].classList.remove( 'is-active' );
								activeLayer = incomingLayer;
								nextImageIndex = ( nextImageIndex + 1 ) % productImages.length;
							}, 800 );
						};
						incomingImage.addEventListener( 'load', finishFade, { once: true } );
						incomingImage.addEventListener( 'error', finishFade, { once: true } );
						incomingImage.src = productImages[ nextImageIndex ];
					};
					window.setInterval( cycleImages, 3500 );
				}
			}
			main.append( visual );
			const pageTitle = $( '.wp-block-post-title', main );
			if ( pageTitle ) {
				pageTitle.classList.add( 'screen-reader-text' );
			}
		}
		loginPanel.classList.add( 'gilda-account-tab-panel' );
		registerPanel.classList.add( 'gilda-account-tab-panel' );
		loginPanel.id = 'gilda-account-login-panel';
		registerPanel.id = 'gilda-account-register-panel';
		loginPanel.setAttribute( 'role', 'tabpanel' );
		registerPanel.setAttribute( 'role', 'tabpanel' );
		loginPanel.setAttribute( 'aria-labelledby', 'gilda-account-login-tab' );
		registerPanel.setAttribute( 'aria-labelledby', 'gilda-account-register-tab' );

		const tabs = document.createElement( 'div' );
		tabs.className = 'gilda-account-tabs__list';
		tabs.setAttribute( 'role', 'tablist' );
		tabs.setAttribute( 'aria-label', 'Acesso à conta' );
		tabs.innerHTML = '<button type="button" id="gilda-account-login-tab" role="tab" aria-controls="gilda-account-login-panel">Entrar</button><button type="button" id="gilda-account-register-tab" role="tab" aria-controls="gilda-account-register-panel">Criar conta</button>';
		columns.before( tabs );
		const tabButtons = $$( '[role="tab"]', tabs );
		const panels = [ loginPanel, registerPanel ];
		const activate = ( index, moveFocus = false ) => {
			tabButtons.forEach( ( button, buttonIndex ) => {
				const selected = buttonIndex === index;
				button.setAttribute( 'aria-selected', String( selected ) );
				button.tabIndex = selected ? 0 : -1;
				panels[ buttonIndex ].hidden = ! selected;
			} );
			if ( moveFocus ) {
				tabButtons[ index ].focus();
			}
		};
		tabButtons.forEach( ( button, index ) => button.addEventListener( 'click', () => activate( index ) ) );
		tabs.addEventListener( 'keydown', ( event ) => {
			const selectedIndex = tabButtons.findIndex( ( button ) => 'true' === button.getAttribute( 'aria-selected' ) );
			let nextIndex = selectedIndex;
			if ( 'ArrowRight' === event.key ) {
				nextIndex = ( selectedIndex + 1 ) % tabButtons.length;
			} else if ( 'ArrowLeft' === event.key ) {
				nextIndex = ( selectedIndex - 1 + tabButtons.length ) % tabButtons.length;
			} else if ( 'Home' === event.key ) {
				nextIndex = 0;
			} else if ( 'End' === event.key ) {
				nextIndex = tabButtons.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			activate( nextIndex, true );
		} );
		activate( 0 );
	}

	function initCheckoutStepper() {
		const form = $( 'form.checkout' );
		if ( ! form ) {
			return; // fora do checkout: sem isso o erro interrompe o boot() e as funções seguintes não rodam
		}
		const customer = $( '#customer_details', form );
		const orderHeading = $( '#order_review_heading', form );
		const orderReview = $( '#order_review', form );
		const billingWrapper = $( '.woocommerce-billing-fields__field-wrapper', customer );
		if ( ! form || ! customer || ! orderHeading || ! orderReview || ! billingWrapper || form.dataset.gildaStepper ) {
			return;
		}
		form.dataset.gildaStepper = '1';
		form.classList.add( 'gilda-checkout-enhanced' );
		const checkoutRoot = form.parentElement;
		checkoutRoot.classList.add( 'gilda-checkout-layout' );

		const loginToggle = $( '.woocommerce-form-login-toggle', checkoutRoot );
		const loginForm = $( 'form.woocommerce-form-login, form.login', checkoutRoot );
		if ( loginToggle && loginForm ) {
			const login = document.createElement( 'div' );
			login.className = 'gilda-checkout-login';
			loginToggle.before( login );
			login.append( loginToggle, loginForm );
		}

		const couponToggle = $( '.woocommerce-form-coupon-toggle', checkoutRoot );
		const couponForm = $( 'form.checkout_coupon', checkoutRoot );
		if ( couponToggle || couponForm ) {
			const couponSource = document.createElement( 'div' );
			couponSource.className = 'gilda-checkout-coupon-source';
			( couponToggle || couponForm ).before( couponSource );
			if ( couponToggle ) {
				couponSource.append( couponToggle );
			}
			if ( couponForm ) {
				couponSource.append( couponForm );
			}
		}

		const summary = document.createElement( 'aside' );
		summary.className = 'gilda-checkout-summary';
		summary.setAttribute( 'aria-label', 'Resumo do pedido e pagamento' );
		orderHeading.before( summary );
		summary.append( orderHeading, orderReview );
		const itemCount = $$( '.cart_item .product-quantity', orderReview ).reduce( ( total, quantity ) => {
			const match = quantity.textContent.match( /[×x]\s*(\d+)/i );
			return total + ( match ? Number( match[ 1 ] ) : 1 );
		}, 0 );
		orderHeading.textContent = `Resumo do pedido (${ itemCount } ${ 1 === itemCount ? 'item' : 'itens' })`;
		const customerSection = document.createElement( 'section' );
		customerSection.className = 'gilda-checkout-fields-section gilda-checkout-step-panel';
		customerSection.dataset.checkoutStep = '0';
		customerSection.innerHTML = '<h2>Informações do cliente</h2>';
		const deliverySection = document.createElement( 'section' );
		deliverySection.className = 'gilda-checkout-fields-section gilda-checkout-step-panel';
		deliverySection.dataset.checkoutStep = '1';
		deliverySection.innerHTML = '<h2>Endereço de entrega</h2>';
		const rows = new Map( Array.from( billingWrapper.children )
			.filter( ( row ) => row.classList.contains( 'form-row' ) )
			.map( ( row ) => [ row.id, row ] ) );
		const addField = ( section, id, size = 'full' ) => {
			const row = rows.get( id );
			if ( ! row ) {
				return;
			}
			rows.delete( id );
			row.classList.add( `gilda-checkout-field--${ size }` );
			section.append( row );
		};
		const addMatchingField = ( section, pattern, size = 'full' ) => {
			const id = Array.from( rows.keys() ).find( ( fieldId ) => {
				const row = rows.get( fieldId );
				return 'billing_address_2_field' !== fieldId && pattern.test( `${ fieldId } ${ row.textContent }` );
			} );
			if ( id ) {
				addField( section, id, size );
			}
		};
		addField( customerSection, 'billing_email_field' );
		addField( customerSection, 'billing_first_name_field', 'half' );
		addField( customerSection, 'billing_last_name_field', 'half' );
		addField( customerSection, 'billing_phone_field' );
		addMatchingField( customerSection, /cpf|documento/i );
		addField( deliverySection, 'billing_country_field' );
		addField( deliverySection, 'billing_postcode_field' );
		addField( deliverySection, 'billing_address_1_field', 'half' );
		addMatchingField( deliverySection, /billing_(number|numero)|\bn[uú]mero\b/i, 'half' );
		addMatchingField( deliverySection, /neighbou?rhood|bairro/i, 'half' );
		addField( deliverySection, 'billing_city_field', 'half' );
		addField( deliverySection, 'billing_state_field', 'half' );
		rows.forEach( ( row ) => {
			if ( 'billing_address_2_field' === row.id ) {
				row.hidden = true;
				deliverySection.append( row );
			} else if ( 'billing_company_field' === row.id || ! row.classList.contains( 'validate-required' ) ) {
				row.hidden = true;
				deliverySection.append( row );
			} else {
				deliverySection.append( row );
			}
		} );
		const shippingFields = $( '.woocommerce-shipping-fields', customer );
		const additionalFields = $( '.woocommerce-additional-fields', customer );
		if ( shippingFields ) {
			shippingFields.remove();
		}
		if ( additionalFields ) {
			const heading = $( 'h3', additionalFields );
			if ( heading ) {
				heading.textContent = 'Informações adicionais (opcional)';
			}
			const notesLabel = $( 'label[for="order_comments"]', additionalFields );
			if ( notesLabel ) {
				notesLabel.classList.add( 'screen-reader-text' );
			}
			const notes = $( 'textarea', additionalFields );
			if ( notes ) {
				notes.placeholder = 'Escreva aqui alguma observação para a entrega (opcional).';
				notes.rows = 5;
			}
			deliverySection.append( additionalFields );
		}

		// deliverySection ainda não está no form aqui (entra no replaceChildren mais abaixo): buscar no form
		// não achava o CEP e a busca de endereço nunca era ligada.
		const postcodeInput = $( 'input[name="billing_postcode"]', deliverySection );
		if ( postcodeInput ) {
			const postcodeRow = postcodeInput.closest( '.form-row' );
			const addressFeedback = document.createElement( 'p' );
			addressFeedback.className = 'gilda-checkout-address-feedback';
			addressFeedback.setAttribute( 'role', 'status' );
			addressFeedback.setAttribute( 'aria-live', 'polite' );
			postcodeRow.after( addressFeedback );
			const billingField = ( name ) => $( `[name="billing_${ name }"]`, form );
			let fillingAddress = false;
			const getAddressControls = () => {
				const currentNeighborhoodRow = $$( '.form-row', deliverySection ).find( ( row ) => /neighbou?rhood|bairro/i.test( `${ row.id } ${ row.textContent }` ) );
				return {
					street: billingField( 'address_1' ),
					neighborhood: currentNeighborhoodRow && $( 'input, select, textarea', currentNeighborhoodRow ),
					city: billingField( 'city' ),
					state: billingField( 'state' ),
				};
			};
			const markAddressEdited = ( event ) => {
				if ( ! fillingAddress && Object.values( getAddressControls() ).includes( event.target ) ) {
					event.target.dataset.userEditedAddress = '1';
				}
			};
			form.addEventListener( 'input', markAddressEdited );
			form.addEventListener( 'change', markAddressEdited );
			let lookupTimer = 0;
			let lookupController = null;
			let lastLookup = '';
			const getPostcodeInput = () => $( 'input[name="billing_postcode"]', form );
			const lookupAddress = async () => {
				const currentPostcode = getPostcodeInput();
				if ( ! currentPostcode ) {
					return;
				}
				const cep = currentPostcode.value.replace( /\D/g, '' );
				const country = billingField( 'country' );
				if ( 8 !== cep.length || ( country && 'BR' !== country.value ) || cep === lastLookup ) {
					return;
				}
				lastLookup = cep;
				lookupController?.abort();
				lookupController = new AbortController();
				addressFeedback.textContent = 'Buscando endereço…';
				try {
					let address = null;
					for ( const url of [ `https://viacep.com.br/ws/${ cep }/json/`, `https://brasilapi.com.br/api/cep/v1/${ cep }` ] ) {
						try {
							const response = await fetch( url, { signal: lookupController.signal, headers: { Accept: 'application/json' } } );
							if ( ! response.ok ) {
								continue;
							}
							const result = await response.json();
							if ( ! result.erro ) {
								address = {
									logradouro: result.logradouro || result.street,
									bairro: result.bairro || result.neighborhood,
									localidade: result.localidade || result.city,
									uf: result.uf || result.state,
								};
								break;
							}
						} catch ( requestError ) {
							if ( 'AbortError' === requestError.name ) {
								throw requestError;
							}
						}
					}
					if ( ! address ) {
						throw new Error( 'Não foi possível consultar este CEP agora. Confira sua conexão ou preencha o endereço manualmente.' );
					}
					fillingAddress = true;
					const addressControls = getAddressControls();
					const values = [
						[ addressControls.street, address.logradouro ],
						[ addressControls.neighborhood, address.bairro ],
						[ addressControls.city, address.localidade ],
						[ addressControls.state, address.uf ],
					];
					values.forEach( ( [ control, value ] ) => {
						if ( ! control || control.dataset.userEditedAddress || ! value ) {
							return;
						}
						control.value = value;
						control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					} );
					fillingAddress = false;
					addressFeedback.textContent = 'Endereço encontrado. Confira e complete o número.';
				} catch ( error ) {
					fillingAddress = false;
					if ( 'AbortError' !== error.name ) {
						lastLookup = '';
						addressFeedback.textContent = error.message || 'Não foi possível consultar o CEP.';
					}
				}
			};
			const handlePostcodeInput = ( input ) => {
				input.value = maskCep( input.value );
				lastLookup = '';
				lookupController?.abort();
				addressFeedback.textContent = '';
				window.clearTimeout( lookupTimer );
				if ( 8 === input.value.replace( /\D/g, '' ).length ) {
					lookupTimer = window.setTimeout( lookupAddress, 300 );
				}
			};
			form.addEventListener( 'input', ( event ) => {
				if ( event.target.matches( 'input[name="billing_postcode"]' ) ) {
					handlePostcodeInput( event.target );
				}
			} );
			form.addEventListener( 'change', ( event ) => {
				if ( event.target.matches( 'input[name="billing_postcode"]' ) ) {
					handlePostcodeInput( event.target );
				}
			} );
			form.addEventListener( 'focusout', ( event ) => {
				if ( event.target.matches( 'input[name="billing_postcode"]' ) ) {
					lookupAddress();
				}
			} );
			if ( postcodeInput.value.replace( /\D/g, '' ).length === 8 ) {
				lookupAddress();
			}
		}
		const stepper = document.createElement( 'ol' );
		stepper.className = 'gilda-checkout-stepper';
		stepper.setAttribute( 'aria-label', 'Etapas do checkout' );
		stepper.innerHTML = '<li><span class="gilda-checkout-stepper__number">1</span><span>Cliente</span></li><li><span class="gilda-checkout-stepper__number">2</span><span>Entrega</span></li>';
		const stepActions = document.createElement( 'div' );
		stepActions.className = 'gilda-checkout-step-actions';
		stepActions.innerHTML = '<button type="button" class="gilda-btn gilda-btn--secondary gilda-checkout-back" hidden>Voltar</button><button type="button" class="gilda-btn gilda-btn--primary gilda-checkout-continue">Continuar</button>';
		customer.replaceChildren( stepper, customerSection, deliverySection, stepActions );
		const panels = [ customerSection, deliverySection ];
		const progressItems = $$( 'li', stepper );
		const backButton = $( '.gilda-checkout-back', stepActions );
		const nextButton = $( '.gilda-checkout-continue', stepActions );
		let activeStep = 0;
		const setStep = ( index ) => {
			activeStep = Math.max( 0, Math.min( panels.length - 1, index ) );
			panels.forEach( ( panel, panelIndex ) => { panel.hidden = panelIndex !== activeStep; } );
			progressItems.forEach( ( item, itemIndex ) => {
				item.classList.toggle( 'is-active', itemIndex === activeStep );
				item.classList.toggle( 'is-complete', itemIndex < activeStep );
				if ( itemIndex === activeStep ) {
					item.setAttribute( 'aria-current', 'step' );
				} else {
					item.removeAttribute( 'aria-current' );
				}
			} );
			backButton.hidden = activeStep === 0;
			nextButton.textContent = activeStep === panels.length - 1 ? 'Ir para pagamento' : 'Continuar';
			// No computador o pagamento já está à vista ao lado: na última etapa o botão some (CSS).
			stepActions.classList.toggle( 'is-last-step', activeStep === panels.length - 1 );
			if ( typeof syncPlaceOrder === 'function' ) {
				syncPlaceOrder();
			}
		};
		/** Primeira linha obrigatória sem preencher (ou inválida) de uma etapa, ou undefined. */
		const missingRowIn = ( panel ) => $$( '.form-row.validate-required', panel ).find( ( row ) => {
			const shipToDifferent = $( '#ship-to-different-address-checkbox', form );
			if ( row.hidden || 'none' === window.getComputedStyle( row ).display || ( row.closest( '.shipping_address' ) && shipToDifferent && ! shipToDifferent.checked ) ) {
				return false;
			}
			const controls = $$( 'input:not([type="hidden"]), select, textarea', row );
			return controls.some( ( control ) => 'checkbox' === control.type ? ! control.checked : ! String( control.value || '' ).trim() || ! control.checkValidity() );
		} );
		const showMissing = ( missingRow ) => {
			missingRow.classList.add( 'woocommerce-invalid' );
			const select2 = $( '.select2-selection', missingRow );
			( $( 'input:not([type="hidden"]), select, textarea', missingRow ) || select2 )?.focus();
		};
		backButton.addEventListener( 'click', () => setStep( activeStep - 1 ) );
		nextButton.addEventListener( 'click', () => {
			const missingRow = missingRowIn( panels[ activeStep ] );
			if ( missingRow ) {
				showMissing( missingRow );
				return;
			}
			if ( activeStep < panels.length - 1 ) {
				setStep( activeStep + 1 );
				customer.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				return;
			}
			const payment = $( '#payment', summary );
			if ( payment ) {
				payment.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				$( 'input[name="payment_method"]:checked', payment )?.focus( { preventScroll: true } );
			}
		} );
		/*
		 * "Finalizar pedido" só fica ativo com as duas etapas completas. Enquanto falta algo ele aparece
		 * apagado, com um aviso em cima; o clique (ou Enter num campo) leva até o que falta, em vez de
		 * mandar o formulário e voltar com uma lista de erros. O #payment é refeito pelo WooCommerce a cada
		 * atualização do checkout (frete, cupom), então o estado é reaplicado no "updated_checkout".
		 */
		const stepsComplete = () => panels.every( ( panel ) => ! missingRowIn( panel ) );
		function syncPlaceOrder() {
			const button = $( '#place_order', form );
			if ( ! button ) {
				return;
			}
			const ready = stepsComplete();
			button.classList.toggle( 'is-waiting', ! ready );
			button.setAttribute( 'aria-disabled', ready ? 'false' : 'true' );
			let note = $( '.gilda-checkout-place-note', form );
			if ( ! note ) {
				note = document.createElement( 'p' );
				note.className = 'gilda-checkout-place-note';
				note.id = 'gilda-checkout-place-note';
				note.textContent = 'Preencha seus dados e o endereço de entrega para finalizar o pedido.';
			}
			if ( note.nextElementSibling !== button ) {
				button.before( note );
			}
			note.hidden = ready;
			if ( ready ) {
				button.removeAttribute( 'aria-describedby' );
			} else {
				button.setAttribute( 'aria-describedby', note.id );
			}
		}
		form.addEventListener(
			'click',
			( event ) => {
				if ( ! event.target.closest( '#place_order' ) || stepsComplete() ) {
					return;
				}
				event.preventDefault();
				event.stopImmediatePropagation();
				const index = panels.findIndex( ( panel ) => missingRowIn( panel ) );
				setStep( index );
				customer.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				showMissing( missingRowIn( panels[ index ] ) );
			},
			true
		);
		let syncTimer;
		const queueSync = () => {
			clearTimeout( syncTimer );
			syncTimer = setTimeout( syncPlaceOrder, 150 );
		};
		form.addEventListener( 'input', queueSync );
		form.addEventListener( 'change', queueSync );
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout', syncPlaceOrder );
		}
		setStep( 0 );

		const couponCode = $( 'form.checkout_coupon input[name="coupon_code"]', checkoutRoot );
		const couponRow = document.createElement( 'tr' );
		couponRow.className = 'gilda-checkout-coupon-row';
		couponRow.innerHTML = '<td colspan="2"><div class="gilda-checkout-coupon"><label class="screen-reader-text" for="gilda-checkout-coupon-code">Código promocional</label><input id="gilda-checkout-coupon-code" type="text" placeholder="Código promocional"><button type="button" class="gilda-checkout-coupon__apply">Aplicar</button></div><p class="gilda-checkout-coupon__feedback" aria-live="polite"></p></td>';
		const placeCouponRow = () => {
			const tableBody = $( '.shop_table tbody', orderReview );
			if ( ! tableBody || couponRow.isConnected ) {
				return;
			}
			const firstTotal = $( 'tr.cart-subtotal, tr.woocommerce-shipping-totals, tr.order-total', tableBody );
			tableBody.insertBefore( couponRow, firstTotal || null );
		};
		placeCouponRow();
		if ( couponCode ) {
			$( '.gilda-checkout-coupon__apply', couponRow ).addEventListener( 'click', () => {
				const code = $( '#gilda-checkout-coupon-code', couponRow ).value.trim();
				if ( ! code ) {
					$( '#gilda-checkout-coupon-code', couponRow ).focus();
					return;
				}
				couponCode.value = code;
				$( '.gilda-checkout-coupon__feedback', couponRow ).textContent = 'Aplicando cupom…';
				couponCode.form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
			} );
			new MutationObserver( placeCouponRow ).observe( orderReview, { childList: true, subtree: true } );
			// Resultado do cupom: o WooCommerce escreve a resposta perto do formulário original, que fica
			// escondido. Sem isto o campo ficava em "Aplicando cupom…" e a recusa (ex.: "válido apenas na
			// primeira compra") nunca aparecia.
			if ( window.jQuery ) {
				let couponErrored = false;
				const feedback = ( text, isError ) => {
					const el = $( '.gilda-checkout-coupon__feedback', couponRow );
					el.textContent = text;
					el.classList.toggle( 'gilda-checkout-coupon__feedback--error', !! isError ); // não usar is-error: o WooCommerce apaga esses elementos
					el.classList.toggle( 'gilda-checkout-coupon__feedback--success', ! isError && !! text );
					el.setAttribute( 'role', isError ? 'alert' : 'status' );
				};
				window.jQuery( document.body )
					.on( 'errored_coupon_in_checkout', ( event, code, response ) => {
						couponErrored = true;
						const box = document.createElement( 'div' );
						box.innerHTML = response || '';
						feedback( box.textContent.replace( /\s+/g, ' ' ).trim() || 'Não foi possível aplicar este cupom.', true );
					} )
					.on( 'applied_coupon_in_checkout', () => {
						if ( ! couponErrored ) {
							feedback( 'Cupom aplicado.', false );
							$( '#gilda-checkout-coupon-code', couponRow ).value = '';
						}
						couponErrored = false;
					} );
			}
		} else {
			couponRow.remove();
		}
	}

	/**
	 * Cards de capítulos da home: troca uma foto por vez (card 1, 2, 3, 1…), em crossfade.
	 * A foto nova é carregada e decodificada antes e aparece POR CIMA da antiga,
	 * então o fundo nunca fica à mostra. Pausa fora da tela e com a aba escondida.
	 */
	function initChapterShowcase() {
		if ( reduceMotion ) {
			return;
		}
		const FADE_MS = 1200;
		const STEP_MS = 2600; // intervalo entre um card e o próximo (cada card troca a cada ~7,8 s)

		const cards = $$( '[data-chapter-slides]' )
			.map( ( card ) => {
				let slides;
				try {
					slides = JSON.parse( card.dataset.chapterSlides );
				} catch ( e ) {
					return null;
				}
				const base = $( '.gilda-chapter-card__image', card );
				if ( ! base || ! Array.isArray( slides ) || slides.length < 2 ) {
					return null;
				}
				const top = base.cloneNode( false );
				top.classList.add( 'is-next' );
				top.setAttribute( 'src', base.getAttribute( 'src' ) );
				top.setAttribute( 'aria-hidden', 'true' );
				base.after( top );
				return { slides, base, top, index: 0, busy: false };
			} )
			.filter( Boolean );
		if ( ! cards.length ) {
			return;
		}

		const ready = ( src ) => {
			const photo = new Image();
			photo.src = src;
			const done = photo.decode ? photo.decode() : new Promise( ( resolve ) => { photo.onload = resolve; photo.onerror = resolve; } );
			return done.catch( () => {} ).then( () => src );
		};

		const swap = ( c ) => {
			// Pausa enquanto a pessoa está em cima do card (mouse ou teclado) ou o zoom do card está abrindo:
			// quem vai clicar vê uma foto parada, e o zoom (category-zoom.js) começa dela, sem pular.
			const card = c.base.closest( '.gilda-chapter-card' );
			if ( c.busy || ( card && card.matches( ':hover, :focus-visible' ) ) || document.querySelector( '.gilda-czoom' ) ) {
				return;
			}
			c.busy = true;
			const next = ( c.index + 1 ) % c.slides.length;
			ready( c.slides[ next ] ).then( ( src ) => {
				c.top.src = src;
				window.requestAnimationFrame( () => c.top.classList.add( 'is-visible' ) );
				window.setTimeout( () => {
					// a de baixo vira a nova (já está em cache) e a de cima volta a ficar invisível sem animação
					c.base.src = src;
					c.top.style.transition = 'none';
					c.top.classList.toggle( 'is-visible', false );
					void c.top.offsetWidth;
					c.top.style.transition = '';
					c.index = next;
					c.busy = false;
					ready( c.slides[ ( next + 1 ) % c.slides.length ] ); // já deixa a seguinte carregando
				}, FADE_MS + 60 );
			} );
		};

		let visible = false;
		const section = cards[ 0 ].base.closest( '.gilda-chapter-showcase' ) || cards[ 0 ].base;
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( entries ) => { visible = entries[ 0 ].isIntersecting; } ).observe( section );
		} else {
			visible = true;
		}
		cards.forEach( ( c ) => ready( c.slides[ 1 ] ) );

		let turn = 0;
		window.setInterval( () => {
			if ( ! visible || document.hidden ) {
				return;
			}
			swap( cards[ turn % cards.length ] );
			turn++;
		}, STEP_MS );
	}

	function initProductDescriptionToggle() {
		$$( '.gilda-pdp-description' ).forEach( ( description ) => {
			description.classList.add( 'is-expanded' );
			const fullHeight = description.scrollHeight;
			description.classList.remove( 'is-expanded' );
			description.classList.add( 'is-collapsed' );
			if ( fullHeight <= description.clientHeight + 1 ) {
				description.classList.remove( 'is-collapsed' );
				return;
			}
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'gilda-pdp-read-more';
			button.setAttribute( 'aria-expanded', 'false' );
			button.textContent = 'Ler mais +';
			description.after( button );
			button.addEventListener( 'click', () => {
				const expanded = description.classList.toggle( 'is-expanded' );
				button.setAttribute( 'aria-expanded', String( expanded ) );
				button.textContent = expanded ? 'Ler menos −' : 'Ler mais +';
			} );
		} );
	}
	function boot() {
		initStickyHeader();
		initAccountTabs();
		initCheckoutStepper();
		initChapterShowcase();
		initProductDescriptionToggle();
		$$( '.gilda-carousel' ).forEach( initCarousel );
		$$( 'form.cart .quantity' ).forEach( initStepper );
		$$( 'form.variations_form select[name="attribute_pa_cor"]' ).forEach( initSwatches );
		$$( 'form.variations_form select[name*=tamanho], form.variations_form select[name*=size]' ).forEach( initSizeButtons );
		$$( '.gilda-shipping' ).forEach( initShipping );
		$$( '.gilda-filters' ).forEach( initFilters );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
