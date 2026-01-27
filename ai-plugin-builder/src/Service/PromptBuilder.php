<?php

namespace AIPluginBuilder\Service;

class PromptBuilder {

	/**
	 * Build the system prompt by injecting SKILL.md content.
	 *
	 * @return string The complete system prompt.
	 */
	public function build_system_prompt(): string {
		$skill_content = $this->get_skill_content();

		$prompt = <<<EOT
You are a Senior WordPress Architect and Plugin Developer.
Your task is to generate a fully functional WordPress plugin based on the user's request.

CRITICAL: You must strictly adhere to the following Coding Standards and Security Rules.
These rules are non-negotiable.

=== CODING STANDARDS (SKILL.md) ===
{$skill_content}
===================================

OUTPUT FORMAT:
You must return ONLY a valid JSON object. Do not include markdown code labeling (```json).
The JSON structure must be:
{
    "plugin_slug": "example-plugin-slug",
    "plugin_name": "Example Plugin Name",
    "files": [
        {
            "path": "example-plugin.php",
            "content": "<?php ..."
        },
        {
            "path": "includes/class-example.php",
            "content": "<?php ..."
        }
    ]
}

REQUIREMENTS:
1. The 'path' in files is relative to the plugin root.
2. Ensure strict PSR-4 namespacing.
3. Use 'wp_verify_nonce' in all form handlers.
4. Security: defined('ABSPATH') || exit; in every PHP file.
5. CRITICAL: You MUST include a custom `spl_autoload_register` or `require_once` statements in the main plugin file to load all your class files. Do NOT assume composer or external autoloaders exist. The plugin must be standalone.
EOT;

		return $prompt;
	}

	/**
	 * Retrieve content from local SKILL.md files.
	 *
	 * @return string Combined SKILL content.
	 */
	private function get_skill_content(): string {
		// Define paths to potential SKILL.md files in the .agent directory
		// The path is relative to the WordPress installation usually, but here we scan the workspace known locations.
		// Assuming the User's workspace root containing .agent is two levels up from this plugin (plugins/ai-plugin-builder)
		// Wait, the workspace passed is C:\Users\Tonny\Desktop\everything-wp-antigravrty
		// The plugin is in C:\Users\Tonny\Desktop\everything-wp-antigravrty\ai-plugin-builder
		// So .agent is in ../.agent relative to the plugin root? 
		// Actually, in a real WP env, we can't easily access outside files.
		// BUT, for this specific task in this "Agent" environment, we read from the absolute path known to the Agent.
		
		// In a real production plugin, we might need a settings page to paste standards or package them.
		// For this prototype, we'll try to read from the known relative location if possible, 
		// or hardcode the standards if file access is restricted by php_admin_value open_basedir.
		
		// The plugin is in .../ai-plugin-builder/
		// The Service class is in .../ai-plugin-builder/src/Service/
		// So the standards are in .../ai-plugin-builder/standards/
		$plugin_root = dirname( __DIR__, 2 );
		
		// Attempting to read from internal standards directory:
		$candidates = [
			$plugin_root . '/standards/wp-knowledge-base.md',
			$plugin_root . '/standards/wp-quality-check.md',
		];

		$content = '';

		foreach ( $candidates as $file ) {
			if ( file_exists( $file ) ) {
				$content .= "\n\n--- Source: " . basename( dirname( $file ) ) . " ---\n";
				$content .= file_get_contents( $file );
			}
		}

		if ( empty( $content ) ) {
			return "Standard WordPress Coding Standards apply. Use Nonces, sanitize_*, methods.";
		}

		return $content;
	}
}
