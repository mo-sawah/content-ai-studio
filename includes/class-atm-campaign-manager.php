<?php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Campaign_Manager {

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

    /**
     * Main dispatcher for executing a campaign.
     * Routes the campaign to a specialized function based on its type.
     */
    public static function execute_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
        if (!$campaign) {
            error_log('ATM Campaign Error: Campaign ID ' . $campaign_id . ' not found.');
            return;
        }

        // --- Dispatcher Logic ---
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

        // Update run times after execution
        $wpdb->update($table_name, ['last_run' => current_time('mysql', 1)], ['id' => $campaign_id]);
        self::update_next_run_time($campaign_id, $campaign->frequency_value, $campaign->frequency_unit);
    }

    /**
     * --- SPECIALIZED CAMPAIGN HANDLERS ---
     */

    private static function _execute_news_campaign($campaign) {
        // --- STEP 1: AI-powered Topic Discovery ---
        // This prompt instructs a fast AI to act as a research assistant.
        $topic_discovery_prompt = "You are a news research assistant. Your task is to find the single most significant and recent news story (from the last 48 hours) related to '{$campaign->keyword}' in '{$campaign->country}'.

        CRITICAL INSTRUCTIONS:
        - You MUST restrict your web search to reputable, major news sources (e.g., Google News, Reuters, Associated Press, BBC News, NYTimes, etc.).
        - Analyze the search results and identify the most newsworthy event.
        - Your ONLY output should be a single, specific, and compelling headline for a news article about that event. Do not write the article, just the headline.";

        // Use a fast, cost-effective model for the research task.
        $specific_headline = ATM_API::enhance_content_with_openrouter(
            ['content' => $campaign->keyword], 
            $topic_discovery_prompt, 
            'anthropic/claude-3-haiku', // Fast model for discovery
            false, 
            true // Web search is ENABLED for this step
        );

        if (empty($specific_headline) || strlen($specific_headline) > 200) {
            error_log('ATM News Campaign ' . $campaign->id . ': AI failed to identify a specific topic for "' . $campaign->keyword . '".');
            return;
        }
        
        // --- STEP 2: Focused Article Generation ---
        // This prompt instructs a powerful AI to write the full article on the specific topic found in Step 1.
        $writer_prompt = "You are a professional journalist writing for an audience in {$campaign->country}. Your task is to write a complete, factual, and objective news article for the following headline: \"{$specific_headline}\".

        CRITICAL INSTRUCTIONS:
        - Use your web search ability to gather detailed facts, quotes, and context related ONLY to this specific headline.
        - Your entire response MUST be a single, valid JSON object with three keys: 'title', 'subheadline', and 'content'.
        - Use the provided headline as the value for the 'title' key in your JSON response.
        - The 'content' must be in clean HTML, start directly with an introductory paragraph (not a heading), and have no 'Conclusion' heading.";
        
        // Use the campaign's selected model (or default) for the main writing task.
        $generated_json = ATM_API::enhance_content_with_openrouter(
            ['content' => $specific_headline], 
            $writer_prompt, 
            '', // Use default high-quality article model
            true // JSON mode is required
        );

        // --- STEP 3: Create the Post ---
        self::_create_post_from_ai_data($campaign, $generated_json);
}
    
    private static function _execute_review_campaign($campaign) {
        $system_prompt = "You are an expert product reviewer. Your task is to write a comprehensive, unbiased review of '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find features, specifications, pricing, user opinions, pros, and cons. Structure the article with clear headings for each section (e.g., Key Features, Performance, Pros, Cons, Final Verdict). The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading.";

        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_how_to_campaign($campaign) {
        $system_prompt = "You are a technical writer creating a clear, step-by-step guide on '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find the most accurate and easy-to-follow steps. Structure the article with an introduction, a 'What You'll Need' section (if applicable), and then the steps using an ordered list (<ol>). The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading.";

        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_listicle_campaign($campaign) {
        $system_prompt = "You are a popular blogger creating a listicle article about '{$campaign->keyword}' for an audience in {$campaign->country}. Use web search to find interesting and relevant items for the list. The title should be in a listicle format (e.g., 'Top 7...' or '5 Ways to...'). Structure the article with an engaging introduction, followed by numbered headings (e.g., <h2>1. First Item</h2>) for each point in the list. The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading.";
        
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_opinion_campaign($campaign) {
        $system_prompt = "You are an opinionated columnist with deep expertise on topics for an audience in {$campaign->country}. Your task is to write a persuasive opinion piece on '{$campaign->keyword}'. Use web search to understand the topic and form a strong, well-supported argument. Structure the article with a clear thesis, several supporting paragraphs with evidence or examples, acknowledge a counter-argument, and end with a strong concluding statement. The final output MUST be a valid JSON object with 'title', 'subheadline', and HTML 'content' keys. The content must start with a paragraph, not a heading, and have no 'Conclusion' heading.";

        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    private static function _execute_default_campaign($campaign) {
        if (post_exists($campaign->keyword)) {
            error_log('ATM Campaign ' . $campaign->id . ': Aborted. An article with the title "' . $campaign->keyword . '" already exists.');
            return;
        }
        
        $base_prompt = $campaign->custom_prompt ?: ATM_API::get_default_article_prompt();
        $replacements = [
            '[article_type]' => $campaign->article_type,
            '[country]'      => $campaign->country,
            '[keyword]'      => $campaign->keyword,
        ];
        $system_prompt = str_replace(array_keys($replacements), array_values($replacements), $base_prompt);
        
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        self::_create_post_from_ai_data($campaign, $generated_json);
    }

    /**
     * --- SHARED HELPER FUNCTIONS ---
     */

    private static function _create_post_from_ai_data($campaign, $generated_json) {
        $article_data = json_decode($generated_json, true);

        if (!$article_data || empty($article_data['title']) || post_exists($article_data['title'])) {
            error_log('ATM Campaign ' . $campaign->id . ': Aborted due to duplicate, empty, or invalid JSON response from AI.');
            return;
        }

        // --- THIS IS THE FIX FOR FORMATTING ---
        // 1. Initialize the Markdown parser
        $Parsedown = new Parsedown();
        // 2. Convert the AI's content (which might be Markdown) to HTML
        $html_content = $Parsedown->text($article_data['content']);
        // --- END FIX ---

        $post_data = [
            'post_title'    => wp_strip_all_tags($article_data['title']),
            'post_content'  => wp_slash($html_content), // Use the converted HTML and wp_slash for safety
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
        }
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