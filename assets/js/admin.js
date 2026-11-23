( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.nsl-copy-btn' );
		if ( ! button ) {
			return;
		}
		var input = document.getElementById( button.getAttribute( 'data-target' ) );
		if ( ! input ) {
			return;
		}
		var done = function () {
			var text = button.textContent;
			button.textContent = ( window.nslAdmin && window.nslAdmin.copied ) || 'OK';
			setTimeout( function () {
				button.textContent = text;
			}, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( input.value ).then( done );
		} else {
			input.select();
			document.execCommand( 'copy' );
			done();
		}
	} );
} )();
