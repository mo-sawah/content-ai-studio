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
    global $wpdb;
    $current_date = date('F j, Y');
    
    // Step 1: Analyze recent coverage to understand what's been covered
    $recent_coverage = self::_analyze_recent_coverage($campaign->keyword);
    
    // Step 2: Intelligent search strategy based on keyword type and recent coverage
    $search_strategy = self::_generate_smart_search_strategy($campaign->keyword, $recent_coverage);
    
    $search_prompt = "You are an expert news researcher with access to current web search. Find a SPECIFIC, RECENT news story about '{$campaign->keyword}' from the last 24-48 hours that is genuinely NEW and different from recent coverage.

    RECENT COVERAGE TO AVOID DUPLICATING:
    {$recent_coverage['summary']}

    SEARCH STRATEGY: {$search_strategy['approach']}
    
    CRITICAL REQUIREMENTS:
    1. Find news about a SPECIFIC EVENT, INCIDENT, or DEVELOPMENT (not general updates)
    2. The event must be substantially different from recent coverage
    3. Look for: {$search_strategy['focus_areas']}
    4. Use these search queries: {$search_strategy['search_queries']}
    
    EVALUATION CRITERIA:
    - Is this a NEW event/incident (not continuation of previously covered story)?
    - Does it involve different people, locations, or circumstances?
    - Is there a clear news angle (what specifically happened)?
    
    YOUR RESPONSE FORMAT:
    If you find a genuinely NEW story, provide:
    1. SPECIFIC EVENT: What exactly happened (in one sentence)
    2. UNIQUENESS: Why this is different from recent coverage
    3. NEWS ANGLE: The specific newsworthy element
    4. KEY DETAILS: Who, what, when, where involved
    5. SOURCE URLS: 2-3 credible news sources
    
    If you cannot find a genuinely NEW story that's different from recent coverage, respond with: 'NO_UNIQUE_EVENT_FOUND'";

    // Search for unique news
    $news_research = ATM_API::enhance_content_with_openrouter(
        ['content' => "Find unique recent news: {$campaign->keyword}"], 
        $search_prompt, 
        '', 
        false
    );

    if (strpos($news_research, 'NO_UNIQUE_EVENT_FOUND') !== false) {
        error_log('ATM News Campaign '.$campaign->id.': No unique events found for "'.$campaign->keyword.'"');
        return;
    }

    // Step 3: Validate uniqueness using AI-powered duplicate detection
    $uniqueness_check = self::_validate_story_uniqueness($news_research, $campaign->keyword, $recent_coverage);
    
    if (!$uniqueness_check['is_unique']) {
        error_log('ATM News Campaign '.$campaign->id.': Story failed uniqueness validation: ' . $uniqueness_check['reason']);
        return;
    }

    // Step 4: Generate article with smart duplicate prevention
    $writer_prompt = "You are a professional news journalist. Write a comprehensive news article about the SPECIFIC EVENT described in the research.

    CRITICAL: This must be about the SPECIFIC EVENT ONLY, not a general overview of the topic.

    EVENT FOCUS: Write about what specifically happened, when, where, and why it matters.

    ARTICLE REQUIREMENTS:
    - Focus on the specific incident/event/development
    - Include timeline of what occurred
    - Add relevant background context (but keep focus on the new event)
    - Use proper journalistic structure
    - Include natural contextual links (not news outlet names in links)

    RESPONSE FORMAT: JSON with 'title', 'subheadline', 'content' (800+ words)";

    $generated_json = ATM_API::enhance_content_with_openrouter(
        ['content' => $news_research], 
        $writer_prompt, 
        '', 
        true
    );

    $data = json_decode($generated_json, true);
    
    if (!self::_validate_news_article_json($data)) {
        error_log('ATM News Campaign '.$campaign->id.': Invalid article generated');
        return;
    }

    // Step 5: Final duplicate check and store event signature
    if (self::_is_duplicate_event($data['title'], $campaign->keyword)) {
        error_log('ATM News Campaign '.$campaign->id.': Final duplicate check failed');
        return;
    }

    // Store event signature for future duplicate prevention
    self::_store_event_signature($campaign->keyword, $data['title'], $uniqueness_check['event_signature']);
    
    self::_create_post_from_ai_data($campaign, $data);
}

