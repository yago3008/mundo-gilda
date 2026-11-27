/**
 * Painel GILDA;: listas de fotos (seletor de mídia + arrastar para ordenar), parágrafos do capítulo
 * e a lista "Para publicar" atualizada enquanto a dona preenche.
 * Marcação: gilda_photo_field() / gilda_paragraph_row() em chapters-admin.php e store-data.php.
 */
( function ( $ ) {
	'use strict';

	function escAttr( value ) {
		return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function tile( attachment, $box ) {
		var name = $box.data( 'name' );
		var sizes = attachment.sizes || {};
		var thumb = ( sizes.medium || sizes.thumbnail || sizes.full || {} ).url || attachment.url;
		var alt = '';
		if ( String( $box.data( 'alt' ) ) === '1' ) {
			alt =
				'<label class="gilda-photo__alt"><span>Descrição da foto</span><input type="text" name="gilda_alt[' +
				attachment.id +
				']" value="' +
				escAttr( attachment.alt ) +
				'" placeholder="Ex.: modelo com a Camisa Grão marrom" /></label>';
		}
		return $(
			'<li class="gilda-photo" data-id="' + attachment.id + '">' +
				'<img src="' + escAttr( thumb ) + '" alt="" />' +
				'<input type="hidden" name="' + escAttr( name ) + '" value="' + attachment.id + '" />' +
				alt +
				'<button type="button" class="gilda-photo__remove" aria-label="Tirar esta foto">×</button>' +
			'</li>'
		);
	}

	function refresh( $box ) {
		var max = parseInt( $box.data( 'max' ), 10 ) || 0;
		var count = $box.find( '.gilda-photo' ).length;
		$box.find( '.gilda-photos__count strong' ).text( count );
		$box.toggleClass( 'is-full', max > 0 && count >= max );
		$box.toggleClass( 'is-over', max > 1 && count > max );
		$box.toggleClass( 'is-empty', count === 0 );
		checklist();
	}

	function initPhotos( box ) {
		var $box = $( box );
		var max = parseInt( $box.data( 'max' ), 10 ) || 0;
		var frame;

		$box.find( '.gilda-photos__list' ).sortable( {
			items: '> .gilda-photo',
			tolerance: 'pointer',
			cancel: 'input,button',
			placeholder: 'gilda-photo gilda-photo--placeholder',
		} );

		$box.on( 'click', '.gilda-photo__remove', function () {
			$( this ).closest( '.gilda-photo' ).remove();
			refresh( $box );
		} );

		$box.on( 'click', '.gilda-photos__add', function ( event ) {
			event.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( {
				title: $box.data( 'title' ) || 'Escolher fotos',
				button: { text: $box.data( 'button' ) || 'Usar' },
				library: { type: 'image' },
				multiple: max === 1 ? false : 'add',
			} );
			frame.on( 'select', function () {
				var $list = $box.find( '.gilda-photos__list' );
				var chosen = frame.state().get( 'selection' ).toJSON();
				if ( max === 1 ) {
					$list.empty();
					chosen = chosen.slice( 0, 1 );
				}
				chosen.forEach( function ( attachment ) {
					if ( $list.find( '.gilda-photo[data-id="' + attachment.id + '"]' ).length ) {
						return;
					}
					if ( max > 1 && $list.find( '.gilda-photo' ).length >= max ) {
						return;
					}
					$list.append( tile( attachment, $box ) );
				} );
				refresh( $box );
			} );
			frame.open();
		} );

		refresh( $box );
	}

	/* Parágrafos */
	function initParagraphs() {
		var $list = $( '[data-gilda-paragraphs]' );
		if ( ! $list.length ) {
			return;
		}
		$list.sortable( { handle: '.gilda-paragraph__handle', items: '> .gilda-paragraph', axis: 'y' } );
		$( '.gilda-paragraphs__add' ).on( 'click', function () {
			var html = document.getElementById( 'gilda-paragraph-template' ).innerHTML;
			var $row = $( html );
			$list.append( $row );
			$row.find( 'textarea' ).trigger( 'focus' );
			checklist();
		} );
		$list.on( 'click', '.gilda-paragraph__remove', function () {
			$( this ).closest( '.gilda-paragraph' ).remove();
			checklist();
		} );
		$list.on( 'change', '.gilda-paragraph__italic-box', function () {
			$( this ).closest( '.gilda-paragraph' ).find( 'input[name="gilda_textos_italico[]"]' ).val( this.checked ? '1' : '0' );
		} );
		$list.on( 'input', 'textarea', checklist );
		$( '#title' ).on( 'input', checklist );
	}

	/* "Para publicar" (o servidor confere de novo ao salvar) */
	function checklist() {
		var $check = $( '[data-gilda-checklist]' );
		if ( ! $check.length ) {
			return;
		}
		var opening = $( '#gilda-chapter-opening .gilda-opening__others .gilda-photo' ).length;
		var state = {
			name: $.trim( $( '#title' ).val() || '' ) !== '',
			text: $( '[data-gilda-paragraphs] textarea' ).filter( function () {
				return $.trim( this.value ) !== '';
			} ).length > 0,
			main: $( '#gilda-chapter-opening .gilda-opening__main .gilda-photo' ).length === 1,
			opening: opening === 6,
		};
		$.each( state, function ( key, ok ) {
			$check.find( '[data-req="' + key + '"]' ).toggleClass( 'is-ok', ok ).toggleClass( 'is-missing', ! ok );
		} );
	}

	$( function () {
		$( '[data-gilda-photos]' ).each( function () {
			initPhotos( this );
		} );
		initParagraphs();
		checklist();
	} );
} )( jQuery );
