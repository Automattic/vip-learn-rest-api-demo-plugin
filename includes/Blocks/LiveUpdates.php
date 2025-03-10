<?php
/**
 * Live Updates Block.
 *
 * @package LiveUpdates
 */

declare(strict_types=1);

namespace LiveUpdates\Blocks;

/**
 * Class LiveUpdates
 */
class LiveUpdates {
    /**
     * Initialize the block.
     */
    public function init(): void {
        add_action('init', [$this, 'register_block']);
    }

    /**
     * Register the block.
     */
    public function register_block(): void {
        // Register block script
        \wp_register_script(
            'live-updates-block',
            \plugins_url('build/live-updates-block/index.js', dirname(__DIR__)),
            [
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-server-side-render'
            ],
            \LiveUpdates\PLUGIN_VERSION,
            true
        );

        // Uncomment the post selector script registration
        \wp_register_script(
            'live-updates-post-selector',
            \plugins_url('build/post-selector/index.js', dirname(__DIR__)),
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'],
            \LiveUpdates\PLUGIN_VERSION,
            true
        );

        // Register block
        \register_block_type('live-updates/display', [
            'editor_script' => 'live-updates-block',
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'postId' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Register frontend script
        \wp_register_script(
            'live-updates-frontend',
            \plugins_url('build/live-updates-frontend/index.js', dirname(__DIR__)),
            [
                'wp-element',
                'wp-api-fetch',
                'wp-i18n'
            ],
            \LiveUpdates\PLUGIN_VERSION,
            true
        );

        // Localize script with API endpoint
        \wp_localize_script('live-updates-frontend', 'liveUpdatesData', [
            'nonce' => \wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Render block on frontend
     */
    public function render_block($attributes): string {
        $post_id = $attributes['postId'] ?? '';
        if (empty($post_id)) {
            return '';
        }

        \wp_enqueue_script('live-updates-frontend');
        
        return sprintf(
            '<div class="wp-block-live-updates-display" data-post-id="%s"></div>',
            \esc_attr($post_id)
        );
    }
} 