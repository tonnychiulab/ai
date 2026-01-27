<?php

namespace AIPluginBuilder\Controller;

class AdminController {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_post_generate_plugin', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_ai_plugin_brainstorm', [ $this, 'handle_brainstorm_ajax' ] );
	}

	// ... (register_menu, render_page functions remain same)

	public function handle_brainstorm_ajax() {
		check_ajax_referer( 'ai_plugin_brainstorm_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Get API Key
		$api_key = '';
		if ( defined( 'AI_PLUGIN_BUILDER_KEY' ) ) {
			$api_key = AI_PLUGIN_BUILDER_KEY;
		} elseif ( ! empty( $_POST['api_key'] ) ) {
			$api_key = sanitize_text_field( $_POST['api_key'] );
		}

		if ( empty( $api_key ) ) {
			wp_send_json_success( 'Mock Idea: A plugin that automatically adds cat GIFs to every H2 tag on your site.' );
		}

		$llm = new \AIPluginBuilder\Service\LLMService( $api_key );
		$idea_or_error = $llm->brainstorm_idea();

		if ( is_wp_error( $idea_or_error ) ) {
			wp_send_json_error( $idea_or_error->get_error_message() );
		}

		wp_send_json_success( $idea_or_error );
	}

	public function register_menu() {
		add_menu_page(
			'AI Plugin Builder',
			'AI Builder',
			'manage_options',
			'ai-plugin-builder',
			[ $this, 'render_page' ],
			'dashicons-superhero',
			60
		);
	}

	public function render_page() {
		require_once dirname( __DIR__, 2 ) . '/templates/admin-page.php';
	}

	public function handle_form_submission() {
		// Security check
		if ( ! isset( $_POST['ai_plugin_builder_nonce'] ) || ! wp_verify_nonce( $_POST['ai_plugin_builder_nonce'], 'ai_plugin_builder_action' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$prompt  = sanitize_textarea_field( $_POST['plugin_prompt'] ?? '' );
		
		// Priority: 1. POST input 2. Constant in wp-config.php
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		if ( empty( $api_key ) && defined( 'AI_PLUGIN_BUILDER_KEY' ) ) {
			$api_key = AI_PLUGIN_BUILDER_KEY;
		}

		if ( empty( $prompt ) ) {
			wp_die( 'Prompt is required.' );
		}

		if ( empty( $api_key ) ) {
			// Mock Mode Fallback if API key is missing
			$generated_slug = 'mock-plugin-' . time();
			wp_redirect( admin_url( 'admin.php?page=ai-plugin-builder&status=success&slug=' . $generated_slug . '&mode=mock' ) );
			exit;
		}

		// 1. Build System Prompt (with SKILL.md)
		$prompt_builder = new \AIPluginBuilder\Service\PromptBuilder();
		$system_prompt  = $prompt_builder->build_system_prompt();

		// 2. Call LLM
		$llm = new \AIPluginBuilder\Service\LLMService( $api_key );
		$schema_or_error = $llm->generate_plugin_schema( $system_prompt, $prompt );

		if ( is_wp_error( $schema_or_error ) ) {
			wp_die( $schema_or_error->get_error_message() );
		}

		// 3. Generate Files
		$generator = new \AIPluginBuilder\Service\GeneratorService();
		$result    = $generator->create_plugin( $schema_or_error );

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}
		
		// 4. Log to History
		$this->log_generation_history( $schema_or_error['plugin_name'], $result['slug'], $prompt );

		// Redirect back
		wp_redirect( admin_url( 'admin.php?page=ai-plugin-builder&status=success&slug=' . $result['slug'] ) );
		exit;
	}

	private function log_generation_history( $name, $slug, $prompt ) {
		$history = get_option( 'ai_plugin_builder_history', [] );
		if ( ! is_array( $history ) ) {
			$history = [];
		}

		// Calculate Next ID
		$next_id = 1;
		if ( ! empty( $history ) ) {
			$ids = array_column( $history, 'id' );
			if ( ! empty( $ids ) ) {
				$next_id = max( $ids ) + 1;
			}
		}

		$new_entry = [
			'id'         => $next_id,
			'name'       => sanitize_text_field( $name ),
			'slug'       => sanitize_text_field( $slug ),
			'prompt'     => sanitize_textarea_field( $prompt ),
			'created_at' => current_time( 'mysql' ),
		];

		// Prepend to start of array
		array_unshift( $history, $new_entry );

		update_option( 'ai_plugin_builder_history', $history );
	}
}
