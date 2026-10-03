( function () {
	'use strict';

	var form = document.getElementById( 'mulino-import-form' );
	if ( ! form ) {
		return;
	}

	var i18n     = mulinoImport.i18n;
	var status   = document.getElementById( 'mulino-import-status' );
	var text     = status.querySelector( '.mulino-import-text' );
	var progress = status.querySelector( '.mulino-import-progress' );
	var errors   = status.querySelector( '.mulino-import-errors' );
	var done     = document.getElementById( 'mulino-import-done' );
	var button   = document.getElementById( 'mulino-import-start' );

	// Fill in "%1$d"-style placeholders in a translated string.
	function format( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;
		return str.replace( /%(?:(\d+)\$)?[sd]/g, function ( match, pos ) {
			return String( args[ pos ? pos - 1 : next++ ] );
		} );
	}

	function addError( message ) {
		var li = document.createElement( 'li' );
		li.textContent = message;
		errors.appendChild( li );
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		var listId = document.getElementById( 'mulino-import-list' ).value;
		var target = document.getElementById( 'mulino-import-target' ).value;
		var totals = { imported: 0, skipped: 0, problems: 0 };

		button.disabled  = true;
		status.hidden    = false;
		done.hidden      = true;
		progress.hidden  = false;
		progress.value   = 0;
		errors.innerHTML = '';
		text.textContent = format( i18n.progress, 0, 0 );

		function step( offset ) {
			var formData = new FormData();
			formData.append( 'action', 'mulino_sfl_import' );
			formData.append( 'nonce', mulinoImport.nonce );
			formData.append( 'list_id', listId );
			formData.append( 'offset', offset );
			formData.append( 'target', target );

			fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( res ) { return res.json(); } )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( json && json.data && json.data.message ? json.data.message : '' );
					}
					var data = json.data;
					totals.imported += data.counts.imported;
					totals.skipped  += data.counts.skipped;
					totals.problems += data.errors.length;
					data.errors.forEach( addError );

					progress.value   = data.total ? data.offset / data.total : 1;
					text.textContent = format( i18n.progress, data.offset, data.total );

					if ( data.done ) {
						text.textContent = format( i18n.finished, totals.imported, totals.skipped, totals.problems );
						progress.hidden  = true;
						button.disabled  = false;
						done.hidden      = false;
					} else {
						step( data.offset );
					}
				} )
				.catch( function ( err ) {
					text.textContent = i18n.failed;
					if ( err && err.message ) {
						addError( err.message );
					}
					progress.hidden = true;
					button.disabled = false;
				} );
		}

		step( 0 );
	} );
} )();
