/**
 * Sends the nonce that core verifies for cookie-authenticated requests.
 *
 * In the block editor or wp-admin, @wordpress/api-fetch adds this header for
 * you from wpApiSettings.nonce, so prefer apiFetch() where it is available.
 */
( function () {
	const { root, nonce } = window.myPluginSettings || {};
	const button = document.querySelector( '#myplugin-subscribe' );

	if ( ! button || ! root ) {
		return;
	}

	button.addEventListener( 'click', async () => {
		const response = await fetch( root + 'subscribe', {
			method: 'POST',
			// Send the auth cookie with the request.
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				// The header core looks for. Without it the request is treated
				// as anonymous and the permission callback refuses it.
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify( { source: 'footer' } ),
		} );

		if ( ! response.ok ) {
			button.textContent = 'Could not subscribe';
			return;
		}

		button.textContent = 'Subscribed';
	} );
} )();
