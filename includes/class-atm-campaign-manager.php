<?php
// in includes/class-atm-campaign-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Campaign_Manager {

    private static $NEWS_WHITELIST = [
        'reuters.com','apnews.com','bbc.com','bbc.co.uk','nytimes.com','theguardian.com',
        'washingtonpost.com','wsj.com','bloomberg.com','ft.com','aljazeera.com',
        'npr.org','axios.com','politico.com','cnbc.com','abcnews.go.com',
        'nbcnews.com','cbsnews.com','usatoday.com','sky.com','skynews.com','time.com',
        'cnn.com','espn.com','wcvb.com','syracuse.com','abc7ny.com'
    ];

    public function __construct() {
        add_action('atm_run_due_campaigns', array($this, 'execute_due_campaigns'));
    }
    
    public static function schedule_main_cron() {
        if (!wp_next_scheduled('atm_run_due_campaigns')) {
            wp_schedule_event(time(), 'five_minutes', 'atm_run_due_campaigns');
        }
    }

    public function execute_due_campaigns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaigns_to_run = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE is_active = 1 AND next_run <= %s", current_time('mysql', 1)));

        foreach ($campaigns_to_run as $campaign) {
            try {
                self::execute_campaign($campaign->id);
            } catch (Exception $e) {
                error_log('Content AI Campaign Error (ID: ' . $campaign->id . '): ' . $e->getMessage());
            }
        }
    }

    public static function execute_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
        if (!$campaign) {
            error_log('ATM Campaign Error: Campaign ID ' . $campaign_id . ' not found.');
            return;
        }

        switch ($campaign->article_type) {
            case 'News':
                self::_execute_news_campaign($campaign);
                break;
            case 'Review':
                self::_execute_review_campaign($campaign);
                break;
            case 'How-To':
            case 'Tutorial':
                self::_execute_how_to_campaign($campaign);
                break;
            case 'Listicle':
                self::_execute_listicle_campaign($campaign);
                break;
            case 'Opinion':
                self::_execute_opinion_campaign($campaign);
                break;
            default: // Catches 'Informative' and any others
                self::_execute_default_campaign($campaign);
                break;
        }

        $wpdb->update($table_name, ['last_run' => current_time('mysql', 1)], ['id' => $campaign_id]);
        self::update_next_run_time($campaign_id, $campaign->frequency_value, $campaign->frequency_unit);
    }

    /**
     * --- SPECIALIZED CAMPAIGN HANDLERS ---
     */

    private static function _execute_news_campaign($campaign) {
        $current_date = date('F j, Y');
        $current_time = date('g:i A T');
        
        // Direct Google search approach - no need to use fetch_news method
        $search_prompt = "You are a news research assistant with access to current web search. Your task is to search for the most recent and newsworthy stories related to '{$campaign->keyword}' that happened in the last 24-48 hours.

        SEARCH INSTRUCTIONS:
        1. Search Google for breaking news, recent developments, or current events about '{$campaign->keyword}'
        2. Look specifically for stories from the past 24-48 hours
        3. Focus on significant events, incidents, policy changes, or developments  
        4. Prioritize stories from reputable news sources (Reuters, AP, BBC, CNN, NBC, Fox News, NPR, etc.)
        5. Today is {$current_date} at {$current_time} - find the most current stories

        Search queries to try:
        - '{$campaign->keyword} news today'
        - '{$campaign->keyword} breaking news'
        - 'latest {$campaign->keyword} news'

        After your web search research, identify the MOST NEWSWORTHY and RECENT story and provide:
        1. A clear headline of what happened
        2. When it happened (specific date/time if available)
        3. Where it happened (location)
        4. Key people, organizations, or entities involved
        5. Why it's significant or newsworthy
        6. 2-3 URLs from credible news sources reporting this story

        If you cannot find any specific recent news events about '{$campaign->keyword}', respond with 'NO_RECENT_NEWS_FOUND'.";

        // First, use AI with web search to find recent news
        $news_research = ATM_API::enhance_content_with_openrouter(
            ['content' => "Research recent news about: {$campaign->keyword}"], 
            $search_prompt, 
            '', // Use default model
            false // Not JSON mode
        );

        if (strpos($news_research, 'NO_RECENT_NEWS_FOUND') !== false) {
            error_log('ATM News Campaign '.$campaign->id.': No recent news found for "'.$campaign->keyword.'" via web search.');
            return;
        }

        // Now use the research to write the article
        $writer_prompt = "You are a professional breaking news journalist writing for an audience in {$campaign->country}. Today is {$current_date} at {$current_time}.

        Based on the news research provided, write a comprehensive breaking news article about the specific recent event that was found.

        CRITICAL REQUIREMENTS:
        1. Focus on the SPECIFIC recent event from the research - not a general topic overview
        2. Write in breaking news style with a strong lead paragraph answering: Who, What, When, Where, Why
        3. Use your web search to verify facts and get additional current details about this specific story
        4. Include direct quotes from officials, witnesses, or official statements when available
        5. Provide background context but keep focus on the breaking news story
        6. Add timeline of events and latest updates available

        ARTICLE STRUCTURE:
        - Lead: What happened, when, where, who involved (first paragraph)
        - Details: How it happened, specific circumstances
        - Context: Background information and why this matters
        - Updates: Latest developments and current status
        - Impact: Implications and what happens next

        RESPONSE FORMAT (MANDATORY):
        Return a single, valid JSON object with keys: 'title', 'subheadline', 'content'.
        - 'title': specific, newsworthy headline about the current event (include key details)
        - 'subheadline': one sentence with crucial breaking news context
        - 'content': clean HTML content (800-1200 words) starting with breaking news lead. Include 3-4 subheadings (<h2>/<h3>) and multiple external links to current news sources using <a href=\"URL\">descriptive text</a>. No 'Conclusion' heading.

        QUALITY STANDARDS:
        - Minimum 800 words of substantial reporting
        - Include at least 3-4 external links to current news sources
        - Use journalistic attribution (\"according to X\", \"officials said\", etc.)
        - Include specific times, dates, locations, and names
        - Reference the most current information available
        - End with latest updates or expected developments";

        $generated_json = ATM_API::enhance_content_with_openrouter(
            ['content' => $news_research], 
            $writer_prompt, 
            '', // Use default model  
            true // JSON mode
        );

        $data = json_decode($generated_json, true);
        
        if (!self::_validate_news_article_json($data)) {
            error_log('ATM News Campaign '.$campaign->id.': Writer returned invalid JSON. Raw response: ' . substr($generated_json, 0, 500));
            return;
        }
        
        // Log successful article creation for debugging
        error_log('ATM News Campaign '.$campaign->id.': Successfully generated article: "' . $data['title'] . '"');
        
        self::_create_post_from_ai_data($campaign, $data);
    }
    
    private static function _execute_review_campaign($campaign) {
        $system_prompt = "You are an expert product reviewer. Your task is to write a comprehensive, unbiased review of '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find features, specifications, pricing, user opinions, pros, and cons. Structure the article with clear headings for each section (e.g., Key Features, Performance, Pros, Cons, Final Verdict). The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading. Write at least 800 words.";
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_how_to_campaign($campaign) {
        $system_prompt = "You are a technical writer creating a clear, step-by-step guide on '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find the most accurate and easy-to-follow steps. Structure the article with an introduction, a 'What You'll Need' section (if applicable), and then the steps using an ordered list (<ol>). The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading. Write at least 800 words.";
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_listicle_campaign($campaign) {
        $system_prompt = "You are a popular blogger creating a listicle article about '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find interesting and relevant items for the list. The title should be in a listicle format (e.g., 'Top 7...' or '5 Ways to...'). Structure the article with an engaging introduction, followed by numbered headings (e.g., <h2>1. First Item</h2>) for each point in the list. The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading. Write at least 800 words.";
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }
    
    private static function _execute_opinion_campaign($campaign) {
        $system_prompt = "You are an opinionated columnist with deep expertise on topics for an audience in {$campaign->country}. Your task is to write a persuasive opinion piece on '{$campaign->keyword}'. Use web search to understand the topic and form a strong, well-supported argument. Structure the article with a clear thesis, several supporting paragraphs with evidence or examples, acknowledge a counter-argument, and end with a strong concluding statement. The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading. Write at least 800 words.";
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_default_campaign($campaign) {
        if (post_exists($campaign->keyword)) {
            error_log('ATM Campaign ' . $campaign->id . ': Aborted. An article with the title "' . $campaign->keyword . '" already exists.');
            return;
        }
        $base_prompt = $campaign->custom_prompt ?: ATM_API::get_default_article_prompt();
        $replacements = ['[article_type]' => $campaign->article_type, '[country]' => $campaign->country, '[keyword]' => $campaign->keyword];
        $system_prompt = str_replace(array_keys($replacements), array_values($replacements), $base_prompt);
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    /**
     * --- SHARED & NEW HELPER FUNCTIONS ---
     */

    private static function _create_post_from_ai_data($campaign, $article_data) {
        if (!$article_data || empty($article_data['content']) || empty($article_data['title']) || post_exists($article_data['title'])) {
            error_log('ATM Campaign ' . $campaign->id . ': Aborted due to invalid data or duplicate title from AI.');
            return;
        }

        // Don't use Parsedown since content is already HTML
        $html_content = $article_data['content'];

        $post_data = [
            'post_title'    => wp_strip_all_tags($article_data['title']),
            'post_content'  => wp_slash($html_content),
            'post_status'   => $campaign->post_status,
            'post_author'   => $campaign->author_id,
            'post_category' => array($campaign->category_id)
        ];
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            if (!empty($article_data['subheadline'])) {
                ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $article_data['subheadline'], '');
            }
            if ($campaign->generate_image) {
                $ajax_handler = new ATM_Ajax();
                $image_prompt = ATM_API::get_default_image_prompt();
                $processed_prompt = ATM_API::replace_prompt_shortcodes($image_prompt, get_post($post_id));
                $image_url = ATM_API::generate_image_with_openai($processed_prompt);
                $attachment_id = $ajax_handler->set_image_from_url($image_url, $post_id);
                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            }
            
            error_log('ATM Campaign ' . $campaign->id . ': Successfully created post ID ' . $post_id . ' with title: ' . $article_data['title']);
        }
    }

    private static function _fetch_recent_google_news($query, $country, $limit = 10, $max_age_hours = 36) {
        // This function is now deprecated in favor of using NewsAPI directly
        // Keeping it for backward compatibility but not using it for news campaigns
        return [];
    }

    private static function _resolve_gnews_url($link) {
        return null;
    }

    private static function _is_reputable_domain($host) {
        $host = strtolower($host);
        foreach (self::$NEWS_WHITELIST as $good) {
            if (str_ends_with($host, $good)) return true;
        }
        return false;
    }

    private static function _clean_host($host) {
        return str_starts_with(strtolower($host), 'www.') ? substr($host, 4) : $host;
    }

    private static function _pick_best_article(array $articles, string $keyword) {
        return $articles[0] ?? null;
    }

    private static function _google_codes($country) {
        $map = [
            'United States' => ['hl'=>'en-US','gl'=>'US','ceid'=>'US:en'],
            'United Kingdom'=> ['hl'=>'en-GB','gl'=>'GB','ceid'=>'GB:en'],
            'Turkey' => ['hl'=>'tr-TR','gl'=>'TR','ceid'=>'TR:tr'],
            'Türkiye' => ['hl'=>'tr-TR','gl'=>'TR','ceid'=>'TR:tr'],
            'Canada' => ['hl'=>'en-CA','gl'=>'CA','ceid'=>'CA:en'],
            'Australia' => ['hl'=>'en-AU','gl'=>'AU','ceid'=>'AU:en'],
        ];
        
        return $map[$country] ?? ['hl'=>'en-US','gl'=>'US','ceid'=>'US:en'];
    }

    private static function _validate_news_article_json($data) {
        if (!is_array($data)) return false;
        foreach (['title','subheadline','content'] as $k) {
            if (!array_key_exists($k, $data)) return false;
        }
        return !empty($data['title']) && !empty($data['content']) && strlen(strip_tags($data['content'])) > 500;
    }
    
    private static function update_next_run_time($campaign_id, $value, $unit) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $interval_string = "+$value $unit";
        $next_run_timestamp = strtotime($interval_string, current_time('timestamp', 1));
        $next_run_mysql = date('Y-m-d H:i:s', $next_run_timestamp);
        $wpdb->update($table_name, ['next_run' => $next_run_mysql], ['id' => $campaign_id]);
    }
}