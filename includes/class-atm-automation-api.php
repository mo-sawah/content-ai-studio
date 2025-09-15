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

    /**
     * Format bytes into human readable format
     */
    if (!function_exists('format_bytes')) {
        function format_bytes($size, $precision = 2) {
            if ($size <= 0) {
                return '0 B';
            }
            
            $base = log($size, 1024);
            $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
            
            return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
        }
    }

class ATM_Automation_API {

    public static function generate_article_from_trend_automation($topic, $settings, $language = 'English') {
        // Extract all settings with proper defaults
        $writing_style = esc_html($settings['writing_style'] ?? 'news');
        $word_count = $settings['word_count'] ?? 0;
        $ai_model = $settings['ai_model'] ?? 'openai/gpt-4o'; // Default to GPT-4o
        $enable_web_search = $settings['enable_web_search'] ?? true;
        $creativity_level = $settings['creativity_level'] ?? 'high';
        $custom_prompt = $settings['custom_prompt'] ?? '';
        
        // Convert word count to range
        $word_count_text = $word_count > 0 ? "approximately {$word_count}" : '800-1200';
        
        // Build the comprehensive system prompt
        $base_style_instruction = '';
        switch ($writing_style) {
            case 'news':
                $base_style_instruction = 'professional journalistic tone, objective and fact-based';
                break;
            case 'professional':
                $base_style_instruction = 'professional business tone, authoritative and polished';
                break;
            case 'conversational':
                $base_style_instruction = 'conversational and friendly tone, engaging and approachable';
                break;
            case 'technical':
                $base_style_instruction = 'technical and expert tone, detailed and precise';
                break;
            case 'educational':
                $base_style_instruction = 'educational and tutorial tone, clear and instructional';
                break;
            default:
                $base_style_instruction = 'professional SEO-optimized tone, engaging and informative';
        }
        
        $system_prompt = "You are an expert trending content writer and news analyst. Your task is to create a compelling, highly relevant article about trending topics using your web search capabilities for current information.

        **TRENDING TOPIC ANALYSIS:**
        - **Topic Title:** {$topic['title']}
        - **Initial Context:** {$topic['snippet']}
        - **Traffic Level:** {$topic['traffic']}

        **CRITICAL INSTRUCTIONS:**
        1. **Language**: Write the entire article in {$language}. This is mandatory.
        2. **Style**: Use a {$base_style_instruction}. Write like a professional human journalist.
        3. **Current Information**: Use your web search ability extensively to:
        - Verify all facts and claims
        - Add the most recent developments
        - Include current statistics and data
        - Reference recent events and context
        4. **Relevance**: The article must be directly relevant to the trending topic
        5. **Length**: Aim for {$word_count_text} words
        6. **Originality**: Create unique content, don't copy from sources

        **CONTENT STRUCTURE REQUIREMENTS:**
        - The `content` field must NOT contain any H1 headings (`# Heading`)
        - Use H2 (`##`) for main section headings only
        - Do NOT start with a title - begin with the introductory paragraph
        - Do NOT include final headings like \"Conclusion\", \"Summary\", \"Final Thoughts\"
        - End naturally with a concluding paragraph (no heading above it)

        **LINK FORMATTING RULES:**
        - When including external links, NEVER use URLs as anchor text
        - Use only 1-3 descriptive words as anchor text
        - Keep anchor text concise (maximum 2 words)
        - Make links feel natural in the sentence flow
        - Example: [Reuters](url) reported that... or according to [BBC News](url)

        **WEB SEARCH USAGE:**
        Since web search is " . ($enable_web_search ? 'ENABLED' : 'DISABLED') . ", " . 
        ($enable_web_search ? 
            "you MUST use it extensively to gather current information, verify facts, and add recent developments to make the article comprehensive and up-to-date." :
            "rely on your existing knowledge but focus on the specific trending angle provided."
        );
        
        // Add custom prompt if provided
        if (!empty($custom_prompt)) {
            $system_prompt .= "\n\n**ADDITIONAL CUSTOM INSTRUCTIONS:**\n" . $custom_prompt;
        }
        
        $system_prompt .= "\n\n**FINAL OUTPUT FORMAT:**
        Your output MUST be a valid JSON object with exactly three keys:
        1. \"title\": A compelling, trending-focused headline in {$language} that captures the essence of this trending topic
        2. \"subheadline\": A brief, engaging subtitle that expands on the main headline
        3. \"content\": The complete article in {$language}, formatted with Markdown, following all structure requirements above

        **AI MODEL BEING USED:** {$ai_model}
        **CREATIVITY LEVEL:** {$creativity_level}
        **WEB SEARCH:** " . ($enable_web_search ? 'Enabled - Use extensively' : 'Disabled - Use existing knowledge');

        error_log("ATM Trending Generation: Using model {$ai_model} with web search " . ($enable_web_search ? 'enabled' : 'disabled'));
        
        // Make the API call to OpenRouter with all settings
        $raw_response = ATM_API::enhance_content_with_openrouter(
            ['content' => $topic['title']],
            $system_prompt,
            $ai_model, // Use the specified OpenRouter model
            true, // JSON mode
            $enable_web_search, // Use web search setting
            $creativity_level // Use creativity setting
        );

        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Trending: Invalid AI response: ' . $raw_response);
            throw new Exception('The AI returned an invalid response structure. Please try again.');
        }
        
