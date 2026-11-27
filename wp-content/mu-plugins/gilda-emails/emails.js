/* Tela "E-mails" do painel: a prévia acompanha o que está sendo digitado (sem salvar). */
( () => {
	const form = document.querySelector( '.gilda-emails__form' );
	const frame = form && form.querySelector( '.gilda-emails__frame' );
	if ( ! form || ! frame ) {
		return;
	}
	const base = form.dataset.preview;
	let timer;
	const refresh = () => {
		const url = new URL( base, window.location.href );
		form.querySelectorAll( '[data-live]' ).forEach( ( field ) => {
			if ( field.value ) {
				url.searchParams.set( field.name, field.value );
			}
		} );
		frame.src = url.toString();
	};
	form.addEventListener( 'input', ( e ) => {
		if ( e.target.matches( '[data-live]' ) ) {
			clearTimeout( timer );
			timer = setTimeout( refresh, 600 );
		}
	} );
	// A altura da prévia acompanha o e-mail.
	frame.addEventListener( 'load', () => {
		try {
			frame.style.height = `${ Math.max( 480, frame.contentDocument.documentElement.scrollHeight + 8 ) }px`;
		} catch ( e ) { /* segue com a altura padrão */ }
	} );
} )();
