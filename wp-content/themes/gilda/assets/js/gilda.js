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
	/* 3.2 Quick view                                                      */
	/* ------------------------------------------------------------------ */

	let dialog;
	function getDialog() {
		if ( dialog ) {
			return dialog;
		}
		dialog = document.createElement( 'dialog' );
		dialog.className = 'gilda-dialog';
		dialog.setAttribute( 'aria-labelledby', 'gilda-qv-title' );
		dialog.innerHTML = `<button type="button" class="gilda-dialog__close" aria-label="${ escapeHTML( t( 'close' ) ) }">${ ICONS.close }</button><div class="gilda-dialog__body"></div>`;
		$( '.gilda-dialog__close', dialog ).addEventListener( 'click', () => dialog.close() );
		dialog.addEventListener( 'click', ( e ) => {
			if ( e.target === dialog ) {
				dialog.close();
			}
		} );
		document.body.appendChild( dialog );
		return dialog;
	}

	async function openQuickView( button ) {
		const id = button.dataset.productId;
		const dlg = getDialog();
		const body = $( '.gilda-dialog__body', dlg );
		body.innerHTML = `<div class="gilda-quickview"><p>${ escapeHTML( t( 'calculating' ) ) }</p></div>`;
		dlg.showModal();
		try {
			const p = await storeApi( `products/${ encodeURIComponent( id ) }` );
			const img = p.images && p.images[ 0 ];
			const price = p.prices ? formatBRL( p.prices.price, p.prices.currency_minor_unit ) : '';
			const canAdd = 'simple' === p.type && p.is_purchasable && p.is_in_stock;
			let action;
			if ( ! p.is_in_stock ) {
				action = `<button type="button" class="gilda-btn gilda-btn--disabled" disabled>${ escapeHTML( t( 'soldOut' ) ) }</button>`;
			} else if ( canAdd ) {
				action = `<button type="button" class="gilda-btn gilda-btn--primary" data-qv-add="${ p.id }">${ escapeHTML( t( 'addToCart' ) ) }</button>`;
			} else {
				action = '';
			}
			body.innerHTML = `
				<div class="gilda-quickview">
					${ img ? `<img src="${ escapeHTML( img.src ) }" alt="${ escapeHTML( img.alt || p.name ) }" />` : '<div></div>' }
					<div class="gilda-quickview__info">
						<h2 id="gilda-qv-title" class="gilda-quickview__name">${ escapeHTML( p.name ) }</h2>
						<p class="gilda-quickview__price">${ p.is_in_stock ? escapeHTML( price ) : escapeHTML( t( 'soldOut' ) ) }</p>
						<div class="gilda-quickview__desc">${ p.short_description || '' }</div>
						${ action }
						<a class="gilda-btn gilda-btn--secondary" href="${ escapeHTML( p.permalink ) }">${ escapeHTML( t( 'seeProduct' ) ) }</a>
					</div>
				</div>`;
		} catch ( err ) {
			body.innerHTML = `<div class="gilda-quickview"><p class="gilda-form-error">${ escapeHTML( err.message ) }</p></div>`;
		}
	}

	document.addEventListener( 'click', async ( e ) => {
		const qv = e.target.closest( '.gilda-quickview-btn' );
		if ( qv ) {
			e.preventDefault();
			openQuickView( qv );
			return;
		}
		const add = e.target.closest( '[data-qv-add]' );
		if ( add ) {
			add.disabled = true;
			try {
				const cart = await storeApi( 'cart/add-item', { method: 'POST', body: JSON.stringify( { id: Number( add.dataset.qvAdd ), quantity: 1 } ) } );
				setCartCount( cart.items_count || 0 );
				document.body.dispatchEvent( new Event( 'wc-blocks_added_to_cart' ) );
				dialog?.close();
				toast( t( 'added' ), { href: S.cartUrl, label: t( 'viewCart' ) } );
			} catch ( err ) {
				add.disabled = false;
				add.insertAdjacentHTML( 'afterend', `<p class="gilda-form-error" role="alert">${ escapeHTML( err.message ) }</p>` );
			}
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
			s.nextElementSibling?.classList?.contains( 'gilda-swatches' ) &&
				$$( '.gilda-swatch-option', s.nextElementSibling ).forEach( ( o ) => o.classList.add( 'has-error' ) );
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

	function initFilters( aside ) {
		const form = $( 'form', aside );
		const results = $( '.gilda-shop-results' );
		$$( '.gilda-range', aside ).forEach( initRange );

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
	/* Transparent header over the hero → solid on scroll                  */
	/* ------------------------------------------------------------------ */

	function initStickyHeader() {
		if ( ! document.body.classList.contains( 'gilda-has-hero' ) ) {
			return;
		}
		const header = $( '.wp-site-blocks > header' );
		if ( ! header ) {
			return;
		}
		let ticking = false;
		const sync = () => {
			header.classList.toggle( 'is-stuck', window.scrollY > 40 );
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

	function boot() {
		initStickyHeader();
		$$( '.gilda-carousel' ).forEach( initCarousel );
		$$( 'form.cart .quantity' ).forEach( initStepper );
		$$( 'form.variations_form select[name="attribute_pa_cor"]' ).forEach( initSwatches );
		$$( '.gilda-shipping' ).forEach( initShipping );
		$$( '.gilda-filters' ).forEach( initFilters );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
