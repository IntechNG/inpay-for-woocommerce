jQuery( function( $ ) {
	'use strict';

	const params = window.wc_inpay_checkout_params || {};
	const button = document.getElementById( 'inpay-checkout-button' );

	if ( ! params.publicKey || ! button ) {
		return;
	}

	let sdkPromise;
	let isLaunching = false;

	button.addEventListener( 'click', function( event ) {
		event.preventDefault();
		startPayment();
	} );

	startPayment();

	function startPayment() {
		if ( typeof Promise === 'undefined' ) {
			loadPromisePolyfill().then( launchCheckout ).catch( function() {
				window.alert( 'Unable to start payment on this browser.' );
			} );
			return;
		}

		launchCheckout();
	}

	function launchCheckout() {
		if ( isLaunching ) {
			return;
		}

		if ( ! params.amount || Number( params.amount ) <= 0 ) {
			window.alert( params.amountError || 'Invalid payment amount.' );
			return;
		}

		isLaunching = true;
		setButtonLoading( true );

		loadSdk()
			.then( function( Checkout ) {
				const checkout = new Checkout();
				checkout.checkout( {
					apiKey: params.publicKey,
					amount: Number( params.amount ),
					email: params.email,
					currency: params.currency,
					firstName: params.firstName,
					lastName: params.lastName,
					metadata: params.metadata,
					onSuccess: function( reference ) {
						handleSuccess( reference );
					},
					onFailure: function( error ) {
						handleFailure( error );
					},
					onExpired: function() {
						handleExpired();
					},
					onError: function( error ) {
						handleError( error );
					},
				} );
			} )
			.catch( function( error ) {
				isLaunching = false;
				setButtonLoading( false );
				window.alert( ( error && error.message ) || 'Unable to start payment.' );
			} );
	}

	function loadSdk() {
		if ( window.iNPAY && window.iNPAY.InpayCheckout ) {
			return Promise.resolve( window.iNPAY.InpayCheckout );
		}

		if ( sdkPromise ) {
			return sdkPromise;
		}

		sdkPromise = new Promise( function( resolve, reject ) {
			const script = document.createElement( 'script' );
			script.src = 'https://js.inpaycheckout.com/v1/inline.js';
			script.onload = function() {
				if ( window.iNPAY && window.iNPAY.InpayCheckout ) {
					resolve( window.iNPAY.InpayCheckout );
				} else {
					reject( new Error( 'iNPAY checkout initialisation failed.' ) );
				}
			};
			script.onerror = function() {
				reject( new Error( 'Unable to load iNPAY checkout script.' ) );
			};
			document.head.appendChild( script );
		} );

		return sdkPromise;
	}

	function loadPromisePolyfill() {
		return new Promise( function( resolve, reject ) {
			const script = document.createElement( 'script' );
			script.src = 'https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js';
			script.onload = function() {
				if ( typeof Promise === 'undefined' ) {
					reject( new Error( 'Promise polyfill failed to load.' ) );
					return;
				}
				resolve();
			};
			script.onerror = function() {
				reject( new Error( 'Unable to load required browser features.' ) );
			};
			document.head.appendChild( script );
		} );
	}

	function handleSuccess( reference ) {
		const ref = typeof reference === 'object' && reference !== null ? reference.reference : reference;

		if ( ! ref ) {
			isLaunching = false;
			setButtonLoading( false );
			window.alert( 'Payment reference missing. Please try again.' );
			return;
		}

		blockPage();

		verifyPayment( ref )
			.then( function( response ) {
				if ( response && response.success && response.data && response.data.redirect ) {
					window.location.href = response.data.redirect;
					return;
				}

				if ( response && response.success ) {
					window.location.href = params.orderUrl;
					return;
				}

				throw new Error( ( response && response.data && response.data.message ) || 'Unable to confirm payment.' );
			} )
			.catch( function( error ) {
				unblockPage();
				isLaunching = false;
				setButtonLoading( false );
				window.alert( ( error && error.message ) || 'Unable to verify payment. Please contact support.' );
			} );
	}

	function handleFailure( error ) {
		isLaunching = false;
		setButtonLoading( false );
		window.alert( buildErrorMessage( 'Payment failed.', error ) );
	}

	function handleExpired() {
		isLaunching = false;
		setButtonLoading( false );
		window.alert( 'Payment session expired. Please try again.' );
	}

	function handleError( error ) {
		isLaunching = false;
		setButtonLoading( false );
		window.alert( buildErrorMessage( 'An error occurred while starting the payment.', error ) );
	}

	function setButtonLoading( loading ) {
		const button = document.getElementById( 'inpay-checkout-button' );
		if ( button ) {
			if ( loading ) {
				button.classList.add( 'loading' );
				button.disabled = true;
			} else {
				button.classList.remove( 'loading' );
				button.disabled = false;
			}
		}
	}

	function verifyPayment( reference ) {
		return fetch( params.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				reference: reference,
				order_id: params.orderId,
				nonce: params.nonce,
			} ),
		} ).then( function( response ) {
			if ( ! response.ok ) {
				return response.json().catch( function() {
					throw new Error( 'Unable to confirm payment.' );
				} ).then( function( data ) {
					const message = data && data.data && data.data.message ? data.data.message : data && data.message ? data.message : null;
					throw new Error( message || 'Unable to confirm payment.' );
				} );
			}

			return response.json();
		} );
	}

	function buildErrorMessage( prefix, error ) {
		const detail = error && error.message ? error.message : null;
		return detail ? prefix + ' ' + detail : prefix;
	}

	function blockPage() {
		$( 'body' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
			css: {
				cursor: 'wait',
			},
		} );
	}

	function unblockPage() {
		$( 'body' ).unblock();
	}
} );
