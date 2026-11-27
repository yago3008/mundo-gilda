/**
 * Painel GILDA;: listas de fotos (seletor de mídia + arrastar para ordenar), parágrafos do capítulo e a
 * lista "Publicar" do capítulo, atualizada enquanto a dona preenche (o servidor confere de novo).
 * Marcação: gilda_photo_field() / gilda_paragraph_row() em chapters-admin.php.
 * Eventos: dispara "gilda:photos" no documento quando uma lista muda (a tela da peça usa para a prévia).
 */
( function ( $ ) {
	'use strict';

	function escAttr( value ) {
		return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function captions( $box ) {
		var list = $box.data( 'captions' );
		return Array.isArray( list ) ? list : [];
	}

	function tile( attachment, $box ) {
		var sizes = attachment.sizes || {};
		var thumb = ( sizes.medium || sizes.thumbnail || sizes.full || {} ).url || attachment.url;
		var alt = '';
		if ( String( $box.data( 'alt' ) ) === '1' ) {
			alt =
				'<label class="gilda-photo__alt"><span>Descrição</span><input type="text" name="gilda_alt[' +
				attachment.id + ']" value="' + escAttr( attachment.alt ) + '" placeholder="o que aparece na foto" /></label>';
		}
		return $(
			'<li class="gilda-photo" data-id="' + attachment.id + '">' +
				'<img src="' + escAttr( thumb ) + '" alt="" />' +
				'<input type="hidden" name="' + escAttr( $box.data( 'name' ) ) + '" value="' + attachment.id + '" />' +
				'<span class="gilda-photo__cap"></span>' + alt +
				'<button type="button" class="gilda-photo__remove" aria-label="Tirar esta foto">×</button>' +
			'</li>'
		);
	}

	/* Vagas "+ Foto N" (ou uma só "+ Adicionar"), legendas por posição, estado cheio/vazio. */
	function refresh( $box ) {
		var max = parseInt( $box.data( 'max' ), 10 ) || 0;
		var $list = $box.find( '.gilda-photos__list' );
		var $photos = $list.children( '.gilda-photo' );
		var count = $photos.length;
		var label = String( $box.data( 'add' ) || '+ Adicionar' );
		var caps = captions( $box );

		$list.children( '.gilda-photo-slot' ).remove();
		var slots = 0;
		if ( String( $box.data( 'slots' ) ) === '1' && max > 0 ) {
			slots = Math.max( 0, max - count );
		} else if ( max === 0 || count < max ) {
			slots = 1;
		}
		for ( var i = 0; i < slots; i++ ) {
			var text = label.indexOf( '%d' ) > -1 ? label.replace( '%d', count + i + 1 ) : label;
			$list.append( '<li class="gilda-photo-slot"><button type="button" class="gilda-photos__add">' + escAttr( text ) + '</button></li>' );
		}
		$photos.each( function ( index ) {
			$( this ).find( '.gilda-photo__cap' ).text( caps[ index ] || '' ).toggleClass( 'gilda-photo__cap--muted', index > 0 );
		} );
		$box.toggleClass( 'is-full', max > 0 && count >= max );
		$box.toggleClass( 'is-empty', count === 0 );
		$( document ).trigger( 'gilda:photos', [ $box ] );
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
			update: function () { refresh( $box ); },
		} );
		// As fotos ficam lado a lado (grade/flex), mas o jQuery UI só reconhece "lado a lado" por float ou
		// display inline: sem isto ele compara só o movimento vertical e arrastar para o lado não reordena.
		var sortable = $box.find( '.gilda-photos__list' ).sortable( 'instance' );
		if ( sortable ) {
			sortable._isFloating = function () { return true; };
		}

		$box.on( 'click', '.gilda-photo__remove', function () {
			$( this ).closest( '.gilda-photo' ).remove();
			refresh( $box );
		} );

		$box.on( 'click', '.gilda-photos__add', function ( event ) {
			event.preventDefault();
			if ( ! frame ) {
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
						$list.children( '.gilda-photo' ).remove();
						chosen = chosen.slice( 0, 1 );
					}
					chosen.forEach( function ( attachment ) {
						if ( $list.find( '.gilda-photo[data-id="' + attachment.id + '"]' ).length ) {
							return;
						}
						if ( max > 1 && $list.children( '.gilda-photo' ).length >= max ) {
							return;
						}
						var $slot = $list.children( '.gilda-photo-slot' ).first();
						if ( $slot.length ) {
							$slot.before( tile( attachment, $box ) );
						} else {
							$list.append( tile( attachment, $box ) );
						}
					} );
					frame.state().get( 'selection' ).reset();
					refresh( $box );
				} );
			}
			frame.open();
		} );

		refresh( $box );
	}

	/* Parágrafos */
	function renumber() {
		$( '[data-gilda-paragraphs] > .gilda-paragraph' ).each( function ( i ) {
			$( this ).find( '.gilda-paragraph__n' ).text( 'Parágrafo ' + ( i + 1 ) );
		} );
	}

	function initParagraphs() {
		var $list = $( '[data-gilda-paragraphs]' );
		if ( ! $list.length ) {
			return;
		}
		$list.sortable( { handle: '.gilda-paragraph__handle', items: '> .gilda-paragraph', axis: 'y', update: renumber } );
		$( '.gilda-paragraphs__add' ).on( 'click', function () {
			var $row = $( document.getElementById( 'gilda-paragraph-template' ).innerHTML );
			$list.append( $row );
			renumber();
			$row.find( 'textarea' ).trigger( 'focus' );
			checklist();
		} );
		$list.on( 'click', '.gilda-paragraph__remove', function () {
			$( this ).closest( '.gilda-paragraph' ).remove();
			renumber();
			checklist();
		} );
		$list.on( 'change', '.gilda-paragraph__italic-box', function () {
			var $row = $( this ).closest( '.gilda-paragraph' );
			$row.toggleClass( 'is-italic', this.checked );
			$row.find( 'input[name="gilda_textos_italico[]"]' ).val( this.checked ? '1' : '0' );
		} );
		$list.on( 'input', 'textarea', checklist );
		$( '#title' ).on( 'input', checklist );
		$( '#gilda-chapter-products' ).on( 'change', 'input[type="checkbox"]', checklist );
		renumber();
	}

	/* Capítulo: "5 de 7 fotos · faltam 2", lista "Para publicar", botão e prévia do card. */
	function checklist() {
		var $check = $( '[data-gilda-checklist]' );
		if ( ! $check.length ) {
			return;
		}
		var main = $( '#gilda-chapter-opening .gilda-opening__main .gilda-photo' ).length;
		var others = $( '#gilda-chapter-opening .gilda-opening__others .gilda-photo' ).length;
		var total = main + others;
		var missing = 7 - total;
		var state = {
			name: $.trim( $( '#title' ).val() || '' ) !== '',
			text: $( '[data-gilda-paragraphs] textarea' ).filter( function () {
				return $.trim( this.value ) !== '';
			} ).length > 0,
			main: main === 1,
			opening: others === 6,
		};
		var $pill = $( '[data-gilda-opening-count]' );
		$pill.text( total + ' de 7 fotos' + ( missing > 0 ? ' · faltam ' + missing : missing < 0 ? ' · sobram ' + -missing : ' · pronto' ) );
		$pill.toggleClass( 'is-ok', missing === 0 );

		$.each( state, function ( key, ok ) {
			var $li = $check.find( '[data-req="' + key + '"]' );
			var label = $li.data( 'label' );
			if ( key === 'opening' && ! ok ) {
				label = '7 fotos da abertura (' + ( others < 6 ? 'faltam ' + ( 6 - others ) : 'sobram ' + ( others - 6 ) ) + ')';
			}
			$li.text( label ).toggleClass( 'is-ok', ok ).toggleClass( 'is-missing', ! ok );
		} );
		var products = $( '#gilda-chapter-products input[type="checkbox"]:checked' ).length;
		$check.find( '[data-req="products"]' )
			.text( products ? 'Peças escolhidas (' + products + ')' : 'Peças: nenhuma ainda (opcional)' )
			.toggleClass( 'is-ok', products > 0 ).toggleClass( 'is-optional', ! products );

		var ready = state.name && state.text && state.main && state.opening;
		$( '[data-gilda-publish]' ).prop( 'disabled', ! ready ).attr( 'aria-disabled', ready ? 'false' : 'true' );

		var $first = $( '#gilda-chapter-gallery .gilda-photo img' ).first();
		var $preview = $( '[data-gilda-card-preview] img' );
		if ( $first.length ) {
			$preview.attr( 'src', $first.attr( 'src' ) ).prop( 'hidden', false );
		} else {
			$preview.prop( 'hidden', true );
		}
	}

	$( function () {
		$( '[data-gilda-photos]' ).each( function () {
			initPhotos( this );
		} );
		initParagraphs();
		if ( document.body.classList.contains( 'gilda-chapter-screen' ) ) {
			$( '#title' ).attr( 'placeholder', 'Ex.: Ritual Brasileiro' );
		}
		checklist();
	} );
} )( jQuery );
