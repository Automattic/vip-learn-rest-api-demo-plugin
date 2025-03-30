<?php
/**
 * REST API Response Viewer admin page
 *
 * @package LiveUpdates
 */

declare(strict_types=1);

namespace LiveUpdates\Admin;

/**
 * Class ResponseViewer
 */
class ResponseViewer {
    /**
     * Initialize the admin page
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add menu page
     */
    public function add_menu_page(): void {
        add_menu_page(
            __('REST API Viewer', 'live-updates'),
            __('REST API Viewer', 'live-updates'),
            'manage_options',
            'rest-api-viewer',
            [$this, 'render_page'],
            'dashicons-rest-api',
            30
        );
    }

    /**
     * Enqueue assets
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_assets(string $hook_suffix): void {
        if ('toplevel_page_rest-api-viewer' !== $hook_suffix) {
            return;
        }

        wp_enqueue_script(
            'rest-api-viewer',
            plugins_url('build/rest-api-viewer/index.js', dirname(__DIR__)),
            ['wp-api-fetch', 'wp-components', 'wp-element'],
            \LiveUpdates\PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'rest-api-viewer',
            plugins_url('build/rest-api-viewer/style.css', dirname(__DIR__)),
            ['wp-components'],
            \LiveUpdates\PLUGIN_VERSION
        );
    }

    /**
     * Render the admin page
     */
    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('REST API Response Viewer', 'live-updates'); ?></h1>
            <div id="rest-api-viewer-root"></div>
        </div>
        <?php
    }
} 