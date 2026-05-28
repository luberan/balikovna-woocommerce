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

	function normalizePoint( data ) {
		if ( ! data || typeof data !== 'object' ) return null;
		var src = data.point || data.location || data.detail || data;
		var id = src.id || src.code || src.zipCode || src.PSC || src.postCode;
		var name = src.name || src.title || src.NAZEV;
		if ( ! id || ! name ) return null;
		return {
			id: String( id ),
			name: String( name ),
			street: String( src.street || src.address || src.ULICE || '' ),
			city: String( src.city || src.OBEC || '' ),
			zip: String( src.zip || src.psc || src.PSC || '' ),
			country: String( src.country || 'CZ' )
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
				point ? BalikovnaWCBlock.i18n.change : BalikovnaWCBlock.i18n.choose
			),
			point && el(
				'div',
				{ className: 'balikovna-selected' },
				el( 'strong', null, BalikovnaWCBlock.i18n.selected + ' ' ),
				point.name,
				point.street && el( wp.element.Fragment, null, el( 'br' ), point.street ),
				( point.zip || point.city ) && el( wp.element.Fragment, null, el( 'br' ), ( point.zip || '' ) + ' ' + ( point.city || '' ) )
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
