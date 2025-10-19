<?php
/**
 * AIPG_Generator Class
 *
 * Handles post generation logic using OpenAI API.
 *
 * @package AI_Post_Generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPG_Generator
 *
 * Manages the generation of WordPress posts using AI.
 */
class AIPG_Generator {

	/**
	 * OpenAI API wrapper instance.
	 *
	 * @var AIPG_OpenAI
	 */
	private $openai;

	/**
	 * Utilities instance.
	 *
	 * @var AIPG_Utils
	 */
	private $utils;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->openai = new AIPG_OpenAI();
		$this->utils  = new AIPG_Utils();
	}

	/**
	 * Generate multiple posts on a given topic.
	 *
	 * @param string $topic     The topic to generate posts about.
	 * @param int    $count     Number of posts to generate.
	 * @param string $post_type Post type (default 'post').
	 * @param int    $category  Category ID (optional).
	 * @return array|WP_Error Array with results or WP_Error on failure.
	 */
	public function generate_posts( $topic, $count, $post_type = 'post', $category = 0 ) {
		// Validate API key.
		$api_key = get_option( 'aipg_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'OpenAI API key is not set. Please configure it in settings.', 'ai-post-generator' )
			);
		}

		$posts_created = 0;
		$total_tokens  = 0;
		$errors        = array();

		// Generate each post.
		for ( $i = 1; $i <= $count; $i++ ) {
			$result = $this->generate_single_post( $topic, $post_type, $category, $i );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf(
					// translators: %1$d is the post number, %2$s is the error message.
					__( 'Post %1$d: %2$s', 'ai-post-generator' ),
					$i,
					$result->get_error_message()
				);
				continue;
			}

			$posts_created++;
			$total_tokens += $result['token_usage'];

			// Small delay to avoid rate limiting.
			usleep( 500000 ); // 0.5 seconds.
		}

		// Log the generation.
		$this->utils->log_generation( $topic, $posts_created, $total_tokens );

		// Return results.
		if ( $posts_created === 0 ) {
			return new WP_Error(
				'generation_failed',
				__( 'Failed to generate any posts. ', 'ai-post-generator' ) . implode( ' ', $errors )
			);
		}

		return array(
			'posts_count' => $posts_created,
			'token_usage' => $total_tokens,
			'errors'      => $errors,
		);
	}

	/**
	 * Generate a single post.
	 *
	 * @param string $topic     The topic to generate a post about.
	 * @param string $post_type Post type.
	 * @param int    $category  Category ID.
	 * @param int    $index     Post index number.
	 * @return array|WP_Error Array with post ID and token usage or WP_Error on failure.
	 */
	private function generate_single_post( $topic, $post_type, $category, $index ) {
		// Build the prompt.
		$prompt = $this->build_prompt( $topic, $index );

		// Call OpenAI API.
		$response = $this->openai->generate_content( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse the response.
		$parsed = $this->parse_ai_response( $response['content'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Create the post.
		$post_data = array(
			'post_title'   => $parsed['title'],
			'post_content' => $parsed['body'],
			'post_excerpt' => $parsed['excerpt'],
			'post_status'  => 'draft', // Create as draft for review.
			'post_type'    => $post_type,
			'post_author'  => get_current_user_id(),
		);

		// Add category if specified.
		if ( $category > 0 && $post_type === 'post' ) {
			$post_data['post_category'] = array( $category );
		}

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Add tags if available.
		if ( ! empty( $parsed['tags'] ) && $post_type === 'post' ) {
			wp_set_post_tags( $post_id, $parsed['tags'], false );
		}

		// Add meta to track AI generation.
		update_post_meta( $post_id, '_aipg_generated', true );
		update_post_meta( $post_id, '_aipg_topic', $topic );
		update_post_meta( $post_id, '_aipg_generated_date', current_time( 'mysql' ) );

		return array(
			'post_id'     => $post_id,
			'token_usage' => $response['token_usage'],
		);
	}

	/**
	 * Build the prompt for OpenAI API.
	 *
	 * @param string $topic The topic.
	 * @param int    $index Post index number.
	 * @return array Prompt with system and user messages.
	 */
	private function build_prompt( $topic, $index ) {
		$system_message = __(
			'You are a professional blog writer. Write engaging, SEO-friendly articles on the given topic. Each post must have a unique angle, clear structure, and human-like tone.',
			'ai-post-generator'
		);

		$user_message = sprintf(
			"Topic: %s\n\nGenerate blog post #%d with a unique angle.\n\nProvide the response in the following format:\n\nTITLE: [Your catchy title here]\n\nBODY:\n[Your article content here - at least 300 words]\n\nEXCERPT:\n[A brief 1-2 sentence summary]\n\nTAGS:\n[3-5 comma-separated tags]",
			$topic,
			$index
		);

		return array(
			'system' => $system_message,
			'user'   => $user_message,
		);
	}

	/**
	 * Parse the AI response to extract title, body, tags, and excerpt.
	 *
	 * @param string $content The raw AI response.
	 * @return array|WP_Error Parsed content or WP_Error on failure.
	 */
	private function parse_ai_response( $content ) {
		$parsed = array(
			'title'   => '',
			'body'    => '',
			'excerpt' => '',
			'tags'    => array(),
		);

		// Extract TITLE.
		if ( preg_match( '/TITLE:\s*(.+?)(?=\n\n|BODY:|$)/is', $content, $title_match ) ) {
			$parsed['title'] = trim( $title_match[1] );
		}

		// Extract BODY.
		if ( preg_match( '/BODY:\s*(.+?)(?=\n\nEXCERPT:|TAGS:|$)/is', $content, $body_match ) ) {
			$parsed['body'] = trim( $body_match[1] );
		}

		// Extract EXCERPT.
		if ( preg_match( '/EXCERPT:\s*(.+?)(?=\n\nTAGS:|$)/is', $content, $excerpt_match ) ) {
			$parsed['excerpt'] = trim( $excerpt_match[1] );
		}

		// Extract TAGS.
		if ( preg_match( '/TAGS:\s*(.+?)$/is', $content, $tags_match ) ) {
			$tags_string  = trim( $tags_match[1] );
			$parsed['tags'] = array_map( 'trim', explode( ',', $tags_string ) );
		}

		// Validate required fields.
		if ( empty( $parsed['title'] ) || empty( $parsed['body'] ) ) {
			// Fallback: use the entire content as body if parsing fails.
			if ( empty( $parsed['body'] ) ) {
				$parsed['body'] = $content;
			}
			if ( empty( $parsed['title'] ) ) {
				// Generate a title from the first line or first few words.
				$lines          = explode( "\n", $content );
				$parsed['title'] = ! empty( $lines[0] ) ? wp_trim_words( $lines[0], 10 ) : __( 'Untitled Post', 'ai-post-generator' );
			}
		}

		// Generate excerpt if not provided.
		if ( empty( $parsed['excerpt'] ) ) {
			$parsed['excerpt'] = wp_trim_words( wp_strip_all_tags( $parsed['body'] ), 30 );
		}

		return $parsed;
	}

	/**
	 * Get statistics about generated posts.
	 *
	 * @return array Statistics.
	 */
	public function get_statistics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aipg_logs';

		// Get total posts generated.
		$total_posts = $wpdb->get_var(
			"SELECT SUM(post_count) FROM $table_name"
		);

		// Get total token usage.
		$total_tokens = $wpdb->get_var(
			"SELECT SUM(token_usage) FROM $table_name"
		);

		// Get total generations.
		$total_generations = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name"
		);

		return array(
			'total_posts'       => (int) $total_posts,
			'total_tokens'      => (int) $total_tokens,
			'total_generations' => (int) $total_generations,
		);
	}
}


