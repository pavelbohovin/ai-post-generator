# AI Post Generator

A powerful WordPress plugin that automatically generates high-quality blog posts using OpenAI's GPT models. Create 10-100 unique, SEO-friendly posts on any topic with just a few clicks.

## Features

- **Bulk Post Generation**: Generate 10-100 posts at once
- **AI-Powered Content**: Uses OpenAI GPT-4o-mini, GPT-4o, or GPT-4 Turbo
- **Flexible Configuration**: Customize model, temperature, and token limits
- **Post Customization**: Support for custom post types and categories
- **Smart Content**: Each post includes title, body, excerpt, and tags
- **Progress Tracking**: Real-time progress bar during generation
- **Token Usage Logging**: Track API usage and costs
- **REST API**: Programmatic access via WordPress REST API
- **Security First**: Nonces, capability checks, and input sanitization
- **WordPress Standards**: Follows official WordPress Coding Standards

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **OpenAI API Key**: Required (get it from [OpenAI Platform](https://platform.openai.com/api-keys))

## Installation

1. Download the plugin files or clone this repository
2. Upload the `ai-post-generator` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **AI Content → Settings** and enter your OpenAI API key

## Configuration

### Settings

Navigate to **AI Content → Settings** to configure:

- **API Key**: Your OpenAI API key (required)
- **Model**: Choose from GPT-4o-mini (recommended), GPT-4o, GPT-4 Turbo, or GPT-3.5 Turbo
- **Max Tokens**: Set the maximum tokens per request (100-4000)
- **Temperature**: Control creativity (0 = focused, 1 = creative)

### Recommended Settings

```
Model: gpt-4o-mini
Max Tokens: 2000
Temperature: 0.7
```

## Usage

### Admin Interface

1. Go to **AI Content → Post Generator**
2. Enter your topic or subject
3. Select the number of posts (10-100)
4. Choose post type (default: post)
5. Optionally select a category
6. Click **Generate Posts**
7. Watch the progress bar and wait for completion
8. Review generated posts in **Posts → All Posts** (they're saved as drafts)

### REST API

The plugin provides REST API endpoints for programmatic access:

#### Generate Posts

```http
POST /wp-json/aipg/v1/generate
Content-Type: application/json

{
  "topic": "Digital Marketing Strategies",
  "count": 10,
  "post_type": "post",
  "category": 5
}
```

**Response:**

```json
{
  "success": true,
  "posts_count": 10,
  "token_usage": 15420,
  "message": "Successfully generated 10 posts."
}
```

#### Get Logs

```http
GET /wp-json/aipg/v1/logs
```

**Response:**

```json
{
  "success": true,
  "logs": [
    {
      "id": 1,
      "topic": "Digital Marketing",
      "post_count": 10,
      "token_usage": 15420,
      "created_at": "2024-01-15 10:30:00"
    }
  ]
}
```

## File Structure

```
ai-post-generator/
├── ai-post-generator.php          # Plugin bootstrap
├── includes/
│   ├── class-aipg-admin.php       # Admin UI and menu
│   ├── class-aipg-generator.php   # Post generation logic
│   ├── class-aipg-openai.php      # OpenAI API wrapper
│   └── class-aipg-utils.php       # Utilities and logging
├── assets/
│   ├── admin.js                   # Admin JavaScript
│   └── admin.css                  # Admin styles
└── README.md                      # This file
```

## How It Works

1. **User Input**: Admin enters topic and preferences
2. **API Request**: Plugin sends prompt to OpenAI API
3. **Content Generation**: AI generates unique title, body, excerpt, and tags
4. **Post Creation**: Content is saved as WordPress post using `wp_insert_post()`
5. **Logging**: Generation details are logged to database
6. **Metadata**: Posts are tagged with AI generation info

### Prompt Template

The plugin uses a structured prompt:

```
SYSTEM:
You are a professional blog writer. Write engaging, SEO-friendly articles 
on the given topic. Each post must have a unique angle, clear structure, 
and human-like tone.

USER:
Topic: {topic}
Generate blog post #{index} with a unique angle.

Provide the response in the following format:
TITLE: [Your catchy title here]
BODY: [Your article content here - at least 300 words]
EXCERPT: [A brief 1-2 sentence summary]
TAGS: [3-5 comma-separated tags]
```

## Database

The plugin creates a custom table `wp_aipg_logs` to track:

- Topic
- Number of posts generated
- Token usage
- Generation timestamp

## Security

- **Capability Checks**: Only users with `manage_options` can generate posts
- **Nonce Verification**: All forms use WordPress nonces
- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Protection**: Uses `$wpdb->prepare()` for queries
- **XSS Prevention**: All outputs are escaped

## Cost Estimation

Approximate costs based on OpenAI pricing (2024):

| Model | Cost per 1M tokens | 10 Posts (~20K tokens) | 100 Posts (~200K tokens) |
|-------|-------------------|------------------------|--------------------------|
| GPT-4o-mini | $0.15 | $0.003 | $0.03 |
| GPT-4o | $2.50 | $0.05 | $0.50 |
| GPT-4 Turbo | $10.00 | $0.20 | $2.00 |

*Actual costs may vary based on content length and complexity.*

## Troubleshooting

### API Key Issues

**Problem**: "OpenAI API key is not set"  
**Solution**: Navigate to Settings and enter your valid API key

### Generation Errors

**Problem**: Posts fail to generate  
**Solution**: 
- Check API key validity
- Verify internet connection
- Check OpenAI API status
- Review error logs in **AI Content → Logs**

### Timeout Issues

**Problem**: Request times out  
**Solution**: 
- Reduce number of posts
- Increase PHP `max_execution_time`
- Use smaller `max_tokens` value

## Development

### Debug Mode

Enable WordPress debug mode to see detailed logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will be written to `wp-content/debug.log`

### Custom Prompts

To customize prompts, modify the `build_prompt()` method in `class-aipg-generator.php`

### Hooks and Filters

The plugin provides hooks for extensibility:

```php
// Modify prompt before sending to API
add_filter('aipg_prompt', function($prompt, $topic, $index) {
    // Your modifications
    return $prompt;
}, 10, 3);

// Modify post data before insertion
add_filter('aipg_post_data', function($post_data, $content) {
    // Your modifications
    return $post_data;
}, 10, 2);
```

## Support

For issues, questions, or feature requests:

- Create an issue on GitHub
- Email: support@example.com

## Changelog

### Version 1.0.0 (2024-01-15)
- Initial release
- Bulk post generation (10-100 posts)
- OpenAI API integration
- Admin interface with progress tracking
- REST API endpoints
- Usage logging and statistics
- WordPress Coding Standards compliance

## Credits

- **Author**: Pavel Bohovin
- **License**: GPL-2.0+
- **Powered by**: OpenAI GPT Models

## License

This plugin is licensed under the GPL-2.0+ License. See [LICENSE](http://www.gnu.org/licenses/gpl-2.0.txt) for details.

---

**Made with ❤️ for WordPress**


