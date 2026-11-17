<?php
/**
 * Verifies the securing-api-data examples against a running WordPress.
 *
 * Requires VIP_LEARN_LOAD_EXAMPLES to be defined, so that the examples in this
 * directory are loaded. Run from the site root:
 *
 *     wp eval-file wp-content/plugins/vip-learn-rest-api-demo-plugin/examples/securing-api-data/verify-examples.php
 *
 * Exits non-zero if any check fails.
 *
 * Note: no declare(strict_types=1) here — `wp eval-file` eval()s the file, and
 * a declare() has to be the first statement in a script.
 *
 * @package LiveUpdates\Examples
 */

if ( ! defined( 'VIP_LEARN_LOAD_EXAMPLES' ) || ! VIP_LEARN_LOAD_EXAMPLES ) {
    echo "VIP_LEARN_LOAD_EXAMPLES is not enabled; the examples are not loaded.\n";
    exit( 1 );
}

$failures = 0;

/**
 * @param string $label  What is being checked.
 * @param bool   $passed Whether it held.
 * @param string $detail Observed value, shown either way.
 */
$check = function ( string $label, bool $passed, string $detail ) use ( &$failures ): void {
    if ( ! $passed ) {
        ++$failures;
    }
    printf( "%-4s %-52s %s\n", $passed ? 'PASS' : 'FAIL', $label, $detail );
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];

$subscribers = get_users( array( 'role' => 'subscriber', 'number' => 1 ) );
$subscriber_id = $subscribers
    ? $subscribers[0]->ID
    : wp_insert_user( array(
        'user_login' => 'vip-learn-subscriber',
        'user_pass'  => wp_generate_password(),
        'role'       => 'subscriber',
    ) );

update_user_meta( $admin->ID, 'internal_crm_id', 'CRM-99' );

// --- 01: validating input -------------------------------------------------
wp_set_current_user( $admin->ID );

$response = rest_do_request( new WP_REST_Request( 'GET', '/custom/v1/user/' . $admin->ID ) );
$check(
    '01 known user returns 200',
    200 === $response->get_status(),
    (string) wp_json_encode( $response->get_data() )
);

$response = rest_do_request( new WP_REST_Request( 'GET', '/custom/v1/user/99999999' ) );
$check( '01 unknown user returns 404', 404 === $response->get_status(), 'status ' . $response->get_status() );

wp_set_current_user( $subscriber_id );
$response = rest_do_request( new WP_REST_Request( 'GET', '/custom/v1/user/' . $admin->ID ) );
$check(
    '01 subscriber is refused',
    in_array( $response->get_status(), array( 401, 403 ), true ),
    'status ' . $response->get_status()
);

// --- 02: closure validate_callback ---------------------------------------
wp_set_current_user( $admin->ID );
try {
    $response = rest_do_request( new WP_REST_Request( 'GET', '/custom/v1/user-closure/' . $admin->ID ) );
    $check( '02 closure callback does not throw', 200 === $response->get_status(), 'status ' . $response->get_status() );
} catch ( \Throwable $e ) {
    $check( '02 closure callback does not throw', false, get_class( $e ) . ': ' . $e->getMessage() );
}

// --- 03: sanitizing input -------------------------------------------------
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'POST', '/custom/v1/posts' );
$request->set_param( 'title', 'Anonymous' );
$request->set_param( 'content', 'Anonymous' );
$response = rest_do_request( $request );
$check(
    '03 anonymous write is refused',
    in_array( $response->get_status(), array( 401, 403 ), true ),
    'status ' . $response->get_status()
);

wp_set_current_user( $admin->ID );
$request = new WP_REST_Request( 'POST', '/custom/v1/posts' );
$request->set_param( 'title', '<script>alert(1)</script>Hello' );
$request->set_param( 'content', '<p>Keep this</p><script>alert(2)</script>' );
$response = rest_do_request( $request );
$post_id  = $response->get_data()['post_id'] ?? 0;
$post     = $post_id ? get_post( $post_id ) : null;

