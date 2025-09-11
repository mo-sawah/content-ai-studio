<?php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Ajax {

    private function get_massive_scale_angles() {
        return [
            'target_audiences' => [
                'beginners', 'professionals', 'entrepreneurs', 'small_businesses', 'startups',
                'freelancers', 'consultants', 'agencies', 'enterprises', 'non_profits',
                'students', 'job_seekers', 'managers', 'executives', 'creatives'
            ],
            'industries' => [
                'healthcare', 'finance', 'education', 'retail', 'manufacturing',
                'real_estate', 'hospitality', 'automotive', 'legal', 'consulting',
                'technology', 'media', 'sports', 'fashion', 'food_beverage',
                'construction', 'agriculture', 'energy', 'government', 'aerospace'
            ],
            'problem_types' => [
                'mistakes_to_avoid', 'optimization_strategies', 'cost_reduction',
                'time_saving', 'efficiency_improvement', 'quality_enhancement',
                'security_concerns', 'compliance_issues', 'scalability_challenges',
                'integration_problems', 'training_gaps', 'measurement_difficulties'
            ],
            'content_formats' => [
                'ultimate_guide', 'step_by_step', 'checklist', 'case_study',
                'comparison', 'review', 'trend_analysis', 'prediction', 'interview',
                'toolkit', 'template', 'framework', 'strategy', 'blueprint'
            ],
            'time_contexts' => [
                '2025', '2026', 'next_5_years', '30_days', '90_days', '6_months',
                'this_year', 'pandemic_era', 'post_covid', 'recession_proof',
                'economic_uncertainty', 'digital_transformation_era'
            ],
            'skill_levels' => [
                'complete_beginner', 'intermediate', 'advanced', 'expert',
                'transitioning_career', 'self_taught', 'formally_trained'
            ],
            'budget_constraints' => [
                'zero_budget', 'bootstrap', 'small_budget', 'medium_investment',
                'enterprise_budget', 'cost_effective', 'premium_solutions'
            ]
        ];
    }

    private function generate_massive_scale_angle($keyword, $previous_angles) {
        $dimensions = $this->get_massive_scale_angles();
        
        // Get previously used combinations to avoid duplicates
        $used_combinations = [];
        foreach ($previous_angles as $prev) {
            if (isset($prev['angle'])) {
                $used_combinations[] = md5($prev['angle']);
            }
        }
        
        // Generate unique combination
        $max_attempts = 50;
        $attempts = 0;
        
        do {
            $combination = [
                'audience' => $dimensions['target_audiences'][array_rand($dimensions['target_audiences'])],
                'industry' => $dimensions['industries'][array_rand($dimensions['industries'])],
                'problem' => $dimensions['problem_types'][array_rand($dimensions['problem_types'])],
                'format' => $dimensions['content_formats'][array_rand($dimensions['content_formats'])],
                'time' => $dimensions['time_contexts'][array_rand($dimensions['time_contexts'])],
                'skill' => $dimensions['skill_levels'][array_rand($dimensions['skill_levels'])],
                'budget' => $dimensions['budget_constraints'][array_rand($dimensions['budget_constraints'])]
            ];
            
            $combination_key = implode('|', $combination);
            $combination_hash = md5($combination_key);
            $attempts++;
            
        } while (in_array($combination_hash, $used_combinations) && $attempts < $max_attempts);
        
        // Generate title from combination
        $title = $this->generate_title_from_combination($keyword, $combination);
        
        return [
            'title' => $title,
            'combination' => $combination,
            'combination_key' => $combination_key,
            'prompt_focus' => $this->build_detailed_prompt_focus($combination)
        ];
    }

    private function generate_title_from_combination($keyword, $combination) {
        // Base templates (your current ones)
        $base_templates = [
            "{keyword} for {audience} in {industry}: {format} for {time}",
            "How {audience} in {industry} Can Master {keyword} ({skill} Level)",
            "{keyword} {problem}: A {format} for {audience} on a {budget} Budget",
            "The {time} {keyword} Strategy for {industry} {audience}",
            "{keyword} Success: How {audience} in {industry} Can Avoid {problem}",
            "From Zero to Hero: {keyword} {format} for {skill} {audience}",
            "{industry} {keyword}: {problem} Solutions for {time}",
            "The Complete {keyword} {format} for {audience} in {industry} ({time} Edition)",
            "Why {audience} in {industry} Fail at {keyword} (And How to Fix It)",
            "{keyword} on a {budget} Budget: {format} for {industry} {audience}",
        ];

        // Enhanced viral/clickable templates
        $viral_templates = [
            "The Ultimate {keyword} Guide for {industry} {audience} in {time}",
            "{industry} Leaders' Secret: Advanced {keyword} Strategies",
            "Breaking: How {keyword} is Transforming {industry} in {time}",
            "The {keyword} Revolution: {audience} Guide to {industry} Success",
            "Insider's {keyword} Playbook for {industry} Professionals",
            "{time} Forecast: {keyword} Trends Every {industry} {audience} Must Know",
            "The Hidden {keyword} Strategies {industry} Experts Don't Share",
            "Next-Level {keyword}: How {industry} {audience} Stay Ahead",
            "The {keyword} Transformation: {industry} Success Stories",
            "Proven {keyword} Methods for {industry} Growth in {time}",
        ];

        // Problem-focused templates
        $problem_templates = [
            "The {keyword} Crisis: How {industry} {audience} Can Survive {time}",
            "Stop Making These {keyword} Mistakes in {industry}",
            "The {keyword} Nightmare Every {industry} {audience} Fears",
            "How to Fix Your {keyword} Problems in {industry} ({time} Solutions)",
            "The {keyword} Disaster That's Killing {industry} Businesses",
        ];

        // Success/transformation templates
        $success_templates = [
            "From Failure to Success: {keyword} Transformation in {industry}",
            "How I Used {keyword} to Dominate {industry} ({skill} Level Guide)",
            "The {keyword} Blueprint That Built My {industry} Empire",
            "Case Study: {keyword} Success in {industry} ({time} Results)",
            "How {audience} Are Crushing {industry} with {keyword}",
        ];

        // Contrarian/controversial templates
        $contrarian_templates = [
            "Why Everything You Know About {keyword} in {industry} is Wrong",
            "The {keyword} Lie That's Destroying {industry} Businesses",
            "Controversial: Why {industry} {audience} Should Ignore {keyword}",
            "The {keyword} Myth That's Costing {industry} Millions",
            "Why Most {keyword} Advice for {industry} is Garbage",
        ];

        // Future/prediction templates
        $future_templates = [
            "{time} Prediction: The Future of {keyword} in {industry}",
            "What {keyword} Will Look Like in {industry} by {time}",
            "The Coming {keyword} Revolution in {industry}",
            "{time}: The Year {keyword} Changes {industry} Forever",
            "Future-Proof Your {industry} Business with {keyword}",
        ];

        // Combine all template arrays
        $all_templates = array_merge(
            $base_templates,
            $viral_templates, 
            $problem_templates,
            $success_templates,
            $contrarian_templates,
            $future_templates
        );
        
        $template = $all_templates[array_rand($all_templates)];
        
        // Same replacements as before
        $replacements = [
            '{keyword}' => ucwords($keyword),
            '{audience}' => $this->format_simple($combination['audience']),
            '{industry}' => $this->format_simple($combination['industry']),
            '{problem}' => $this->format_simple($combination['problem']),
            '{format}' => $this->format_simple($combination['format']),
            '{time}' => $this->format_simple($combination['time']),
            '{skill}' => $this->format_simple($combination['skill']),
            '{budget}' => $this->format_simple($combination['budget'])
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function format_simple($text) {
        // Replace underscores with spaces and capitalize words
        $formatted = str_replace('_', ' ', $text);
        return ucwords($formatted);
    }

    // Helper methods for smart formatting
    private function format_keyword($keyword) {
        return ucwords(strtolower($keyword));
    }

    private function format_audience($audience) {
        $mappings = [
            'small_businesses' => 'Small Business Owners',
            'job_seekers' => 'Job Seekers',
            'non_profits' => 'Nonprofit Organizations'
        ];
        return $mappings[$audience] ?? ucwords(str_replace('_', ' ', $audience));
    }

    private function format_industry($industry) {
        $mappings = [
            'real_estate' => 'Real Estate',
            'food_beverage' => 'Food & Beverage',
        ];
        return $mappings[$industry] ?? ucwords(str_replace('_', ' ', $industry));
    }

    private function format_problem($problem) {
        $mappings = [
            'mistakes_to_avoid' => 'Critical Mistakes',
            'optimization_strategies' => 'Performance Optimization',
            'cost_reduction' => 'Cost Management',
            'time_saving' => 'Time Efficiency',
            'efficiency_improvement' => 'Operational Excellence',
            'quality_enhancement' => 'Quality Improvement',
            'security_concerns' => 'Security Challenges',
            'compliance_issues' => 'Regulatory Compliance',
            'scalability_challenges' => 'Growth Scalability',
            'integration_problems' => 'System Integration',
            'training_gaps' => 'Skills Development',
            'measurement_difficulties' => 'Performance Metrics'
        ];
        return $mappings[$problem] ?? ucwords(str_replace('_', ' ', $problem));
    }

    private function build_detailed_prompt_focus($combination) {
        return "Write specifically for {$combination['audience']} in the {$combination['industry']} industry who are dealing with {$combination['problem']}. " .
            "Focus on {$combination['skill']} level content with a {$combination['budget']} budget approach. " .
            "Structure this as a {$combination['format']} with a {$combination['time']} perspective. " .
            "Include industry-specific examples, realistic constraints, and actionable advice that this specific audience can actually implement.";
    }
    
    public function ensure_angles_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        error_log("ATM Debug: Checking if angles table exists");
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            error_log("ATM Debug: Creating content angles table");
            ATM_Main::create_content_angles_table();
            
            // Verify it was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                error_log("ATM Debug: FAILED to create content angles table");
            } else {
                error_log("ATM Debug: Successfully created content angles table");
            }
        } else {
            error_log("ATM Debug: Content angles table already exists");
        }
    }
    
    public function debug_twitter_response() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $api_key = get_option('atm_twitterapi_key');
            if (empty($api_key)) {
                throw new Exception('TwitterAPI.io key not configured');
            }
            
            $url = 'https://api.twitterapi.io/twitter/tweet/advanced_search';
            $params = [
                'query' => 'news',
                'queryType' => 'Latest',
            ];
            
            $response = wp_remote_get($url . '?' . http_build_query($params), [
                'headers' => [
                    'X-API-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Connection failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception("API Error ($response_code): $body");
            }
            
            $data = json_decode($body, true);
            
            // Return structured debug info
            $debug_info = [
                'response_code' => $response_code,
                'response_keys' => array_keys($data),
                'data_structure' => 'unknown'
            ];
            
            // Identify data structure
            if (isset($data['data']) && is_array($data['data'])) {
                $debug_info['data_structure'] = 'data array';
                $debug_info['tweet_count'] = count($data['data']);
                if (!empty($data['data'])) {
                    $debug_info['first_tweet'] = $data['data'][0];
                }
            } elseif (isset($data['tweets']) && is_array($data['tweets'])) {
                $debug_info['data_structure'] = 'tweets array';
                $debug_info['tweet_count'] = count($data['tweets']);
                if (!empty($data['tweets'])) {
                    $debug_info['first_tweet'] = $data['tweets'][0];
                }
            } elseif (is_array($data)) {
                $debug_info['data_structure'] = 'direct array';
                $debug_info['tweet_count'] = count($data);
                if (!empty($data)) {
                    $debug_info['first_tweet'] = $data[0];
                }
            }
            
            wp_send_json_success($debug_info);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Search Twitter for news content
     */
    public function search_twitter_news() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 180);

        try {
            $keyword = sanitize_text_field($_POST['keyword']);
            $verified_only = isset($_POST['verified_only']) && $_POST['verified_only'] === 'true';
            $credible_sources_only = isset($_POST['credible_sources_only']) && $_POST['credible_sources_only'] === 'true';
            $min_followers = isset($_POST['min_followers']) ? intval($_POST['min_followers']) : 10000;
            $max_results = isset($_POST['max_results']) ? min(50, intval($_POST['max_results'])) : 20;

            if (empty($keyword)) {
                throw new Exception('Search keyword is required.');
            }

            $filters = [
                'verified_only' => $verified_only,
                'credible_sources_only' => $credible_sources_only,
                'min_followers' => $min_followers,
                'max_results' => $max_results,
                'language' => 'en' // Default to English for now
            ];

            $results = ATM_Twitter_API::search_twitter_news($keyword, $filters);

            wp_send_json_success([
                'results' => $results['results'],
                'total' => $results['total'],
                'keyword' => $keyword
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Generate article from selected tweets
     */
    public function generate_article_from_tweets() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $keyword = sanitize_text_field($_POST['keyword']);
            $selected_tweets = isset($_POST['selected_tweets']) ? $_POST['selected_tweets'] : [];
            $article_language = isset($_POST['article_language']) ? sanitize_text_field($_POST['article_language']) : 'English';

            if (empty($keyword) || empty($selected_tweets)) {
                throw new Exception('Keyword and selected tweets are required.');
            }

            $result = ATM_Twitter_API::generate_article_from_tweets($keyword, $selected_tweets, $article_language);

            // Save subtitle if provided
            if ($post_id > 0 && !empty($result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $result['subtitle']);
                error_log("ATM Plugin: Saved Twitter subtitle '{$result['subtitle']}' to SmartMag field for post {$post_id}");
            }

            wp_send_json_success([
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? ''
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_google_news() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        try {
            $query = sanitize_text_field($_POST['query']);
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $per_page = isset($_POST['per_page']) ? max(1, min(50, intval($_POST['per_page']))) : 10;

            // --- MODIFIED: Correctly read the new filter values from the AJAX request ---
            $article_language = isset($_POST['article_language']) ? sanitize_text_field($_POST['article_language']) : 'English';
            $source_languages = isset($_POST['source_languages']) && is_array($_POST['source_languages']) ? array_map('sanitize_text_field', $_POST['source_languages']) : [];
            $countries = isset($_POST['countries']) && is_array($_POST['countries']) ? array_map('sanitize_text_field', $_POST['countries']) : [];
            
            if (empty($query)) {
                throw new Exception('Search query is required.');
            }

            // --- MODIFIED: Pass the new filter arrays to the API function ---
            $articles = ATM_API::search_google_news_direct($query, $page, $per_page, $source_languages, $countries);
            
            wp_send_json_success([
                'articles' => $articles['results'],
                'query' => $query,
                'page' => $page,
                'per_page' => $per_page,
                'total' => $articles['total_results'] ?? count($articles['results'])
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // Update this method in class-atm-ajax.php
    public function generate_article_from_news_source() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $source_url = esc_url_raw($_POST['source_url']);
            $source_title = sanitize_text_field($_POST['source_title']);
            $source_snippet = wp_kses_post($_POST['source_snippet']);
            $source_date = sanitize_text_field($_POST['source_date']);
            $source_domain = sanitize_text_field($_POST['source_domain']);
            $generate_image = isset($_POST['generate_image']) && $_POST['generate_image'] === 'true';
            
            // --- NEW: Correctly read the article_language for generation ---
            $article_language = isset($_POST['article_language']) ? sanitize_text_field($_POST['article_language']) : 'English';

            if (empty($source_url) || empty($source_title)) {
                throw new Exception('Source URL and title are required.');
            }

            // --- MODIFIED: Pass the article language to the API function ---
            $result = ATM_API::generate_article_from_news_source(
                $source_url,
                $source_title,
                $source_snippet,
                $source_date,
                $source_domain,
                $article_language // Pass the language
            );

            // Save subtitle if provided
            if ($post_id > 0 && !empty($result['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $result['subtitle']);
                error_log("ATM Plugin: Saved News Search subtitle '{$result['subtitle']}' to SmartMag field for post {$post_id}");
            }

            // Track this article as used
            global $wpdb;
            $table_name = $wpdb->prefix . 'atm_used_news_articles';
            
            $wpdb->replace($table_name, [
                'article_url' => $source_url,
                'article_title' => $source_title,
                'used_at' => current_time('mysql'),
                'post_id' => $post_id
            ]);

            $response_data = [
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? ''
            ];

            // Generate featured image if requested
            if ($generate_image && $post_id > 0) {
                try {
                    $image_prompt = ATM_API::get_news_image_prompt($result['title']);
                    $image_result = ATM_API::generate_image_with_configured_provider(
                        $image_prompt,
                        get_option('atm_image_size', '1792x1024'),
                        get_option('atm_image_quality', 'hd')
                    );
                    
                    if ($image_result['is_url']) {
                        $attachment_id = $this->set_image_from_url($image_result['data'], $post_id);
                    } else {
                        $attachment_id = $this->set_image_from_data($image_result['data'], $post_id, $image_prompt);
                    }
                    
                    if (!is_wp_error($attachment_id)) {
                        set_post_thumbnail($post_id, $attachment_id);
                        $response_data['featured_image_generated'] = true;
                        $response_data['featured_image_id'] = $attachment_id;
                    } else {
                        error_log('ATM: Featured image generation failed: ' . $attachment_id->get_error_message());
                        $response_data['featured_image_error'] = $attachment_id->get_error_message();
                    }
                } catch (Exception $e) {
                    error_log('ATM: Featured image generation failed: ' . $e->getMessage());
                    $response_data['featured_image_error'] = $e->getMessage();
                }
            }

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_live_news() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 180);

        try {
            $keyword = sanitize_text_field($_POST['keyword']);
            $force_fresh = isset($_POST['force_fresh']) && $_POST['force_fresh'] === 'true';

            if (empty($keyword)) {
                throw new Exception('Keyword is required for live news search.');
            }

            $categories = ATM_API::search_live_news_with_openrouter($keyword, $force_fresh);

            wp_send_json_success([
                'categories' => $categories,
                'keyword' => $keyword,
                'cached' => !$force_fresh
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Generate article from live news category
     */
    public function generate_article_from_live_news() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $keyword = sanitize_text_field($_POST['keyword']);
            $category_title = sanitize_text_field($_POST['category_title']);
            $category_sources = isset($_POST['category_sources']) ? $_POST['category_sources'] : [];
            // Remove the generate_image parameter - we'll handle this separately

            if (empty($keyword) || empty($category_title) || empty($category_sources)) {
                throw new Exception('Missing required parameters for article generation.');
            }

            // Get previous angles for this keyword to ensure uniqueness
            $previous_angles = get_option('atm_live_news_angles_' . md5($keyword), []);

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
                error_log("ATM Plugin: Saved Live News subtitle '{$result['subtitle']}' to SmartMag field for post {$post_id}");
            }

            $response_data = [
                'article_title' => $result['title'],
                'article_content' => $result['content'],
                'subtitle' => $result['subtitle'] ?? '',
                'angle' => $result['angle'] ?? ''
            ];

            // Remove all the image generation logic - it's now handled separately
            // This makes the function faster and more reliable

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    

    // Add this new AJAX function to force subtitle population
    public function populate_subtitle_field() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $subtitle = get_post_meta($post_id, '_bunyad_sub_title', true);
            
            if ($subtitle) {
                wp_send_json_success(['subtitle' => $subtitle, 'found' => true]);
            } else {
                wp_send_json_success(['subtitle' => '', 'found' => false]);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_post_subtitle() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $subtitle = get_post_meta($post_id, '_bunyad_sub_title', true);
            wp_send_json_success(['subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // --- NEW: Generate lifelike comments from post content ---
    public function generate_post_comments() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 180);

        try {
            $post_id  = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $title    = isset($_POST['title']) ? sanitize_text_field(stripslashes($_POST['title'])) : '';
            $content  = isset($_POST['content']) ? wp_kses_post(stripslashes($_POST['content'])) : '';
            $count    = isset($_POST['count']) ? intval($_POST['count']) : 7;
            $count    = max(5, min(50, $count));
            $threaded = isset($_POST['threaded']) && in_array($_POST['threaded'], ['true','1',1,true], true);
            $model    = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';

            if (empty($content) && $post_id) {
                $post = get_post($post_id);
                if ($post) $content = $post->post_content;
            }
            if (empty($content)) {
                throw new Exception('Editor content is empty.');
            }

            $comments = ATM_API::generate_lifelike_comments($title, $content, $count, $threaded, $model);

            wp_send_json_success(['comments' => $comments]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // Randomize timestamps and insert comments
    public function save_generated_comments() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        try {
            $post_id = intval($_POST['post_id']);
            if (!$post_id || !($post = get_post($post_id))) {
                throw new Exception('Invalid post.');
            }
            $raw = isset($_POST['comments']) ? $_POST['comments'] : '[]';
            $data = is_array($raw) ? $raw : json_decode(stripslashes($raw), true);
            if (!is_array($data)) {
                throw new Exception('Invalid comments payload.');
            }
            $approve_flag = isset($_POST['approve']) && $_POST['approve'] === 'true' ? 1 : 0;

            // Timestamp window: between post_date and now, capped by setting window days
            $window_days = max(1, intval(get_option('atm_comments_randomize_window_days', 3)));
            $now_ts = time();
            $start_ts = max(strtotime($post->post_date_gmt ?: $post->post_date), $now_ts - ($window_days * DAY_IN_SECONDS));

            $index_to_id = [];
            $index_to_time = [];
            $inserted = 0;

            foreach ($data as $idx => $c) {
                $author = isset($c['author_name']) ? sanitize_text_field($c['author_name']) : 'Guest';

                // Sanitize and strip links defensively
                $text = isset($c['text']) ? wp_kses_post($c['text']) : '';
                $text = preg_replace('/\[(.*?)\]\((https?:\/\/|www\.)[^\s)]+\)/i', '$1', $text);
                $text = preg_replace('/https?:\/\/\S+/i', '', $text);
                $text = preg_replace('/\bwww\.[^\s]+/i', '', $text);
                $text = trim(preg_replace('/\s{2,}/', ' ', $text));
                $text = wp_kses($text, ['br' => [], 'em' => [], 'strong' => [], 'i' => [], 'b' => []]);

                if ($text === '') continue;

                $parent_index = isset($c['parent_index']) && $c['parent_index'] !== '' ? intval($c['parent_index']) : -1;
                $parent_id    = ($parent_index >= 0 && isset($index_to_id[$parent_index])) ? intval($index_to_id[$parent_index]) : 0;

                // Randomize timestamps:
                // - Top-level: random between start_ts and now
                // - Reply: >= parent's time + 2..60 minutes
                if ($parent_index >= 0 && isset($index_to_time[$parent_index])) {
                    $base = $index_to_time[$parent_index] + rand(2 * MINUTE_IN_SECONDS, 60 * MINUTE_IN_SECONDS);
                    $ts = min($now_ts, $base + rand(0, 45 * MINUTE_IN_SECONDS));
                } else {
                    $ts = rand($start_ts, $now_ts);
                }
                $date_mysql = gmdate('Y-m-d H:i:s', $ts);
                // Convert to local time for comment_date; comment_date_gmt stays GMT
                $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
                $date_local = gmdate('Y-m-d H:i:s', $ts + $offset);

                $commentdata = [
                    'comment_post_ID'      => $post_id,
                    'comment_author'       => $author,
                    'comment_author_email' => '',
                    'comment_author_url'   => '',
                    'comment_content'      => $text,
                    'comment_type'         => '',
                    'comment_parent'       => $parent_id,
                    'user_id'              => 0,
                    'comment_approved'     => $approve_flag,
                    'comment_date'         => $date_local,
                    'comment_date_gmt'     => $date_mysql,
                ];
                $cid = wp_insert_comment(wp_slash($commentdata));
                if ($cid && !is_wp_error($cid)) {
                    $index_to_id[$idx] = $cid;
                    $index_to_time[$idx] = $ts;
                    $inserted++;
                }
            }

            wp_send_json_success(['inserted' => $inserted]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

   // --- NEW: MULTIPAGE ARTICLE FUNCTIONS ---
    public function generate_multipage_title() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $keyword = sanitize_text_field($_POST['keyword']);
            $page_count = intval($_POST['page_count']);
            $model = sanitize_text_field($_POST['model']);
            $title = ATM_API::generate_multipage_title($keyword, $page_count, $model);
            wp_send_json_success(['article_title' => $title]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_multipage_outline() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $params = [
                'article_title' => sanitize_text_field($_POST['article_title']),
                'page_count' => intval($_POST['page_count']),
                'model' => sanitize_text_field($_POST['model']),
                'writing_style' => sanitize_text_field($_POST['writing_style']),
                'include_subheadlines' => filter_var($_POST['include_subheadlines'], FILTER_VALIDATE_BOOLEAN),
                'enable_web_search' => filter_var($_POST['enable_web_search'], FILTER_VALIDATE_BOOLEAN),
            ];
            $outline = ATM_API::generate_multipage_outline($params);
            wp_send_json_success(['outline' => $outline]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_multipage_content() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
             $params = [
                'article_title' => sanitize_text_field($_POST['article_title']),
                'page_number' => intval($_POST['page_number']),
                'total_pages' => intval($_POST['total_pages']),
                'page_outline' => wp_kses_post_deep($_POST['page_outline']),
                'words_per_page' => intval($_POST['words_per_page']),
                'model' => sanitize_text_field($_POST['model']),
                'writing_style' => sanitize_text_field($_POST['writing_style']),
                'custom_prompt' => wp_kses_post(stripslashes($_POST['custom_prompt'])),
                'include_subheadlines' => filter_var($_POST['include_subheadlines'], FILTER_VALIDATE_BOOLEAN),
                'enable_web_search' => filter_var($_POST['enable_web_search'], FILTER_VALIDATE_BOOLEAN),
            ];
            $content = ATM_API::generate_multipage_content($params);
            wp_send_json_success(['page_content' => $content]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function create_multipage_article() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $main_title = sanitize_text_field($_POST['main_title']);
            $pages_data = $_POST['pages']; // This comes from React, should be clean

            if (!$post_id || empty($main_title) || empty($pages_data)) {
                throw new Exception('Missing required data for multipage creation.');
            }
            
            // Sanitize the pages data
            $sanitized_pages = [];
            $Parsedown = new Parsedown();
            foreach ($pages_data as $page) {
                $sanitized_pages[] = [
                    'title' => sanitize_text_field($page['title']),
                    'content_html' => wp_kses_post($Parsedown->text($page['content']))
                ];
            }

            // Save all pages data to a single post meta field
            update_post_meta($post_id, '_atm_multipage_data', $sanitized_pages);

            // Prepare the content for the editor, which is just the shortcode
            $editor_content = '[atm_multipage_article]';

            wp_send_json_success(['message' => 'Multipage article data saved.', 'editor_content' => $editor_content]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // New AJAX handler for fetching page content on the frontend
    public function get_multipage_page_content() {
        check_ajax_referer('atm_multipage_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $page_index = intval($_POST['page_index']);

            if (!$post_id) {
                throw new Exception('Invalid post ID.');
            }

            $multipage_data = get_post_meta($post_id, '_atm_multipage_data', true);

            if (empty($multipage_data) || !isset($multipage_data[$page_index])) {
                throw new Exception('Page data not found.');
            }
            
            // Send back the pre-rendered HTML content for the requested page
            wp_send_json_success(['html_content' => $multipage_data[$page_index]['content_html']]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function save_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        $data = [
            'keyword'          => sanitize_text_field($_POST['keyword']),
            'country'          => sanitize_text_field($_POST['country']),
            'article_type'     => sanitize_text_field($_POST['article_type']),
            'custom_prompt'    => wp_kses_post(stripslashes($_POST['custom_prompt'])),
            'generate_image'   => isset($_POST['generate_image']) ? 1 : 0,
            'category_id'      => intval($_POST['category_id']),
            'author_id'        => intval($_POST['author_id']),
            'post_status'      => sanitize_text_field($_POST['post_status']),
            'frequency_value'  => intval($_POST['frequency_value']),
            'frequency_unit'   => sanitize_text_field($_POST['frequency_unit']),
        ];

        $is_new = $campaign_id === 0;

        if ($is_new) {
            $wpdb->insert($table_name, $data);
            $campaign_id = $wpdb->insert_id;
        } else {
            $wpdb->update($table_name, $data, ['id' => $campaign_id]);
        }

        // --- NEW LOGIC for Find Sources button ---
        if (isset($_POST['find_sources']) && $_POST['find_sources'] == '1') {
            $keywords = sanitize_text_field($_POST['source_keywords']);
            if (!empty($keywords)) {
                $found_urls = ATM_API::find_news_sources_for_keywords($keywords);
                // Append new sources to existing ones, avoiding duplicates
                $existing_urls = isset($_POST['source_urls']) ? array_filter(explode("\n", $_POST['source_urls'])) : [];
                $all_urls = array_unique(array_merge($existing_urls, $found_urls));
                $data['source_urls'] = implode("\n", $all_urls);
            }
        }
        // --- END NEW LOGIC ---
        
        // Set the next_run time
        $interval_string = "+{$data['frequency_value']} {$data['frequency_unit']}";
        // If it's a new campaign, schedule it to run in 5 mins. Otherwise, respect the new interval from now.
        $start_time = current_time('timestamp', 1);
        $next_run_timestamp = strtotime($interval_string, $start_time);
        $next_run_mysql = date('Y-m-d H:i:s', $next_run_timestamp);

        $wpdb->update($table_name, ['next_run' => $next_run_mysql], ['id' => $campaign_id]);

        wp_send_json_success(['message' => 'Campaign saved successfully!', 'redirect_url' => admin_url('admin.php?page=content-ai-studio-automatic')]);
    }

    // Find the empty delete_campaign function and replace it with this:
    public function delete_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign_id = intval($_POST['id']);

        ATM_Campaign_Manager::unschedule_campaign($campaign_id);
        $wpdb->delete($table_name, ['id' => $campaign_id]);

        wp_send_json_success(['message' => 'Campaign deleted.']);
    }

    // Find the empty run_campaign_now function and replace it with this:
    public function run_campaign_now() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        $campaign_id = intval($_POST['id']);

        ATM_Campaign_Manager::execute_campaign($campaign_id);
        
        wp_send_json_success(['message' => 'Campaign executed successfully! A new article is being generated.']);
    }
    
    public function generate_key_takeaways() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $content = wp_kses_post(stripslashes($_POST['content']));
            $model_override = sanitize_text_field($_POST['model']);

            if (empty($content)) {
                throw new Exception('Editor content is empty.');
            }

            $takeaways = ATM_API::generate_takeaways_from_content($content, $model_override);
            wp_send_json_success(['takeaways' => $takeaways]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function save_key_takeaways() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        try {
            $post_id = intval($_POST['post_id']);
            $takeaways = sanitize_textarea_field(stripslashes($_POST['takeaways']));
            $theme = sanitize_text_field($_POST['theme']); // <-- ADD THIS LINE

            if (empty($post_id)) {
                throw new Exception('Invalid Post ID.');
            }
            
            // Save takeaways and theme as post meta
            update_post_meta($post_id, '_atm_key_takeaways', $takeaways);
            update_post_meta($post_id, '_atm_takeaways_theme', $theme); // <-- ADD THIS LINE
            
            wp_send_json_success(['message' => 'Takeaways saved successfully.']);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function save_atm_chart() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        try {
            $chart_title = sanitize_text_field($_POST['title']);
            $chart_config = wp_kses_post(stripslashes($_POST['chart_config']));
            $chart_id = isset($_POST['chart_id']) ? intval($_POST['chart_id']) : 0;

            if (empty($chart_title) || empty($chart_config)) {
                throw new Exception('Chart title and configuration are required.');
            }

            $post_data = array(
                'post_title'  => $chart_title,
                'post_type'   => 'atm_chart',
                'post_status' => 'publish',
            );

            if ($chart_id > 0) {
                $post_data['ID'] = $chart_id;
                $new_chart_id = wp_update_post($post_data);
            } else {
                $new_chart_id = wp_insert_post($post_data);
            }

            if (is_wp_error($new_chart_id)) {
                throw new Exception($new_chart_id->get_error_message());
            }

            update_post_meta($new_chart_id, '_atm_chart_config', $chart_config);

            wp_send_json_success(['chart_id' => $new_chart_id]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_chart_from_ai() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $prompt = sanitize_textarea_field(stripslashes($_POST['prompt']));
            if (empty($prompt)) {
                throw new Exception('Prompt cannot be empty.');
            }
            
            $chart_config_json = ATM_API::generate_chart_config_from_prompt($prompt);
            
            wp_send_json_success(['chart_config' => $chart_config_json]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }    

    public function get_youtube_suggestions() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $query = sanitize_text_field($_POST['query']);
            $suggestions = ATM_API::get_youtube_autocomplete_suggestions($query);
            wp_send_json_success($suggestions);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_youtube() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $query = sanitize_text_field($_POST['query']);
            $filters = isset($_POST['filters']) ? (array) $_POST['filters'] : [];
            $results = ATM_API::search_youtube_videos($query);
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }


    public function translate_editor_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        
        // --- ADD THIS LINE ---
        @ini_set('max_execution_time', 300); // Allow up to 5 minutes for translation

        try {
            if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['target_language'])) {
                throw new Exception('Title, content, and target language are required.');
            }

            $title = sanitize_text_field(stripslashes($_POST['title']));
            // Use a more permissive sanitization for content to preserve HTML/Markdown
            $content = wp_kses_post(stripslashes($_POST['content']));
            $target_language = sanitize_text_field($_POST['target_language']);

            $translation_result = ATM_API::translate_document($title, $content, $target_language);

            wp_send_json_success([
                'translated_title' => $translation_result['translated_title'],
                'translated_content' => $translation_result['translated_content']
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function transcribe_audio() {
    if (!ATM_Licensing::is_license_active()) {
        wp_send_json_error('Please activate your license key to use this feature.');
    }

    check_ajax_referer('atm_nonce', 'nonce');

    try {
        if (!isset($_FILES['audio_file'])) {
            throw new Exception('No audio file was received.');
        }

        $file = $_FILES['audio_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Prompt logic has been removed.
        
        // Pass only the file to the API method.
        $transcript = ATM_API::transcribe_audio_with_whisper($file['tmp_name']);
        wp_send_json_success(['transcript' => $transcript]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

public function translate_text() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');

        try {
            if (empty($_POST['source_text']) || empty($_POST['target_language'])) {
                throw new Exception('Source text and target language are required.');
            }

            $source_text = sanitize_textarea_field(stripslashes($_POST['source_text']));
            $target_language = sanitize_text_field($_POST['target_language']);

            $translated_text = ATM_API::translate_text($source_text, $target_language);

            wp_send_json_success(['translated_text' => $translated_text]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function upload_podcast_image() {
        check_ajax_referer('atm_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $image_url = sanitize_url($_POST['image_url']);
        if ($image_url) {
            update_post_meta($post_id, '_atm_podcast_image', $image_url);
            wp_send_json_success(['image_url' => $image_url]);
        } else {
            wp_send_json_error('Invalid image URL');
        }
    }
    
    public function __construct() {
        // Core Actions for the React App & other features
        add_action('wp_ajax_generate_article_title', array($this, 'generate_article_title'));
        add_action('wp_ajax_generate_article_content', array($this, 'generate_article_content'));
        add_action('wp_ajax_generate_news_article', array($this, 'generate_news_article'));
        add_action('wp_ajax_fetch_rss_articles', array($this, 'fetch_rss_articles'));
        add_action('wp_ajax_generate_article_from_rss', array($this, 'generate_article_from_rss'));
        add_action('wp_ajax_generate_featured_image', array($this, 'generate_featured_image'));
        add_action('wp_ajax_generate_podcast_script', array($this, 'generate_podcast_script'));
        add_action('wp_ajax_generate_podcast', array($this, 'generate_podcast'));
        add_action('wp_ajax_upload_podcast_image', array($this, 'upload_podcast_image'));
        add_action('wp_ajax_transcribe_audio', array($this, 'transcribe_audio'));
        add_action('wp_ajax_translate_text', array($this, 'translate_text'));
        add_action('wp_ajax_translate_editor_content', array($this, 'translate_editor_content'));
        add_action('wp_ajax_get_youtube_suggestions', array($this, 'get_youtube_suggestions'));
        add_action('wp_ajax_search_youtube', array($this, 'search_youtube'));
        add_action('wp_ajax_generate_chart_from_ai', array($this, 'generate_chart_from_ai'));
        add_action('wp_ajax_save_atm_chart', array($this, 'save_atm_chart'));
        add_action('wp_ajax_generate_key_takeaways', array($this, 'generate_key_takeaways'));
        add_action('wp_ajax_save_key_takeaways', array($this, 'save_key_takeaways'));
        add_action('wp_ajax_get_post_subtitle', array($this, 'get_post_subtitle'));
        add_action('wp_ajax_populate_subtitle_field', array($this, 'populate_subtitle_field'));
        add_action('wp_ajax_check_podcast_progress', array($this, 'check_podcast_progress'));
        add_action('wp_ajax_debug_twitter_response', array($this, 'debug_twitter_response'));

        add_action('wp_ajax_search_google_news', array($this, 'search_google_news'));
        add_action('wp_ajax_generate_article_from_news_source', array($this, 'generate_article_from_news_source'));

        // NEW: Live News actions
        add_action('wp_ajax_search_live_news', array($this, 'search_live_news'));
        add_action('wp_ajax_generate_article_from_live_news', array($this, 'generate_article_from_live_news'));
        
        // NEW: Twitter/X News actions
        add_action('wp_ajax_search_twitter_news', array($this, 'search_twitter_news'));
        add_action('wp_ajax_generate_article_from_tweets', array($this, 'generate_article_from_tweets'));

        // --- MULTIPAGE ACTIONS ---
        add_action('wp_ajax_generate_multipage_title', array($this, 'generate_multipage_title'));
        add_action('wp_ajax_generate_multipage_outline', array($this, 'generate_multipage_outline'));
        add_action('wp_ajax_generate_multipage_content', array($this, 'generate_multipage_content'));
        add_action('wp_ajax_create_multipage_article', array($this, 'create_multipage_article'));
        add_action('wp_ajax_get_multipage_page_content', array($this, 'get_multipage_page_content')); // New AJAX action for frontend
        // --- END ---

        // Campaign Management Actions
        add_action('wp_ajax_atm_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_atm_delete_campaign', array($this, 'delete_campaign'));
        add_action('wp_ajax_atm_run_campaign_now', array($this, 'run_campaign_now'));

        // NEW hooks for comments tool
        add_action('wp_ajax_generate_post_comments', array($this, 'generate_post_comments'));
        add_action('wp_ajax_save_generated_comments', array($this, 'save_generated_comments'));

        // Helper/Legacy Actions
        add_action('wp_ajax_test_rss_feed', array($this, 'test_rss_feed'));
        add_action('wp_ajax_generate_inline_image', array($this, 'generate_inline_image'));
    }


    public function generate_podcast_script() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $article_content = wp_strip_all_tags(stripslashes($_POST['content']));
            $language = sanitize_text_field($_POST['language']);
            $post_id = intval($_POST['post_id']);
            $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : 'medium';

            if (empty($article_content)) {
                throw new Exception("Article content is empty. Please write your article first.");
            }

            // For long scripts, use background processing
            if ($duration === 'long') {
                $job_id = ATM_API::queue_script_generation($post_id, $language, $duration);
                
                wp_send_json_success([
                    'job_id' => $job_id,
                    'message' => 'Long script generation started in background...',
                    'status' => 'processing'
                ]);
                return;
            }

            // For short/medium scripts, process immediately but with extended timeout
            @set_time_limit(300);
            
            $post = get_post($post_id);
            $article_title = $post ? $post->post_title : 'Article';

            $generated_script = ATM_API::generate_advanced_podcast_script(
                $article_title,
                $article_content,
                $language,
                $duration
            );

            wp_send_json_success(['script' => $generated_script]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Check script generation progress
     */
    public function check_script_progress() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $job_id = sanitize_text_field($_POST['job_id']);
            $progress = ATM_API::get_script_progress($job_id);
            
            wp_send_json_success($progress);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // In class-atm-ajax.php - replace generate_podcast() method:
    public function generate_podcast() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $post_id = intval($_POST['post_id']);
            $script = wp_unslash($_POST['script']);
            $provider = sanitize_text_field($_POST['provider'] ?? 'openai');
            $host_a_voice = sanitize_text_field($_POST['host_a_voice'] ?? 'alloy');
            $host_b_voice = sanitize_text_field($_POST['host_b_voice'] ?? 'nova');
            
            if (empty($script) || empty($post_id)) {
                throw new Exception("Script and Post ID are required.");
            }

            // Start background processing with WordPress cron
            $job_id = ATM_API::queue_podcast_generation($post_id, $script, $host_a_voice, $host_b_voice, $provider);

            wp_send_json_success([
                'job_id' => $job_id,
                'message' => 'Podcast generation started in background...',
                'status' => 'processing'
            ]);

        } catch (Exception $e) {
            error_log('Content AI Studio - Podcast generation error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    // Add this new method for progress checking
    public function check_podcast_progress() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $job_id = sanitize_text_field($_POST['job_id']);
            $progress = ATM_API::get_podcast_progress($job_id);
            
            wp_send_json_success($progress);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_article_title() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $keyword = sanitize_text_field($_POST['keyword']);
            $title_input = isset($_POST['article_title']) ? sanitize_text_field($_POST['article_title']) : '';
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
            $topic = !empty($title_input) ? 'the article title: "' . $title_input . '"' : 'the keyword: "' . $keyword . '"';
            if (empty($topic)) {
                throw new Exception("Please provide a keyword or title.");
            }
            $system_prompt = 'You are an expert SEO content writer. Use your web search ability to understand the current context and popular phrasing for the given topic. Your task is to generate a single, compelling, SEO-friendly title. Return only the title itself, with no extra text or quotation marks.';
            $generated_title = ATM_API::enhance_content_with_openrouter(['content' => $topic], $system_prompt, $model_override ?: get_option('atm_article_model'));
            $cleaned_title = trim($generated_title, " \t\n\r\0\x0B\"");
            wp_send_json_success(['article_title' => $cleaned_title]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_article_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $post = get_post($post_id);
            $article_title = isset($_POST['article_title']) ? sanitize_text_field($_POST['article_title']) : '';
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
            $style_key = isset($_POST['writing_style']) ? sanitize_key($_POST['writing_style']) : 'default_seo';
            $custom_prompt = isset($_POST['custom_prompt']) ? wp_kses_post(stripslashes($_POST['custom_prompt'])) : '';
            $word_count = isset($_POST['word_count']) ? intval($_POST['word_count']) : 0;
            $creativity_level = isset($_POST['creativity_level']) ? sanitize_text_field($_POST['creativity_level']) : 'high';
            
            if (empty($article_title) && empty($keyword)) {
                throw new Exception("Please provide a keyword or an article title.");
            }
            
            // Ensure the angles table exists
            $this->ensure_angles_table_exists();
            
            $final_title = $article_title;
            $angle_combination = '';
            $angle_description = '';
            
            // Generate angle and title locally (NO API CALL) if no title provided
            if (empty($article_title) && !empty($keyword)) {
                $tracking_keyword = $keyword;
                $previous_angles = $this->get_previous_angles($tracking_keyword);
                
                error_log("ATM Debug: Found " . count($previous_angles) . " previous angles for: " . $tracking_keyword);
                
                // Generate angle locally without API call
                $angle_info = $this->generate_local_angle_and_title($tracking_keyword, $previous_angles);
                $final_title = $angle_info['title'];
                $angle_combination = $angle_info['combination_key'];
                $angle_description = $this->build_angle_description($angle_info['combination']); // NEW: Build readable description
                
                error_log("ATM Debug: Generated LOCAL title: " . $final_title);
                error_log("ATM Debug: Angle combination: " . $angle_combination);
                error_log("ATM Debug: Angle description: " . $angle_description);
                
                // Store the angle BEFORE API call - FIXED: Use angle description, not combination key
                $this->store_content_angle($tracking_keyword, $angle_description, $final_title);
            }
            
            // Build content prompt (this will be the ONLY API call)
            $writing_styles = ATM_API::get_writing_styles();
            $base_prompt = isset($writing_styles[$style_key]) ? $writing_styles[$style_key]['prompt'] : $writing_styles['default_seo']['prompt'];
            if (!empty($custom_prompt)) {
                $base_prompt = $custom_prompt;
            }
            
            // Add angle-specific instructions if we generated an angle
            if (!empty($angle_combination)) {
                $base_prompt .= "\n\nSPECIFIC CONTENT FOCUS: " . $this->get_prompt_focus_from_combination($angle_combination);
                $base_prompt .= "\nThis article MUST target this specific angle. Do not write a generic overview.";
            }
            
            $output_instructions = '
            **Final Output Format:**
            Your entire output MUST be a single, valid JSON object with two keys:
            1. "subheadline": A creative and engaging one-sentence subtitle that complements the main title.
            2. "content": The full article text, formatted using Markdown. 
            
            **IMPORTANT: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.**
            
            **Content Rules:**
            - The `content` field must NOT start with a title or any heading (like `# Heading`). It must begin directly with the first paragraph of the introduction.
            - Do NOT include a final heading titled "Conclusion". The article should end naturally with the concluding paragraph itself.';
            
            $system_prompt = $base_prompt . "\n\n" . $output_instructions;
            
            if ($post) {
                $system_prompt = ATM_API::replace_prompt_shortcodes($system_prompt, $post);
            }
            if ($word_count > 0) {
                $system_prompt .= " The final article should be approximately " . $word_count . " words long.";
            }
            
            error_log("ATM Debug: Making SINGLE API call for content generation");
            
            // SINGLE API CALL - this is the only charge
            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $final_title], 
                $system_prompt, 
                $model_override ?: get_option('atm_article_model'), 
                true, // JSON mode
                true, // enable web search - but only this ONE call
                $creativity_level
            );
            
            // Process response (same as before)
            $json_string = trim($raw_response);
            if (!str_starts_with($json_string, '{')) {
                if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                    $json_string = $matches[0];
                }
            }

            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
                error_log('Content AI Studio - Invalid JSON from Creative AI: ' . $raw_response);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }

            $subtitle = '';
            if (isset($result['subheadline']) && !empty(trim($result['subheadline']))) {
                $subtitle = trim($result['subheadline']);
            } elseif (isset($result['subtitle']) && !empty(trim($result['subtitle']))) {
                $subtitle = trim($result['subtitle']);
            }

            $final_content = trim($result['content']);

            // Save subtitle
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                update_post_meta($post_id, '_atm_subtitle', $subtitle);
            }

            wp_send_json_success(['article_title' => $final_title, 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

   // Add this method to the ATM_Ajax class
    private function build_angle_description($combination) {
        return sprintf(
            "Target %s in %s industry focusing on %s, structured as %s, with %s perspective, at %s level, considering %s budget constraints",
            $this->format_simple($combination['audience']),
            $this->format_simple($combination['industry']),
            $this->format_simple($combination['problem']),
            $this->format_simple($combination['format']),
            $this->format_simple($combination['time']),
            $this->format_simple($combination['skill']),
            $this->format_simple($combination['budget'])
        );
    }

    private function build_detailed_angle_context($angle_parts, $title) {
        if (count($angle_parts) < 7) return "";
        
        [$audience, $industry, $problem, $format, $time, $skill, $budget] = $angle_parts;
        
        return "**MANDATORY CONTENT ANGLE - FOLLOW EXACTLY:**

    SPECIFIC AUDIENCE: Write exclusively for {$audience} working in the {$industry} industry
    EXACT PROBLEM FOCUS: Address {$problem} - this must be the central theme
    SKILL LEVEL REQUIREMENT: Target {$skill} level readers with appropriate depth
    BUDGET CONTEXT: Consider {$budget} budget constraints in all recommendations
    TIME PERSPECTIVE: Frame everything from a {$time} viewpoint
    CONTENT FORMAT: Structure as a {$format} with appropriate formatting

    **STRICT CONTENT RULES:**
    1. Every paragraph must relate to this specific audience-problem combination
    2. Use industry-specific terminology and examples relevant to {$industry}
    3. Address the {$problem} challenge in multiple sections throughout the article
    4. Provide solutions appropriate for {$skill} level readers
    5. Consider {$budget} constraints in all recommendations
    6. Include 3-5 specific examples from the {$industry} industry
    7. Use the {$time} context to frame trends, predictions, or current relevance

    **FORBIDDEN CONTENT:**
    - Generic advice that could apply to any industry
    - Solutions inappropriate for the {$skill} level
    - Recommendations that ignore {$budget} constraints
    - Content that doesn't address the core {$problem}

    This is not optional - every single piece of content must align with this angle.";
    }

    // Add this new method for local angle generation (no API calls)
    private function generate_local_angle_and_title($keyword, $previous_angles) {
        $dimensions = $this->get_massive_scale_angles();
        
        // Get used combinations
        $used_combinations = [];
        foreach ($previous_angles as $prev) {
            if (isset($prev['angle'])) {
                $used_combinations[] = $prev['angle'];
            }
        }
        
        // Generate unique combination locally
        $max_attempts = 20;
        $attempts = 0;
        
        do {
            $combination = [
                'audience' => $dimensions['target_audiences'][array_rand($dimensions['target_audiences'])],
                'industry' => $dimensions['industries'][array_rand($dimensions['industries'])],
                'problem' => $dimensions['problem_types'][array_rand($dimensions['problem_types'])],
                'format' => $dimensions['content_formats'][array_rand($dimensions['content_formats'])],
                'time' => $dimensions['time_contexts'][array_rand($dimensions['time_contexts'])],
                'skill' => $dimensions['skill_levels'][array_rand($dimensions['skill_levels'])],
                'budget' => $dimensions['budget_constraints'][array_rand($dimensions['budget_constraints'])]
            ];
            
            $combination_key = implode('|', array_values($combination));
            $attempts++;
            
        } while (in_array($combination_key, $used_combinations) && $attempts < $max_attempts);
        
        // Generate title from template locally
        $title = $this->generate_title_from_combination($keyword, $combination);
        
        return [
            'title' => $title,
            'combination_key' => $combination_key,
            'combination' => $combination
        ];
    }

    private function get_prompt_focus_from_combination($combination_key) {
        $parts = explode('|', $combination_key);
        if (count($parts) >= 7) {
            return "Target audience: {$parts[0]} in {$parts[1]} industry, focusing on {$parts[2]}, structured as {$parts[3]}, with {$parts[4]} perspective, at {$parts[5]} level, considering {$parts[6]} budget constraints.";
        }
        return "Focus on creating unique, valuable content for the specific target audience.";
    }

    // Add this new method to generate title with angle
    private function generate_title_with_angle($keyword, $angle, $model_override = '') {
        $title_prompt = "You are an expert copywriter specializing in creating HIGHLY COMPELLING, click-worthy titles. 

    MANDATORY ANGLE TO FOLLOW: {$angle}

    TITLE REQUIREMENTS:
    - Must reflect this SPECIFIC angle, not be a generic overview
    - Should be 8-18 words long
    - Must be engaging and clickable (think viral content)
    - Should target the specific audience mentioned in the angle
    - Use power words and emotional triggers
    - Make it feel fresh and unique
    - Include numbers, years, or specific benefits when relevant

    TITLE FORMULAS TO USE:
    1. Problem/Solution: 'Why [Problem] and How [Solution]'
    2. List/Number: '[Number] [Specific Things] That [Benefit]'
    3. Contrarian: 'Why Everything You Know About [Topic] Is Wrong'
    4. Urgency: 'The [Year] Guide to [Specific Outcome]'
    5. Secret/Insider: '[Number] Industry Secrets [Professionals] Don't Want You to Know'
    6. Transformation: 'How [Specific Group] Can [Transform] Using [Method]'
    7. Mistake-focused: '[Number] [Keyword] Mistakes That Are [Negative Outcome]'

    EXAMPLES of angle-focused titles:
    Angle: 'How small local businesses can compete with big brands'
    Title: 'How Small Businesses Are Crushing Corporate Giants with These 7 Guerrilla Marketing Tactics'

    Angle: 'The hidden psychological triggers for Gen Z engagement'
    Title: 'The 5 Psychological Triggers That Make Gen Z Actually Click Your Ads (Data-Driven)'

    Angle: 'Why nonprofits fail at digital marketing'
    Title: 'Why 90% of Nonprofits Fail at Digital Marketing (And How to Be the Exception)'

    Generate a compelling title for this angle: {$angle}
    Include the keyword '{$keyword}' naturally in the title.
    Return ONLY the title, nothing else.";

        return trim(ATM_API::enhance_content_with_openrouter(
            ['content' => $keyword], 
            $title_prompt, 
            $model_override ?: get_option('atm_article_model'),
            false, // not JSON mode
            true   // enable web search
        ));
    }

    // Keep your existing helper methods
    private function get_previous_angles($keyword) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT angle, title, created_at FROM $table_name 
            WHERE keyword = %s 
            ORDER BY created_at DESC 
            LIMIT 50",
            $keyword
        ), ARRAY_A);
        
        // Log angle diversity metrics
        if (!empty($results)) {
            $unique_combinations = array_unique(array_column($results, 'angle'));
            $diversity_score = count($unique_combinations) / count($results);
            error_log("ATM Angle Diversity for '$keyword': " . round($diversity_score * 100, 1) . "% unique combinations");
        }
        
        return $results ?: [];
    }

    private function get_random_angle_category() {
        $categories = [
            'industry_specific' => [
                'healthcare', 'education', 'finance', 'retail', 'manufacturing', 
                'real estate', 'hospitality', 'automotive', 'legal', 'consulting'
            ],
            'audience_specific' => [
                'beginners', 'advanced professionals', 'entrepreneurs', 'students', 
                'freelancers', 'small business owners', 'corporate executives', 'startups'
            ],
            'problem_focused' => [
                'common mistakes to avoid', 'optimization strategies', 'troubleshooting guide',
                'cost-effective solutions', 'time-saving techniques', 'ROI improvement'
            ],
            'trend_based' => [
                '2025 predictions', 'emerging technologies', 'future impact', 'AI integration',
                'post-pandemic changes', 'mobile-first approaches', 'voice search optimization'
            ]
        ];
        
        $category_keys = array_keys($categories);
        $random_category = $categories[$category_keys[array_rand($category_keys)]];
        return $random_category[array_rand($random_category)];
    }

    private function build_angle_generation_prompt($keyword, $previous_angles) {
        $previous_context = '';
        if (!empty($previous_angles)) {
            $previous_context = "\n\nPREVIOUS ANGLES ALREADY COVERED for '{$keyword}':\n";
            foreach ($previous_angles as $i => $angle_data) {
                $previous_context .= "- " . ($i + 1) . ". " . $angle_data['angle'] . "\n";
            }
            $previous_context .= "\nYou MUST create a completely different angle that hasn't been covered before. Avoid ANY similarity to these previous angles.";
        }
        
        return "You are a content strategist tasked with creating EXTREMELY DIVERSE angles for '{$keyword}'. Each angle must target different audiences, industries, or perspectives.

    REQUIREMENTS:
    - Return ONLY a single sentence describing a RADICALLY different angle
    - Make it highly specific to a particular audience, use case, or industry
    - Focus on unique problems, solutions, or perspectives
    - Avoid generic overviews or similar phrasing to previous angles

    ANGLE CATEGORIES TO ROTATE BETWEEN:
    1. Industry-specific applications (healthcare, education, finance, retail, etc.)
    2. Audience-specific guides (beginners, professionals, entrepreneurs, students)
    3. Problem-solving approaches (common mistakes, optimization, troubleshooting)
    4. Trend-based perspectives (2025 predictions, emerging technologies, future impact)
    5. Comparative analysis (vs competitors, before/after, tool comparisons)
    6. Case study approaches (success stories, failure analysis, real-world examples)
    7. Technical deep-dives (advanced strategies, expert techniques, insider secrets)
    8. Ethical/philosophical angles (privacy concerns, social impact, responsibility)

    {$previous_context}

    EXAMPLES of diverse angles for 'Digital Marketing':
    - 'How small local businesses can compete with big brands using grassroots digital marketing tactics'
    - 'The hidden psychological triggers that make Gen Z consumers actually engage with digital ads'
    - 'Why 90% of nonprofits fail at digital marketing and how to be in the winning 10%'
    - 'The dark side of digital marketing: ethical considerations every marketer must address'
    - 'How B2B manufacturing companies can leverage digital marketing to reach decision-makers'

    Generate a completely unique angle for: {$keyword}";
    }

    private function build_enhanced_system_prompt($base_prompt, $new_angle, $previous_angles) {
        $uniqueness_instruction = "
        
        **MANDATORY ANGLE ENFORCEMENT:**
        YOUR SPECIFIC REQUIRED ANGLE: {$new_angle}
        
        YOU MUST WRITE ENTIRELY FROM THIS ANGLE. This is not optional.
        - Do NOT write a general overview of the topic
        - Do NOT cover multiple angles - focus ONLY on this specific angle
        - Every paragraph must relate directly to this angle
        - Your title, introduction, and conclusion must all reflect this specific angle";
        
        if (!empty($previous_angles)) {
            $uniqueness_instruction .= "\n\n**ANGLES ALREADY COVERED - AVOID COMPLETELY:**\n";
            foreach ($previous_angles as $angle_data) {
                $uniqueness_instruction .= "❌ " . $angle_data['angle'] . "\n";
            }
            $uniqueness_instruction .= "\nYour content must be COMPLETELY DIFFERENT from these previous approaches.";
        }
        
        $uniqueness_instruction .= "\n\n**EXTREME CREATIVITY REQUIREMENTS:**
        - Use unexpected examples from different industries
        - Include contrarian or controversial viewpoints when appropriate  
        - Focus on very specific, niche aspects rather than broad topics
        - Add surprising statistics or research findings
        - Use analogies from completely unrelated fields
        - Challenge conventional wisdom about the topic
        - Include emerging trends that others might miss
        - Write from a unique demographic or business perspective
        - Use storytelling elements and case studies
        - Include actionable insights that are rarely discussed
        
        **CONTENT STRUCTURE MANDATE:**
        - Start with a hook that immediately relates to your specific angle
        - Every major section must advance your specific angle
        - Include at least 3 specific examples or case studies that support your angle
        - End with actionable advice specific to your angle
        
        FAILURE TO FOLLOW YOUR ASSIGNED ANGLE WILL RESULT IN CONTENT REJECTION.";
        
        return $base_prompt . $uniqueness_instruction;
    }

    private function store_content_angle($keyword, $angle, $title) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        // Verify table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            error_log("ATM Debug: Content angles table does not exist, creating...");
            ATM_Main::create_content_angles_table();
        }
        
        error_log("ATM Debug: Attempting to store angle - Keyword: $keyword, Angle: $angle, Title: $title");
        
        $result = $wpdb->insert($table_name, [
            'keyword' => $keyword,
            'angle' => $angle,
            'title' => $title,
            'created_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            error_log("ATM Debug: Failed to store angle. Error: " . $wpdb->last_error);
            error_log("ATM Debug: Last query: " . $wpdb->last_query);
            // Try to create table again if insert failed
            ATM_Main::create_content_angles_table();
        } else {
            error_log("ATM Debug: Successfully stored angle. Insert ID: " . $wpdb->insert_id);
        }
        
        return $result;
    }

    public function generate_news_article() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $topic = sanitize_text_field($_POST['topic']);
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : get_option('atm_article_model');
            $force_fresh = isset($_POST['force_fresh']) && $_POST['force_fresh'] === 'true';
            $news_source = isset($_POST['news_source']) ? sanitize_key($_POST['news_source']) : 'newsapi';
            if (empty($topic)) {
                throw new Exception("Please provide a topic for the news article.");
            }
            $news_context = ATM_API::fetch_news($topic, $news_source, $force_fresh);
            if (empty($news_context)) {
                throw new Exception("No recent news found for the topic: '" . esc_html($topic) . "'. Please try a different keyword or source.");
            }
            $system_prompt = 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English. **Use your web search ability to verify the information and add any missing context.**

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
                - Example: railway that [had a deadly crash](https://reuters.com/specific-article) last week and will...
                - Example: Example: [Reuters](https://reuters.com/actual-article-url) reported that...
                - Example: According to [BBC News](https://bbc.com/specific-article), the incident...
                - Do NOT use generic phrases like "click here", "read more", or "this article" as anchor text
                - Anchor text should be relevant keywords from the article topic (e.g., marketing, design, finance, AI)
                - Keep anchor text extremely concise (maximum 2 words)
                - Make links feel natural within the sentence flow
                - Avoid long phrases as anchor text

                **Final Output Format:**
                Your entire output MUST be a single, valid JSON object with three keys:
                1. "title": A concise, factual, and compelling headline for the new article.
                2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
                3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.';
            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $news_context], $system_prompt, $model_override, true);

            error_log("ATM Debug: Final system prompt length: " . strlen($system_prompt));
            
            // More robust JSON extraction
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

            // Robust field extraction
            $headline = '';
            if (isset($result['title']) && !empty(trim($result['title']))) {
                $headline = trim($result['title']);
            } elseif (isset($result['headline']) && !empty(trim($result['headline']))) {
                $headline = trim($result['headline']);
            } else {
                throw new Exception('No headline found in AI response.');
            }

            $subtitle = '';
            if (isset($result['subheadline']) && !empty(trim($result['subheadline']))) {
                $subtitle = trim($result['subheadline']);
            } elseif (isset($result['subtitle']) && !empty(trim($result['subtitle']))) {
                $subtitle = trim($result['subtitle']);
            }

            $original_content = trim($result['content']);
            $final_content = $original_content;

            // Direct SmartMag subtitle saving
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                error_log("ATM Plugin: Saved NEWS subtitle '{$subtitle}' to SmartMag field for post {$post_id}");
            }

            if (empty($headline) || empty($final_content)) {
                throw new Exception('Generated title or content is empty.');
            }

            wp_send_json_success(['article_title' => $headline, 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function fetch_rss_articles() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
            $use_scraping = isset($_POST['use_scraping']) && $_POST['use_scraping'] === 'true';
            $rss_feeds_string = get_option('atm_rss_feeds', '');
            if (empty($rss_feeds_string)) {
                throw new Exception('No RSS feeds configured in settings.');
            }
            if ($use_scraping) {
                $articles = ATM_API::search_rss_feeds($rss_feeds_string, $keyword, true);
            } else {
                $articles = ATM_API::parse_rss_feeds($rss_feeds_string, $post_id, $keyword);
            }
            if (empty($articles) && !empty($keyword)) {
                error_log('ATM RSS: No articles found for keyword "' . $keyword . '" in ' . count(explode("\n", $rss_feeds_string)) . ' feeds');
            }
            wp_send_json_success($articles);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function test_rss_feed() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $feed_url = esc_url_raw($_POST['feed_url']);
            $keyword = sanitize_text_field($_POST['keyword'] ?? '');
            if (empty($feed_url)) {
                throw new Exception('Feed URL is required.');
            }
            $articles = ATM_RSS_Parser::parse_rss_feeds_advanced($feed_url, 0, $keyword);
            wp_send_json_success(['articles' => $articles, 'total_found' => count($articles), 'feed_url' => $feed_url, 'keyword' => $keyword]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_article_from_rss() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        @ini_set('max_execution_time', 300); // 5 minutes
        @set_time_limit(300);

        
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $url = esc_url_raw($_POST['article_url']);
            $guid = sanitize_text_field($_POST['article_guid']);
            $use_full_content = isset($_POST['use_full_content']) && $_POST['use_full_content'] === 'true';
            
            $final_url = ATM_API::resolve_redirect_url($url);
            $source_content = '';
            if ($use_full_content) {
                try {
                    $source_content = ATM_API::fetch_full_article_content($final_url);
                } catch (Exception $e) {
                    error_log('ATM RSS: Scraping failed, falling back to RSS content: ' . $e->getMessage());
                }
            }
            if (empty($source_content) && isset($_POST['rss_content'])) {
                $source_content = wp_kses_post(stripslashes($_POST['rss_content']));
            }
            if (empty($source_content)) {
                throw new Exception('Could not extract sufficient content from the source article.');
            }
            if (strlen($source_content) > 4000) {
                $source_content = ATM_API::summarize_content_for_rewrite($source_content);
            }
            $system_prompt = 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English. **Use your web search ability to verify the information and add any missing context.**

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
                - Example: railway that [had a deadly crash](https://reuters.com/specific-article) last week and will...
                - Example: Example: [Reuters](https://reuters.com/actual-article-url) reported that...
                - Example: According to [BBC News](https://bbc.com/specific-article), the incident...
                - Do NOT use generic phrases like "click here", "read more", or "this article" as anchor text
                - Anchor text should be relevant keywords from the article topic (e.g., marketing, design, finance, AI)
                - Keep anchor text extremely concise (maximum 2 words)
                - Make links feel natural within the sentence flow
                - Avoid long phrases as anchor text

                **Final Output Format:**
                Your entire output MUST be a single, valid JSON object with three keys:
                1. "title": A concise, factual, and compelling headline for the new article.
                2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
                3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.';
            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $source_content], $system_prompt, get_option('atm_article_model', 'openai/gpt-4o'), true);
            
            // More robust JSON extraction
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

            // Robust field extraction
            $headline = '';
            if (isset($result['title']) && !empty(trim($result['title']))) {
                $headline = trim($result['title']);
            } elseif (isset($result['headline']) && !empty(trim($result['headline']))) {
                $headline = trim($result['headline']);
            }

            $subtitle = '';
            if (isset($result['subheadline']) && !empty(trim($result['subheadline']))) {
                $subtitle = trim($result['subheadline']);
            } elseif (isset($result['subtitle']) && !empty(trim($result['subtitle']))) {
                $subtitle = trim($result['subtitle']);
            }

            if (empty($headline) || empty($result['content'])) {
                throw new Exception('AI response missing required fields.');
            }

            $original_content = trim($result['content']);
            $final_content = $original_content;

            // Direct SmartMag subtitle saving
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                error_log("ATM Plugin: Saved RSS subtitle '{$subtitle}' to SmartMag field for post {$post_id}");
            }

            if ($post_id > 0) {
                $used_guids = get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [];
                $used_guids[] = $guid;
                update_post_meta($post_id, '_atm_used_rss_guids', array_unique($used_guids));
            }

            wp_send_json_success(['article_title' => $headline, 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_featured_image() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);
        try {
            $post_id = intval($_POST['post_id']);
            $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(stripslashes($_POST['prompt'])) : '';
            $size_override = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
            $quality_override = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : '';
            $provider_override = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';

            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found.");
            }

            // If no prompt provided, generate one automatically from the post title
            if (empty(trim($prompt))) {
                $prompt = ATM_API::get_default_image_prompt();
            }

            // We use the prompt, replacing any shortcodes it might contain
            $final_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);

            $provider = !empty($provider_override) ? $provider_override : get_option('atm_image_provider', 'openai');
            $image_data = null;
            $is_url = false;

            switch ($provider) {
                case 'google':
                    $image_data = ATM_API::generate_image_with_google_imagen($final_prompt, $size_override);
                    $is_url = false;
                    break;
                case 'blockflow':
                    $image_data = ATM_API::generate_image_with_blockflow($final_prompt, '', $size_override);
                    $is_url = false;
                    break;
                case 'openai':
                default:
                    $image_data = ATM_API::generate_image_with_openai($final_prompt, $size_override, $quality_override);
                    $is_url = true;
                    break;
            }

            if ($is_url) {
                $attachment_id = $this->set_image_from_url($image_data, $post_id);
            } else {
                $attachment_id = $this->set_image_from_data($image_data, $post_id, $final_prompt);
            }

            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }

            set_post_thumbnail($post_id, $attachment_id);
            $thumbnail_html = _wp_post_thumbnail_html($attachment_id, $post_id);

            wp_send_json_success([
                'attachment_id' => $attachment_id, 
                'html' => $thumbnail_html,
                'generated_prompt' => $final_prompt // Include the generated prompt for reference
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_inline_image() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $prompt = sanitize_textarea_field($_POST['prompt']);
            if (empty($prompt)) {
                throw new Exception("Prompt cannot be empty.");
            }
            $post = get_post($post_id);
            $processed_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);
            $image_url = ATM_API::generate_image_with_openai($processed_prompt);
            $attachment_id = $this->set_image_from_url($image_url, $post_id);
            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }
            $image_data = wp_get_attachment_image_src($attachment_id, 'large');
            wp_send_json_success(['url' => $image_data[0], 'alt' => $prompt]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function set_image_from_url($url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        $file_array = array();
        preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }
        return $id;
    }

    public function set_image_from_data($image_data, $post_id, $prompt) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $upload_dir = wp_upload_dir();
        $filename = 'ai-image-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;
        file_put_contents($filepath, $image_data);
        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename($filepath),
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field($prompt),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}