<?php
/**
 * Validating input data on a custom REST API route.
 *
 * Accompanies the VIP Learn lesson "Best Practices for Securing API Data
 * Input and Output".
 *
 * Declaring `'type' => 'integer'` is enough for WordPress to validate the
 * parameter against the schema with rest_validate_request_arg() before the
 * callback runs, and `'sanitize_callback' => 'absint'` casts it to a
 * positive integer.
 *
 * Functions here are deliberately global and prefixed rather than namespaced,
 * so that the code matches the lesson snippet line for line.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/user/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'myplugin_get_user_data',
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

function myplugin_get_user_data( WP_REST_Request $request ) {
    $user = get_user_by( 'ID', $request['id'] );

    if ( ! $user ) {
        return new WP_Error(
            'invalid_user',
            __( 'Invalid user ID.', 'live-updates' ),
            array( 'status' => 404 )
        );
    }

    return rest_ensure_response(
        array(
            'id'   => $user->ID,
            'name' => $user->display_name,
        )
    );
}

function myplugin_can_access_user_data( $request ) {
    return current_user_can( 'list_users' );
}
