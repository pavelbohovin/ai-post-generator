/**
 * AI Post Generator - Admin JavaScript
 *
 * Handles AJAX requests, progress tracking, and UI interactions.
 *
 * @package AI_Post_Generator
 */

(function($) {
	'use strict';

	/**
	 * Post Generator Handler
	 */
	const AIPostGenerator = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$('#aipg-form').on('submit', this.handleSubmit.bind(this));
		},

		/**
		 * Handle form submission
		 */
		handleSubmit: function(e) {
			e.preventDefault();

			const $form = $('#aipg-form');
			const $button = $('#aipg-generate-btn');
			const $progress = $('#aipg-progress');
			const $result = $('#aipg-result');

			// Get form data
			const data = {
				action: 'aipg_generate_posts',
				nonce: aipgAjax.nonce,
				topic: $('#aipg-topic').val().trim(),
				count: parseInt($('#aipg-count').val(), 10),
				post_type: $('#aipg-post-type').val(),
				category: parseInt($('#aipg-category').val(), 10)
			};

			// Validate
			if (!data.topic) {
				this.showError('Please enter a topic.');
				return;
			}

			if (data.count < 10 || data.count > 100) {
				this.showError('Please enter a number between 10 and 100.');
				return;
			}

			// Disable form
			$button.prop('disabled', true).text('Generating...');
			$form.find('input, select').prop('disabled', true);
			$result.hide();
			$progress.show();

			// Show progress animation
			this.animateProgress(data.count);

			// Send AJAX request
			$.ajax({
				url: aipgAjax.ajax_url,
				type: 'POST',
				data: data,
				timeout: data.count * 10000, // 10 seconds per post
				success: this.handleSuccess.bind(this),
				error: this.handleError.bind(this),
				complete: function() {
					$button.prop('disabled', false).text('Generate Posts');
					$form.find('input, select').prop('disabled', false);
					$progress.hide();
				}
			});
		},

		/**
		 * Animate progress bar
		 */
		animateProgress: function(totalPosts) {
			const $fill = $('#aipg-progress-fill');
			const $text = $('#aipg-progress-text');
			let progress = 0;
			const increment = 100 / (totalPosts * 2); // Slower progression
			
			const interval = setInterval(function() {
				progress += increment;
				if (progress >= 95) {
					progress = 95; // Stop at 95% until complete
					clearInterval(interval);
				}
				$fill.css('width', progress + '%');
				$text.text(Math.round(progress) + '%');
			}, 500);

			// Store interval ID to clear later
			$progress.data('interval', interval);
		},

		/**
		 * Complete progress bar
		 */
		completeProgress: function() {
			const $progress = $('#aipg-progress');
			const $fill = $('#aipg-progress-fill');
			const $text = $('#aipg-progress-text');
			const interval = $progress.data('interval');

			if (interval) {
				clearInterval(interval);
			}

			$fill.css('width', '100%');
			$text.text('100%');

			setTimeout(function() {
				$progress.fadeOut();
			}, 1000);
		},

		/**
		 * Handle successful response
		 */
		handleSuccess: function(response) {
			this.completeProgress();

			if (response.success) {
				const message = response.data.message;
				const postsCount = response.data.posts_count;
				const tokenUsage = response.data.token_usage;

				this.showSuccess(
					`<strong>${message}</strong><br>` +
					`<ul>` +
					`<li>Posts generated: ${postsCount}</li>` +
					`<li>Tokens used: ${tokenUsage.toLocaleString()}</li>` +
					`</ul>` +
					`<a href="${this.getPostsUrl()}" class="button">View Posts</a>`
				);

				// Reset form
				$('#aipg-form')[0].reset();
			} else {
				this.showError(response.data.message || 'An error occurred.');
			}
		},

		/**
		 * Handle error response
		 */
		handleError: function(xhr, status, error) {
			this.completeProgress();

			let message = 'An error occurred while generating posts.';

			if (status === 'timeout') {
				message = 'Request timed out. The generation may still be running in the background.';
			} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				message = xhr.responseJSON.data.message;
			} else if (error) {
				message += ' ' + error;
			}

			this.showError(message);
		},

		/**
		 * Show success message
		 */
		showSuccess: function(message) {
			$('#aipg-result')
				.removeClass('aipg-error')
				.addClass('aipg-success')
				.html(message)
				.fadeIn();
		},

		/**
		 * Show error message
		 */
		showError: function(message) {
			$('#aipg-result')
				.removeClass('aipg-success')
				.addClass('aipg-error')
				.html('<strong>Error:</strong> ' + message)
				.fadeIn();
		},

		/**
		 * Get URL for viewing posts
		 */
		getPostsUrl: function() {
			const postType = $('#aipg-post-type').val() || 'post';
			return `edit.php?post_type=${postType}`;
		}
	};

	/**
	 * Settings Page Handler
	 */
	const AIPostGeneratorSettings = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Temperature slider value display
			$('#aipg-temperature').on('input', function() {
				$('#aipg-temperature-value').text($(this).val());
			});

			// API key visibility toggle
			this.setupApiKeyToggle();
		},

		/**
		 * Setup API key visibility toggle
		 */
		setupApiKeyToggle: function() {
			const $apiKeyField = $('#aipg-api-key');
			
			if ($apiKeyField.length) {
				const $toggle = $('<button type="button" class="button aipg-toggle-api-key">Show</button>');
				$apiKeyField.after($toggle);

				$toggle.on('click', function() {
					const type = $apiKeyField.attr('type');
					if (type === 'password') {
						$apiKeyField.attr('type', 'text');
						$toggle.text('Hide');
					} else {
						$apiKeyField.attr('type', 'password');
						$toggle.text('Show');
					}
				});
			}
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		// Initialize generator on generator page
		if ($('#aipg-form').length) {
			AIPostGenerator.init();
		}

		// Initialize settings on settings page
		if ($('#aipg-api-key').length) {
			AIPostGeneratorSettings.init();
		}

		// Add confirmation for large generations
		$('#aipg-count').on('change', function() {
			const count = parseInt($(this).val(), 10);
			if (count >= 50) {
				const $warning = $('#aipg-count-warning');
				if ($warning.length === 0) {
					$(this).after(
						'<p id="aipg-count-warning" class="aipg-warning">' +
						'⚠️ Generating ' + count + ' posts may take several minutes and consume significant API tokens.' +
						'</p>'
					);
				} else {
					$warning.html(
						'⚠️ Generating ' + count + ' posts may take several minutes and consume significant API tokens.'
					);
				}
			} else {
				$('#aipg-count-warning').remove();
			}
		});
	});

})(jQuery);


