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
    $start_time = microtime(true);
    $start_memory = memory_get_usage();
    
    try {
        // Mark campaign as running
        ATM_Automation_Database::update_campaign_status($campaign_id, 'running');
        
        $campaign = ATM_Automation_Database::get_campaign($campaign_id);
        if (!$campaign) {
            throw new Exception("Campaign not found: $campaign_id");
        }

        if (!$campaign->is_active) {
            ATM_Automation_Database::update_campaign_status($campaign_id, 'paused');
            return ['success' => false, 'message' => 'Campaign is not active'];
        }

        $settings = json_decode($campaign->settings, true) ?: [];
        
        // Execute based on type
        $result = null;
        switch ($campaign->type) {
            case 'articles':
                $result = self::execute_article_campaign($campaign, $settings);
                break;
            case 'news':
                $result = self::execute_news_campaign($campaign, $settings);
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

        // Calculate execution metrics
        $execution_time = microtime(true) - $start_time;
        $memory_usage = memory_get_usage() - $start_memory;
        
        if ($result['success']) {
            // Update next run time
            ATM_Automation_Database::update_next_run(
                $campaign_id, 
                $campaign->schedule_value, 
                $campaign->schedule_unit
            );
            
            // Mark as idle and log success
            ATM_Automation_Database::update_campaign_status($campaign_id, 'idle');
            ATM_Automation_Database::log_execution(
                $campaign_id, 
                'completed', 
                'Successfully generated content', 
                $result['post_id'] ?? null,
                $execution_time,
                format_bytes($memory_usage)
            );
        } else {
            // Mark as failed and log error
            ATM_Automation_Database::update_campaign_status($campaign_id, 'failed');
            ATM_Automation_Database::log_execution(
                $campaign_id, 
                'failed', 
                $result['message'] ?? 'Unknown error',
                null,
                $execution_time,
                format_bytes($memory_usage)
            );
        }
        
        return $result;

    } catch (Exception $e) {
        // Mark as failed and log error
        ATM_Automation_Database::update_campaign_status($campaign_id, 'failed');
        ATM_Automation_Database::log_execution(
            $campaign_id, 
            'failed', 
            $e->getMessage(),
            null,
            microtime(true) - $start_time,
            format_bytes(memory_get_usage() - $start_memory)
        );
        
        error_log('ATM Automation Campaign Execution Error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

    /**
     * Execute article campaign (with sub-types)
     */
    private static function execute_article_campaign($campaign, $settings) {
        $sub_type = $campaign->sub_type ?? 'standard';
        
        switch ($sub_type) {
            case 'standard':
                return self::execute_article_automation($campaign, $settings);
            case 'trending':
                return self::execute_trending_automation($campaign, $settings);
            case 'listicle':
                return self::execute_listicle_automation($campaign, $settings);
            case 'multipage':
                return self::execute_multipage_automation($campaign, $settings);
            default:
                return self::execute_article_automation($campaign, $settings);
        }
    }

    /**
     * Execute news campaign (with sub-types)
     */
    private static function execute_news_campaign($campaign, $settings) {
        $sub_type = $campaign->sub_type ?? 'search';
        
        // Map sub_type to news_method for backward compatibility
        $news_method_map = [
            'search' => 'google_news',
            'twitter' => 'twitter',
            'rss' => 'rss',
            'apis' => 'api_news',
            'live' => 'live_news'
        ];
        
        $settings['news_method'] = $news_method_map[$sub_type] ?? 'google_news';
        
        return self::execute_news_automation($campaign, $settings);
    }
    
    /**
     * Execute article automation campaign
     */
    private static function execute_article_automation($campaign, $settings) {
        try {
            // Prepare parameters for the unified service
            $params = [
                'keyword' => $campaign->keyword,
                'article_title' => '', // Let the service generate title with angle
                'post_id' => 0, // No existing post for automation
                'model' => $settings['ai_model'] ?? get_option('atm_article_model', 'openai/gpt-4o'),
                'writing_style' => $settings['writing_style'] ?? 'default_seo',
                'custom_prompt' => $settings['custom_prompt'] ?? '',
                'word_count' => $settings['word_count'] ?? 0,
                'creativity_level' => $settings['creativity_level'] ?? 'high',
                'is_automation' => true
            ];
            
            error_log("ATM Automation: Generating content for campaign: " . $campaign->name);
            
            // Use the unified service to generate content
            $content_result = ATM_Content_Generation_Service::generate_article_content($params);
            
            if (!$content_result['success']) {
                throw new Exception($content_result['message']);
            }
            
            // Prepare post parameters
            $post_params = [
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                'campaign_id' => $campaign->id,
                'generate_image' => $settings['generate_image'] ?? false
            ];
            
            // Create the post
            $post_result = ATM_Content_Generation_Service::create_post_from_content($content_result, $post_params);
            
            if ($post_result['success']) {
                error_log("ATM Automation: Successfully created post ID {$post_result['post_id']} for campaign '{$campaign->name}'");
                return $post_result;
            } else {
                throw new Exception($post_result['message']);
            }
            
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
            // Determine which news generation method to use based on settings
            $news_method = $settings['news_method'] ?? 'google_news'; // google_news, rss, live_news, twitter, api_news
            
            switch ($news_method) {
                case 'google_news':
                    return self::execute_google_news_automation($campaign, $settings);
                    
                case 'rss':
                    return self::execute_rss_automation($campaign, $settings);
                    
                case 'live_news':
                    return self::execute_live_news_automation($campaign, $settings);
                    
                case 'twitter':
                    return self::execute_twitter_news_automation($campaign, $settings);
                    
                case 'api_news':
                    return self::execute_api_news_automation($campaign, $settings);
                    
                default:
                    return self::execute_google_news_automation($campaign, $settings); // Default fallback
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation News Generation Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute Google News automation
     */
    private static function execute_google_news_automation($campaign, $settings) {
        try {
            // Search for news articles using the unified service
            $search_params = [
                'query' => $campaign->keyword,
                'page' => 1,
                'per_page' => 5,
                'source_languages' => $settings['source_languages'] ?? [],
                'countries' => $settings['countries'] ?? ['United States']
            ];
            
            $search_result = ATM_News_Generation_Service::search_google_news($search_params);
            
            if (!$search_result['success'] || empty($search_result['articles'])) {
                throw new Exception('No recent news articles found for keyword: ' . $campaign->keyword);
            }
            
            // Select best article (first unused one)
            $selected_article = null;
            foreach ($search_result['articles'] as $article) {
                if (!ATM_News_Generation_Service::is_news_article_used($article['link'])) {
                    $selected_article = $article;
                    break;
                }
            }
            
            if (!$selected_article) {
                throw new Exception('All recent news articles have already been used.');
            }
            
            // Generate article from news source using unified service
            $generation_params = [
                'source_url' => $selected_article['link'],
                'source_title' => $selected_article['title'],
                'source_snippet' => $selected_article['snippet'] ?? '',
                'source_date' => $selected_article['date'] ?? '',
                'source_domain' => $selected_article['source'] ?? '',
                'article_language' => $settings['article_language'] ?? 'English',
                'post_id' => 0, // No existing post for automation
                'generate_image' => $settings['generate_image'] ?? false,
                'is_automation' => true
            ];
            
            $content_result = ATM_News_Generation_Service::generate_from_news_source($generation_params);
            
            if (!$content_result['success']) {
                throw new Exception($content_result['message']);
            }
            
            // Create post using unified service
            $post_params = [
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                'campaign_id' => $campaign->id,
                'generate_image' => $settings['generate_image'] ?? false
            ];
            
            $post_result = ATM_News_Generation_Service::create_post_from_news($content_result, $post_params);
            
            if ($post_result['success']) {
                error_log("ATM Automation: Successfully created Google News post ID {$post_result['post_id']} for campaign '{$campaign->name}'");
                return $post_result;
            } else {
                throw new Exception($post_result['message']);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation Google News Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute Live News automation
     */
    private static function execute_live_news_automation($campaign, $settings) {
        try {
            // Search live news using unified service
            $search_params = [
                'keyword' => $campaign->keyword,
                'force_fresh' => true // Always get fresh news for automation
            ];
            
            $search_result = ATM_News_Generation_Service::search_live_news($search_params);
            
            if (!$search_result['success'] || empty($search_result['categories'])) {
                throw new Exception('No live news categories found for keyword: ' . $campaign->keyword);
            }
            
            // Select first category with sources
            $selected_category = null;
            foreach ($search_result['categories'] as $category) {
                if (!empty($category['sources'])) {
                    $selected_category = $category;
                    break;
                }
            }
            
            if (!$selected_category) {
                throw new Exception('No live news categories with sources found.');
            }
            
            // Generate article from live news using unified service
            $generation_params = [
                'keyword' => $campaign->keyword,
                'category_title' => $selected_category['title'],
                'category_sources' => $selected_category['sources'],
                'post_id' => 0,
                'is_automation' => true
            ];
            
            $content_result = ATM_News_Generation_Service::generate_from_live_news($generation_params);
            
            if (!$content_result['success']) {
                throw new Exception($content_result['message']);
            }
            
            // Create post using unified service
            $post_params = [
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                'campaign_id' => $campaign->id,
                'generate_image' => $settings['generate_image'] ?? false
            ];
            
            $post_result = ATM_News_Generation_Service::create_post_from_news($content_result, $post_params);
            
            if ($post_result['success']) {
                error_log("ATM Automation: Successfully created Live News post ID {$post_result['post_id']} for campaign '{$campaign->name}'");
                return $post_result;
            } else {
                throw new Exception($post_result['message']);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation Live News Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Execute API News automation
     */
    private static function execute_api_news_automation($campaign, $settings) {
        try {
            // Generate news article using unified service
            $generation_params = [
                'topic' => $campaign->keyword,
                'model' => $settings['ai_model'] ?? get_option('atm_article_model', 'openai/gpt-4o'),
                'force_fresh' => true,
                'news_source' => $settings['news_source'] ?? 'newsapi',
                'post_id' => 0,
                'is_automation' => true
            ];
            
            $content_result = ATM_News_Generation_Service::generate_news_article($generation_params);
            
            if (!$content_result['success']) {
                throw new Exception($content_result['message']);
            }
            
            // Create post using unified service
            $post_params = [
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                'campaign_id' => $campaign->id,
                'generate_image' => $settings['generate_image'] ?? false
            ];
            
            $post_result = ATM_News_Generation_Service::create_post_from_news($content_result, $post_params);
            
            if ($post_result['success']) {
                error_log("ATM Automation: Successfully created API News post ID {$post_result['post_id']} for campaign '{$campaign->name}'");
                return $post_result;
            } else {
                throw new Exception($post_result['message']);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation API News Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute video automation campaign
     */
    private static function execute_video_automation($campaign, $settings) {
        try {
            // Search for videos using unified service
            $search_params = [
                'query' => $campaign->keyword,
                'filters' => [
                    'order' => $settings['video_order'] ?? 'relevance',
                    'videoDuration' => $settings['video_duration'] ?? 'any',
                ]
            ];
            
            $search_result = ATM_Media_Generation_Service::search_youtube_videos($search_params);
            
            if (!$search_result['success'] || empty($search_result['results'])) {
                throw new Exception('No videos found for keyword: ' . $campaign->keyword);
            }
            
            // Select first video
            $selected_video = $search_result['results'][0];
            
            // Create post with embedded video using unified service
            $video_params = [
                'video_data' => $selected_video,
                'post_params' => [
                    'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                    'post_author' => $campaign->author_id,
                    'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                    'campaign_id' => $campaign->id,
                    'generate_image' => $settings['generate_image'] ?? false
                ]
            ];
            
            $post_result = ATM_Media_Generation_Service::create_video_post($video_params);
            
            if ($post_result['success']) {
                error_log("ATM Automation: Successfully created Video post ID {$post_result['post_id']} for campaign '{$campaign->name}'");
                return $post_result;
            } else {
                throw new Exception($post_result['message']);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation Video Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute podcast automation campaign
     */
    private static function execute_podcast_automation($campaign, $settings) {
        try {
            // First generate an article using the main content service
            $article_params = [
                'keyword' => $campaign->keyword,
                'article_title' => '', // Let the service generate title with angle
                'post_id' => 0, // No existing post for automation
                'model' => $settings['ai_model'] ?? get_option('atm_article_model', 'openai/gpt-4o'),
                'writing_style' => $settings['writing_style'] ?? 'default_seo',
                'custom_prompt' => $settings['custom_prompt'] ?? '',
                'word_count' => $settings['word_count'] ?? 800,
                'creativity_level' => $settings['creativity_level'] ?? 'high',
                'is_automation' => true
            ];
            
            $article_result = ATM_Content_Generation_Service::generate_article_content($article_params);
            
            if (!$article_result['success']) {
                throw new Exception('Failed to generate base article for podcast: ' . $article_result['message']);
            }
            
            // Create the post first
            $post_params = [
                'post_status' => $campaign->content_mode === 'publish' ? 'publish' : 'draft',
                'post_author' => $campaign->author_id,
                'post_category' => $campaign->category_id ? [$campaign->category_id] : [],
                'campaign_id' => $campaign->id,
                'generate_image' => $settings['generate_image'] ?? false
            ];
            
            $post_result = ATM_Content_Generation_Service::create_post_from_content($article_result, $post_params);
            
            if (!$post_result['success']) {
                throw new Exception('Failed to create base post for podcast: ' . $post_result['message']);
            }
            
            $post_id = $post_result['post_id'];
            
            // Generate podcast script using unified service
            $script_params = [
                'content' => $article_result['article_content'],
                'language' => $settings['podcast_language'] ?? 'English',
                'post_id' => $post_id,
                'duration' => $settings['podcast_duration'] ?? 'medium'
            ];
            
            $script_result = ATM_Media_Generation_Service::generate_podcast_script($script_params);
            
            if (!$script_result['success']) {
                throw new Exception('Failed to generate podcast script: ' . $script_result['message']);
            }
            
            // If it's a background job, save job ID and return
            if (isset($script_result['job_id'])) {
                update_post_meta($post_id, '_atm_script_job_id', $script_result['job_id']);
                update_post_meta($post_id, '_atm_automation_status', 'generating_script');
                
                return [
                    'success' => true,
                    'post_id' => $post_id,
                    'post_url' => get_permalink($post_id),
                    'script_job_id' => $script_result['job_id'],
                    'message' => 'Article created, podcast script generation in progress'
                ];
            }
            
            // Generate podcast audio using unified service
            $audio_params = [
                'post_id' => $post_id,
                'script' => $script_result['script'],
                'host_a_voice' => $settings['host_a_voice'] ?? 'alloy',
                'host_b_voice' => $settings['host_b_voice'] ?? 'nova',
                'provider' => $settings['audio_provider'] ?? 'openai'
            ];
            
            $audio_result = ATM_Media_Generation_Service::generate_podcast_audio($audio_params);
            
            // Save script and job info to post meta
            update_post_meta($post_id, '_atm_podcast_script', $script_result['script']);
            
            if ($audio_result['success'] && isset($audio_result['job_id'])) {
                update_post_meta($post_id, '_atm_podcast_job_id', $audio_result['job_id']);
                update_post_meta($post_id, '_atm_automation_status', 'generating_audio');
            }
            
            error_log("ATM Automation: Successfully created Podcast post ID {$post_id} for campaign '{$campaign->name}'");
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'podcast_job_id' => $audio_result['job_id'] ?? null,
                'message' => 'Article created, podcast generation in progress'
            ];
            
        } catch (Exception $e) {
            error_log('ATM Automation Podcast Error: ' . $e->getMessage());
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