        // Validate required fields
        if (empty($result['title']) || empty($result['content'])) {
            throw new Exception('Generated article is missing required title or content.');
        }
        
        error_log("ATM Trending Generation: Successfully generated article '{$result['title']}' using {$ai_model}");
        
        return [
            'title' => $result['title'],
            'content' => $result['content'],
            'subheadline' => $result['subheadline'] ?? $result['subtitle'] ?? '',
            'ai_model_used' => $ai_model,
            'web_search_used' => $enable_web_search,
            'word_count_target' => $word_count,
            'creativity_level' => $creativity_level
        ];
    }

    /**
     * Enhanced 2-API Call Trending System with Topic Understanding
     * Replace the execute_trending_automation method in class-atm-automation-api.php
     */
    private static function execute_trending_automation($campaign, $settings) {
        try {
            $base_keyword = $campaign->keyword;
            $region = $settings['trending_region'] ?? 'US';
            $language = $settings['trending_language'] ?? 'en';
            $ai_model = $settings['ai_model'] ?? 'openai/gpt-4o';
            $writing_style = $settings['writing_style'] ?? 'news';
            $word_count = $settings['word_count'] ?? 0;
            $creativity_level = $settings['creativity_level'] ?? 'high';
            $custom_prompt = $settings['custom_prompt'] ?? '';
            
            error_log("ATM Trending: Starting 2-API approach for keyword: {$base_keyword}");
            
            // Get trending topics
            $trending_result = ATM_API::fetch_trending_topics($base_keyword, $region, $language, 'now 1-d', true);
            
            if (empty($trending_result['trends'])) {
                throw new Exception('No trending topics found for keyword: ' . $base_keyword);
            }
            
            // Select most relevant trending topic
            $relevant_trends = self::filter_trending_topics_by_relevance($trending_result['trends'], $base_keyword);
            $selected_topic = !empty($relevant_trends) ? $relevant_trends[0] : $trending_result['trends'][0];
            
            error_log("ATM Trending: Selected topic: " . $selected_topic['title']);
            
            // Get recent titles to avoid duplication
            $recent_titles = self::get_recent_trending_titles($campaign->id, 7);
            $avoid_titles = !empty($recent_titles) ? "\n\nRECENT TITLES TO AVOID:\n- " . implode("\n- ", $recent_titles) : "";
            
            // ========== API CALL 1: RESEARCH & TITLE GENERATION ==========
            $research_prompt = "You are a trending topic research expert. Your task is to deeply understand why a topic is trending and create the perfect title.

**RESEARCH TARGET:**
- Base Keyword: \"{$base_keyword}\"
- Trending Topic: \"{$selected_topic['title']}\"
- Context: {$selected_topic['snippet']}

**RESEARCH PHASE:**
1. Use extensive web search to understand WHY this topic is trending right now
2. Identify the specific events, developments, or news that made it trend
3. Understand the topic category (news, entertainment, fashion, celebrity, technology, etc.)
4. Find the most current and newsworthy angle
5. Determine what makes this topic compelling to audiences

**TITLE CREATION REQUIREMENTS:**
- Must include the exact keyword: \"{$base_keyword}\"
- Should reflect WHY the topic is trending
- Must be compelling and clickable
- Should match the topic category (news, celebrity, fashion, etc.)
- 8-15 words long
- Professional and engaging{$avoid_titles}

**IMPORTANT: Return your response as a JSON object with this exact structure:**
{
    \"topic_category\": \"Type of trending topic (news, celebrity, fashion, technology, etc.)\",
    \"trending_reason\": \"Why this topic is trending right now\",
    \"key_developments\": \"Main events or developments driving the trend\",
    \"target_audience\": \"Who would be interested in this topic\",
    \"recommended_title\": \"Perfect title that includes '{$base_keyword}'\",
    \"content_angle\": \"Best angle for the article content\"
}

Research thoroughly using web search to understand the trending context and return the results in JSON format.";

            $research_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $selected_topic['title']],
                $research_prompt,
                $ai_model,
                true,  // JSON mode
                true,  // Web search enabled
                'high' // High creativity for research
            );
            
            $research_result = json_decode($research_response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($research_result['recommended_title'])) {
                throw new Exception('Failed to research topic and generate title');
            }
            
            $researched_title = $research_result['recommended_title'];
            $topic_category = $research_result['topic_category'] ?? 'general';
            $trending_reason = $research_result['trending_reason'] ?? '';
            
            error_log("ATM Trending: Generated title: {$researched_title}");
            error_log("ATM Trending: Topic category: {$topic_category}");
            
            // Validate keyword inclusion
            if (stripos($researched_title, $base_keyword) === false) {
                throw new Exception("Generated title doesn't include the keyword '{$base_keyword}'");
            }
            
            // ========== API CALL 2: CONTENT GENERATION ==========
            
            // Get writing style description
            $style_descriptions = [
                'default_seo' => 'SEO-optimized and informative',
                'professional' => 'professional business tone',
                'conversational' => 'conversational and friendly',
                'technical' => 'technical and expert-level',
                'news' => 'professional journalistic',
                'educational' => 'educational and tutorial-style'
            ];
            
            $style_description = $style_descriptions[$writing_style] ?? 'professional and engaging';
            $word_count_text = $word_count > 0 ? "approximately {$word_count}" : '800-1200';
            $key_developments = $research_result['key_developments'] ?? 'Latest developments';
            $target_audience  = $research_result['target_audience'] ?? 'general readers';
            
            $content_prompt = "You are a professional content writer specializing in {$topic_category} content. Write a comprehensive article based on the research provided.

