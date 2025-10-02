( function() {
	const wpI18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
	const wpElement = window.wp && window.wp.element ? window.wp.element : null;
	const wc = window.wc || {};
	const registry = wc.wcBlocksRegistry || null;
	const settingsStore = wc.wcSettings || null;

	if ( ! wpElement || ! registry || ! settingsStore ) {
		return;
	}

	const { __ } = wpI18n || { __( text ) { return text; } };
	const { createElement: el, Fragment } = wpElement;
	const { registerPaymentMethod } = registry;
	const settings = settingsStore.getSetting( 'inpay_checkout_data', {} );

	const title = settings.title || __( 'iNPAY Checkout', 'inpay-checkout' );
	const description = settings.description || '';
	const isEnabled = typeof settings.isEnabled === 'undefined' ? true : settings.isEnabled;

	const Icon = () => el( 'img', {
		src: settings.logoUrl,
		alt: title,
		style: { height: '20px', width: '20px', marginRight: '8px' },
	} );

	const Label = () => el( 'span', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, settings.logoUrl ? el( Icon ) : null, el( 'span', null, title ) );

	const Content = () => el( Fragment, null, description ? el( 'p', null, description ) : null );

	registerPaymentMethod( {
		name: 'inpay_checkout',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: () => Boolean( isEnabled ),
		ariaLabel: title,
		supports: {
			showSavedCards: false,
			showSaveOption: false,
			features: settings.supports || [],
		},
	} );
} )();
