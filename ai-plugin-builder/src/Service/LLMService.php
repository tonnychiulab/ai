<?php

namespace AIPluginBuilder\Service;

class LLMService {

	private $api_key;
	private $endpoint = 'https://api.openai.com/v1/chat/completions';

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Send prompt to LLM and get JSON response.
	 *
	 * @param string $system_prompt
	 * @param string $user_prompt
	 * @return array|WP_Error Parsed JSON array or error.
	 */
	public function generate_plugin_schema( string $system_prompt, string $user_prompt ) {
		$body = [
			'model'       => 'gpt-4o', // Or gpt-4-turbo, defaulting to high capability
			'messages'    => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $user_prompt,
				],
			],
			'temperature' => 0.1, // Low temp for deterministic code
			'response_format' => [ 'type' => 'json_object' ], // Force JSON mode
		];

		$response = wp_remote_post( $this->endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode( $body ),
			'timeout' => 120, // Long timeout for generation
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new \WP_Error( 'api_error', 'API Error: ' . wp_remote_retrieve_body( $response ) );
		}

		$body_content = json_decode( wp_remote_retrieve_body( $response ), true );
		$content      = $body_content['choices'][0]['message']['content'] ?? '';

		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_response', 'LLM returned empty response.' );
		}

		$decoded = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_parse_error', 'Failed to parse JSON: ' . json_last_error_msg() );
		}

		return $decoded;
	}

	/**
	 * Brainstorm a plugin idea.
	 *
	 * @return string|WP_Error The idea description.
	 */
	public function brainstorm_idea() {
		$body = [
			'model'       => 'gpt-4o',
			'messages'    => [
				[
					'role'    => 'system',
					'content' => 'You are a creative WordPress product manager. Generate a unique, practical, and interesting WordPress plugin idea description. Keep it concise (2-3 sentences). Do not include "Plugin Name:" prefix, just the description.',
				],
				[
					'role'    => 'user',
					'content' => 'Give me a cool plugin idea.',
				],
			],
			'temperature' => 0.9, // High creativity
		];

		$response = wp_remote_post( $this->endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode( $body ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body_content = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body_content['choices'][0]['message']['content'] ?? 'Could not generate idea.';
	}
}
