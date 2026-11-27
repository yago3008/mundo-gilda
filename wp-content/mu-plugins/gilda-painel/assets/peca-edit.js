/**
 * Tela "Nova peça" (peca.php): grade de estoque cor × tamanho, "+ Nova cor", peso/medidas pela
 * categoria e a prévia do card da loja.
 */
( function ( $ ) {
	'use strict';

	var $form = $( '#gilda-peca-form' );
	if ( ! $form.length ) {
		return;
	}
	var config = $form.data( 'gilda-peca' ) || {};
	var stock = $.extend( {}, config.stock || {} ); // "corId-tamId" → quantidade (o que já foi digitado)
	// SKU por combinação: { v: valor, stored: já gravado (não muda sozinho), manual: a dona digitou }.
	var skus = {};
	$.each( config.skus || {}, function ( key, value ) {
		skus[ key ] = { v: value, stored: true, manual: false };
	} );

	/* SKU sugerido: CAT-NOME[-COR][-TAM], igual ao sku.php (o servidor confere e garante que é único). */
	function clean( text ) {
		return String( text || '' ).normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toUpperCase().replace( /[^A-Z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}
	function nameToken() {
		var $cat = $form.find( '#gilda-peca-category :selected' );
		var slug = String( $cat.data( 'slug' ) || '' );
		var stem = slug.replace( /s$/, '' );
		var words = clean( $form.find( '#gilda-peca-name' ).val() ).toLowerCase().split( '-' ).filter( Boolean );
		if ( words.length > 1 && stem && ( words[ 0 ] === stem || words[ 0 ] === slug ) ) {
			words.shift();
		}
		return clean( words.join( '-' ) ) || 'PECA';
	}
	function baseSku() {
		var code = String( $form.find( '#gilda-peca-category :selected' ).data( 'code' ) || '' );
		return [ code || 'GIL', nameToken() ].join( '-' );
	}
	function suggest( color, size ) {
		return [ baseSku(), color && color.id !== '0' ? color.code : '', size && size.id !== '0' ? size.code : '' ].filter( Boolean ).join( '-' );
	}

	function esc( value ) {
		return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function checked( selector ) {
		return $form.find( selector + ' input[type="checkbox"]:checked' ).map( function () {
			return { id: this.value, name: $( this ).data( 'name' ), hex: $( this ).data( 'hex' ), code: String( $( this ).data( 'code' ) || '' ) };
		} ).get();
	}

	/* Grade de estoque */
	function renderStock() {
		var colors = checked( '[data-gilda-colors]' );
		var sizes = checked( '[data-gilda-sizes]' );
		var rows = colors.length ? colors : [ { id: '0', name: '', hex: '', code: '' } ];
		var cols = sizes.length ? sizes : [ { id: '0', name: 'Estoque', code: '' } ];
		var variable = colors.length > 0 || sizes.length > 0;
		var html = '<table><thead><tr><th scope="col">' + ( colors.length ? 'Cor' : '' ) + '</th>';
		cols.forEach( function ( s ) {
			html += '<th scope="col">' + esc( s.name ) + '</th>';
		} );
		html += '</tr></thead><tbody>';
		rows.forEach( function ( c ) {
			html += '<tr><th scope="row">' + ( c.name ? '<span><span class="gilda-swatch" style="background:' + esc( c.hex ) + '" aria-hidden="true"></span>' + esc( c.name ) + '</span>' : ( sizes.length ? 'Quantidade' : 'Peça' ) ) + '</th>';
			cols.forEach( function ( s ) {
				var key = c.id + '-' + s.id;
				var value = stock[ key ] != null ? stock[ key ] : 0;
				var label = [ c.name, s.id !== '0' ? s.name : '' ].filter( Boolean ).join( ' ' ) || 'Estoque';
				html += '<td><input type="number" min="0" step="1" inputmode="numeric" name="estoque[' + esc( c.id ) + '][' + esc( s.id ) + ']" value="' + esc( value ) + '" data-key="' + esc( key ) + '" aria-label="' + esc( label ) + '"' + ( +value === 0 ? ' class="is-zero"' : '' ) + ' />';
				if ( variable ) {
					var sku = skus[ key ] || ( skus[ key ] = { v: '', stored: false, manual: false } );
					if ( ! sku.stored && ! sku.manual ) {
						sku.v = suggest( c, s );
					}
					html += '<input type="text" class="gilda-sku" name="sku[' + esc( c.id ) + '][' + esc( s.id ) + ']" value="' + esc( sku.v ) + '" data-key="' + esc( key ) + '" aria-label="SKU ' + esc( label ) + '" autocomplete="off" spellcheck="false" />' +
						'<input type="hidden" name="sku_manual[' + esc( c.id ) + '][' + esc( s.id ) + ']" value="' + ( sku.manual ? '1' : '0' ) + '" />';
				}
				html += '</td>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		$form.find( '[data-gilda-stock]' ).html( html );
	}
	$form.on( 'input', '[data-gilda-stock] input', function () {
		stock[ $( this ).data( 'key' ) ] = this.value;
		$( this ).toggleClass( 'is-zero', +this.value === 0 );
	} );
	$form.on( 'change', '[data-gilda-colors] input, [data-gilda-sizes] input', renderStock );
	$form.on( 'input', '[data-gilda-stock] .gilda-sku', function () {
		var sku = skus[ $( this ).data( 'key' ) ];
		sku.v = this.value;
		sku.manual = true;
		$( this ).next( 'input[type="hidden"]' ).val( '1' );
	} );

	/* SKU da peça: sugerido enquanto não existe; gravado, só muda se a dona editar. */
	var $parentSku = $form.find( '#gilda-peca-sku' );
	var parentManual = false;
	function refreshSkus() {
		if ( ! $parentSku.data( 'stored' ) && ! parentManual ) {
			$parentSku.val( baseSku() );
		}
		renderStock();
	}
	$parentSku.on( 'input', function () {
		parentManual = true;
		$form.find( '[data-gilda-sku-manual]' ).val( '1' );
	} );
	$form.on( 'input change', '#gilda-peca-name, #gilda-peca-category', refreshSkus );

	/* + Nova cor */
	var $new = $form.find( '[data-gilda-newcolor]' );
	$form.on( 'click', '[data-gilda-newcolor-open]', function () {
		$new.prop( 'hidden', false ).find( '[data-gilda-newcolor-name]' ).trigger( 'focus' );
	} );
	$form.on( 'click', '[data-gilda-newcolor-cancel]', function () {
		$new.prop( 'hidden', true );
	} );
	$form.on( 'keydown', '[data-gilda-newcolor-name]', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			$form.find( '[data-gilda-newcolor-save]' ).trigger( 'click' );
		}
	} );
	$form.on( 'click', '[data-gilda-newcolor-save]', function () {
		var name = $.trim( $new.find( '[data-gilda-newcolor-name]' ).val() );
		var hex = $new.find( '[data-gilda-newcolor-hex]' ).val();
		var $msg = $new.find( '[data-gilda-newcolor-msg]' );
		if ( ! name ) {
			$msg.text( 'Escreva o nome da cor.' );
			return;
		}
		$msg.text( 'Criando…' );
		$.post( config.ajaxUrl, { action: 'gilda_new_color', nonce: config.nonce, name: name, hex: hex } )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$msg.text( ( res && res.data && res.data.message ) || 'Não deu para criar a cor.' );
					return;
				}
				var c = res.data;
				var $existing = $form.find( '[data-gilda-colors] input[value="' + c.id + '"]' );
				if ( $existing.length ) {
					$existing.prop( 'checked', true ).data( 'hex', c.hex ).siblings( '.gilda-swatch' ).css( 'background', c.hex );
				} else {
					$(
						'<label class="gilda-chip"><input type="checkbox" name="cores[]" value="' + esc( c.id ) + '" data-name="' + esc( c.name ) + '" data-hex="' + esc( c.hex ) + '" data-code="' + esc( c.code ) + '" checked /><span class="gilda-swatch" style="background:' + esc( c.hex ) + '" aria-hidden="true"></span>' + esc( c.name ) + '</label>'
					).insertBefore( $form.find( '[data-gilda-newcolor-open]' ) );
				}
				$new.prop( 'hidden', true ).find( '[data-gilda-newcolor-name]' ).val( '' );
				$msg.text( '' );
				renderStock();
			} )
			.fail( function ( xhr ) {
				$msg.text( ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) || 'Não deu para criar a cor.' );
			} );
	} );

	/* Peso e medidas pela categoria (só enquanto a dona não mexeu nelas) */
	var touched = $form.find( 'input[name="product_id"]' ).val() !== '0';
	$form.on( 'input', '[data-gilda-ship]', function () {
		touched = true;
	} );
	$form.on( 'change', '#gilda-peca-category', function () {
		var $opt = $( this ).find( ':selected' );
		var defaults = config.defaults || {};
		var own = defaults[ $opt.data( 'slug' ) ];
		var values = own || defaults[ '' ];
		if ( touched || ! values ) {
			return;
		}
		$form.find( '[data-gilda-ship]' ).each( function () {
			$( this ).val( String( values[ $( this ).data( 'gilda-ship' ) ] ).replace( '.', ',' ) );
		} );
		var help = $opt.val() === '0'
			? 'Escolha a categoria: peso e medidas vêm preenchidos com o padrão dela.'
			: own
				? 'Já vem preenchido pela categoria (' + $opt.text() + '). Ajuste se a embalagem for diferente: o frete é calculado com isso.'
				: 'A categoria ' + $opt.text() + ' ainda não tem peso e medidas padrão: estes são genéricos, ajuste para a embalagem desta peça. (O padrão da categoria se define em Categorias e cores.)';
		$form.find( '[data-gilda-ship-help]' ).text( help );
	} );

	/* Prévia do card */
	function money( raw ) {
		var text = String( raw || '' ).replace( /[^\d,.]/g, '' );
		if ( text.indexOf( ',' ) > -1 ) {
			text = text.replace( /\./g, '' ).replace( ',', '.' );
		}
		var value = parseFloat( text );
		return isNaN( value ) ? 'R$ —' : 'R$ ' + value.toFixed( 2 ).replace( '.', ',' ).replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
	}
	$form.on( 'input', '#gilda-peca-name', function () {
		$form.find( '[data-gilda-preview-name]' ).text( this.value || 'Nome da peça' );
	} );
	$form.on( 'input', '#gilda-peca-price', function () {
		$form.find( '[data-gilda-preview-price]' ).text( money( this.value ) );
	} );
	$( document ).on( 'gilda:photos', function () {
		var $first = $form.find( '.gilda-photos--cover .gilda-photo img' ).first();
		$form.find( '[data-gilda-preview-img]' ).attr( 'src', $first.attr( 'src' ) || '' ).prop( 'hidden', ! $first.length );
		$form.find( '[data-gilda-preview-empty]' ).prop( 'hidden', !! $first.length );
	} );

	refreshSkus();
} )( jQuery );
