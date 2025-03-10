<?php
/**
 * REST API Handler.
 *
 * @package LiveUpdates
 */

declare(strict_types=1);

namespace LiveUpdates\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Api
 */
class Api {
	/**
	 * Namespace for the REST API.
	 */
	const API_NAMESPACE = 'liveupdates/v1';

	/**
	 * Option name for JWT secret.
	 */
	const JWT_SECRET_OPTION = 'live_updates_jwt_secret';

	/**
	 * JWT expiration time in seconds (24 hours).
	 */
	const JWT_EXPIRATION = 86400;

	/**
	 * JWT algorithm.
	 */
	const JWT_ALGORITHM = 'HS256';

	/**
	 * Rate limit requests per minute.
	 */
	const RATE_LIMIT_REQUESTS = 30;

	/**
	 * Rate limit window in seconds.
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Cache group for rate limiting.
	 */
	const RATE_LIMIT_CACHE_GROUP = 'live_updates_rate_limit';

	/**
	 * Initialize the REST API.
	 */
	public function init(): void {
		// Ensure we have a JWT secret
		$this->ensure_jwt_secret();
		
		// Add rate limiting
		add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
		
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * Ensure JWT secret exists.
	 */
	private function ensure_jwt_secret(): void {
		if (!\get_option(self::JWT_SECRET_OPTION)) {
			\add_option(self::JWT_SECRET_OPTION, \wp_generate_password(32, true, true));
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/posts/(?P<post_id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_live_updates'],
				'permission_callback' => '__return_true',
				'args'               => [
					'post_id' => [
						'required'          => true,
						'validate_callback' => function($param) {
							return is_numeric($param) && get_post($param);
						},
					],
				],
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/posts/(?P<post_id>\d+)/(?P<timestamp>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_live_updates_since'],
				'permission_callback' => '__return_true',
				'args'               => [
					'post_id'   => [
						'required'          => true,
						'validate_callback' => function($param) {
							return is_numeric($param) && get_post($param);
						},
					],
					'timestamp' => [
						'required'          => true,
						'validate_callback' => function($param) {
							return is_numeric($param);
						},
					],
				],
			]
		);

		\register_rest_route(
			self::API_NAMESPACE,
			'/posts/(?P<parent_id>\d+)/create',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'create_live_update'],
				'permission_callback' => [$this, 'check_basic_auth'],
				'args'               => [
					'parent_id' => [
						'required'          => true,
						'validate_callback' => function($param) {
							return is_numeric($param) && \get_post($param);
						},
					],
					'title' => [
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'content' => [
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
				],
			]
		);

		// JWT token endpoint
		\register_rest_route(
			self::API_NAMESPACE,
			'/token',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'generate_jwt_token'],
				'permission_callback' => [$this, 'check_basic_auth'],
			]
		);

		// Protected endpoint example using JWT
		\register_rest_route(
			self::API_NAMESPACE,
			'/posts/(?P<parent_id>\d+)/create-with-jwt',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'create_live_update'],
				'permission_callback' => [$this, 'verify_jwt_auth'],
				'args'               => [
					'parent_id' => [
						'required'          => true,
						'validate_callback' => function($param) {
							return is_numeric($param) && \get_post($param);
						},
					],
					'title' => [
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'content' => [
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
				],
			]
		);

		// JWT verification endpoint
		\register_rest_route(
			self::API_NAMESPACE,
			'/verify-jwt',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'verify_jwt_token'],
				'permission_callback' => [$this, 'verify_jwt_auth'],
			]
		);

		// User profile endpoint
		\register_rest_route(
			self::API_NAMESPACE,
			'/user-profile',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_user_profile'],
				'permission_callback' => [$this, 'verify_admin_jwt_auth'],
				'schema'             => [$this, 'get_user_profile_schema'],
			]
		);
	}

	/**
	 * Get live updates for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_live_updates(WP_REST_Request $request) {
		$post_id = (int) $request->get_param('post_id');
		
		$query = $this->get_live_updates_query([
			'post_parent' => $post_id,
		]);

		return $this->prepare_response($query);
	}

	/**
	 * Get live updates for a post since a timestamp.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_live_updates_since(WP_REST_Request $request) {
		$post_id = (int) $request->get_param('post_id');
		$timestamp = (int) $request->get_param('timestamp');

		$query = $this->get_live_updates_query([
			'post_parent' => $post_id,
			'date_query'  => [
				[
					'after'     => date('c', $timestamp),
					'inclusive' => false,
				],
			],
		]);

		return $this->prepare_response($query);
	}

	/**
	 * Get live updates query.
	 *
	 * @param array $args Query arguments.
	 * @return \WP_Query Query object.
	 */
	private function get_live_updates_query(array $args): \WP_Query {
		$default_args = [
			'post_type'      => 'live-update',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_parent'    => 0,  // Default parent ID
			'posts_per_page' => 20,
		];

		// Merge with custom args, allowing post_parent to be overridden
		$query_args = wp_parse_args($args, $default_args);

		return new \WP_Query($query_args);
	}

	/**
	 * Prepare response.
	 *
	 * @param \WP_Query $query Query object.
	 * @return WP_REST_Response Response object.
	 */
	private function prepare_response(\WP_Query $query): WP_REST_Response {
		$posts = array_map(function($post) {
			return [
				'id' => $post->ID,
				'date' => \get_post_datetime($post)->format('c'),
				'modified' => \get_post_modified_time('c', true, $post),
				'title' => [
					'rendered' => \get_the_title($post),
				],
				'content' => [
					'rendered' => \apply_filters('the_content', $post->post_content),
				],
			];
		}, $query->posts);

		$response = new WP_REST_Response($posts);
		
		// Add server timestamp to response
		$response->header('X-Server-Time', time());
		
		// Add cache headers
		$response->header('Cache-Control', 'public, max-age=300');

		// Add pagination headers
		$response->header('X-WP-Total', $query->found_posts);
		$response->header('X-WP-TotalPages', ceil($query->found_posts / $query->query_vars['posts_per_page']));

		return $response;
	}

	/**
	 * Check Basic Authentication using Application Passwords.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error if not.
	 */
	public function check_basic_auth($request) {
		// Get authentication header
		$auth_header = $request->get_header('authorization');
		if (!$auth_header || strpos($auth_header, 'Basic ') !== 0) {
			return new \WP_Error(
				'rest_forbidden',
				__('Missing authentication header.', 'live-updates'),
				['status' => 401]
			);
		}

		// Decode credentials
		$credentials = base64_decode(substr($auth_header, 6));
		if (!$credentials || strpos($credentials, ':') === false) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid authentication header.', 'live-updates'),
				['status' => 401]
			);
		}

		list($raw_username, $raw_password) = explode(':', $credentials, 2);
		
		// Sanitize credentials
		$username = \sanitize_user($raw_username);
		$password = \sanitize_text_field($raw_password);

		// Verify credentials are not empty after sanitization
		if (empty($username) || empty($password)) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid credentials format.', 'live-updates'),
				['status' => 401]
			);
		}
		
		// Get user by username or email
		$user = \get_user_by('login', $username);
		if (!$user) {
			$user = \get_user_by('email', $username);
		}

		if (!$user) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid username.', 'live-updates'),
				['status' => 401]
			);
		}

		// Verify the application password using WordPress core function
		$authenticated = \wp_authenticate_application_password(null, $username, $password);
		if (\is_wp_error($authenticated)) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid application password.', 'live-updates'),
				['status' => 401]
			);
		}

		// Check if user has permission to create posts
		if (!\user_can($user, 'publish_posts')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to create posts.', 'live-updates'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Create a live update.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function create_live_update($request) {
		$parent_id = (int) $request->get_param('parent_id');
		$title = $request->get_param('title');
		$content = $request->get_param('content');

		$post_data = [
			'post_type'    => 'live-update',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
			'post_parent'  => $parent_id,
		];

		$post_id = \wp_insert_post($post_data, true);
		if (\is_wp_error($post_id)) {
			return $post_id;
		}

		$post = \get_post($post_id);
		$response = $this->prepare_single_response($post);
		$response->set_status(201);

		return $response;
	}

	/**
	 * Prepare single post response.
	 *
	 * @param \WP_Post $post Post object.
	 * @return WP_REST_Response Response object.
	 */
	private function prepare_single_response(\WP_Post $post): WP_REST_Response {
		$data = [
			'id' => $post->ID,
			'date' => \get_post_datetime($post)->format('c'),
			'modified' => \get_post_modified_time('c', true, $post),
			'title' => [
				'rendered' => \get_the_title($post),
			],
			'content' => [
				'rendered' => \apply_filters('the_content', $post->post_content),
			],
		];

		return new WP_REST_Response($data, 201);
	}

	/**
	 * Generate JWT token after successful basic auth.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function generate_jwt_token($request) {
		$user = \wp_get_current_user();
		if (!$user || !$user->ID) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid authentication.', 'live-updates'),
				['status' => 401]
			);
		}

		// Generate token
		$issued_at = time();
		$expiration = $issued_at + self::JWT_EXPIRATION;
		
		$payload = [
			'iss' => \get_site_url(),
			'iat' => $issued_at,
			'exp' => $expiration,
			'user_id' => $user->ID,
		];

		$token = $this->generate_token($payload);

		return new \WP_REST_Response([
			'token' => $token,
			'expires_in' => self::JWT_EXPIRATION,
		]);
	}

	/**
	 * Verify JWT authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error if not.
	 */
	public function verify_jwt_auth($request) {
		$auth_header = $request->get_header('authorization');
		if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
			return new \WP_Error(
				'rest_forbidden',
				__('Missing JWT token.', 'live-updates'),
				['status' => 401]
			);
		}

		$token = substr($auth_header, 7);
		$payload = $this->verify_token($token);
		
		if (\is_wp_error($payload)) {
			return $payload;
		}

		// Set current user
		\wp_set_current_user($payload['user_id']);
		
		return true;
	}

	/**
	 * Generate JWT token.
	 *
	 * @param array $payload Data to encode in the token.
	 * @return string Generated token.
	 */
	private function generate_token(array $payload): string {
		$secret = \get_option(self::JWT_SECRET_OPTION);
		
		$header = [
			'typ' => 'JWT',
			'alg' => self::JWT_ALGORITHM
		];

		$base64_header = $this->base64url_encode(json_encode($header));
		$base64_payload = $this->base64url_encode(json_encode($payload));
		
		// Use the defined algorithm
		$signature = $this->generate_signature("$base64_header.$base64_payload", $secret);
		$base64_signature = $this->base64url_encode($signature);

		return "$base64_header.$base64_payload.$base64_signature";
	}

	/**
	 * Generate signature based on algorithm.
	 *
	 * @param string $data Data to sign.
	 * @param string $secret Secret key.
	 * @return string Signature.
	 */
	private function generate_signature(string $data, string $secret): string {
		switch (self::JWT_ALGORITHM) {
			case 'HS256':
				return hash_hmac('sha256', $data, $secret, true);
			// Add support for other algorithms here if needed
			default:
				throw new \RuntimeException('Unsupported JWT algorithm');
		}
	}

	/**
	 * Verify JWT token.
	 *
	 * @param string $token JWT token.
	 * @return array|WP_Error Payload if valid, WP_Error if not.
	 */
	private function verify_token(string $token) {
		$token_parts = explode('.', $token);
		if (count($token_parts) !== 3) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid token format.', 'live-updates'),
				['status' => 401]
			);
		}

		list($base64_header, $base64_payload, $base64_signature) = $token_parts;

		$payload = json_decode($this->base64url_decode($base64_payload), true);
		if (!$payload || !isset($payload['user_id'])) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid token payload.', 'live-updates'),
				['status' => 401]
			);
		}

		// Check expiration
		if (isset($payload['exp']) && $payload['exp'] < time()) {
			return new \WP_Error(
				'rest_forbidden',
				__('Token has expired.', 'live-updates'),
				['status' => 401]
			);
		}

		// Verify signature using global secret and defined algorithm
		$secret = \get_option(self::JWT_SECRET_OPTION);
		$signature = $this->base64url_decode($base64_signature);
		$expected_signature = $this->generate_signature("$base64_header.$base64_payload", $secret);
		
		if (!hash_equals($signature, $expected_signature)) {
			return new \WP_Error(
				'rest_forbidden',
				__('Invalid token signature.', 'live-updates'),
				['status' => 401]
			);
		}

		return $payload;
	}

	/**
	 * Base64URL encode.
	 *
	 * @param string $data Data to encode.
	 * @return string Encoded data.
	 */
	private function base64url_encode(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Base64URL decode.
	 *
	 * @param string $data Data to decode.
	 * @return string Decoded data.
	 */
	private function base64url_decode(string $data): string {
		return base64_decode(strtr($data, '-_', '+/'));
	}

	/**
	 * Verify JWT token and return user information.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function verify_jwt_token($request): \WP_REST_Response {
		// At this point, verify_jwt_auth has already validated the token
		// and set the current user
		$user = \wp_get_current_user();
		
		return new \WP_REST_Response([
			'code'    => 'jwt_valid',
			'message' => __('Token is valid.', 'live-updates'),
			'data'    => [
				'user_id'    => $user->ID,
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
			],
		], 200);
	}

	/**
	 * Get user profile data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_user_profile($request): \WP_REST_Response {
		$user = \wp_get_current_user();
		
		$profile_data = [
			'id'            => (int) $user->ID,
			'username'      => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'url'          => $user->user_url,
			'registered'   => \mysql_to_rfc3339($user->user_registered),
			'roles'        => $user->roles,
			'capabilities' => array_keys(array_filter($user->allcaps)),
			'avatar_url'   => \get_avatar_url($user->ID),
		];

		return new \WP_REST_Response($profile_data, 200);
	}

	/**
	 * Get user profile schema.
	 *
	 * @return array Schema array.
	 */
	public function get_user_profile_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'user-profile',
			'type'      => 'object',
			'properties' => [
				'id' => [
					'description' => __('Unique identifier for the user.', 'live-updates'),
					'type'        => 'integer',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'username' => [
					'description' => __('Login name for the user.', 'live-updates'),
					'type'        => 'string',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'email' => [
					'description' => __('Email address of the user.', 'live-updates'),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'display_name' => [
					'description' => __('Display name of the user.', 'live-updates'),
					'type'        => 'string',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'first_name' => [
					'description' => __('First name of the user.', 'live-updates'),
					'type'        => 'string',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'last_name' => [
					'description' => __('Last name of the user.', 'live-updates'),
					'type'        => 'string',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'url' => [
					'description' => __('URL of the user.', 'live-updates'),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'registered' => [
					'description' => __('Registration date for the user.', 'live-updates'),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => ['view'],
					'readonly'    => true,
				],
				'roles' => [
					'description' => __('Roles assigned to the user.', 'live-updates'),
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
					],
					'context'     => ['view'],
					'readonly'    => true,
				],
				'capabilities' => [
					'description' => __('All capabilities the user has.', 'live-updates'),
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
					],
					'context'     => ['view'],
					'readonly'    => true,
				],
				'avatar_url' => [
					'description' => __('URL of the user\'s avatar image.', 'live-updates'),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => ['view'],
					'readonly'    => true,
				],
			],
		];
	}

	/**
	 * Verify JWT auth and admin capabilities.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error if not.
	 */
	public function verify_admin_jwt_auth($request) {
		// First verify JWT auth
		$jwt_result = $this->verify_jwt_auth($request);
		if (\is_wp_error($jwt_result)) {
			return $jwt_result;
		}

		// Then check for manage_options capability
		if (!\current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have sufficient permissions to access this endpoint.', 'live-updates'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Check rate limit for requests.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed|WP_Error
	 */
	public function check_rate_limit($result, $server, $request) {
		// Only rate limit our namespace
		if (strpos($request->get_route(), '/' . self::API_NAMESPACE) !== 0) {
			return $result;
		}

		$ip = $this->get_client_ip();
		$cache_key = 'rate_limit_' . md5($ip);
		
		// Get current requests count and timestamp
		$rate_data = wp_cache_get($cache_key, self::RATE_LIMIT_CACHE_GROUP);
		$current_time = time();
		
		if (false === $rate_data) {
			// First request from this IP
			$rate_data = [
				'count' => 1,
				'timestamp' => $current_time,
			];
		} else {
			// Check if we're in a new window
			if (($current_time - $rate_data['timestamp']) > self::RATE_LIMIT_WINDOW) {
				$rate_data = [
					'count' => 1,
					'timestamp' => $current_time,
				];
			} else {
				// Increment request count
				$rate_data['count']++;
			}
		}

		// Store updated rate data
		wp_cache_set(
			$cache_key,
			$rate_data,
			self::RATE_LIMIT_CACHE_GROUP,
			self::RATE_LIMIT_WINDOW
		);

		// Check if rate limit exceeded
		if ($rate_data['count'] > self::RATE_LIMIT_REQUESTS) {
			$retry_after = $rate_data['timestamp'] + self::RATE_LIMIT_WINDOW - $current_time;
			
			return new \WP_Error(
				'rest_rate_limited',
				__('Too many requests, please try again later.', 'live-updates'),
				[
					'status' => 429,
					'headers' => [
						'Retry-After' => $retry_after,
						'X-RateLimit-Limit' => self::RATE_LIMIT_REQUESTS,
						'X-RateLimit-Remaining' => 0,
						'X-RateLimit-Reset' => $rate_data['timestamp'] + self::RATE_LIMIT_WINDOW,
					],
				]
			);
		}

		// Add rate limit headers to successful responses
		add_filter('rest_post_dispatch', function($response) use ($rate_data) {
			if ($response instanceof \WP_REST_Response) {
				$response->header('X-RateLimit-Limit', self::RATE_LIMIT_REQUESTS);
				$response->header(
					'X-RateLimit-Remaining',
					max(0, self::RATE_LIMIT_REQUESTS - $rate_data['count'])
				);
				$response->header(
					'X-RateLimit-Reset',
					$rate_data['timestamp'] + self::RATE_LIMIT_WINDOW
				);
			}
			return $response;
		});

		return $result;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';
		
		// Check for CloudFlare IP
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		// Check for proxy headers
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']))[0];
		}
		// Fallback to REMOTE_ADDR
		elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return (string) $ip;
	}
} 