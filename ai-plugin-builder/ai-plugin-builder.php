<?php
/**
 * Plugin Name: AI Plugin Builder
 * Description: Generates WordPress plugins from natural language using a Meta-Plugin architecture.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: ai-plugin-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PSR-4 Autoloader
spl_autoload_register( function ( $class ) {
	$prefix   = 'AIPluginBuilder\\';
	$base_dir = __DIR__ . '/src/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Initialize Plugin
add_action( 'plugins_loaded', function() {
    new \AIPluginBuilder\Controller\AdminController();
} );
