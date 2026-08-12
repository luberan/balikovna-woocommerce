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
}() );