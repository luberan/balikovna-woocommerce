/* global wp, wc, BalikovnaWCBlock */
( function () {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var SlotComponent =
		( wc && wc.blocksCheckout && (
			wc.blocksCheckout.OrderShippingPackages ||
			wc.blocksCheckout.ExperimentalOrderShippingPackages
		) ) || null;
	var extensionCartUpdate =
		( wc && wc.blocksCheckout && wc.blocksCheckout.extensionCartUpdate ) || null;
	var useSelect = ( wp.data && wp.data.useSelect ) || function () { return null; };

	var NS = 'balikovna-wc';

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	// Oficiální payload widgetu ČP (dle podporaobchodu@cpost.cz, 2026-05):
	// { message: 'pickResult', id: 'B15033', point: { id, zip, name, street,
	//   municipality_name, municipality_district_name, country, type, subtype,
	//   coor_x_wgs84, coor_y_wgs84, ... } }
	function normalizePoint( data ) {
		if ( ! data ) return null;
		if ( typeof data === 'string' ) {
			try { data = JSON.parse( data ); } catch ( e ) { return null; }
		}
		if ( typeof data !== 'object' ) return null;
		var p = data.point || ( data.id && data.name ? data : null );
		if ( ! p || ! p.id || ! p.name ) return null;
		var city = String( p.municipality_name || p.city || '' ).trim();
		if ( p.municipality_district_name && p.municipality_district_name !== city ) {
			city = city + ' - ' + p.municipality_district_name;
		}
		return {
			id: String( p.id ),
			zip: String( p.zip || '' ),
			name: String( p.name ),
			street: String( p.street || p.address || '' ).trim(),
			city: city.trim(),
			country: String( p.country || 'CZ' ),
			type: String( p.type || '' ),
			subtype: String( p.subtype || '' ),
			lat: p.coor_y_wgs84 ? String( p.coor_y_wgs84 ) : '',
			lng: p.coor_x_wgs84 ? String( p.coor_x_wgs84 ) : ''
		};
	}

	function Picker() {
		var state = useState( null );
		var point = state[ 0 ];
		var setPoint = state[ 1 ];

		var serviceIds = Object.keys( BalikovnaWCBlock.services || {} );
		var chosenService = useSelect( function ( select ) {
			try {
				var store = select( 'wc/store/cart' );
				var rates = store && store.getShippingRates ? store.getShippingRates() : [];
				for ( var i = 0; i < rates.length; i++ ) {
					var shipping_rates = rates[ i ].shipping_rates || [];
					for ( var j = 0; j < shipping_rates.length; j++ ) {
						var r = shipping_rates[ j ];
						if ( r.selected && r.rate_id ) {
							for ( var k = 0; k < serviceIds.length; k++ ) {
								if ( r.rate_id.indexOf( serviceIds[ k ] ) === 0 ) {
									return serviceIds[ k ];
								}
							}
						}
					}
				}
			} catch ( e ) {}
			return null;
		}, [] );

		// Persistovaný bod ze Store API (extensions.balikovna-wc.point) přežije re-mount komponenty.
		var persistedPoint = useSelect( function ( select ) {
			try {
				var store = select( 'wc/store/cart' );
				var data = store && store.getCartData ? store.getCartData() : null;
				var ext = data && data.extensions ? data.extensions[ NS ] : null;
				return ( ext && ext.point && ext.point.id ) ? ext.point : null;
			} catch ( e ) { return null; }
		}, [] );

		var effectivePoint = point || persistedPoint;

		useEffect( function () {
			function onMessage( ev ) {
				if ( ! document.getElementById( 'balikovna-block-iframe' ) ) return;
				if ( BalikovnaWCBlock.debug ) {
					try { console.log( '[Balíkovna] postMessage origin=%s data=%o', ev.origin, ev.data ); } catch ( e ) {}
				}
				var p = normalizePoint( ev.data );
				if ( ! p ) return;
				if ( BalikovnaWCBlock.debug ) console.log( '[Balíkovna] normalized point: %o', p );
				setPoint( p );
				closeModal();
				if ( extensionCartUpdate ) {
					extensionCartUpdate( {
						namespace: NS,
						data: { point: p }
					} );
				}
			}
			window.addEventListener( 'message', onMessage );
			return function () {
				window.removeEventListener( 'message', onMessage );
			};
		}, [] );

		function openModal() {
			var svc = chosenService && BalikovnaWCBlock.services[ chosenService ];
			if ( ! svc ) return;
			closeModal();
			var wrap = document.createElement( 'div' );
			wrap.className = 'balikovna-modal';
			wrap.setAttribute( 'role', 'dialog' );
			wrap.innerHTML =
				'<div class="balikovna-modal__inner">' +
				'<div class="balikovna-modal__head"><h3>' + escapeHtml( BalikovnaWCBlock.i18n.title ) + '</h3>' +
				'<button type="button" class="balikovna-modal__close" aria-label="' + escapeHtml( BalikovnaWCBlock.i18n.close ) + '">&times;</button></div>' +
				'<div class="balikovna-modal__body"><iframe id="balikovna-block-iframe" src="' + svc.widgetUrl + '" allow="geolocation"></iframe></div>' +
				'</div>';
			document.body.appendChild( wrap );
			wrap.addEventListener( 'click', function ( e ) {
				if ( e.target === wrap ) closeModal();
			} );
			wrap.querySelector( '.balikovna-modal__close' ).addEventListener( 'click', closeModal );
		}

		function closeModal() {
			var existing = document.querySelector( '.balikovna-modal' );
			if ( existing ) existing.remove();
		}

		if ( ! chosenService ) {
			return null;
		}

		return el(
			'div',
			{ className: 'balikovna-block-wrap' },
			el(
				'button',
				{ type: 'button', className: 'wc-block-components-button', onClick: openModal },
				effectivePoint ? BalikovnaWCBlock.i18n.change : BalikovnaWCBlock.i18n.choose
			),
			effectivePoint && el(
				'div',
				{ className: 'balikovna-selected' },
				el( 'strong', null, BalikovnaWCBlock.i18n.selected + ' ' ),
				effectivePoint.name,
				effectivePoint.street && el( wp.element.Fragment, null, el( 'br' ), effectivePoint.street ),
				( effectivePoint.zip || effectivePoint.city ) && el( wp.element.Fragment, null, el( 'br' ), ( effectivePoint.zip || '' ) + ' ' + ( effectivePoint.city || '' ) )
			)
		);
	}

	if ( SlotComponent ) {
		registerPlugin( 'balikovna-wc', {
			render: function () {
				return el(
					SlotComponent,
					null,
					el( Picker, null )
				);
			},
			scope: 'woocommerce-checkout'
		} );
	}
} )();
