<?php
/**
 * Plugin Name: My Tiny Skills
 * Description: A lightweight tool to inspect enabled and disabled wp-config.php constants.
 * Version: 1.1.0
 * Author: Tonny & Antigravity
 * License: GPLv2 or later
 * Text Domain: my-tiny-skills
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'MTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MTS_URL', plugin_dir_url( __FILE__ ) );
define( 'MTS_VERSION', '1.1.0' );

// Load Admin Class
require_once MTS_PATH . 'includes/class-mts-admin.php';

// Initialize
function mts_init() {
	new MTS_Admin();
}
add_action( 'init', 'mts_init' );