// Analyze what's been recently covered for this keyword
private static function _analyze_recent_coverage($keyword) {
    global $wpdb;
    
    // Get recent posts for this keyword (last 14 days)
    $recent_posts = $wpdb->get_results($wpdb->prepare(
        "SELECT post_title, post_content, post_date 
         FROM {$wpdb->posts} 
         WHERE post_status = 'publish' 
         AND post_date > DATE_SUB(NOW(), INTERVAL 14 DAY)
         AND (post_title LIKE %s OR post_content LIKE %s)
         ORDER BY post_date DESC 
         LIMIT 10",
        '%' . $wpdb->esc_like($keyword) . '%',
        '%' . $wpdb->esc_like($keyword) . '%'
    ));

    if (empty($recent_posts)) {
        return ['summary' => 'No recent coverage found.', 'events' => []];
    }

    // Use AI to analyze what's been covered
    $titles_and_dates = [];
    foreach ($recent_posts as $post) {
        $titles_and_dates[] = date('M j', strtotime($post->post_date)) . ": " . $post->post_title;
    }
    
    $coverage_text = implode("\n", $titles_and_dates);
    
    $analysis_prompt = "Analyze this recent news coverage about '{$keyword}' and summarize what types of events/stories have been covered. Focus on identifying specific events, incidents, or developments that were reported.

Recent coverage:
{$coverage_text}

Provide a brief summary of what's been covered so we can avoid duplicating these stories.";

    $coverage_summary = ATM_API::enhance_content_with_openrouter(
        ['content' => $coverage_text],
        $analysis_prompt,
        'anthropic/claude-3-haiku', // Fast model for analysis
        false
    );

    return [
        'summary' => $coverage_summary,
        'events' => wp_list_pluck($recent_posts, 'post_title'),
        'count' => count($recent_posts)
    ];
}

// Generate smart search strategy based on keyword type
private static function _generate_smart_search_strategy($keyword, $recent_coverage) {
    $keyword_lower = strtolower($keyword);
    
    // Detect keyword category
    $strategies = [
        // People (politicians, celebrities, etc.)
        'person' => [
            'detect' => function($kw) {
                $person_indicators = ['trump', 'biden', 'taylor swift', 'elon musk'];
                return str_word_count($kw) <= 3 && 
                       (ctype_upper($kw[0]) || in_array(strtolower($kw), $person_indicators));
            },
            'focus_areas' => 'specific actions, statements, appearances, legal developments, business moves',
            'search_queries' => [
                "$keyword latest news today",
                "$keyword statement announcement",
                "$keyword court case legal",
                "$keyword business deal meeting"
            ]
        ],
        
        // Locations (cities, countries)
        'location' => [
            'detect' => function($kw) {
                $location_indicators = ['new york', 'california', 'texas', 'florida', 'vegas', 'miami'];
                return in_array(strtolower($kw), $location_indicators) || 
                       preg_match('/\b(city|county|state)\b/i', $kw);
            },
            'focus_areas' => 'local incidents, government decisions, business openings/closings, crime events, infrastructure',
            'search_queries' => [
                "$keyword incident today",
                "$keyword government announcement", 
                "$keyword crime arrest",
                "$keyword business opening"
            ]
        ],
        
        // Ongoing conflicts/issues
        'ongoing_issue' => [
            'detect' => function($kw) {
                $issue_indicators = ['war', 'conflict', 'crisis', 'global warming', 'climate'];
                return str_contains(strtolower($kw), 'war') || 
                       array_intersect(explode(' ', strtolower($kw)), $issue_indicators);
            },
            'focus_areas' => 'specific incidents, new developments, policy changes, major events within the broader issue',
            'search_queries' => [
                "$keyword incident attack today",
                "$keyword policy announcement",
                "$keyword breakthrough development",
                "$keyword major event"
            ]
        ],
        
        // Industries/topics
        'industry' => [
            'detect' => function($kw) {
                $industry_indicators = ['tech', 'ai', 'crypto', 'fitness', 'hiphop', 'music', 'sports'];
                return in_array(strtolower($kw), $industry_indicators) || 
                       str_word_count($kw) == 1;
            },
            'focus_areas' => 'product launches, company news, industry changes, notable achievements, controversies',
            'search_queries' => [
                "$keyword company announcement",
                "$keyword product launch",
                "$keyword controversy news",
                "$keyword achievement breakthrough"
            ]
        ]
    ];

    // Determine category
    foreach ($strategies as $type => $strategy) {
        if ($strategy['detect']($keyword_lower)) {
            return [
                'approach' => "Focus on finding specific recent {$type}-related events",
                'focus_areas' => $strategy['focus_areas'],
                'search_queries' => implode(', ', $strategy['search_queries'])
            ];
        }
    }

    // Default strategy
    return [
        'approach' => 'Look for specific recent developments or events',
        'focus_areas' => 'breaking news, announcements, incidents, developments',
        'search_queries' => "$keyword news today, $keyword breaking news, latest $keyword updates"
    ];
}

