/**
 * GILDA; Fotos por cor — ao escolher a cor, a galeria já mostra só as fotos daquela cor,
 * mesmo antes de escolher o tamanho.
 *
 * As fotos ficam na galeria de cada variação (recurso do próprio WooCommerce 11: gallery_image_ids /
 * gallery_images_html em data-product_variations). O WooCommerce só troca a galeria quando a variação
 * inteira (cor + tamanho) está escolhida; aqui, enquanto só a cor está escolhida, usamos a galeria de
 * qualquer variação daquela cor. Também evita trocar de novo quando a cor não mudou (sem piscar).
 */
( function ( $ ) {
	'use strict';

	const ATTR = ( window.gildaColorGallery && window.gildaColorGallery.attribute ) || 'attribute_pa_cor';
	const original = $.fn.wc_variations_image_update;
	if ( typeof original !== 'function' ) {
		return;
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
			return original.call( this, target );
		}

		$form.removeData( 'gildaColorShown' );
		return original.call( this, variation );
	};
} )( jQuery );
