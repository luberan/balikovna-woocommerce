( function () {
	'use strict';

	var toggle = document.getElementById( 'balikovna-auto-order-status' );
	var mapping = document.getElementById( 'balikovna-status-mapping' );
	if ( ! toggle || ! mapping ) {
		return;
	}

	function updateMappingVisibility() {
		mapping.hidden = ! toggle.checked;
	}

	toggle.addEventListener( 'change', updateMappingVisibility );
	updateMappingVisibility();

	document.querySelectorAll( '[data-balikovna-mixed-polling]' ).forEach( function ( checkbox ) {
		checkbox.indeterminate = true;
		checkbox.addEventListener( 'change', function () {
			var groupKey = checkbox.getAttribute( 'data-balikovna-mixed-polling' );
			document.querySelectorAll( '[data-balikovna-mixed-marker]' ).forEach( function ( marker ) {
				if ( marker.getAttribute( 'data-balikovna-mixed-marker' ) === groupKey ) {
					marker.disabled = true;
				}
			} );
			checkbox.indeterminate = false;
		} );
	} );
}() );