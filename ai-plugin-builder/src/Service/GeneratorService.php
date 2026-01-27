<?php

namespace AIPluginBuilder\Service;

class GeneratorService {

	/**
	 * Generate plugin files from schema.
	 *
	 * @param array $schema The JSON schema from LLM.
	 * @return array|WP_Error Success result or error.
	 */
	public function create_plugin( array $schema ) {
		if ( empty( $schema['plugin_slug'] ) || empty( $schema['files'] ) ) {
			return new \WP_Error( 'invalid_schema', 'Invalid JSON Schema missing slug or files.' );
		}

		// Sanitize slug
		$slug = sanitize_title( $schema['plugin_slug'] );
		
		// Target Directory: wp-content/plugins/{slug}
		$plugins_dir = WP_PLUGIN_DIR;
		$target_dir  = $plugins_dir . '/' . $slug;

		if ( file_exists( $target_dir ) ) {
			return new \WP_Error( 'plugin_exists', "Plugin '$slug' already exists." );
		}

		// Create Root Directory
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new \WP_Error( 'fs_error', 'Could not create directory: ' . $target_dir );
		}

		// Iterate and create files
		foreach ( $schema['files'] as $file ) {
			$path    = sanitize_text_field( $file['path'] ); // Relative path
			$content = $file['content'];

			// Security: Prevent breaking out of directory
			if ( strpos( $path, '..' ) !== false ) {
				continue;
			}

			$full_path = $target_dir . '/' . $path;
			$dir_name  = dirname( $full_path );

			if ( ! file_exists( $dir_name ) ) {
				wp_mkdir_p( $dir_name );
			}

			// Syntax Check (Basic) - In production we might want to pipe to `php -l`
			// For now, simply write.
			$written = file_put_contents( $full_path, $content );
			if ( false === $written ) {
				return new \WP_Error( 'write_error', "Failed to write file: $path" );
			}
		}

		return [
			'success' => true,
			'slug'    => $slug,
			'path'    => $target_dir,
		];
	}
}
