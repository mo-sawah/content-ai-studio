<?php
/**
 * ATM Automation API
 * Handles automation campaign execution and content generation
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_API {
    
    /**
     * Execute automation campaign
     */
    public static function execute_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
        if (!$campaign) {
            return ['success' => false, 'message' => 'Campaign not found.'];
        }
        
        $settings = json_decode($campaign->settings, true) ?: [];
        
        try {
            // Log execution start
            self::log_execution($campaign_id, 'started', 'Execution started');
            
            $result = null;
            
            switch ($campaign->type) {
                case 'articles':
                    $result = self::execute_article_automation($campaign, $settings);
                    break;
                    
                case 'news':
                    $result = self::execute_news_automation($campaign, $settings);
                    break;
                    
                case 'videos':
                    $result = self::execute_video_automation($campaign, $settings);
                    break;
                    
                case 'podcasts':
                    $result = self::execute_podcast_automation($campaign, $settings);
                    break;
                    
                default:
                    throw new Exception('Unknown campaign type: ' . $campaign->type);
            }
            
            if ($result && $result['success']) {
                // Update next run time
                self::update_campaign_next_run($campaign_id, $campaign->schedule_type, $campaign->schedule_value, $campaign->schedule_unit);
                
                // Log successful execution
                self::log_execution($campaign_id, 'completed', 'Execution completed successfully', $result['post_id'] ?? null);
                
                return [
                    'success' => true,
                    'message' => 'Campaign executed successfully.',
                    'post_id' => $result['post_id'] ?? null,
                    'post_url' => $result['post_url'] ?? null
                ];
            } else {
                throw new Exception($result['message'] ?? 'Campaign execution failed.');
            }
            
        } catch (Exception $e) {
            // Log execution failure
            self::log_execution($campaign_id, 'failed', $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
        /**
     * Execute article automation campaign
     */
    private static function execute_article_automation($campaign, $settings) {
        try {
            // Get model with proper fallbacks
            $ai_model = $settings['ai_model'] ?? get_option('atm_article_model', 'openai/gpt-4o');
            if (empty($ai_model)) {
                $ai_model = 'openai/gpt-4o';
            }
            
            error_log("ATM Automation: Using model: " . $ai_model . " for campaign: " . $campaign->name);
            
            // Ensure angles table exists (using shared utility)
            ATM_Content_Generator_Utility::ensure_angles_table_exists();
            
            // Get previous angles for this keyword (using shared utility)
            $previous_angles = ATM_Content_Generator_Utility::get_previous_angles($campaign->keyword);
            error_log("ATM Debug: Found " . count($previous_angles) . " previous angles for: " . $campaign->keyword);
            
            // Generate intelligent angle (using shared utility)
            $angle_data = ATM_Content_Generator_Utility::generate_intelligent_angle_classification($campaign->keyword, $previous_angles);
            error_log("ATM Debug: Generated angle: " . ($angle_data['angle_description'] ?? 'none'));
            
            // Store the angle BEFORE content generation (using shared utility)
            if ($angle_data && isset($angle_data['angle_description'])) {
                ATM_Content_Generator_Utility::store_content_angle($campaign->keyword, $angle_data['angle_description'], '[Automation Generated]');
            }
            
            // Build system prompt
            $writing_styles = method_exists('ATM_API', 'get_writing_styles') ? ATM_API::get_writing_styles() : [];
            if (empty($writing_styles)) {
                $writing_styles = ['default_seo' => ['prompt' => 'Write a professional, SEO-optimized article.']];
            }
            
            $base_prompt = isset($writing_styles[$settings['writing_style'] ?? 'default_seo']) ? 
                $writing_styles[$settings['writing_style'] ?? 'default_seo']['prompt'] : 
                $writing_styles['default_seo']['prompt'];
                
            if (!empty($settings['custom_prompt'])) {
                $base_prompt = $settings['custom_prompt'];
            }
            
            // Add intelligent angle context (using shared utility)
            if ($angle_data) {
                $base_prompt .= ATM_Content_Generator_Utility::build_comprehensive_angle_context($angle_data, $campaign->keyword);
            }
            
            // Rest of the method remains the same...
            $system_prompt = $base_prompt . "\n\n**AUTOMATION CONTENT GENERATION INSTRUCTIONS:**

    Follow these strict formatting guidelines:
    - **Style**: Write in a professional, engaging tone appropriate for the topic
    - **Length**: Aim for " . ($settings['word_count'] ? $settings['word_count'] : '800-1200') . " words
    - **HTML Format**: Use clean HTML with <h2> for main sections, <h3> for subsections, <p> for paragraphs, <ul>/<ol> for lists
    - **CRITICAL**: Do NOT use H1 headings anywhere in the content
    - **CRITICAL**: The content must begin with a regular paragraph, NOT with any heading (H1, H2, H3, etc.)
    - **CRITICAL**: Do NOT include conclusion headings like 'Conclusion', 'Summary', 'Final Thoughts', etc.
    - End with a natural concluding paragraph without any heading

    **Link Formatting Rules:**
    - When including external links, NEVER use the website URL as the anchor text
    - Use ONLY 1-3 descriptive words as anchor text
    - Example: railway that [had a deadly crash](https://reuters.com/specific-article) last week and will...
    - Example: Example: [Digital Marketing](https://yahoo.com/actual-article-url) reported that...
    - Example: According to [BBC News](https://bbc.com/specific-article), the incident...
    - Do NOT use generic phrases like \"click here\", \"read more\", or \"this article\" as anchor text
    - Anchor text should be relevant keywords from the article topic (e.g., marketing, design, finance, AI)
    - Keep anchor text extremely concise (maximum 2 words)
    - Make links feel natural within the sentence flow
    - Avoid long phrases as anchor text

    **Output Format:**
    Return a JSON object with exactly these keys:
    1. \"title\": An engaging, SEO-friendly headline
    2. \"subheadline\": A compelling one-sentence subtitle  
    3. \"content\": The complete article as clean HTML (not Markdown)

    **Content Quality Requirements:**
    - Use current, factual information with web search
    - Include specific examples and data when relevant
    - Write for human readers, not search engines
    - Ensure content flows naturally between sections
    - Maintain consistency in tone throughout";

            // Generate content using existing API (if available)
            if (class_exists('ATM_API') && method_exists('ATM_API', 'enhance_content_with_openrouter')) {
                $raw_response = ATM_API::enhance_content_with_openrouter(
                    ['content' => $campaign->keyword],
                    $system_prompt,
                    $ai_model,
                    true, // JSON mode
                    true, // web search
                    $settings['creativity_level'] ?? 'high'
                );
            } else {
                throw new Exception('ATM_API class or enhance_content_with_openrouter method not available');
            }
            
            // Parse JSON response (same as before)
            $json_string = trim($raw_response);
            if (!str_starts_with($json_string, '{')) {
                if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                    $json_string = $matches[0];
                }
            }
            
            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
                error_log('ATM Automation: Invalid JSON response: ' . $raw_response);
                throw new Exception('Invalid AI response format.');
            }
            
            $generated_title = $result['title'] ?? '';
            $subtitle = $result['subheadline'] ?? $result['subtitle'] ?? '';
            $final_content = trim($result['content']);
            
            // Update the stored angle with the actual generated title (using shared utility)
            if ($angle_data && !empty($generated_title)) {
                ATM_Content_Generator_Utility::update_stored_angle($campaign->keyword, $angle_data['angle_description'], $generated_title);
            }
            
            // Create post with clean content
            $post_data = [
                'post_title' => wp_strip_all_tags($generated_title),
                'post_content' => wp_kses_post($final_content),
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : []
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Save subtitle
            if (!empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                update_post_meta($post_id, '_atm_subtitle', $subtitle);
            }
            
            // Save automation metadata
            update_post_meta($post_id, '_atm_automation_generated', true);
            update_post_meta($post_id, '_atm_campaign_id', $campaign->id);
            update_post_meta($post_id, '_atm_generation_date', current_time('mysql'));
            
            // Generate featured image if requested
            if ($settings['generate_image'] ?? false) {
                self::generate_automation_featured_image($post_id, $generated_title);
            }
            
            error_log("ATM Automation: Successfully created post ID {$post_id} for campaign '{$campaign->name}'");
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id)
            ];
            
        } catch (Exception $e) {
            error_log('ATM Automation Article Generation Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private static function get_previous_automation_angles($keyword) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT angle, title FROM $table_name WHERE keyword = %s ORDER BY created_at DESC LIMIT 20",
            $keyword
        ), ARRAY_A);
    }

    private static function generate_automation_angle($keyword, $previous_angles) {
        $previous_text = '';
        if (!empty($previous_angles)) {
            $previous_text = "\n\nPREVIOUS ANGLES USED:\n";
            foreach ($previous_angles as $angle) {
                $previous_text .= "- " . $angle['angle'] . "\n";
            }
            $previous_text .= "\nCreate a completely different angle.";
        }
        
        $prompt = "Create a unique content angle for '$keyword'. $previous_text Return JSON with 'angle_description' and 'target_audience'.";
        
        try {
            $response = ATM_API::enhance_content_with_openrouter(
                ['content' => $keyword],
                $prompt,
                'anthropic/claude-3-haiku',
                true, // JSON mode
                false // No web search for angle generation
            );
            
            return json_decode($response, true) ?: ['angle_description' => 'General comprehensive coverage'];
        } catch (Exception $e) {
            return ['angle_description' => 'General comprehensive coverage'];
        }
    }

    private static function store_automation_angle($campaign_id, $keyword, $angle) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        $wpdb->insert($table_name, [
            'keyword' => $keyword,
            'angle' => $angle,
            'title' => '[Automation Generated]',
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Execute news automation campaign
     */
    private static function execute_news_automation($campaign, $settings) {
        try {
            // Search for news articles using existing methods
            $search_params = [
                'query' => $campaign->keyword,
                'article_language' => $settings['article_language'] ?? 'English',
                'source_languages' => $settings['source_languages'] ?? [],
                'countries' => $settings['countries'] ?? ['United States'],
                'page' => 1,
                'per_page' => 5
            ];
            
            // Use existing Google News search logic
            $news_results = self::search_google_news_for_automation($search_params);
            
            if (empty($news_results)) {
                throw new Exception('No recent news articles found for keyword: ' . $campaign->keyword);
            }
            
            // Select best article (first unused one)
            $selected_article = null;
            foreach ($news_results as $article) {
                if (!self::is_news_article_used($article['link'])) {
                    $selected_article = $article;
                    break;
                }
            }
            
            if (!$selected_article) {
                throw new Exception('All recent news articles have already been used.');
            }
            
            // Generate article from news source using existing logic
            $article_result = ATM_API::generate_article_from_news_source(
                $selected_article['link'],
                $selected_article['title'],
                $selected_article['snippet'] ?? '',
                $selected_article['date'] ?? '',
                $selected_article['source'] ?? '',
                $settings['article_language'] ?? 'English'
            );
            
            // Create post
            $post_data = [
                'post_title' => wp_strip_all_tags($article_result['title']),
                'post_content' => $article_result['content'],
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : []
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Save subtitle
            if (!empty($article_result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $article_result['subtitle']);
                update_post_meta($post_id, '_atm_subtitle', $article_result['subtitle']);
            }
            
            // Mark article as used
            self::mark_news_article_as_used($selected_article['link'], $selected_article['title'], $post_id);
            
            // Generate featured image if requested
            if ($settings['generate_image'] ?? false) {
                self::generate_automation_featured_image($post_id, $article_result['title']);
            }
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Execute video automation campaign
     */
    private static function execute_video_automation($campaign, $settings) {
        try {
            // Search for videos using existing YouTube search logic
            $video_params = [
                'query' => $campaign->keyword,
                'order' => $settings['video_order'] ?? 'relevance',
                'videoDuration' => $settings['video_duration'] ?? 'any',
                'maxResults' => 5
            ];
            
            $videos = self::search_youtube_for_automation($video_params);
            
            if (empty($videos)) {
                throw new Exception('No videos found for keyword: ' . $campaign->keyword);
            }
            
            // Select first video
            $selected_video = $videos[0];
            
            // Create post with embedded video
            $embed_code = sprintf(
                '<div class="wp-video" style="width: 100%;"><iframe src="https://www.youtube.com/embed/%s" width="500" height="281" style="width: 100%%; aspect-ratio: 16/9; height: auto;" frameborder="0" allowfullscreen></iframe></div>',
                $selected_video['id']
            );
            
            $content = sprintf(
                "<p>%s</p>\n\n%s\n\n<p><a href=\"%s\" target=\"_blank\" rel=\"noopener\">Watch on YouTube</a></p>",
                esc_html($selected_video['description']),
                $embed_code,
                esc_url($selected_video['url'])
            );
            
            $post_data = [
                'post_title' => wp_strip_all_tags($selected_video['title']),
                'post_content' => $content,
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : []
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Set video thumbnail as featured image if available and requested
            if (($settings['generate_image'] ?? false) && !empty($selected_video['thumbnail'])) {
                self::set_featured_image_from_url($post_id, $selected_video['thumbnail']);
            }
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Execute podcast automation campaign
     */
    private static function execute_podcast_automation($campaign, $settings) {
        try {
            // First generate an article for the podcast content
            $article_result = self::execute_article_automation($campaign, $settings);
            
            if (!$article_result['success']) {
                throw new Exception('Failed to generate base article for podcast.');
            }
            
            $post_id = $article_result['post_id'];
            $post = get_post($post_id);
            
            // Generate podcast script using existing logic
            $script = ATM_API::generate_advanced_podcast_script(
                $post->post_title,
                $post->post_content,
                $settings['podcast_language'] ?? 'English',
                $settings['podcast_duration'] ?? 'medium'
            );
            
            // Queue podcast generation in background
            $job_id = ATM_API::queue_podcast_generation(
                $post_id,
                $script,
                $settings['host_a_voice'] ?? 'alloy',
                $settings['host_b_voice'] ?? 'nova',
                $settings['audio_provider'] ?? 'openai'
            );
            
            // Save script to post meta
            update_post_meta($post_id, '_atm_podcast_script', $script);
            update_post_meta($post_id, '_atm_podcast_job_id', $job_id);
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'podcast_job_id' => $job_id
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Helper methods for automation
     */
    
    /**
     * Log automation execution
     */
    private static function log_execution($campaign_id, $status, $message = '', $post_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_executions';
        
        $data = [
            'campaign_id' => $campaign_id,
            'status' => $status,
            'message' => $message,
            'post_id' => $post_id,
            'executed_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table_name, $data);
    }
    
    /**
     * Update campaign next run time
     */
    private static function update_campaign_next_run($campaign_id, $schedule_type, $value, $unit) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $current_time = current_time('timestamp');
        
        switch ($schedule_type) {
            case 'interval':
                $interval_map = [
                    'minute' => $value * MINUTE_IN_SECONDS,
                    'hour' => $value * HOUR_IN_SECONDS,
                    'day' => $value * DAY_IN_SECONDS,
                    'week' => $value * WEEK_IN_SECONDS
                ];
                
                $seconds = $interval_map[$unit] ?? HOUR_IN_SECONDS;
                $next_run = gmdate('Y-m-d H:i:s', $current_time + $seconds);
                break;
                
            case 'daily':
                $next_run = gmdate('Y-m-d H:i:s', $current_time + DAY_IN_SECONDS);
                break;
                
            case 'weekly':
                $next_run = gmdate('Y-m-d H:i:s', $current_time + WEEK_IN_SECONDS);
                break;
                
            default:
                $next_run = gmdate('Y-m-d H:i:s', $current_time + HOUR_IN_SECONDS);
        }
        
        $wpdb->update(
            $table_name,
            ['next_run' => $next_run, 'updated_at' => current_time('mysql')],
            ['id' => $campaign_id]
        );
    }
    
    /**
     * Search Google News for automation
     */
    private static function search_google_news_for_automation($params) {
        // Use existing Google News search method from ATM_API
        // This is a simplified version - you can expand based on your existing implementation
        
        $api_key = get_option('atm_google_news_search_api_key');
        $cse_id = get_option('atm_google_news_cse_id');
        
        if (empty($api_key) || empty($cse_id)) {
            throw new Exception('Google News Search API not configured. Please add API key and CSE ID in settings.');
        }
        
        $query = $params['query'];
        $countries = $params['countries'] ?? ['United States'];
        $gl = self::get_google_country_code($countries[0]);
        
        $url = add_query_arg([
            'key' => $api_key,
            'cx' => $cse_id,
            'q' => $query,
            'gl' => $gl,
            'num' => $params['per_page'] ?? 5,
            'sort' => 'date'
        ], 'https://www.googleapis.com/customsearch/v1');
        
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to search Google News: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new Exception('Google News API error: ' . $data['error']['message']);
        }
        
        $articles = [];
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $articles[] = [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'snippet' => $item['snippet'] ?? '',
                    'source' => parse_url($item['link'], PHP_URL_HOST),
                    'date' => date('Y-m-d H:i:s') // Google CSE doesn't provide dates
                ];
            }
        }
        
        return $articles;
    }
    
    /**
     * Search YouTube for automation
     */
    private static function search_youtube_for_automation($params) {
        $api_key = get_option('atm_google_youtube_api_key');
        
        if (empty($api_key)) {
            throw new Exception('YouTube API not configured. Please add API key in settings.');
        }
        
        $url = add_query_arg([
            'key' => $api_key,
            'part' => 'snippet',
            'type' => 'video',
            'q' => $params['query'],
            'order' => $params['order'] ?? 'relevance',
            'videoDuration' => $params['videoDuration'] ?? 'any',
            'maxResults' => $params['maxResults'] ?? 5
        ], 'https://www.googleapis.com/youtube/v3/search');
        
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to search YouTube: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new Exception('YouTube API error: ' . $data['error']['message']);
        }
        
        $videos = [];
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $videos[] = [
                    'id' => $item['id']['videoId'],
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'],
                    'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? '',
                    'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId']
                ];
            }
        }
        
        return $videos;
    }
    
    /**
     * Check if news article is already used
     */
    private static function is_news_article_used($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE article_url = %s",
            $url
        ));
        
        return $count > 0;
    }
    
    /**
     * Mark news article as used
     */
    private static function mark_news_article_as_used($url, $title, $post_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        
        $wpdb->replace($table_name, [
            'article_url' => $url,
            'article_title' => $title,
            'used_at' => current_time('mysql'),
            'post_id' => $post_id
        ]);
    }
    
    /**
     * Generate automation featured image
     */
    private static function generate_automation_featured_image($post_id, $title) {
        try {
            $image_prompt = ATM_API::get_default_image_prompt();
            $processed_prompt = str_replace('[article_title]', $title, $image_prompt);
            
            $image_result = ATM_API::generate_image_with_configured_provider(
                $processed_prompt,
                get_option('atm_image_size', '1792x1024'),
                get_option('atm_image_quality', 'hd')
            );
            
            if ($image_result['is_url']) {
                $attachment_id = self::set_image_from_url($image_result['data'], $post_id);
            } else {
                $attachment_id = self::set_image_from_data($image_result['data'], $post_id, $processed_prompt);
            }
            
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation: Featured image generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Set featured image from URL
     */
    private static function set_featured_image_from_url($post_id, $image_url) {
        $upload_dir = wp_upload_dir();
        $image_data = wp_remote_get($image_url);
        
        if (is_wp_error($image_data)) {
            return false;
        }
        
        $filename = basename($image_url);
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.jpg';
        }
        
        $file = $upload_dir['path'] . '/' . $filename;
        file_put_contents($file, wp_remote_retrieve_body($image_data));
        
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
    
    /**
     * Set image from data
     */
    private static function set_image_from_data($image_data, $post_id, $prompt) {
        $upload_dir = wp_upload_dir();
        $filename = 'atm-automation-' . $post_id . '-' . time() . '.png';
        $file = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents($file, $image_data);
        
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_text_field($prompt),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * Set image from URL
     */
    private static function set_image_from_url($image_url, $post_id) {
        return self::set_featured_image_from_url($post_id, $image_url);
    }
    
    /**
     * Get Google country code
     */
    private static function get_google_country_code($country) {
        $country_codes = [
            'United States' => 'US',
            'United Kingdom' => 'GB',
            'Canada' => 'CA',
            'Australia' => 'AU',
            'Germany' => 'DE',
            'France' => 'FR',
            'Italy' => 'IT',
            'Spain' => 'ES',
            'Japan' => 'JP',
            'China' => 'CN',
            'India' => 'IN',
            'Brazil' => 'BR',
            'Turkey' => 'TR',
            'Türkiye' => 'TR'
        ];
        
        return $country_codes[$country] ?? 'US';
    }
    
    /**
     * Check due campaigns and execute them
     */
    public static function check_and_execute_due_campaigns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $due_campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE is_active = 1 AND next_run <= %s",
            current_time('mysql')
        ));
        
        foreach ($due_campaigns as $campaign) {
            self::execute_campaign($campaign->id);
        }
    }
}