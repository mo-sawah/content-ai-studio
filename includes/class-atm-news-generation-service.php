<?php
/**
 * ATM News Generation Service
 * Handles all news-related content generation
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_News_Generation_Service {
    
    /**
     * Generate article from Google News search
     */
    public static function generate_from_news_source($params) {
        try {
            // Validate required parameters
            $required = ['source_url', 'source_title', 'post_id'];
            foreach ($required as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            $source_url = esc_url_raw($params['source_url']);
            $source_title = sanitize_text_field($params['source_title']);
            $source_snippet = wp_kses_post($params['source_snippet'] ?? '');
            $source_date = sanitize_text_field($params['source_date'] ?? '');
            $source_domain = sanitize_text_field($params['source_domain'] ?? '');
            $article_language = sanitize_text_field($params['article_language'] ?? 'English');
            $post_id = intval($params['post_id']);
            $generate_image = isset($params['generate_image']) && $params['generate_image'] === true;
            $is_automation = isset($params['is_automation']) && $params['is_automation'] === true;
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_article_from_news_source')) {
                throw new Exception('ATM_API news generation method not available');
            }
            
            $result = ATM_API::generate_article_from_news_source(
                $source_url,
                $source_title,
                $source_snippet,
                $source_date,
                $source_domain,
                $article_language
            );
            
            // Save subtitle if provided and post exists
            if ($post_id > 0 && !empty($result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $result['subtitle']);
                update_post_meta($post_id, '_atm_subtitle', $result['subtitle']);
            }
            
            // Track this article as used
            self::mark_news_article_as_used($source_url, $source_title, $post_id);
            
            // Generate featured image if requested
            if ($generate_image && $post_id > 0) {
                $image_result = ATM_Content_Generation_Service::generate_featured_image(
                    $post_id, 
                    $result['title']
                );
                $result['featured_image_generated'] = $image_result['success'];
                if (!$image_result['success']) {
                    $result['featured_image_error'] = $image_result['message'];
                }
            }
            
            return [
                'success' => true,
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? '',
                'source_url' => $source_url
            ];
            
        } catch (Exception $e) {
            error_log('ATM News Generation Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate article from Twitter/X news
     */
    public static function generate_from_tweets($params) {
        try {
            $required = ['keyword', 'selected_tweets'];
            foreach ($required as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            $keyword = sanitize_text_field($params['keyword']);
            $selected_tweets = $params['selected_tweets'];
            $article_language = sanitize_text_field($params['article_language'] ?? 'English');
            $post_id = intval($params['post_id'] ?? 0);
            
            if (!class_exists('ATM_Twitter_API') || !method_exists('ATM_Twitter_API', 'generate_article_from_tweets')) {
                throw new Exception('ATM_Twitter_API not available');
            }
            
            $result = ATM_Twitter_API::generate_article_from_tweets($keyword, $selected_tweets, $article_language);
            
            // Save subtitle if provided
            if ($post_id > 0 && !empty($result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $result['subtitle']);
                update_post_meta($post_id, '_atm_subtitle', $result['subtitle']);
            }
            
            return [
                'success' => true,
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? ''
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate article from RSS feed
     */
    public static function generate_from_rss($params) {
        try {
            $required = ['article_url', 'article_guid'];
            foreach ($required as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            $url = esc_url_raw($params['article_url']);
            $guid = sanitize_text_field($params['article_guid']);
            $use_full_content = isset($params['use_full_content']) && $params['use_full_content'] === true;
            $post_id = intval($params['post_id'] ?? 0);
            $rss_content = isset($params['rss_content']) ? wp_kses_post(stripslashes($params['rss_content'])) : '';
            
            $final_url = method_exists('ATM_API', 'resolve_redirect_url') ? 
                ATM_API::resolve_redirect_url($url) : $url;
            
            $source_content = '';
            if ($use_full_content) {
                try {
                    if (method_exists('ATM_API', 'fetch_full_article_content')) {
                        $source_content = ATM_API::fetch_full_article_content($final_url);
                    }
                } catch (Exception $e) {
                    error_log('ATM RSS: Scraping failed, falling back to RSS content: ' . $e->getMessage());
                }
            }
            
            if (empty($source_content) && !empty($rss_content)) {
                $source_content = $rss_content;
            }
            
            if (empty($source_content)) {
                throw new Exception('Could not extract sufficient content from the source article.');
            }
            
            if (strlen($source_content) > 4000 && method_exists('ATM_API', 'summarize_content_for_rewrite')) {
                $source_content = ATM_API::summarize_content_for_rewrite($source_content);
            }
            
            $system_prompt = self::get_news_generation_prompt();
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'enhance_content_with_openrouter')) {
                throw new Exception('ATM_API content generation not available');
            }
            
            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $source_content], 
                $system_prompt, 
                get_option('atm_article_model', 'openai/gpt-4o'), 
                true
            );
            
            // Parse JSON response
            $json_string = trim($raw_response);
            if (!str_starts_with($json_string, '{')) {
                if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                    $json_string = $matches[0];
                } else {
                    throw new Exception('The AI returned a non-JSON response. Please try again.');
                }
            }

            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('ATM Plugin - Invalid JSON from AI: ' . $json_string);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }

            $headline = $result['title'] ?? $result['headline'] ?? '';
            $subtitle = $result['subheadline'] ?? $result['subtitle'] ?? '';
            $content = trim($result['content'] ?? '');

            if (empty($headline) || empty($content)) {
                throw new Exception('AI response missing required fields.');
            }

            // Save subtitle if provided
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                update_post_meta($post_id, '_atm_subtitle', $subtitle);
                
                // Track used RSS GUID
                $used_guids = get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [];
                $used_guids[] = $guid;
                update_post_meta($post_id, '_atm_used_rss_guids', array_unique($used_guids));
            }

            return [
                'success' => true,
                'article_title' => $headline,
                'article_content' => $content,
                'subtitle' => $subtitle
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate article from live news
     */
    public static function generate_from_live_news($params) {
        try {
            $required = ['keyword', 'category_title', 'category_sources'];
            foreach ($required as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            $keyword = sanitize_text_field($params['keyword']);
            $category_title = sanitize_text_field($params['category_title']);
            $category_sources = $params['category_sources'];
            $post_id = intval($params['post_id'] ?? 0);
            
            // Get previous angles for this keyword to ensure uniqueness
            $previous_angles = get_option('atm_live_news_angles_' . md5($keyword), []);

            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_live_news_article')) {
                throw new Exception('ATM_API live news generation not available');
            }

            $result = ATM_API::generate_live_news_article($keyword, $category_title, $category_sources, $previous_angles);

            // Store the new angle to prevent duplication in future generations
            if (!empty($result['angle'])) {
                $previous_angles[] = $result['angle'];
                // Keep only the last 10 angles to prevent unlimited growth
                $previous_angles = array_slice($previous_angles, -10);
                update_option('atm_live_news_angles_' . md5($keyword), $previous_angles);
            }

            // Save subtitle if provided
            if ($post_id > 0 && !empty($result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $result['subtitle']);
                update_post_meta($post_id, '_atm_subtitle', $result['subtitle']);
            }

            return [
                'success' => true,
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? '',
                'angle' => $result['angle'] ?? ''
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate regular news article from API
     */
    public static function generate_news_article($params) {
        try {
            $topic = sanitize_text_field($params['topic'] ?? '');
            $model_override = sanitize_text_field($params['model'] ?? get_option('atm_article_model'));
            $force_fresh = isset($params['force_fresh']) && $params['force_fresh'] === true;
            $news_source = sanitize_key($params['news_source'] ?? 'newsapi');
            $post_id = intval($params['post_id'] ?? 0);
            
            if (empty($topic)) {
                throw new Exception("Please provide a topic for the news article.");
            }
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'fetch_news')) {
                throw new Exception('ATM_API news fetch method not available');
            }
            
            $news_context = ATM_API::fetch_news($topic, $news_source, $force_fresh);
            
            if (empty($news_context)) {
                throw new Exception("No recent news found for the topic: '" . esc_html($topic) . "'. Please try a different keyword or source.");
            }
            
            $system_prompt = self::get_news_generation_prompt();
            
            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $news_context], 
                $system_prompt, 
                $model_override, 
                true
            );

            // Parse JSON response (same logic as RSS)
            $json_string = trim($raw_response);
            if (!str_starts_with($json_string, '{')) {
                if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                    $json_string = $matches[0];
                } else {
                    throw new Exception('The AI returned a non-JSON response. Please try again.');
                }
            }

            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
                error_log('ATM Plugin - Invalid JSON from AI: ' . $json_string);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }

            $headline = $result['title'] ?? $result['headline'] ?? '';
            $subtitle = $result['subheadline'] ?? $result['subtitle'] ?? '';
            $final_content = trim($result['content']);

            // Save subtitle if provided
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                update_post_meta($post_id, '_atm_subtitle', $subtitle);
            }

            if (empty($headline) || empty($final_content)) {
                throw new Exception('Generated title or content is empty.');
            }

            return [
                'success' => true,
                'article_title' => $headline,
                'article_content' => $final_content,
                'subtitle' => $subtitle
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create post from news content
     */
    public static function create_post_from_news($content_data, $post_params) {
        if (!$content_data['success']) {
            return $content_data;
        }
        
        // Use the main content service for post creation
        return ATM_Content_Generation_Service::create_post_from_content($content_data, $post_params);
    }
    
    /**
     * Search Google News
     */
    public static function search_google_news($params) {
        try {
            $query = sanitize_text_field($params['query'] ?? '');
            $page = max(1, intval($params['page'] ?? 1));
            $per_page = max(1, min(50, intval($params['per_page'] ?? 10)));
            $source_languages = isset($params['source_languages']) && is_array($params['source_languages']) ? 
                array_map('sanitize_text_field', $params['source_languages']) : [];
            $countries = isset($params['countries']) && is_array($params['countries']) ? 
                array_map('sanitize_text_field', $params['countries']) : [];
            
            if (empty($query)) {
                throw new Exception('Search query is required.');
            }

            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'search_google_news_direct')) {
                throw new Exception('ATM_API Google News search not available');
            }

            $articles = ATM_API::search_google_news_direct($query, $page, $per_page, $source_languages, $countries);
            
            return [
                'success' => true,
                'articles' => $articles['results'],
                'query' => $query,
                'page' => $page,
                'per_page' => $per_page,
                'total' => $articles['total_results'] ?? count($articles['results'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search Twitter news
     */
    public static function search_twitter_news($params) {
        try {
            $keyword = sanitize_text_field($params['keyword'] ?? '');
            $verified_only = isset($params['verified_only']) && $params['verified_only'] === true;
            $credible_sources_only = isset($params['credible_sources_only']) && $params['credible_sources_only'] === true;
            $min_followers = intval($params['min_followers'] ?? 10000);
            $max_results = min(50, intval($params['max_results'] ?? 20));

            if (empty($keyword)) {
                throw new Exception('Search keyword is required.');
            }

            $filters = [
                'verified_only' => $verified_only,
                'credible_sources_only' => $credible_sources_only,
                'min_followers' => $min_followers,
                'max_results' => $max_results,
                'language' => 'en'
            ];

            if (!class_exists('ATM_Twitter_API') || !method_exists('ATM_Twitter_API', 'search_twitter_news')) {
                throw new Exception('ATM_Twitter_API not available');
            }

            $results = ATM_Twitter_API::search_twitter_news($keyword, $filters);

            return [
                'success' => true,
                'results' => $results['results'],
                'total' => $results['total'],
                'keyword' => $keyword
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search live news
     */
    public static function search_live_news($params) {
        try {
            $keyword = sanitize_text_field($params['keyword'] ?? '');
            $force_fresh = isset($params['force_fresh']) && $params['force_fresh'] === true;

            if (empty($keyword)) {
                throw new Exception('Keyword is required for live news search.');
            }

            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'search_live_news_with_openrouter')) {
                throw new Exception('ATM_API live news search not available');
            }

            $categories = ATM_API::search_live_news_with_openrouter($keyword, $force_fresh);

            return [
                'success' => true,
                'categories' => $categories,
                'keyword' => $keyword,
                'cached' => !$force_fresh
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get news generation system prompt
     */
    private static function get_news_generation_prompt() {
        return 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English. **Use your web search ability to verify the information and add any missing context.**

Follow these strict guidelines:
- **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human.
- **Originality**: Do not copy verbatim from the source. You must rewrite, summarize, and humanize the content.
- **Length**: Aim for 800–1200 words.
- **IMPORTANT**: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.
- The `content` field must NOT start with a title. It must begin directly with the introductory paragraph in a news article style.
- Do NOT include a final heading titled "Conclusion". The article should end naturally with the concluding paragraph itself.

**Link Formatting Rules:**
- When including external links, NEVER use the website URL as the anchor text
- Use ONLY 1-3 descriptive words as anchor text
- Keep anchor text extremely concise (maximum 2 words)
- Make links feel natural within the sentence flow
- Avoid long phrases as anchor text

**Final Output Format:**
Your entire output MUST be a single, valid JSON object with three keys:
1. "title": A concise, factual, and compelling headline for the new article.
2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.';
    }
    
    /**
     * Mark news article as used (prevent duplication)
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
     * Check if news article is already used
     */
    public static function is_news_article_used($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE article_url = %s",
            $url
        ));
        
        return $count > 0;
    }
}