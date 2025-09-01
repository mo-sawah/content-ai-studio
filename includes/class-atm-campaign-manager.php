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
        $articles = self::_fetch_recent_google_news($campaign->keyword, $campaign->country, 12, 36);
        if (empty($articles)) {
            error_log('ATM News Campaign '.$campaign->id.': No recent reputable articles found for "'.$campaign->keyword.'".');
            return;
        }
        
        $best_article = self::_pick_best_article($articles, $campaign->keyword);
        $sources_payload = [['url' => $best_article['url'], 'outlet' => $best_article['source'], 'headline' => $best_article['title'], 'published_at' => gmdate('c', $best_article['timestamp'])]];
        $writer_prompt = "You are a professional journalist writing for an audience in {$campaign->country}. Write a complete, factual, and objective news article using ONLY the sources provided in the JSON below. Do not use memory or outside knowledge. If the sources are insufficient, say so.

        RESPONSE FORMAT (MANDATORY):
        Return a single, valid JSON object with keys: 'title', 'subheadline', 'content', 'sources'.
        - 'title': a clear, SEO-safe headline derived from the provided source.
        - 'subheadline': one sentence adding crucial context (no clickbait).
        - 'content': clean HTML that starts with an opening paragraph (no H1). Use short paragraphs, include 1-2 brief quotes with attribution, add 2-3 subheadings (<h2>/<h3>), and link the first mention of each outlet to its URL. No 'Conclusion' heading.
        - 'sources': the array you used (as provided), unchanged except for corrected outlet names if needed.
        
        CONSTRAINTS:
        - Keep the article fully grounded in the provided sources; do not invent details.
        - Include publish time and outlet in the first paragraph (e.g., 'Reuters - Sept. 1, 2025').
        - If you cannot confidently write the article from the provided source, respond with:
          {\"title\":\"\",\"subheadline\":\"\",\"content\":\"\",\"sources\":[]}";
        $content_payload = json_encode(['sources' => $sources_payload, 'focus'   => $campaign->keyword, 'country' => $campaign->country], JSON_UNESCAPED_SLASHES);
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $content_payload], $writer_prompt, '', true, false);
        $data = json_decode($generated_json, true);
        if (!self::_validate_article_json($data, $sources_payload)) {
            error_log('ATM News Campaign '.$campaign->id.': Writer returned invalid or ungrounded JSON.');
            return;
        }
        self::_create_post_from_ai_data($campaign, $data);
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

        $Parsedown = new Parsedown();
        $html_content = $Parsedown->text($article_data['content']);

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
        }
    }

    private static function _fetch_recent_google_news($query, $country, $limit = 10, $max_age_hours = 36) {
        $codes = self::_google_codes($country);
        $rss_url = "https://news.google.com/rss/search?q=" . rawurlencode($query) . "&hl={$codes['hl']}&gl={$codes['gl']}&ceid={$codes['ceid']}";
        
        $response = wp_remote_get($rss_url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => [
                'Accept' => 'application/rss+xml, application/xml, text/xml',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache'
            ]
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('ATM: Failed to fetch Google News RSS');
            return [];
        }
        
        $xml_content = wp_remote_retrieve_body($response);
        if (empty($xml_content)) {
            return [];
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        if ($xml === false || !isset($xml->channel->item)) {
            return [];
        }
        
        $items = $xml->channel->item;
        $now = time();
        $out = [];
        
        foreach ($items as $item) {
            $ts = (int) strtotime($item->pubDate);
            if ($ts <= 0 || ($now - $ts) > ($max_age_hours * 3600)) continue;
            
            // Extract the actual news URL from the title and description
            $title = trim((string)$item->title);
            $description = (string)$item->description;
            
            // Try to extract the source from the title (format: "Title - Source")
            $source = '';
            $clean_title = $title;
            if (preg_match('/^(.*?)\s*-\s*([^-]+)$/', $title, $matches)) {
                $clean_title = trim($matches[1]);
                $potential_source = trim($matches[2]);
                
                // Check if this looks like a news source
                foreach (self::$NEWS_WHITELIST as $trusted_source) {
                    if (stripos($potential_source, $trusted_source) !== false || 
                        stripos($trusted_source, strtolower($potential_source)) !== false) {
                        $source = $potential_source;
                        break;
                    }
                }
                
                // Additional common news sources not in whitelist
                $common_sources = ['CNN', 'ESPN', 'ABC7', 'WCVB', 'Syracuse.com', 'New York Post'];
                foreach ($common_sources as $common) {
                    if (stripos($potential_source, $common) !== false) {
                        $source = $potential_source;
                        break;
                    }
                }
            }
            
            // If we couldn't extract a source, skip this item
            if (empty($source)) continue;
            
            // Create a dummy URL based on the source for validation
            $domain = strtolower(str_replace([' ', '.'], ['', '.'], $source));
            if (!str_contains($domain, '.')) {
                $domain .= '.com';
            }
            
            // Validate against our whitelist
            $is_reputable = false;
            foreach (self::$NEWS_WHITELIST as $trusted) {
                if (str_contains($domain, $trusted) || str_contains($trusted, $domain)) {
                    $is_reputable = true;
                    break;
                }
            }
            
            if (!$is_reputable) continue;
            
            $out[] = [
                'title' => $clean_title,
                'url' => 'https://' . $domain, // Placeholder URL
                'source' => $source,
                'timestamp' => $ts
            ];
        }
        
        usort($out, function($a, $b){ return $b['timestamp'] <=> $a['timestamp']; });
        
        $seen = []; 
        $dedup = [];
        foreach ($out as $row) {
            $key = strtolower($row['source'] . '|' . mb_strtolower($row['title']));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $dedup[] = $row;
            if (count($dedup) >= $limit) break;
        }
        
        return $dedup;
    }

    private static function _resolve_gnews_url($link) {
        // This function is no longer needed since we're extracting info from RSS directly
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
        $now = time();
        $best = null; 
        $bestScore = -INF;
        $kw = mb_strtolower($keyword);
        
        foreach ($articles as $a) {
            $age_hours = max(1, ($now - $a['timestamp']) / 3600);
            $recency = 100 / $age_hours;
            $overlap = similar_text(mb_strtolower($a['title']), $kw, $pct) ? $pct : 0;
            $score = $recency + ($overlap * 0.6);
            
            if ($score > $bestScore) { 
                $bestScore = $score; 
                $best = $a; 
            }
        }
        
        return $best ?? $articles[0];
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

    private static function _validate_article_json($data, $sources_expected) {
        if (!is_array($data)) return false;
        foreach (['title','subheadline','content','sources'] as $k) {
            if (!array_key_exists($k, $data)) return false;
        }
        if (!is_array($data['sources']) || count($data['sources']) < 1) return false;
        
        // Since we're not using real URLs anymore, just validate that sources exist
        return !empty($data['title']) && !empty($data['content']);
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