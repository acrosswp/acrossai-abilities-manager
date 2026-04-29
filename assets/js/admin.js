/* global jQuery */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Dismiss notices on click.
		$( document ).on( 'click', '.notice.is-dismissible .notice-dismiss', function () {
			$( this ).closest( '.notice' ).fadeOut( 200 );
		} );
	} );
} )( jQuery );
