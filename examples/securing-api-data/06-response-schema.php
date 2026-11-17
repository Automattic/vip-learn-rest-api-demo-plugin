<?php
/**
 * Describing a response with JSON Schema.
 *
 * The `schema` key is a ROUTE option, not an endpoint option. When the third
 * argument to register_rest_route() has `callback` at its top level, core
 * treats the whole array as a single endpoint definition and wraps it, which
 * carries `schema` into the endpoint with it — where nothing reads it, so the
 * schema is silently dropped from the OPTIONS response and the namespace
 * index.
 *
 * To register it, nest the endpoint definition in its own array and leave
 * `schema` as its sibling, as below.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/user-schema/(?P<id>\d+)',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'myplugin_get_user_data',
                'permission_callback' => 'myplugin_can_access_user_data',
                'args'                => array(
                    'id' => array(
                        'required' => true,
                        'type'     => 'integer',
                        'minimum'  => 1,
                    ),
                ),
            ),
            'schema' => 'myplugin_get_user_data_schema',
        )
    );
} );

function myplugin_get_user_data_schema() {
    return array(
        '$schema'    => 'http://json-schema.org/draft-04/schema#',
        'title'      => 'user',
        'type'       => 'object',
        'properties' => array(
            'id' => array(
                'type'        => 'integer',
                'context'     => array( 'view' ),
                'description' => 'User ID.',
            ),
            'name' => array(
                'type'        => 'string',
                'context'     => array( 'view' ),
                'description' => 'Display name of the user.',
            ),
            'email' => array(
                'type'        => 'string',
                'format'      => 'email',
                'context'     => array( 'view' ),
                'description' => 'Email address of the user.',
            ),
        ),
        'required' => array( 'id', 'name', 'email' ),
    );
}
