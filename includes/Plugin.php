<?php
/**
 * Main plugin class
 *
 * @package LiveUpdates
 */

declare(strict_types=1);

namespace LiveUpdates;

/**
 * Main plugin class
 */
class Plugin {
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init(): void {
        // Register post type
        add_action('init', [$this, 'register_post_type']);
        
        // Initialize REST API
        $api = new Rest\Api();
        $api->init();

        // Register editor assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);

        // Initialize blocks
        $blocks = new Blocks\LiveUpdates();
        $blocks->init();

        // Initialize REST API Viewer
        $viewer = new Admin\ResponseViewer();
        $viewer->init();
    }

    /**
     * Register live update post type
     */
    public function register_post_type(): void {
        register_post_type('live-update', [
            'labels' => [
                'name'               => __('Live Updates', 'live-updates'),
                'singular_name'      => __('Live Update', 'live-updates'),
                'add_new'           => __('Add New', 'live-updates'),
                'add_new_item'      => __('Add New Live Update', 'live-updates'),
                'edit_item'         => __('Edit Live Update', 'live-updates'),
                'new_item'          => __('New Live Update', 'live-updates'),
                'view_item'         => __('View Live Update', 'live-updates'),
                'search_items'      => __('Search Live Updates', 'live-updates'),
                'not_found'         => __('No live updates found', 'live-updates'),
                'not_found_in_trash'=> __('No live updates found in Trash', 'live-updates'),
            ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'supports'            => ['title', 'editor'],
            'has_archive'         => false,
            'rewrite'             => ['slug' => 'live-update'],
            'show_in_rest'        => true,
            'hierarchical'        => true, // Enable parent-child relationship
        ]);
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets(): void {
        $screen = get_current_screen();
        
        // Only enqueue on live-update post type
        if ($screen && $screen->post_type !== 'live-update') {
            return;
        }

        wp_enqueue_script(
            'live-updates-post-selector',
            plugins_url('build/post-selector/index.js', dirname(__FILE__)),
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n',
            ],
            PLUGIN_VERSION,
            true
        );
    }
} 