/* global wp, wc, BalikovnaWCBlock */
( function () {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = ( wp.data && wp.data.useSelect ) || function () { return null; };
	var SlotComponent = ( wc && wc.blocksCheckout && (
		wc.blocksCheckout.OrderShippingPackages ||
		wc.blocksCheckout.ExperimentalOrderShippingPackages
	) ) || null;
	var extensionCartUpdate = ( wc && wc.blocksCheckout && wc.blocksCheckout.extensionCartUpdate ) || null;
	var NS = 'balikovna-wc';
	var activeModal = null;

	function indexSelections( items ) {
		var indexed = {};
		if ( ! Array.isArray( items ) ) return indexed;
		items.forEach( function ( selection ) {
			if ( selection && selection.packageKey !== undefined && selection.rateId ) {
				indexed[ String( selection.packageKey ) ] = selection;
			}
		} );
		return indexed;
	}

	function normalizePoint( data ) {
		if ( ! data ) return null;
		if ( typeof data === 'string' ) {
			try { data = JSON.parse( data ); } catch ( error ) { return null; }
		}
		if ( typeof data !== 'object' || [ 'pickerResult', 'pickResult' ].indexOf( data.message ) === -1 || ! data.point ) return null;
		var point = data.point;
		if ( ! point.id || ! point.name ) return null;
		var city = String( point.municipality_name || point.city || '' ).trim();
		if ( point.municipality_district_name && point.municipality_district_name !== city ) {
			city += ' - ' + point.municipality_district_name;
		}
		return {
			id: String( point.id ),
			zip: String( point.zip || '' ),
			name: String( point.name ),
			street: String( point.street || point.address || '' ).trim(),
			city: city.trim(),
			country: String( point.country || 'CZ' ),
			type: String( point.type || '' ),
			subtype: String( point.subtype || '' ),
			lat: point.coor_y_wgs84 ? String( point.coor_y_wgs84 ) : '',
			lng: point.coor_x_wgs84 ? String( point.coor_x_wgs84 ) : '',
			phone: String( data.phone || '' ).trim()
		};
	}

	function createElement( tag, className, text ) {
		var element = document.createElement( tag );
		if ( className ) element.className = className;
		if ( text !== undefined ) element.textContent = text;
		return element;
	}

	function closeModal( restoreFocus, force ) {
		if ( ! activeModal ) return;
		if ( activeModal.saving && ! force ) return;
		var closing = activeModal;
		var trigger = closing.trigger;
		closing.wrap.remove();
		activeModal = null;
		restorePageInert( closing.inertElements );
		document.body.classList.remove( 'balikovna-modal-open' );
		document.removeEventListener( 'keydown', handleModalKeydown );
		if ( restoreFocus && trigger && document.contains( trigger ) ) trigger.focus();
	}

	function setPageInert( modalElement ) {
		return Array.prototype.map.call( document.body.children, function ( element ) {
			if ( element === modalElement ) return null;
			var state = { element: element, hadInert: element.hasAttribute( 'inert' ) };
			element.setAttribute( 'inert', '' );
			return state;
		} ).filter( Boolean );
	}

	function restorePageInert( elements ) {
		( elements || [] ).forEach( function ( state ) {
			if ( ! state.hadInert ) state.element.removeAttribute( 'inert' );
		} );
	}

	function handleModalKeydown( event ) {
		if ( ! activeModal ) return;
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeModal( true );
			return;
		}
		if ( event.key !== 'Tab' ) return;
		var focusable = Array.prototype.slice.call( activeModal.wrap.querySelectorAll( 'button:not([disabled]), iframe' ) );
		if ( ! focusable.length ) return;
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function openModal( context, trigger, onPoint ) {
		if ( ! context.service || ! context.service.widgetUrl ) return;
		if ( activeModal && activeModal.saving ) return;
		closeModal( false );
		var expectedOrigin;
		try { expectedOrigin = new URL( context.service.widgetUrl, window.location.href ).origin; } catch ( error ) { return; }

		var wrap = createElement( 'div', 'balikovna-modal' );
		var inner = createElement( 'div', 'balikovna-modal__inner' );
		var head = createElement( 'div', 'balikovna-modal__head' );
		var title = createElement( 'h3', '', BalikovnaWCBlock.i18n.title );
		var close = createElement( 'button', 'balikovna-modal__close', '×' );
		var message = createElement( 'div', 'balikovna-modal__message' );
		var body = createElement( 'div', 'balikovna-modal__body' );
		var startSentinel = createElement( 'span', 'balikovna-modal__sentinel' );
		var endSentinel = createElement( 'span', 'balikovna-modal__sentinel' );
		var iframe = document.createElement( 'iframe' );
		title.id = 'balikovna-block-dialog-title';
		close.type = 'button';
		close.setAttribute( 'aria-label', BalikovnaWCBlock.i18n.close );
		message.setAttribute( 'role', 'alert' );
		message.hidden = true;
		iframe.src = context.service.widgetUrl;
		iframe.title = BalikovnaWCBlock.i18n.title;
		iframe.setAttribute( 'allow', 'geolocation' );
		startSentinel.tabIndex = 0;
		endSentinel.tabIndex = 0;
		wrap.setAttribute( 'role', 'dialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		wrap.setAttribute( 'aria-labelledby', title.id );
		head.appendChild( title );
		head.appendChild( close );
		body.appendChild( iframe );
		inner.appendChild( head );
		inner.appendChild( message );
		inner.appendChild( body );
		wrap.appendChild( startSentinel );
		wrap.appendChild( inner );
		wrap.appendChild( endSentinel );
		document.body.appendChild( wrap );
		document.body.classList.add( 'balikovna-modal-open' );

		activeModal = {
			wrap: wrap,
			iframe: iframe,
			close: close,
			message: message,
			trigger: trigger,
			expectedOrigin: expectedOrigin,
			onPoint: onPoint,
			inertElements: setPageInert( wrap ),
			saving: false
		};
		wrap.addEventListener( 'click', function ( event ) {
			if ( event.target === wrap ) closeModal( true );
		} );
		close.addEventListener( 'click', function () { closeModal( true ); } );
		startSentinel.addEventListener( 'focus', function () { iframe.focus(); } );
		endSentinel.addEventListener( 'focus', function () {
			( activeModal && activeModal.saving ? iframe : close ).focus();
		} );
		document.addEventListener( 'keydown', handleModalKeydown );
		close.focus();
	}

	function errorMessage( error ) {
		if ( error && error.message ) return error.message;
		if ( error && error.data && error.data.message ) return error.data.message;
		return BalikovnaWCBlock.i18n.saveError;
	}

	window.addEventListener( 'message', function ( event ) {
		if ( BalikovnaWCBlock.debug ) {
			try { console.log( '[Balíkovna] postMessage origin=%s data=%o', event.origin, event.data ); } catch ( error ) {}
		}
		if ( ! activeModal || activeModal.saving ) return;
		if ( event.origin !== activeModal.expectedOrigin || event.source !== activeModal.iframe.contentWindow ) return;
		var point = normalizePoint( event.data );
		if ( ! point ) return;

		var modal = activeModal;
		modal.saving = true;
		modal.close.disabled = true;
		modal.message.hidden = false;
		modal.message.classList.remove( 'balikovna-error' );
		modal.message.textContent = BalikovnaWCBlock.i18n.saving;
		Promise.resolve().then( function () {
			return modal.onPoint( point );
		} ).then( function () {
			if ( activeModal === modal ) closeModal( true, true );
		} ).catch( function ( error ) {
			if ( activeModal !== modal ) return;
			modal.saving = false;
			modal.close.disabled = false;
			modal.message.classList.add( 'balikovna-error' );
			modal.message.textContent = errorMessage( error );
		} );
	}, false );

	function Picker() {
		var localState = useState( {} );
		var localSelections = localState[ 0 ];
		var setLocalSelections = localState[ 1 ];
		var errorState = useState( {} );
		var errors = errorState[ 0 ];
		var setErrors = errorState[ 1 ];
		var serviceIds = Object.keys( BalikovnaWCBlock.services || {} );

		var chosenServices = useSelect( function ( select ) {
			try {
				var store = select( 'wc/store/cart' );
				var packages = store && store.getShippingRates ? store.getShippingRates() : [];
				var chosen = [];
				packages.forEach( function ( shippingPackage, packageIndex ) {
					var packageKey = shippingPackage.package_id !== undefined ? String( shippingPackage.package_id ) : String( packageIndex );
					( shippingPackage.shipping_rates || [] ).forEach( function ( rate ) {
						if ( ! rate.selected || ! rate.rate_id ) return;
						var rateId = String( rate.rate_id );
						var serviceId = rateId.split( ':' )[ 0 ];
						if ( serviceIds.indexOf( serviceId ) !== -1 ) {
							chosen.push( {
								packageKey: packageKey,
								rateId: rateId,
								serviceId: serviceId,
								service: BalikovnaWCBlock.services[ serviceId ]
							} );
						}
					} );
				} );
				return chosen;
			} catch ( error ) {
				return [];
			}
		}, [] ) || [];

		var persistedSelections = useSelect( function ( select ) {
			try {
				var store = select( 'wc/store/cart' );
				var data = store && store.getCartData ? store.getCartData() : null;
				var extension = data && data.extensions ? data.extensions[ NS ] : null;
				return extension && Array.isArray( extension.selections ) ? extension.selections : [];
			} catch ( error ) {
				return [];
			}
		}, [] ) || [];

		var persistedSignature = JSON.stringify( persistedSelections );
		useEffect( function () {
			setLocalSelections( indexSelections( persistedSelections ) );
		}, [ persistedSignature ] );

		function savePoint( context, point ) {
			if ( ! extensionCartUpdate ) return Promise.reject( new Error( BalikovnaWCBlock.i18n.saveError ) );
			setErrors( function ( current ) {
				var next = Object.assign( {}, current );
				delete next[ context.packageKey ];
				return next;
			} );
			return extensionCartUpdate( {
				namespace: NS,
				data: {
					packageKey: context.packageKey,
					rateId: context.rateId,
					point: point
				}
			} ).then( function () {
				setLocalSelections( function ( current ) {
					var next = Object.assign( {}, current );
					next[ context.packageKey ] = {
						packageKey: context.packageKey,
						rateId: context.rateId,
						serviceId: context.serviceId,
						point: point
					};
					return next;
				} );
			} ).catch( function ( error ) {
				setErrors( function ( current ) {
					var next = Object.assign( {}, current );
					next[ context.packageKey ] = errorMessage( error );
					return next;
				} );
				throw error;
			} );
		}

		if ( ! chosenServices.length ) return null;

		return el(
			'div',
			{ className: 'balikovna-block-wrap' },
			chosenServices.map( function ( context ) {
				var selection = localSelections[ context.packageKey ];
				var point = selection && selection.rateId === context.rateId ? selection.point : null;
				return el(
					'div',
					{ className: 'balikovna-picker', key: context.packageKey + ':' + context.rateId },
					el( 'div', { className: 'balikovna-picker__label' }, context.service.label || context.serviceId ),
					el(
						'button',
						{
							type: 'button',
							className: 'wc-block-components-button balikovna-open',
							onClick: function ( event ) {
								openModal( context, event.currentTarget, function ( selectedPoint ) {
									return savePoint( context, selectedPoint );
								} );
							}
						},
						point ? BalikovnaWCBlock.i18n.change : BalikovnaWCBlock.i18n.choose
					),
					point && el(
						'div',
						{ className: 'balikovna-selected', 'aria-live': 'polite' },
						el( 'strong', null, BalikovnaWCBlock.i18n.selected + ' ' ),
						point.name,
						point.street && el( Fragment, null, el( 'br' ), point.street ),
						( point.zip || point.city ) && el( Fragment, null, el( 'br' ), ( point.zip || '' ) + ' ' + ( point.city || '' ) )
					),
					errors[ context.packageKey ] && el( 'div', { className: 'balikovna-error', role: 'alert' }, errors[ context.packageKey ] )
				);
			} )
		);
	}

	if ( SlotComponent ) {
		registerPlugin( 'balikovna-wc', {
			render: function () {
				return el( SlotComponent, null, el( Picker, null ) );
			},
			scope: 'woocommerce-checkout'
		} );
	}
} )();
