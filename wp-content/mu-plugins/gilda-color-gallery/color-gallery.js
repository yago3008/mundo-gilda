/**
 * GILDA; Fotos por cor — ao escolher a cor, a galeria já mostra só as fotos daquela cor,
 * mesmo antes de escolher o tamanho.
 *
 * As fotos ficam na galeria de cada variação (recurso do próprio WooCommerce 11: gallery_image_ids /
 * gallery_images_html em data-product_variations). O WooCommerce só troca a galeria quando a variação
 * inteira (cor + tamanho) está escolhida; aqui, enquanto só a cor está escolhida, usamos a galeria de
 * qualquer variação daquela cor. Também evita trocar de novo quando a cor não mudou (sem piscar).
 *
 * Troca suave: o WooCommerce tira a galeria e põe a nova invisível, e ela aparece enquanto as fotos
 * ainda carregam (piscava). Aqui uma cópia da galeria atual fica por cima, a nova monta embaixo com a
 * 1ª foto já decodificada, e só então a cópia some devagar (crossfade).
 */
( function ( $ ) {
	'use strict';

	const ATTR = ( window.gildaColorGallery && window.gildaColorGallery.attribute ) || 'attribute_pa_cor';
	const original = $.fn.wc_variations_image_update;
	if ( typeof original !== 'function' ) {
		return;
	}

	const FADE_MS = 380;
	const reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function galleryOf( $form ) {
		return $form.closest( '.product' ).find( '.woocommerce-product-gallery' ).not( '.gilda-gallery-ghost' ).first()[ 0 ] || null;
	}

	/** Roda a troca da galeria por baixo de uma cópia da atual, que some quando a nova está pronta. */
	function crossfade( $form, swap ) {
		const old = galleryOf( $form );
		if ( reduceMotion || ! old || ! old.offsetWidth ) {
			return swap();
		}
		const parent = old.parentElement;
		parent.querySelectorAll( ':scope > .gilda-gallery-ghost' ).forEach( ( g ) => g.remove() );
		if ( 'static' === getComputedStyle( parent ).position ) {
			parent.style.position = 'relative';
		}
		const ghost = old.cloneNode( true );
		ghost.classList.add( 'gilda-gallery-ghost' );
		ghost.removeAttribute( 'id' );
		ghost.querySelectorAll( '[id]' ).forEach( ( el ) => el.removeAttribute( 'id' ) );
		ghost.setAttribute( 'aria-hidden', 'true' );
		ghost.inert = true;
		Object.assign( ghost.style, {
			position: 'absolute',
			left: `${ old.offsetLeft }px`,
			top: `${ old.offsetTop }px`,
			width: `${ old.offsetWidth }px`,
			height: `${ old.offsetHeight }px`,
			margin: '0',
			zIndex: '5',
			pointerEvents: 'none',
			opacity: '1',
			transition: `opacity ${ FADE_MS }ms ease`,
		} );
		parent.appendChild( ghost ); // depois da original: o WooCommerce troca sempre a primeira

		const result = swap();

		const fresh = galleryOf( $form );
		let first = null;
		if ( fresh && fresh !== old ) {
			// a nova fica visível já (escondida pela cópia), sem o fade-in próprio do WooCommerce
			fresh.style.transition = 'none';
			fresh.style.opacity = '1';
			fresh.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img, i ) => {
				if ( i < 6 ) {
					img.loading = 'eager';
				}
			} );
			first = fresh.querySelector( '.woocommerce-product-gallery__image img' );
		}
		const ready = first && first.decode ? first.decode().catch( () => {} ) : Promise.resolve();
		let done = false;
		const fade = () => {
			if ( done ) {
				return;
			}
			done = true;
			requestAnimationFrame( () => {
				ghost.style.opacity = '0';
				setTimeout( () => ghost.remove(), FADE_MS + 60 );
			} );
		};
		ready.then( fade );
		setTimeout( fade, 900 ); // foto lenta: não segura a cópia para sempre
		return result;
	}

	function selectedColor( $form ) {
		return String( $form.find( `select[name="${ ATTR }"]` ).val() || '' );
	}

	function colorVariation( $form, color ) {
		const variations = $form.data( 'product_variations' );
		if ( ! color || ! Array.isArray( variations ) ) {
			return null;
		}
		return variations.find( ( v ) => v && v.attributes && v.attributes[ ATTR ] === color && v.gallery_images_html ) || null;
	}

	$.fn.wc_variations_image_update = function ( variation ) {
		const $form = $( this );
		if ( ! $form.is( 'form.variations_form' ) ) {
			return original.call( this, variation );
		}

		// Sem variação completa: se há cor escolhida, mostra as fotos dela.
		const color = variation && variation.attributes ? String( variation.attributes[ ATTR ] || '' ) || selectedColor( $form ) : selectedColor( $form );
		const target = variation && variation.gallery_images_html ? variation : colorVariation( $form, color );

		if ( target ) {
			// A galeria dessa cor já está na tela (trocou só o tamanho): não reconstrói.
			if ( $form.data( 'gildaColorShown' ) === color && $form.wc_variations_gallery_is_active && $form.wc_variations_gallery_is_active() ) {
				return this;
			}
			$form.data( 'gildaColorShown', color );
			return crossfade( $form, () => original.call( this, target ) );
		}

		const hadColor = $form.data( 'gildaColorShown' );
		$form.removeData( 'gildaColorShown' );
		// "Limpar": volta para todas as fotos com a mesma troca suave
		return hadColor ? crossfade( $form, () => original.call( this, variation ) ) : original.call( this, variation );
	};
} )( jQuery );
