<?php
/**
 * Escaping output data in the right place.
 *
 * Escaping is contextual: you escape data at the point where it is rendered,
 * for the context it is rendered into. A REST API response is not that point.
 * WordPress encodes the response with wp_json_encode(), which already makes
 * the payload safe as JSON, so running esc_html() over values on their way out
 * of an endpoint corrupts them.
 *
 * The exception is a field that intentionally carries markup: filter that
 * through wp_kses_post() so the response can only contain allowed tags.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/user-profile/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'myplugin_get_user_profile',
            'permission_callback' => 'myplugin_can_access_user_data',
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                ),
            ),
        )
    );
} );

function myplugin_get_user_profile( WP_REST_Request $request ) {
    $user = get_user_by( 'ID', $request['id'] );

    if ( ! $user ) {
        return new WP_Error(
            'user_not_found',
            __( 'User not found.', 'live-updates' ),
            array( 'status' => 404 )
        );
    }

    // No HTML escaping here. The response is encoded as JSON by WordPress,
    // and esc_html() would entity-encode the values: a display name of
    // "O'Brien" would reach the client as "O&#039;Brien".
    return rest_ensure_response(
        array(
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            // A field that intentionally carries markup is filtered to an
            // allowed set of tags before it leaves the API.
            'bio'   => wp_kses_post( get_user_meta( $user->ID, 'description', true ) ),
        )
    );
}