$check( '03 authorised write succeeds', $post instanceof WP_Post, 'post_id ' . (string) $post_id );
$check(
    '03 title has no markup',
    $post && false === strpos( $post->post_title, '<' ),
    $post ? $post->post_title : '-'
);
$check(
    '03 content keeps allowed tags, drops script',
    $post && false !== strpos( $post->post_content, '<p>' ) && false === strpos( $post->post_content, '<script' ),
    $post ? trim( $post->post_content ) : '-'
);

// --- 04: output escaping --------------------------------------------------
update_user_meta( $admin->ID, 'description', '<p>Bio</p><script>alert(3)</script>' );
$response = rest_do_request( new WP_REST_Request( 'GET', '/custom/v1/user-profile/' . $admin->ID ) );
$data     = (array) $response->get_data();

$check(
    '04 name is not entity-encoded by the endpoint',
    isset( $data['name'] ) && $data['name'] === get_user_by( 'ID', $admin->ID )->display_name,
    (string) ( $data['name'] ?? '-' )
);
$check(
    '04 bio keeps allowed tags, drops script',
    isset( $data['bio'] ) && false !== strpos( $data['bio'], '<p>' ) && false === strpos( $data['bio'], '<script' ),
    (string) ( $data['bio'] ?? '-' )
);

// --- 05: limiting data exposure ------------------------------------------
wp_set_current_user( $admin->ID );
$data = (array) rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users/' . $admin->ID ) )->get_data();
$check(
    '05 privileged caller still sees internal_crm_id',
    array_key_exists( 'internal_crm_id', $data ),
    (string) ( $data['internal_crm_id'] ?? 'absent' )
);

wp_set_current_user( $subscriber_id );
$data = (array) rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users/' . $admin->ID ) )->get_data();
$check(
    '05 unprivileged caller does not',
    ! array_key_exists( 'internal_crm_id', $data ),
    array_key_exists( 'internal_crm_id', $data ) ? 'still present' : 'removed'
);

// --- 06: response schema --------------------------------------------------
wp_set_current_user( $admin->ID );
$data = (array) rest_do_request(
    new WP_REST_Request( 'OPTIONS', '/custom/v1/user-schema/' . $admin->ID )
)->get_data();

$check(
    '06 schema is exposed on OPTIONS',
    isset( $data['schema']['title'] ) && 'user' === $data['schema']['title'],
    isset( $data['schema'] ) ? 'title=' . $data['schema']['title'] : 'schema missing'
);

// --- 07: nonces on the cookie-authentication path -------------------------
wp_set_current_user( 0 );
$response = rest_do_request( new WP_REST_Request( 'POST', '/custom/v1/subscribe' ) );
$check(
    '07 anonymous subscribe is refused',
    in_array( $response->get_status(), array( 401, 403 ), true ),
    'status ' . $response->get_status()
);

wp_set_current_user( $admin->ID );
$response = rest_do_request( new WP_REST_Request( 'POST', '/custom/v1/subscribe' ) );
$check( '07 logged-in subscribe succeeds', 200 === $response->get_status(),
    (string) wp_json_encode( $response->get_data() ) );

// Why the example does not roll its own nonce: core checks _wpnonce itself,
// against the wp_rest action, before any callback runs.
$GLOBALS['wp_rest_auth_cookie'] = true;

$_REQUEST['_wpnonce'] = wp_create_nonce( 'some_custom_action' );
$result = rest_cookie_check_errors( null );
$check(
    '07 a custom nonce in _wpnonce is rejected by core',
    is_wp_error( $result ) && 'rest_cookie_invalid_nonce' === $result->get_error_code(),
    is_wp_error( $result ) ? $result->get_error_code() : 'allowed through'
);

$_REQUEST['_wpnonce'] = wp_create_nonce( 'wp_rest' );
$result = rest_cookie_check_errors( null );
$check(
    '07 a wp_rest nonce passes',
    ! is_wp_error( $result ),
    is_wp_error( $result ) ? $result->get_error_code() : 'allowed through'
);

unset( $_REQUEST['_wpnonce'], $GLOBALS['wp_rest_auth_cookie'] );

printf( "\n%d check(s) failed.\n", $failures );
exit( $failures > 0 ? 1 : 0 );
