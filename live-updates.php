<?php
/**
 * Plugin Name: Live Updates
 * Plugin URI: https://learn.wpvip.com
 * Description: A live-blogging plugin demonstrating advanced use of the WordPress REST API
 * Version: 0.1.2
 * Author: VIP Learn
 * Author URI: https://learn.wpvip.com
 * License: GPL v2 or later
 * Text Domain: live-updates
 *
 * @package LiveUpdates
 */

declare(strict_types=1);

namespace LiveUpdates;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
const PLUGIN_VERSION = '0.1.1';

// Autoload classes
spl_autoload_register(function ($class) {
    // Check if the class is in our namespace
    if (strpos($class, __NAMESPACE__) !== 0) {
        return;
    }

    // Convert namespace to file path
    $class_path = str_replace(__NAMESPACE__ . '\\', '', $class);
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_path);
    $file = plugin_dir_path(__FILE__) . 'includes' . DIRECTORY_SEPARATOR . $class_path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize plugin
add_action('plugins_loaded', function () {
    Plugin::get_instance();
}); 