**ARTICLE REQUIREMENTS:**
- **Exact Title:** {$researched_title}
- **Topic Category:** {$topic_category}
- **Why It's Trending:** {$trending_reason}
- **Writing Style:** {$style_description}
- **Length:** {$word_count_text} words

**CONTENT RESEARCH:**
Use web search extensively to gather current information about:
- {$key_developments}
- Current status and updates
- Relevant quotes, statistics, and facts
- Impact and implications

**STYLE GUIDELINES:**
- Write in {$style_description} tone
- Match the {$topic_category} content style
- Use current, accurate information from web search
- Include relevant details that explain why this is trending
- Make it engaging for the target audience: {$target_audience}

**STRUCTURE REQUIREMENTS:**
- Content field must NOT start with title or H1 headings
- Use H2 (##) for main sections only
- No conclusion headings - end naturally
- Start with engaging intro paragraph";

if (!empty($custom_prompt)) {
    $content_prompt .= "\n\n**ADDITIONAL CUSTOM INSTRUCTIONS:**\n{$custom_prompt}";
}

$content_prompt .= "\n\n**IMPORTANT: Return your response as a JSON object with this structure:**
{
    \"title\": \"{$researched_title}\",
    \"subheadline\": \"Engaging subtitle that complements the title\",
    \"content\": \"Full article text, formatted using Markdown\"
}

Use web search to ensure all information is current and accurate, then return the results in JSON format.";

            $content_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $researched_title],
                $content_prompt,
                $ai_model,
                true,  // JSON mode
                true,  // Web search enabled
                $creativity_level
            );
            
            $content_result = json_decode($content_response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($content_result['content'])) {
                throw new Exception('Failed to generate article content');
            }
            
            // Create the post
            $post_status = $campaign->content_mode === 'publish' ? 'publish' : 'draft';
            
            if (class_exists('Parsedown')) {
                $Parsedown = new Parsedown();
                $html_content = $Parsedown->text($content_result['content']);
            } else {
                $html_content = $content_result['content'];
            }
            
            $category_ids = [];
            if (!empty($settings['category_ids']) && is_array($settings['category_ids'])) {
                $category_ids = array_map('intval', $settings['category_ids']);
            }
            
            $post_data = [
                'post_title' => wp_strip_all_tags($researched_title),
                'post_content' => wp_kses_post($html_content),
                'post_status' => $post_status,
                'post_author' => $campaign->author_id,
                'post_category' => $category_ids
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Save metadata
            if (!empty($content_result['subheadline'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $content_result['subheadline']);
                update_post_meta($post_id, '_atm_subtitle', $content_result['subheadline']);
            }
            
            update_post_meta($post_id, '_atm_automation_generated', true);
            update_post_meta($post_id, '_atm_campaign_id', $campaign->id);
            update_post_meta($post_id, '_atm_trending_keyword', $base_keyword);
            update_post_meta($post_id, '_atm_trending_category', $topic_category);
            update_post_meta($post_id, '_atm_trending_reason', $trending_reason);
            update_post_meta($post_id, '_atm_ai_calls_used', 2); // Track API usage
            
            // Generate featured image if requested
            if ($settings['generate_image'] ?? false) {
                ATM_Content_Generation_Service::generate_featured_image($post_id, $researched_title);
            }
            
            error_log("ATM Trending: Created {$topic_category} article '{$researched_title}' (ID: {$post_id})");
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'article_title' => $researched_title,
                'topic_category' => $topic_category,
                'trending_reason' => $trending_reason,
                'api_calls_used' => 2
            ];
            
        } catch (Exception $e) {
            error_log('ATM Trending Automation Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get recent titles for duplication prevention
     */
    private static function get_recent_trending_titles($campaign_id, $days = 7) {
        global $wpdb;
        
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_title FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = '_atm_campaign_id' 
            AND pm.meta_value = %d 
            AND p.post_date > DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY p.post_date DESC 
            LIMIT 10",
            $campaign_id, $days
        ));
        
        return array_column($posts, 'post_title');
    }

    /**
     * Enhanced smart_select_trending_topic method
     */
    private static function smart_select_trending_topic($campaign_id, $base_keyword, $trends, $angle_refresh_days, $min_trend_score, $smart_angles = true, $include_breaking_news = false) {
        // Filter by minimum score
        $filtered_trends = array_filter($trends, function($trend) use ($min_trend_score) {
            return ($trend['traffic_numeric'] ?? 0) >= $min_trend_score;
        });
        
        if (empty($filtered_trends)) {
            $filtered_trends = array_slice($trends, 0, 5); // Fallback to top 5
        }
        
        // If breaking news is enabled, prioritize recent/urgent trends
        if ($include_breaking_news) {
            usort($filtered_trends, function($a, $b) {
                $a_breaking = stripos($a['title'], 'breaking') !== false || stripos($a['title'], 'urgent') !== false;
                $b_breaking = stripos($b['title'], 'breaking') !== false || stripos($b['title'], 'urgent') !== false;
                
                if ($a_breaking && !$b_breaking) return -1;
                if (!$a_breaking && $b_breaking) return 1;
                
                return ($b['traffic_numeric'] ?? 0) - ($a['traffic_numeric'] ?? 0);
            });
        }
        
        // Filter for relevance to base keyword
        $relevant_trends = self::filter_trending_topics_by_relevance($filtered_trends, $base_keyword);
        
        if (empty($relevant_trends)) {
            // If no relevant trends, create a trending angle for the base keyword itself
            error_log("ATM Trending: No relevant trends found, using base keyword with trending angle");
            return [
                'topic' => [
                    'title' => $base_keyword,
                    'snippet' => "Latest developments and trending discussions about {$base_keyword}",
                    'traffic' => 'High Interest',
                    'url' => ''
                ],
                'angle' => 'Latest trending developments and current analysis',
                'focus' => 'Current events and recent updates'
            ];
        }
        
        // Get recently used angles for this campaign
        $used_angles = self::get_recent_trending_angles($campaign_id, $base_keyword, $angle_refresh_days);
        
        // Use AI to select best topic with unique angle (if smart angles enabled)
        if ($smart_angles) {
            $selection_prompt = "You are analyzing trending topics related to '{$base_keyword}'. Select the MOST RELEVANT trending topic and create a unique angle.

            IMPORTANT: Only select topics that are directly related to '{$base_keyword}'. Ignore unrelated topics.
            
            Available trending topics:
            " . json_encode(array_slice($relevant_trends, 0, 8), JSON_PRETTY_PRINT) . "
            
            Recently used angles to AVOID:
            " . implode("\n- ", $used_angles) . "
            
            Requirements:
            1. Select the trending topic most relevant to '{$base_keyword}'
            2. Create a completely unique angle that hasn't been used
            3. Focus on current/trending aspects
            4. Ensure the angle is newsworthy and engaging
            
            Return JSON with:
            {
                \"selected_index\": 0,
                \"reasoning\": \"Why this topic is most relevant to {$base_keyword}\",
                \"unique_angle\": \"Specific unique angle for this topic\",
                \"article_focus\": \"What the article should emphasize\"
            }";
            
            $ai_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $base_keyword],
                $selection_prompt,
                'anthropic/claude-3-haiku',
                true,
                true // Enable web search for better relevance detection
            );
            
            $selection = json_decode($ai_response, true);
            if (!$selection || !isset($selection['selected_index'])) {
                // Fallback: select first available topic
                $selection = [
                    'selected_index' => 0,
                    'unique_angle' => 'Comprehensive analysis of recent developments',
                    'article_focus' => 'Latest updates and implications'
                ];
            }
        } else {
            // Simple selection without AI
            $selection = [
                'selected_index' => 0,
                'unique_angle' => 'Latest developments and trending analysis',
                'article_focus' => 'Current trends and implications'
            ];
        }
        
        $selected_index = min($selection['selected_index'], count($relevant_trends) - 1);
        $selected_topic = $relevant_trends[$selected_index] ?? $relevant_trends[0];
        
        error_log("ATM Trending: Selected topic: " . $selected_topic['title'] . " with angle: " . $selection['unique_angle']);
        
        return [
            'topic' => $selected_topic,
            'angle' => $selection['unique_angle'],
            'focus' => $selection['article_focus'] ?? ''
        ];
    }

    private static function filter_trending_topics_by_relevance($trends, $base_keyword) {
        $relevant_trends = [];
        
        foreach ($trends as $trend) {
            $title = strtolower($trend['title']);
            $snippet = strtolower($trend['snippet'] ?? '');
            $base_lower = strtolower($base_keyword);
            
            // Check for exact keyword match
            if (strpos($title, $base_lower) !== false || strpos($snippet, $base_lower) !== false) {
                $relevant_trends[] = $trend;
                continue;
            }
            
            // Check for partial matches for compound keywords
            $keyword_parts = explode(' ', $base_lower);
            $matches = 0;
            foreach ($keyword_parts as $part) {
                if (strlen($part) > 2 && (strpos($title, $part) !== false || strpos($snippet, $part) !== false)) {
                    $matches++;
                }
            }
            
            // If most parts match, consider it relevant
            if ($matches >= ceil(count($keyword_parts) * 0.6)) {
                $relevant_trends[] = $trend;
            }
        }
        
        error_log("ATM Trending: Filtered from " . count($trends) . " to " . count($relevant_trends) . " relevant trends for '{$base_keyword}'");
        
        return $relevant_trends;
    }

    private static function store_used_trending_angle($campaign_id, $base_keyword, $trending_keyword, $article_title, $angle) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_trending_keywords';
        
        $keyword_hash = md5($campaign_id . $base_keyword . $trending_keyword . $angle);
        
        $wpdb->insert($table_name, [
            'campaign_id' => $campaign_id,
            'base_keyword' => $base_keyword,
            'trending_keyword' => $trending_keyword,
            'trending_title' => $article_title,
            'keyword_hash' => $keyword_hash,
            'article_angle' => $angle,
            'used_at' => current_time('mysql')
        ]);
    }

    private static function get_recent_trending_angles($campaign_id, $base_keyword, $days) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_trending_keywords';
        
        if ($days == 0) {
            return []; // No restrictions
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT article_angle FROM $table_name 
            WHERE campaign_id = %d AND base_keyword = %s 
            AND used_at > DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY used_at DESC",
            $campaign_id, $base_keyword, $days
        ));
        
        return array_column($results, 'article_angle');
    }
    
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