// Validate story uniqueness using AI
private static function _validate_story_uniqueness($news_research, $keyword, $recent_coverage) {
    if ($recent_coverage['count'] == 0) {
        return ['is_unique' => true, 'event_signature' => md5($news_research)];
    }

    $validation_prompt = "You are a news editor checking for duplicate stories. 

NEW STORY RESEARCH:
{$news_research}

RECENT COVERAGE TO COMPARE AGAINST:
{$recent_coverage['summary']}

ANALYSIS REQUIRED:
1. Is this new story about a DIFFERENT specific event/incident?
2. Or is it just a continuation/update of something already covered?

For ongoing topics (like wars, politics), multiple related but separate events can be unique.
Example: 'Attack on City A' vs 'Attack on City B' = UNIQUE
Example: 'Attack casualties 50' vs 'Attack casualties now 75' = DUPLICATE UPDATE

Respond with: UNIQUE or DUPLICATE
If DUPLICATE, explain why.";

    $validation = ATM_API::enhance_content_with_openrouter(
        ['content' => $validation_prompt],
        "Determine if this is a unique news story.",
        'anthropic/claude-3-haiku',
        false
    );

    $is_unique = stripos($validation, 'UNIQUE') !== false && stripos($validation, 'DUPLICATE') === false;
    
    return [
        'is_unique' => $is_unique,
        'reason' => $validation,
        'event_signature' => md5($news_research . date('Y-m-d'))
    ];
}

// Store event signature to prevent future duplicates
private static function _store_event_signature($keyword, $title, $signature) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_used_topics';
    
    // Create table if it doesn't exist
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        $wpdb->query("CREATE TABLE {$table_name} (
            id int AUTO_INCREMENT PRIMARY KEY,
            keyword varchar(255),
            title varchar(500),
            event_signature varchar(64),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX(keyword, created_at)
        )");
    }
    
    $wpdb->insert($table_name, [
        'keyword' => $keyword,
        'title' => $title,
        'event_signature' => $signature,
        'created_at' => current_time('mysql')
    ]);
}

// Final duplicate check
private static function _is_duplicate_event($title, $keyword) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_used_topics';
    
    // Check if very similar title exists in last 7 days
    $similar = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} 
         WHERE keyword = %s 
         AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND SOUNDEX(title) = SOUNDEX(%s)",
        $keyword, $title
    ));
    
    return $similar > 0;
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