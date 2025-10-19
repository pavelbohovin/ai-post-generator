<?php
/**
 * AIPG_Admin Class
 *
 * Handles admin UI, menu registration, settings, and AJAX actions.
 *
 * @package AI_Post_Generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPG_Admin
 *
 * Manages admin interface and user interactions.
 */
class AIPG_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Class initialization.
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'AI Post Generator', 'ai-post-generator' ),
			__( 'AI Content', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator',
			array( $this, 'render_generator_page' ),
			'dashicons-edit',
			30
		);

		add_submenu_page(
			'ai-post-generator',
			__( 'Post Generator', 'ai-post-generator' ),
			__( 'Post Generator', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator',
			array( $this, 'render_generator_page' )
		);

		add_submenu_page(
			'ai-post-generator',
			__( 'Settings', 'ai-post-generator' ),
			__( 'Settings', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ai-post-generator',
			__( 'Logs', 'ai-post-generator' ),
			__( 'Logs', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages.
		if ( strpos( $hook, 'ai-post-generator' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'aipg-admin-style',
			AIPG_URL . 'assets/admin.css',
			array(),
			AIPG_VERSION
		);

		wp_enqueue_script(
			'aipg-admin-script',
			AIPG_URL . 'assets/admin.js',
			array( 'jquery' ),
			AIPG_VERSION,
			true
		);

		wp_localize_script(
			'aipg-admin-script',
			'aipgAjax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aipg_generate_nonce' ),
			)
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'aipg_settings', 'aipg_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'aipg_settings', 'aipg_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'aipg_settings', 'aipg_max_tokens', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'aipg_settings', 'aipg_temperature', array( 'sanitize_callback' => 'floatval' ) );
	}

	/**
	 * Render main generator page.
	 *
	 * @return void
	 */
	public function render_generator_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-post-generator' ) );
		}

		// Get all post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		// Get all categories.
		$categories = get_categories( array( 'hide_empty' => false ) );

		?>
		<div class="wrap aipg-admin-wrap">
			<h1><?php esc_html_e( 'AI Post Generator', 'ai-post-generator' ); ?></h1>

			<?php
			// Check if API key is set.
			$api_key = get_option( 'aipg_api_key' );
			if ( empty( $api_key ) ) :
				?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							// translators: %s is a link to the settings page.
							__( 'Please set your OpenAI API key in the <a href="%s">Settings</a> page.', 'ai-post-generator' ),
							esc_url( admin_url( 'admin.php?page=ai-post-generator-settings' ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="aipg-generator-form">
				<form id="aipg-form" method="post">
					<?php wp_nonce_field( 'aipg_generate_nonce', 'aipg_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="aipg-topic"><?php esc_html_e( 'Subject / Topic', 'ai-post-generator' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="aipg-topic"
									name="topic"
									class="regular-text"
									required
									placeholder="<?php esc_attr_e( 'e.g., Digital Marketing Strategies', 'ai-post-generator' ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Enter the main topic or subject for post generation.', 'ai-post-generator' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="aipg-count"><?php esc_html_e( 'Number of Posts', 'ai-post-generator' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="aipg-count"
									name="count"
									min="10"
									max="100"
									value="10"
									required
								/>
								<p class="description">
									<?php esc_html_e( 'Choose between 10 and 100 posts.', 'ai-post-generator' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="aipg-post-type"><?php esc_html_e( 'Post Type', 'ai-post-generator' ); ?></label>
							</th>
							<td>
								<select id="aipg-post-type" name="post_type">
									<?php foreach ( $post_types as $post_type ) : ?>
										<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $post_type->name, 'post' ); ?>>
											<?php echo esc_html( $post_type->labels->singular_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="aipg-category"><?php esc_html_e( 'Category', 'ai-post-generator' ); ?></label>
							</th>
							<td>
								<select id="aipg-category" name="category">
									<option value="0"><?php esc_html_e( 'None', 'ai-post-generator' ); ?></option>
									<?php foreach ( $categories as $category ) : ?>
										<option value="<?php echo esc_attr( $category->term_id ); ?>">
											<?php echo esc_html( $category->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Optional: Assign posts to a specific category.', 'ai-post-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large" id="aipg-generate-btn">
							<?php esc_html_e( 'Generate Posts', 'ai-post-generator' ); ?>
						</button>
					</p>
				</form>

				<div id="aipg-progress" style="display: none;">
					<h3><?php esc_html_e( 'Generating posts...', 'ai-post-generator' ); ?></h3>
					<div class="aipg-progress-bar">
						<div class="aipg-progress-fill" id="aipg-progress-fill"></div>
					</div>
					<p id="aipg-progress-text">0%</p>
				</div>

				<div id="aipg-result" style="display: none;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-post-generator' ) );
		}

		// Save settings if form submitted.
		if ( isset( $_POST['aipg_save_settings'] ) && check_admin_referer( 'aipg_settings_nonce' ) ) {
			update_option( 'aipg_api_key', sanitize_text_field( $_POST['aipg_api_key'] ) );
			update_option( 'aipg_model', sanitize_text_field( $_POST['aipg_model'] ) );
			update_option( 'aipg_max_tokens', absint( $_POST['aipg_max_tokens'] ) );
			update_option( 'aipg_temperature', floatval( $_POST['aipg_temperature'] ) );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'ai-post-generator' ) . '</p></div>';
		}

		// Get current settings.
		$api_key     = get_option( 'aipg_api_key', '' );
		$model       = get_option( 'aipg_model', 'gpt-4o-mini' );
		$max_tokens  = get_option( 'aipg_max_tokens', 2000 );
		$temperature = get_option( 'aipg_temperature', 0.7 );

		?>
		<div class="wrap aipg-admin-wrap">
			<h1><?php esc_html_e( 'AI Post Generator - Settings', 'ai-post-generator' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'aipg_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="aipg-api-key"><?php esc_html_e( 'OpenAI API Key', 'ai-post-generator' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="aipg-api-key"
								name="aipg_api_key"
								value="<?php echo esc_attr( $api_key ); ?>"
								class="regular-text"
								required
							/>
							<p class="description">
								<?php
								printf(
									// translators: %s is a link to OpenAI API keys page.
									__( 'Enter your OpenAI API key. Get it from <a href="%s" target="_blank">OpenAI Platform</a>.', 'ai-post-generator' ),
									'https://platform.openai.com/api-keys'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aipg-model"><?php esc_html_e( 'Model', 'ai-post-generator' ); ?></label>
						</th>
						<td>
							<select id="aipg-model" name="aipg_model">
								<option value="gpt-4o-mini" <?php selected( $model, 'gpt-4o-mini' ); ?>>GPT-4o-mini</option>
								<option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>GPT-4o</option>
								<option value="gpt-4-turbo" <?php selected( $model, 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
								<option value="gpt-3.5-turbo" <?php selected( $model, 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select the OpenAI model to use for generation.', 'ai-post-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aipg-max-tokens"><?php esc_html_e( 'Max Tokens', 'ai-post-generator' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="aipg-max-tokens"
								name="aipg_max_tokens"
								value="<?php echo esc_attr( $max_tokens ); ?>"
								min="100"
								max="4000"
								step="100"
							/>
							<p class="description">
								<?php esc_html_e( 'Maximum number of tokens per request (100-4000).', 'ai-post-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aipg-temperature"><?php esc_html_e( 'Temperature', 'ai-post-generator' ); ?></label>
						</th>
						<td>
							<input
								type="range"
								id="aipg-temperature"
								name="aipg_temperature"
								value="<?php echo esc_attr( $temperature ); ?>"
								min="0"
								max="1"
								step="0.1"
								oninput="document.getElementById('aipg-temperature-value').textContent = this.value"
							/>
							<span id="aipg-temperature-value"><?php echo esc_html( $temperature ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Controls randomness: 0 is focused, 1 is creative (0-1).', 'ai-post-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input
						type="submit"
						name="aipg_save_settings"
						class="button button-primary"
						value="<?php esc_attr_e( 'Save Settings', 'ai-post-generator' ); ?>"
					/>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render logs page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-post-generator' ) );
		}

		$utils = new AIPG_Utils();
		$logs  = $utils->get_logs( 100 );

		?>
		<div class="wrap aipg-admin-wrap">
			<h1><?php esc_html_e( 'AI Post Generator - Logs', 'ai-post-generator' ); ?></h1>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No logs found.', 'ai-post-generator' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'ai-post-generator' ); ?></th>
							<th><?php esc_html_e( 'Topic', 'ai-post-generator' ); ?></th>
							<th><?php esc_html_e( 'Posts Generated', 'ai-post-generator' ); ?></th>
							<th><?php esc_html_e( 'Token Usage', 'ai-post-generator' ); ?></th>
							<th><?php esc_html_e( 'Date', 'ai-post-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->id ); ?></td>
								<td><?php echo esc_html( $log->topic ); ?></td>
								<td><?php echo esc_html( $log->post_count ); ?></td>
								<td><?php echo esc_html( number_format( $log->token_usage ) ); ?></td>
								<td><?php echo esc_html( $log->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request to generate posts.
	 *
	 * @return void
	 */
	public function handle_ajax_generate() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'aipg_generate_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'ai-post-generator' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-post-generator' ) ) );
		}

		// Get and sanitize inputs.
		$topic     = isset( $_POST['topic'] ) ? sanitize_text_field( $_POST['topic'] ) : '';
		$count     = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 10;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$category  = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;

		// Validate inputs.
		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-post-generator' ) ) );
		}

		if ( $count < 10 || $count > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Post count must be between 10 and 100.', 'ai-post-generator' ) ) );
		}

		// Generate posts.
		$generator = new AIPG_Generator();
		$result    = $generator->generate_posts( $topic, $count, $post_type, $category );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => sprintf(
					// translators: %d is the number of posts generated.
					__( 'Successfully generated %d posts!', 'ai-post-generator' ),
					$result['posts_count']
				),
				'posts_count' => $result['posts_count'],
				'token_usage' => $result['token_usage'],
			)
		);
	}
}


