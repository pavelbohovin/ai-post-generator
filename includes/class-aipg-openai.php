<?php
/**
 * AIPG_OpenAI Class
 *
 * Wrapper for OpenAI API interactions.
 *
 * @package AI_Post_Generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPG_OpenAI
 *
 * Handles communication with OpenAI API.
 */
class AIPG_OpenAI {

	/**
	 * OpenAI API endpoint.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Max tokens.
	 *
	 * @var int
	 */
	private $max_tokens;

	/**
	 * Temperature.
	 *
	 * @var float
	 */
	private $temperature;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key     = get_option( 'aipg_api_key', '' );
		$this->model       = get_option( 'aipg_model', 'gpt-4o-mini' );
		$this->max_tokens  = (int) get_option( 'aipg_max_tokens', 2000 );
		$this->temperature = (float) get_option( 'aipg_temperature', 0.7 );
	}

	/**
	 * Generate content using OpenAI API.
	 *
	 * @param array $prompt Prompt with 'system' and 'user' keys.
	 * @return array|WP_Error Response with 'content' and 'token_usage' or WP_Error on failure.
	 */
	public function generate_content( $prompt ) {
		// Validate API key.
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'OpenAI API key is not configured.', 'ai-post-generator' )
			);
		}

		// Build messages array.
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $prompt['system'],
			),
			array(
				'role'    => 'user',
				'content' => $prompt['user'],
			),
		);

		// Build request body.
		$body = array(
			'model'       => $this->model,
			'messages'    => $messages,
			'max_tokens'  => $this->max_tokens,
			'temperature' => $this->temperature,
		);

		// Build request headers.
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
		);

		// Make API request.
		$response = wp_remote_post(
			$this->api_url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				sprintf(
					// translators: %s is the error message.
					__( 'API request failed: %s', 'ai-post-generator' ),
					$response->get_error_message()
				)
			);
		}

		// Get response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		// Get response body.
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Check for API errors.
		if ( $response_code !== 200 ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error.', 'ai-post-generator' );
			return new WP_Error(
				'api_error',
				sprintf(
					// translators: %1$d is the response code, %2$s is the error message.
					__( 'OpenAI API error (code %1$d): %2$s', 'ai-post-generator' ),
					$response_code,
					$error_message
				)
			);
		}

		// Extract content and token usage.
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI API.', 'ai-post-generator' )
			);
		}

		$content     = $data['choices'][0]['message']['content'];
		$token_usage = isset( $data['usage']['total_tokens'] ) ? $data['usage']['total_tokens'] : 0;

		return array(
			'content'     => $content,
			'token_usage' => $token_usage,
		);
	}

	/**
	 * Test API connection.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection() {
		$prompt = array(
			'system' => 'You are a helpful assistant.',
			'user'   => 'Say "Connection successful" if you receive this message.',
		);

		$response = $this->generate_content( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get available models.
	 *
	 * @return array List of available models.
	 */
	public function get_available_models() {
		return array(
			'gpt-4o-mini'   => 'GPT-4o-mini (Recommended)',
			'gpt-4o'        => 'GPT-4o',
			'gpt-4-turbo'   => 'GPT-4 Turbo',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
		);
	}

	/**
	 * Estimate token count for a text string.
	 *
	 * @param string $text The text to estimate.
	 * @return int Estimated token count.
	 */
	public function estimate_tokens( $text ) {
		// Rough estimation: 1 token ≈ 4 characters.
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Calculate cost estimate based on model and tokens.
	 *
	 * @param int $tokens Token count.
	 * @return float Estimated cost in USD.
	 */
	public function estimate_cost( $tokens ) {
		// Pricing per 1M tokens (as of 2024 - update as needed).
		$pricing = array(
			'gpt-4o-mini'   => 0.15,  // $0.15 per 1M tokens.
			'gpt-4o'        => 2.50,  // $2.50 per 1M tokens.
			'gpt-4-turbo'   => 10.00, // $10.00 per 1M tokens.
			'gpt-3.5-turbo' => 0.50,  // $0.50 per 1M tokens.
		);

		$price_per_million = isset( $pricing[ $this->model ] ) ? $pricing[ $this->model ] : 1.00;

		return ( $tokens / 1000000 ) * $price_per_million;
	}
}


