( function ( window, document ) {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const forms = document.querySelectorAll( '.hd-admin-wrap form' );

		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				form.classList.add( 'is-submitting' );
			} );
		} );

		if ( window.WPHelpdeskAdmin ) {
			document.documentElement.setAttribute( 'data-hd-rest-base', window.WPHelpdeskAdmin.restBase || '' );

			window.wpHelpdeskRequest = function ( path, options ) {
				const requestOptions = options || {};
				requestOptions.headers = Object.assign( {}, requestOptions.headers || {}, {
					'X-WP-Nonce': window.WPHelpdeskAdmin.restNonce || '',
					'Content-Type': 'application/json'
				} );

				return window.fetch( ( window.WPHelpdeskAdmin.restBase || '' ) + path, requestOptions );
			};
		}
	} );
}( window, document ) );
