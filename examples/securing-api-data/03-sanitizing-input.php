<?php
/**
 * Sanitizing input data on a write endpoint.
 *
 * Two details matter here. wp_insert_post() returns 0 on failure unless its
 * second argument is true, so an is_wp_error() check against the default
 * return value never fires. And because the arguments are registered with a
 * sanitize_callback, get_param() already returns the sanitized value —
 * sanitizing a second time inside the callback is redundant.
 *
 * A write endpoint without a permission_callback is open to any visitor.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

add_action( 'rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/posts',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'myplugin_create_custom_post',
            'permission_callback' => static function () {
                return current_user_can( 'publish_posts' );
            },
            'args'                => array(
                'title'   => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'content' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ),
            ),
        )
    );
} );

function myplugin_create_custom_post( WP_REST_Request $request ) {
    // Passing true as the second argument makes wp_insert_post() return a
    // WP_Error object on failure. Without it the function returns 0, and an
    // is_wp_error() check would never fire.
    $post_id = wp_insert_post(
        array(
            'post_type'    => 'post',
            'post_title'   => $request->get_param( 'title' ),
            'post_content' => $request->get_param( 'content' ),
            'post_status'  => 'publish',
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error(
            'post_creation_failed',
            $post_id->get_error_message(),
            array( 'status' => 500 )
        );
    }

    return rest_ensure_response( array( 'post_id' => $post_id ) );
}
