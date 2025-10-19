<?php
/**
 * Plugin Name: AI Post Generator
 * Plugin URI: https://developer-pro.com/ai-post-generator
 * Description: Automatically generate 10-100 WordPress posts on any topic using OpenAI API (GPT-4o-mini or GPT-5).
 * Version: 1.0.0
 * Author: Pavel Bohovin
 * Author URI: https://developer-pro.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-post-generator
 * Domain Path: /languages
 *
 * @package AI_Post_Generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'AIPG_VERSION', '1.0.0' );

/**
 * Plugin root directory path.
 */
define( 'AIPG_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin root directory URL.
 */
define( 'AIPG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'AIPG_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin class files.
 */
require_once AIPG_PATH . 'includes/class-aipg-admin.php';
require_once AIPG_PATH . 'includes/class-aipg-generator.php';
require_once AIPG_PATH . 'includes/class-aipg-openai.php';
require_once AIPG_PATH . 'includes/class-aipg-utils.php';

/**
 * Activation hook - create log table.
 *
 * @return void
 */
function aipg_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'aipg_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		topic varchar(255) NOT NULL,
		post_count int(11) NOT NULL DEFAULT 0,
		token_usage int(11) NOT NULL DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'aipg_activate' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function aipg_init() {
	// Initialize main classes.
	$admin     = new AIPG_Admin();
	$generator = new AIPG_Generator();
	$openai    = new AIPG_OpenAI();
	$utils     = new AIPG_Utils();

	// Register admin menu.
	add_action( 'admin_menu', array( $admin, 'register_admin_menu' ) );

	// Enqueue admin assets.
	add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_admin_assets' ) );

	// Register settings.
	add_action( 'admin_init', array( $admin, 'register_settings' ) );

	// Register AJAX handlers.
	add_action( 'wp_ajax_aipg_generate_posts', array( $admin, 'handle_ajax_generate' ) );

	// Register REST API routes.
	add_action( 'rest_api_init', 'aipg_register_rest_routes' );
}
add_action( 'plugins_loaded', 'aipg_init' );

/**
 * Register REST API routes.
 *
 * @return void
 */
function aipg_register_rest_routes() {
	register_rest_route(
		'aipg/v1',
		'/generate',
		array(
			'methods'             => 'POST',
			'callback'            => 'aipg_rest_generate_posts',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'topic'      => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'count'      => array(
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 10,
					'maximum'           => 100,
				),
				'post_type'  => array(
					'default'           => 'post',
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'category'   => array(
					'default'           => 0,
					'type'              => 'integer',
				),
			),
		)
	);

	register_rest_route(
		'aipg/v1',
		'/logs',
		array(
			'methods'             => 'GET',
			'callback'            => 'aipg_rest_get_logs',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}

/**
 * REST API callback to generate posts.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response object or error.
 */
function aipg_rest_generate_posts( $request ) {
	$topic     = $request->get_param( 'topic' );
	$count     = $request->get_param( 'count' );
	$post_type = $request->get_param( 'post_type' );
	$category  = $request->get_param( 'category' );

	$generator = new AIPG_Generator();
	$result    = $generator->generate_posts( $topic, $count, $post_type, $category );

	if ( is_wp_error( $result ) ) {
		return new WP_Error(
			'generation_failed',
			$result->get_error_message(),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'success'     => true,
			'posts_count' => $result['posts_count'],
			'token_usage' => $result['token_usage'],
			'message'     => sprintf(
				// translators: %d is the number of posts generated.
				__( 'Successfully generated %d posts.', 'ai-post-generator' ),
				$result['posts_count']
			),
		)
	);
}

/**
 * REST API callback to get logs.
 *
 * @return WP_REST_Response Response object.
 */
function aipg_rest_get_logs() {
	$utils = new AIPG_Utils();
	$logs  = $utils->get_logs( 50 );

	return rest_ensure_response(
		array(
			'success' => true,
			'logs'    => $logs,
		)
	);
}


