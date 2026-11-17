<?php
/**
 * Custom validation with a closure.
 *
 * WordPress passes three arguments to a validate_callback: the value, the
 * WP_REST_Request object, and the parameter name. Passing the name of a
 * built-in PHP function such as 'is_numeric' throws an ArgumentCountError on
 * PHP 8 — internal functions reject surplus arguments — and the endpoint
 * returns a 500. Custom logic belongs in a closure that accepts all three.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/user-closure/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'myplugin_get_user_data',
            'permission_callback' => 'myplugin_can_access_user_data',
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ( $value, $request, $param ) {
                        if ( ! is_numeric( $value ) ) {
                            return new WP_Error(
                                'rest_invalid_param',
                                __( 'The user ID must be numeric.', 'live-updates' ),
                                array( 'status' => 400 )
                            );
                        }

                        return true;
                    },
                ),
            ),
        )
    );
} );
