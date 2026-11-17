<?php
/**
 * Nonces and cookie-authenticated REST requests.
 *
 * For a logged-in user making an async request from the same site — the
 * cookie-authentication path — WordPress already does the CSRF check for you.
 * Core runs rest_cookie_check_errors() on the rest_authentication_errors
 * filter, before any route callback, and verifies the nonce against the
 * `wp_rest` action. Your job is to make sure the client sends the nonce core
 * expects, not to invent a second one.
 *
 * Two things to avoid:
 *
 * 1. Do not send your own nonce in a `_wpnonce` parameter. Core reads
 *    $_REQUEST['_wpnonce'] ahead of the X-WP-Nonce header and verifies it
 *    against `wp_rest`, so a nonce created for any other action fails that
 *    check and the request returns 403 rest_cookie_invalid_nonce before your
 *    callback runs.
 *
 * 2. Do not call check_admin_referer() in a REST callback. On failure it calls
 *    wp_nonce_ays(), which renders an HTML error page and exits, rather than
 *    returning a WP_Error the REST server can format.
 *
 * Requests authenticated by application password or OAuth are not cookie
 * requests, so no nonce is involved at all. In every case the
 * permission_callback is the security boundary.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

// Hand the front end the nonce that core will check.
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_user_logged_in() ) {
        return;
    }

    wp_enqueue_script(
        'myplugin-subscribe',
        plugins_url( 'assets/subscribe.js', __FILE__ ),
        array(),
        '1.0.0',
        true
    );

    wp_localize_script(
        'myplugin-subscribe',
        'myPluginSettings',
        array(
            'root'  => esc_url_raw( rest_url( 'custom/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        )
    );
} );

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/subscribe',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'myplugin_handle_subscribe',
            // The capability check is the security boundary. By the time this
            // runs, core has already verified the wp_rest nonce for
            // cookie-authenticated requests — and without a valid nonce the
            // request has been downgraded to an anonymous one, so this fails.
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        )
    );
} );

function myplugin_handle_subscribe( WP_REST_Request $request ) {
    update_user_meta( get_current_user_id(), 'myplugin_subscribed', true );

    return rest_ensure_response( array( 'subscribed' => true ) );
}
