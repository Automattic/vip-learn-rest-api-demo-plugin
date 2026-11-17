<?php
/**
 * Limiting data exposure on a core endpoint.
 *
 * Core builds the /wp/v2/users response from an explicit allow-list of
 * fields, so user_pass and user_activation_key are never in it — filtering
 * them out guards against nothing. Core also keeps `email` out of the default
 * `view` context altogether.
 *
 * The fields worth auditing are the ones your own code, or another plugin,
 * adds through register_rest_field() or register_meta() with show_in_rest.
 * Those are returned exactly as registered, in whatever context they declare.
 *
 * The registration below stands in for that other plugin, so the example is
 * self-contained and testable.
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

// --- Stands in for another plugin exposing an internal identifier. ---
add_action( 'rest_api_init', function () {
    register_rest_field(
        'user',
        'internal_crm_id',
        array(
            'get_callback' => function ( $user ) {
                return get_user_meta( $user['id'], 'internal_crm_id', true );
            },
            'schema'       => array(
                'type'        => 'string',
                'description' => 'Internal CRM identifier.',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );
} );

// --- The part that matters: keep it out of responses for most callers. ---
function myplugin_filter_user_response( $response, $user, $request ) {
    // rest_prepare_user passes a WP_REST_Response object, not an array, so
    // the payload has to be read and written back through its data methods.
    $data = $response->get_data();

    if ( ! current_user_can( 'list_users' ) ) {
        unset( $data['internal_crm_id'] );
    }

    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_prepare_user', 'myplugin_filter_user_response', 10, 3 );
