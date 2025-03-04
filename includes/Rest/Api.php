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
} 