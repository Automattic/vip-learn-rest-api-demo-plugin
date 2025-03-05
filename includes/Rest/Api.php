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
	 * Initialize the REST API.
	 */
	public function init(): void {
		add_action('rest_api_init', [$this, 'register_routes']);
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
			'posts_per_page' => 20,
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
			'no_found_rows'  => false,
		];

		return new \WP_Query(wp_parse_args($args, $default_args));
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

		list($username, $password) = explode(':', $credentials, 2);
		
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
} 