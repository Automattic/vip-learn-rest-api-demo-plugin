<?php
/**
 * Example of using the live_updates_query_args filter
 * 
 * This example shows how to:
 * 1. Modify the number of posts per page
 * 2. Add custom meta query conditions
 * 3. Change the sort order
 * 
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

namespace LiveUpdates\Examples;

/**
 * Modify the query arguments for live updates
 *
 * @param array $query_args The current query arguments
 * @param array $original_args The original arguments passed to the query
 * @return array Modified query arguments
 */
function modify_live_updates_query($query_args, $original_args) {
    // Example 1: Change posts per page based on time of day
    $hour = (int) current_time('G');
    if ($hour >= 20 || $hour <= 6) {
        // Reduce frequency during off-peak hours
        $query_args['posts_per_page'] = 10;
    }

    // Example 2: Add meta query for featured updates
    $query_args['meta_query'] = [
        [
            'key' => 'is_featured',
            'value' => '1',
            'compare' => '=',
        ],
    ];

    // Example 3: Change sort order for specific post parents
    if (isset($original_args['post_parent']) && $original_args['post_parent'] === 123) {
        $query_args['orderby'] = 'menu_order';
        $query_args['order'] = 'ASC';
    }

    return $query_args;
}

// Add the filter
add_filter('live_updates_query_args', __NAMESPACE__ . '\modify_live_updates_query', 10, 2);

/**
 * Example of removing specific post types from query
 */
function exclude_post_types($query_args, $original_args) {
    // Get allowed post types from options or define directly
    $allowed_post_types = get_option('live_updates_allowed_types', ['post', 'page']);
    
    if (!in_array($query_args['post_type'], $allowed_post_types, true)) {
        $query_args['post_type'] = 'post'; // fallback to default
    }

    return $query_args;
}

// Add another filter with lower priority
add_filter('live_updates_query_args', __NAMESPACE__ . '\exclude_post_types', 5, 2); 