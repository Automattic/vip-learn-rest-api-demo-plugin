<?php
/**
 * Loads the securing-api-data examples.
 *
 * These files register live routes and filters, so they are opt-in. Define
 * VIP_LEARN_LOAD_EXAMPLES in wp-config.php (or a test mu-plugin) to load them:
 *
 *     define( 'VIP_LEARN_LOAD_EXAMPLES', true );
 *
 * @package LiveUpdates\Examples
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'VIP_LEARN_LOAD_EXAMPLES' ) || ! VIP_LEARN_LOAD_EXAMPLES ) {
    return;
}

foreach ( glob( __DIR__ . '/0*.php' ) as $example ) {
    require_once $example;
}
