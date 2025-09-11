<?php
// /includes/class-atm-api.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced RSS Parser Class
 */
class ATM_RSS_Parser {
    
    /**
     * Parse RSS feeds with advanced content extraction and keyword matching
     */
    public static function parse_rss_feeds_advanced($feed_urls_string, $post_id, $keyword = '') {
        $feed_urls = array_filter(explode("\n", trim($feed_urls_string)));
        if (empty($feed_urls)) return [];

        $used_guids = ($post_id > 0) ? get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [] : [];
        $all_items = [];
        
        foreach ($feed_urls as $url) {
            $url = trim($url);
            
            // Get raw RSS content with cURL for better control
            $rss_content = self::fetch_rss_content($url);
            if (!$rss_content) continue;
            
            // Parse RSS manually for better content extraction
            $items = self::parse_rss_xml($rss_content, $url);
            
            foreach ($items as $item) {
                if (in_array($item['guid'], $used_guids)) continue;
                
                // Enhanced keyword search if specified
                if (!empty($keyword)) {
                    if (!self::item_matches_keyword($item, $keyword)) {
                        continue;
                    }
                }
                
                $all_items[] = $item;
            }
        }

        // Sort by date, newest first
        usort($all_items, function($a, $b) {
            return strtotime($b['date_raw']) - strtotime($a['date_raw']);
        });

        return array_slice($all_items, 0, 10);
    }
    
    /**
     * Extract image from RSS item
     */
    private static function extract_image_from_rss_item($item) {
        // 1. Check for Media RSS (media:content)
        $media_content = $item->children('media', true)->content;
        if (isset($media_content->attributes()->url)) {
            return (string)$media_content->attributes()->url;
        }

        // 2. Check for enclosure tag
        if (isset($item->enclosure) && isset($item->enclosure->attributes()->type) && strpos($item->enclosure->attributes()->type, 'image') !== false) {
            return (string)$item->enclosure->attributes()->url;
        }

        // 3. Fallback: Search for the first <img> tag in the content
        $content = (string)($item->children('content', true)->encoded ?? $item->description ?? '');
        if (preg_match('/<img[^>]+src="([^">]+)"/', $content, $matches)) {
            return $matches[1];
        }

        return ''; // Return empty if no image is found
    }

    private static function fetch_rss_content($url) {
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; ATM-RSS-Parser/1.0; +' . home_url() . ')',
            ),
            'sslverify' => false, // For feeds with SSL issues
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('ATM RSS Error fetching ' . $url . ': ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200 || empty($body)) {
            error_log('ATM RSS Error: HTTP ' . $code . ' for ' . $url);
            return false;
        }
        
        return $body;
    }
    
    /**
     * Parse RSS XML manually for better content extraction
     */
    private static function parse_rss_xml($xml_content, $feed_url) {
        $items = [];
        
        // Handle encoding issues
        $xml_content = self::fix_xml_encoding($xml_content);
        
        // Suppress XML errors and load
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if ($xml === false) {
            error_log('ATM RSS: Failed to parse XML from ' . $feed_url);
            return [];
        }
        
        // Handle different RSS formats
        $rss_items = [];
        
        // RSS 2.0
        if (isset($xml->channel->item)) {
            $rss_items = $xml->channel->item;
            $feed_title = (string) $xml->channel->title;
        }
        // Atom
        elseif (isset($xml->entry)) {
            $rss_items = $xml->entry;
            $feed_title = (string) $xml->title;
        }
        // RSS 1.0
        elseif (isset($xml->item)) {
            $rss_items = $xml->item;
            $feed_title = (string) $xml->channel->title ?? 'RSS Feed';
        }
        
        foreach ($rss_items as $item) {
            $parsed_item = self::extract_item_data($item, $feed_title, $feed_url);
            if ($parsed_item) {
                $items[] = $parsed_item;
            }
        }
        
        return $items;
    }
    
    /**
     * Extract comprehensive data from RSS item
     */
    private static function extract_item_data($item, $feed_title, $feed_url) {
        // Handle both RSS and Atom formats
        $title = '';
        $link = '';
        $description = '';
        $content = '';
        $date = '';
        $guid = '';
        
        // Extract title
        $title = (string) ($item->title ?? '');
        
        // Extract link
        if (isset($item->link)) {
            if (is_string($item->link)) {
                $link = (string) $item->link;
            } elseif (isset($item->link['href'])) {
                $link = (string) $item->link['href'];
            }
        }
        
        // Extract GUID
        $guid = (string) ($item->guid ?? $item->id ?? $link);

        // Add fallback to use GUID if link is missing
        if (empty($link) && filter_var($guid, FILTER_VALIDATE_URL)) {
            $link = $guid;
        }
        
        // Extract date
        $date = (string) ($item->pubDate ?? $item->published ?? $item->updated ?? '');
        
        // Extract description/summary
        $description = (string) ($item->description ?? $item->summary ?? '');
        
        // Extract full content - try multiple fields
        $content_fields = [
            'content:encoded',  // WordPress and many feeds
            'content',          // Some feeds
            'summary',          // Atom feeds
            'description'       // Fallback
        ];
        
        foreach ($content_fields as $field) {
            if (strpos($field, ':') !== false) {
                // Handle namespaced elements
                $namespace = explode(':', $field)[0];
                $element = explode(':', $field)[1];
                $namespaces = $item->getNamespaces(true);
                
                if (isset($namespaces[$namespace])) {
                    $ns_content = $item->children($namespaces[$namespace]);
                    if (isset($ns_content->$element)) {
                        $content = (string) $ns_content->$element;
                        break;
                    }
                }
            } else {
                if (isset($item->$field) && !empty((string) $item->$field)) {
                    $content = (string) $item->$field;
                    break;
                }
            }
        }
        
        // If still no content, try description
        if (empty($content)) {
            $content = $description;
        }
        
        return [
            'image' => self::extract_image_from_rss_item($item),
            'title' => trim($title),
            'link' => trim($link),
            'description' => trim(strip_tags($description)),
            'content' => trim(strip_tags($content)),
            'full_content' => trim($content), // Keep HTML for potential scraping
            'date' => date('F j, Y', strtotime($date ?: 'now')),
            'date_raw' => $date ?: date('c'),
            'source' => trim($feed_title),
            'guid' => trim($guid),
        ];
    }
    
    /**
     * Enhanced keyword matching with relevance scoring
     */
    private static function item_matches_keyword($item, $keyword) {
        if (empty($keyword)) return true;
        
        $keyword = strtolower(trim($keyword));
        $keywords = explode(' ', $keyword); // Support multi-word searches
        
        // Fields to search with weights
        $search_fields = [
            'title' => 3,        // Title matches are most important
            'description' => 2,   // Description matches are important
            'content' => 1,      // Content matches are least weighted
        ];
        
        $total_score = 0;
        
        foreach ($search_fields as $field => $weight) {
            $field_content = strtolower($item[$field] ?? '');
            
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if (strlen($kw) < 2) continue; // Skip very short words
                
                // Exact phrase match gets higher score
                if (strpos($field_content, $keyword) !== false) {
                    $total_score += $weight * 2;
                }
                
                // Individual word matches
                if (strpos($field_content, $kw) !== false) {
                    $total_score += $weight;
                }
            }
        }
        
        // Require minimum relevance score
        return $total_score >= 2;
    }
    
    /**
     * Fix common XML encoding issues
     */
    private static function fix_xml_encoding($xml_content) {
        // Remove BOM if present
        $xml_content = preg_replace('/^\xEF\xBB\xBF/', '', $xml_content);
        
        // Fix common encoding issues
        if (!mb_check_encoding($xml_content, 'UTF-8')) {
            $xml_content = utf8_encode($xml_content);
        }
        
        // Remove invalid XML characters
        $xml_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_content);
        
        return $xml_content;
    }
    
    /**
     * Enhanced search with full article content scraping for better matching
     */
    public static function enhanced_search_feeds($feed_urls_string, $keyword, $scrape_content = false) {
        $results = self::parse_rss_feeds_advanced($feed_urls_string, 0, $keyword);
        
        // If scraping enabled and ScrapingAnt available, get full article content
        if ($scrape_content && !empty($results) && !empty(get_option('atm_scrapingant_api_key'))) {
            foreach ($results as &$result) {
                try {
                    $full_content = ATM_API::fetch_full_article_content($result['link']);
                    if (!empty($full_content)) {
                        // Re-check keyword match with full content
                        if (stripos(strtolower($full_content), strtolower($keyword)) !== false) {
                            $result['full_scraped_content'] = $full_content;
                            $result['relevance'] = 'high'; // Mark as high relevance
                        }
                    }
                } catch (Exception $e) {
                    // Continue without scraping if it fails
                    error_log('ATM RSS: Scraping failed for ' . $result['link'] . ': ' . $e->getMessage());
                }
            }
            
            // Sort by relevance if we have scraped content
            usort($results, function($a, $b) {
                $a_relevance = ($a['relevance'] ?? '') === 'high' ? 1 : 0;
                $b_relevance = ($b['relevance'] ?? '') === 'high' ? 1 : 0;
                
                if ($a_relevance === $b_relevance) {
                    return strtotime($b['date_raw']) - strtotime($a['date_raw']);
                }
                
                return $b_relevance - $a_relevance;
            });
        }
        
        return $results;
    }
}

class ATM_API {

    public static function get_contextual_seed($keyword) {
        $current_month = date('F');
        $current_year = date('Y'); 
        $day_of_year = date('z');
        
        $contexts = [
            "In the context of {$current_month} {$current_year} trends",
            "From a {$current_year} perspective", 
            "Considering recent industry developments in {$current_year}",
            "With current market conditions in mind",
            "Looking at {$keyword} through a modern lens",
            "In today's digital-first environment",
            "From a post-pandemic business perspective",
            "Considering emerging technologies in {$current_year}"
        ];
        
        // Use day of year to pick different context each day
        $selected_context = $contexts[$day_of_year % count($contexts)];
        return $selected_context;
    }

    private static function is_article_already_used($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        
        // Check if article was used within the last 2 days (configurable)
        $cache_hours = get_option('atm_used_articles_cache_hours', 48); // Default 48 hours
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE article_url = %s 
            AND used_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $url,
            $cache_hours
        ));
        
        return !empty($result);
    }
    
    // Add to class-atm-api.php
    public static function generate_image_with_configured_provider($prompt, $size_override = '', $quality_override = '') {
        $provider = get_option('atm_image_provider', 'openai');
        
        switch ($provider) {
            case 'google':
                return [
                    'data' => self::generate_image_with_google_imagen($prompt, $size_override),
                    'is_url' => false
                ];
            case 'blockflow':
                return [
                    'data' => self::generate_image_with_blockflow($prompt, '', $size_override),
                    'is_url' => false
                ];
            case 'openai':
            default:
                return [
                    'data' => self::generate_image_with_openai($prompt, $size_override, $quality_override),
                    'is_url' => true
                ];
        }
    }

    /**
     * Search Google News directly using Custom Search API
     */
    public static function search_google_news_direct($query, $page = 1, $per_page = 10, $source_languages = [], $countries = []) {
        $api_key = get_option('atm_google_news_search_api_key');
        $search_engine_id = get_option('atm_google_news_cse_id');
        
        if (empty($api_key) || empty($search_engine_id)) {
            throw new Exception('Google Custom Search API key and Search Engine ID must be configured.');
        }

        $start_index = (($page - 1) * $per_page) + 1;
        
        if ($start_index > 91) {
            return ['results' => [], 'total_results' => 0]; // Return empty instead of throwing an error for pagination
        }

        // --- NEW: Logic to build filter queries ---
        $filter_query = '';
        if (!empty($countries)) {
            $country_sites = [];
            // A small map for country names to top-level domains
            $country_tld_map = [
                "United States" => "us", "United Kingdom" => "co.uk", "Canada" => "ca", "Australia" => "com.au", "Germany" => "de",
                "France" => "fr", "Spain" => "es", "Italy" => "it", "Japan" => "co.jp", "India" => "in", "Brazil" => "com.br"
                // This can be expanded, but site:.tld is more reliable for Google Search
            ];
            foreach ($countries as $country_name) {
                if (isset($country_tld_map[$country_name])) {
                    $country_sites[] = 'site:.' . $country_tld_map[$country_name];
                }
            }
            if (!empty($country_sites)) {
                $filter_query .= ' (' . implode(' OR ', $country_sites) . ')';
            }
        }

        $search_params = [
            'key' => $api_key,
            'cx' => $search_engine_id,
            'q' => $query . $filter_query, // Append the country filter to the main query
            'num' => min($per_page, 10),
            'start' => $start_index,
            'sort' => 'date',
            'dateRestrict' => 'd7', // Last 7 days
            'safe' => 'medium',
        ];

        // --- MODIFIED: Handle multiple source languages if provided ---
        if (!empty($source_languages)) {
            $lang_codes = [];
            // This should be expanded with a proper mapping
            $lang_map = ['english' => 'en', 'spanish' => 'es', 'french' => 'fr', 'german' => 'de', 'italian' => 'it', 'portuguese' => 'pt'];
            foreach($source_languages as $lang_name) {
                $lang_key = strtolower($lang_name);
                if(isset($lang_map[$lang_key])) {
                    $lang_codes[] = 'lang_' . $lang_map[$lang_key];
                }
            }
            if(!empty($lang_codes)) {
                $search_params['lr'] = implode('|', $lang_codes);
            }
        }

        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($search_params);

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to connect to Google Search API: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Google API error';
            throw new Exception('Google Search API Error: ' . $error_message);
        }

        if (!isset($result['items']) || empty($result['items'])) {
            return ['results' => [], 'total_results' => 0];
        }

        $articles = [];
        foreach ($result['items'] as $item) {
            $source_domain = parse_url($item['link'], PHP_URL_HOST);
            $source_name = self::format_source_name($source_domain);
            
            $formatted_date = 'Recent';
            if (isset($item['pagemap']['metatags'][0]['article:published_time'])) {
                $formatted_date = date('M j, Y', strtotime($item['pagemap']['metatags'][0]['article:published_time']));
            }
            
            $image_url = '';
            if (isset($item['pagemap']['cse_image'][0]['src'])) {
                $image_url = $item['pagemap']['cse_image'][0]['src'];
            }

            $article_data = [
                'title' => sanitize_text_field($item['title']),
                'link' => esc_url_raw($item['link']),
                'snippet' => sanitize_text_field($item['snippet']),
                'source' => sanitize_text_field($source_name),
                'date' => sanitize_text_field($formatted_date),
                'image' => esc_url_raw($image_url)
            ];
            $article_data['is_used'] = self::is_article_already_used($item['link']);
            $articles[] = $article_data;
        }

        $total_results = isset($result['searchInformation']['totalResults']) ? min(intval($result['searchInformation']['totalResults']), 100) : count($articles);
        return ['results' => $articles, 'total_results' => $total_results];
    }

    /**
     * Generate article from a specific news source
     */
    public static function generate_article_from_news_source($source_url, $source_title, $source_snippet, $source_date, $source_domain, $article_language = 'English') {
        // First, try to get full article content
        $full_content = '';
        try {
            $full_content = self::fetch_full_article_content($source_url);
        } catch (Exception $e) {
            error_log('ATM News Search: Could not scrape full content, using snippet: ' . $e->getMessage());
            $full_content = $source_snippet;
        }

        // If we still don't have enough content, fall back to snippet
        if (strlen($full_content) < 200) {
            $full_content = $source_snippet;
        }

        $system_prompt = "You are a professional news reporter and editor. Using the following source material, write a clear, engaging, and well-structured news article in {$article_language}. **Use your web search ability to verify the information and add any missing context.**

            Follow these strict guidelines:
            - **Language**: Write the entire article in {$article_language}. This is mandatory.
            - **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human.
            - **Originality**: Do not copy verbatim from the source. You must rewrite, summarize, and humanize the content.
            - **Length**: Aim for 800–1500 words.
            - **IMPORTANT**: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.
            - The `content` field must NOT start with a title. It must begin directly with the introductory paragraph in a news article style.
            - **CRITICAL**: Do NOT include any final heading such as \"Conclusion\", \"Summary\", \"Final Thoughts\", \"In Summary\", \"To Conclude\", \"Wrapping Up\", \"Looking Ahead\", \"What's Next\", \"The Bottom Line\", \"Key Takeaways\", or any similar conclusory heading. The article should end naturally with the concluding paragraph itself, without any heading above it.
            - End the article with a natural concluding paragraph that flows seamlessly from the body content, but do NOT put any heading before this final paragraph.

            **Link Formatting Rules:**
            - When including external links, NEVER use the website URL as the anchor text
            - Always link to the specific article URL, NOT the homepage
            - Use ONLY 1-3 descriptive words as anchor text
            - Example: [Reuters](https://reuters.com/actual-article-url) reported that...
            - Example: According to [BBC News](https://bbc.com/specific-article), the incident...
            - Do NOT use generic phrases like \"click here\", \"read more\", or \"this article\" as anchor text
            - Anchor text should be relevant keywords from the article topic
            - Keep anchor text extremely concise (maximum 2 words)
            - Make links feel natural within the sentence flow

            **Final Output Format:**
            Your entire output MUST be a single, valid JSON object with three keys:
            1. \"title\": A clear and compelling news headline in {$article_language}, written in the style of a professional news outlet. It must be concise, factual, and highlight the most newsworthy element of the story.
            2. \"subheadline\": A brief, one-sentence subheadline that expands on the main headline, written in {$article_language}.
            3. \"content\": A complete news article in {$article_language}, formatted using Markdown. The article must follow professional journalistic style: clear, objective, and factual. Structure it with an engaging lead paragraph, followed by supporting details, quotes, and context. Use H2 (##) for section headings, avoid H1. REMEMBER: NO conclusion headings whatsoever - end with a natural concluding paragraph that has no heading above it.

            **SOURCE INFORMATION:**
            Original Title: {$source_title}
            Source: {$source_domain}
            Date: {$source_date}
            URL: {$source_url}

            **SOURCE CONTENT:**
            {$full_content}";

        $model = get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $full_content],
            $system_prompt,
            $model,
            true, // JSON mode
            true  // Enable web search for verification
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
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Plugin - Invalid JSON from News Search AI: ' . $json_string);
            throw new Exception('The AI returned an invalid response structure. Please try again.');
        }

        // Extract fields
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

        $content = trim($result['content']);

        if (empty($headline) || empty($content)) {
            throw new Exception('Generated title or content is empty.');
        }

        return [
            'title' => $headline,
            'content' => $content,
            'subtitle' => $subtitle
        ];
    }

    /**
     * Format source domain name to be more readable
     */
    private static function format_source_name($domain) {
        // Remove www. prefix
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Common news source mappings
        $source_mappings = [
            'cnn.com' => 'CNN',
            'bbc.com' => 'BBC',
            'reuters.com' => 'Reuters',
            'apnews.com' => 'AP News',
            'nytimes.com' => 'The New York Times',
            'washingtonpost.com' => 'The Washington Post',
            'theguardian.com' => 'The Guardian',
            'wsj.com' => 'The Wall Street Journal',
            'bloomberg.com' => 'Bloomberg',
            'foxnews.com' => 'Fox News',
            'nbcnews.com' => 'NBC News',
            'abcnews.go.com' => 'ABC News',
            'cbsnews.com' => 'CBS News',
            'usatoday.com' => 'USA Today'
        ];
        
        return isset($source_mappings[$domain]) 
            ? $source_mappings[$domain] 
            : ucwords(str_replace('.com', '', $domain));
    }

    public static function debug_web_search_settings() {
        $settings = [
            'atm_web_search_results' => get_option('atm_web_search_results', 'NOT_SET'),
            'atm_openrouter_api_key' => !empty(get_option('atm_openrouter_api_key')) ? 'SET' : 'NOT_SET',
            'atm_article_model' => get_option('atm_article_model', 'NOT_SET'),
        ];
        
        error_log('ATM Web Search Debug: ' . print_r($settings, true));
        return $settings;
    }
    
/**
 * Search live news using OpenRouter web search with categorization
 */
public static function search_live_news_with_openrouter($keyword, $force_fresh = false) {
    // Debug current settings
    $web_search_setting = get_option('atm_web_search_results', 0);
    error_log("ATM Live News Debug - Web search setting: " . $web_search_setting);
    error_log("ATM Live News Debug - Keyword: " . $keyword);
    error_log("ATM Live News Debug - Force fresh: " . ($force_fresh ? 'true' : 'false'));
    $cache_key = 'atm_live_news_' . md5($keyword);
    $cache_duration = 3 * HOUR_IN_SECONDS; // 3 hours cache
    
    // Check cache first unless force refresh is requested
    if (!$force_fresh) {
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            error_log("ATM Live News: Returning cached results for keyword: $keyword");
            return $cached_result;
        }
    } else {
        delete_transient($cache_key);
    }
    
    $system_prompt = "You are an expert news researcher and categorization specialist. Your task is to search for the latest news about '{$keyword}' and organize the results into logical categories.

CRITICAL INSTRUCTIONS:
1. Use your web search ability to find the latest news about '{$keyword}' from the past 48 hours
2. Group related articles into 4-6 main topic categories (aim for more categories when possible)
3. For each category, include 4-6 relevant articles (aim for more articles per category)
4. Extract key information for each article: title, source, date, summary, and thumbnail URL if available

Your response MUST be a valid JSON object with this exact structure:
{
    \"categories\": [
        {
            \"title\": \"Category name (e.g., 'Immigration Policy', 'Trade Relations', 'Legal Issues')\",
            \"articles\": [
                {
                    \"title\": \"Article headline\",
                    \"source\": \"Source name (e.g., 'CNN', 'Reuters', 'AP News')\",
                    \"date\": \"Formatted date (e.g., 'Dec 15, 2024')\",
                    \"summary\": \"Brief 2-3 sentence summary with key details\",
                    \"thumbnail\": \"Image URL or empty string\",
                    \"url\": \"Article URL\"
                }
            ]
        }
    ]
}

SEARCH STRATEGY:
- Cast a wide net to find diverse angles and perspectives
- Look for breaking news, analysis pieces, opinion articles, and background reports
- Include both major and specialized news sources
- Prioritize recent, credible journalism from the last 24-48 hours
- Aim for comprehensive coverage of all major aspects of the topic

CATEGORIZATION GUIDELINES:
- Create distinct, meaningful categories that don't overlap
- Examples for political figures: 'Legal Issues', 'Policy Positions', 'Campaign Activities', 'International Relations', 'Economic Impact', 'Public Statements'
- Use specific, descriptive category names rather than generic ones
- Ensure each category has substantial, unique content

Return only the JSON object, no additional text.";

    try {
        $model = get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $keyword],
            $system_prompt,
            $model,
            true, // JSON mode
            true  // Enable web search
        );
        
        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['categories'])) {
            error_log('ATM Live News - Invalid JSON response: ' . $raw_response);
            throw new Exception('Invalid response format from news search API');
        }
        
        $categories = $result['categories'];
        
        // Validate and clean the results
        $cleaned_categories = [];
        foreach ($categories as $category) {
            if (!isset($category['title']) || !isset($category['articles']) || !is_array($category['articles'])) {
                continue;
            }
            
            $cleaned_articles = [];
            foreach ($category['articles'] as $article) {
                // Ensure required fields exist
                $cleaned_article = [
                    'title' => sanitize_text_field($article['title'] ?? 'Untitled'),
                    'source' => sanitize_text_field($article['source'] ?? 'Unknown Source'),
                    'date' => sanitize_text_field($article['date'] ?? date('M j, Y')),
                    'summary' => sanitize_textarea_field($article['summary'] ?? ''),
                    'thumbnail' => esc_url_raw($article['thumbnail'] ?? ''),
                    'url' => esc_url_raw($article['url'] ?? '')
                ];
                
                // Only include articles with meaningful content
                if (!empty($cleaned_article['title']) && $cleaned_article['title'] !== 'Untitled') {
                    $cleaned_articles[] = $cleaned_article;
                }
            }
            
            if (!empty($cleaned_articles)) {
                $cleaned_categories[] = [
                    'title' => sanitize_text_field($category['title']),
                    'articles' => $cleaned_articles
                ];
            }
        }
        
        // Cache the results
        set_transient($cache_key, $cleaned_categories, $cache_duration);
        
        error_log("ATM Live News: Found " . count($cleaned_categories) . " categories for keyword: $keyword");
        
        return $cleaned_categories;
        
    } catch (Exception $e) {
        error_log('ATM Live News Error: ' . $e->getMessage());
        throw new Exception('Failed to search live news: ' . $e->getMessage());
    }
}

/**
 * Generate article from live news category with focus on specific news events
 */
public static function generate_live_news_article($keyword, $category_title, $category_sources, $previous_angles = []) {
    // Prepare detailed source content for analysis
    $sources_text = "BREAKING NEWS SOURCES:\n\n";
    
    foreach ($category_sources as $index => $source) {
        $sources_text .= "SOURCE " . ($index + 1) . ":\n";
        $sources_text .= "Headline: {$source['title']}\n";
        $sources_text .= "Publisher: {$source['source']} | Date: {$source['date']}\n";
        if (!empty($source['summary'])) {
            $sources_text .= "Summary: {$source['summary']}\n";
        }
        if (!empty($source['url'])) {
            $sources_text .= "URL: {$source['url']}\n";
        }
        $sources_text .= "---\n";
    }
    
    // Build angle differentiation context
    $angle_context = '';
    if (!empty($previous_angles)) {
        $angle_context = "\n\nPREVIOUS ANGLES ALREADY COVERED for '{$keyword}':\n";
        foreach ($previous_angles as $i => $angle) {
            $angle_context .= "- " . ($i + 1) . ". " . $angle . "\n";
        }
        $angle_context .= "\nYou MUST create a completely different angle that hasn't been covered before.";
    }
    
    $system_prompt = "You are a breaking news journalist. Your task is to analyze these specific news sources and write a focused news article about ONE specific event or development.

{$angle_context}

CRITICAL ANALYSIS REQUIREMENTS:
1. Read ALL the provided sources carefully
2. Identify the MOST NEWSWORTHY single event, development, or breaking story from the sources
3. Focus on ONE specific happening, not a general overview of multiple topics
4. Write about what's actually happening RIGHT NOW based on these sources

HEADLINE GENERATION RULES:
- Write headlines like actual breaking news: \"Netanyahu Faces Military Pressure Over Gaza Strategy\" 
- NOT generic reports like: \"Israel Faces Intensified Pressure as Crisis Escalates\"
- Focus on WHO is doing WHAT, WHEN it's happening
- Use specific names, places, and actions from the sources
- Make it immediate and specific to today's developments

ARTICLE REQUIREMENTS:
1. Choose ONE main story thread from the sources (the most urgent/recent)
2. Write a 900-1200 word breaking news article focused on that specific event
3. Lead with the most important new development
4. Include specific details: names, dates, quotes, numbers from the sources
5. Explain what happened, why it matters, and what comes next
6. Use the other sources as supporting context, not co-equal stories

FORMATTING RULES:
- NO H1 headings (# format) in content
- Start content directly with the lead paragraph
- Use only H2 (##) and H3 (###) subheadings
- Content should be pure article text with no title repetition

Link Formatting Rules:
- When including external links, NEVER use the website URL as the anchor text
- Use ONLY 1-3 descriptive words as anchor text
- Example: railway that [had a deadly crash](https://reuters.com/specific-article) last week and will...
- Example: Example: [Reuters](https://reuters.com/actual-article-url) reported that...
- Example: According to [BBC News](https://bbc.com/specific-article), the incident...
- Do NOT use generic phrases like 'click here', 'read more', or 'this article' as anchor text
- Anchor text should be relevant keywords from the article topic (e.g., marketing, design, finance, AI)
- Keep anchor text extremely concise (maximum 2 words)
- Make links feel natural within the sentence flow
- Avoid long phrases as anchor text

WRITING STYLE:
- Breaking news tone: immediate, urgent, factual
- Present tense for ongoing situations
- Past tense for completed events
- Include attribution: \"according to CNN\", \"Reuters reported\", etc.
- Focus on the human impact and immediate consequences

OUTPUT FORMAT (JSON):
{
    \"title\": \"Specific breaking news headline about ONE main event\",
    \"subtitle\": \"Additional context or latest development from the story\",
    \"content\": \"Full news article in Markdown (no H1, start with paragraph)\",
    \"angle\": \"Brief description of the specific event/angle chosen\"
}

Remember: You're writing about a SPECIFIC NEWS EVENT, not a general topic overview. Pick the most compelling, recent, or urgent story from the sources and focus entirely on that.";

    try {
        $model = get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $sources_text],
            $system_prompt,
            $model,
            true, // JSON mode
            true // Keep web search enabled for additional context
        );
        
        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Live News Article - Invalid JSON response: ' . $raw_response);
            throw new Exception('Invalid response format from article generation API');
        }
        
        // Validate required fields
        if (empty($result['title']) || empty($result['content'])) {
            throw new Exception('Generated article is missing required fields');
        }
        
        // Log the generated headline for debugging
        error_log("ATM Live News: Generated headline: " . $result['title']);
        
        return [
            'title' => sanitize_text_field($result['title']),
            'subtitle' => sanitize_text_field($result['subtitle'] ?? ''),
            'content' => wp_kses_post($result['content']),
            'angle' => sanitize_text_field($result['angle'] ?? '')
        ];
        
    } catch (Exception $e) {
        error_log('ATM Live News Article Generation Error: ' . $e->getMessage());
        throw new Exception('Failed to generate article: ' . $e->getMessage());
    }
}

    /**
 * Queue script generation for background processing
 */
public static function queue_script_generation($post_id, $language, $duration) {
    global $wpdb;
    
    $job_id = uniqid('script_', true);
    $table_name = $wpdb->prefix . 'atm_script_jobs';
    
    // Get post content for script generation
    $post = get_post($post_id);
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    $article_content = wp_strip_all_tags($post->post_content);
    $article_title = $post->post_title;
    
    // Create job record
    $result = $wpdb->insert($table_name, [
        'post_id' => $post_id,
        'job_id' => $job_id,
        'article_title' => $article_title,
        'article_content' => $article_content,
        'language' => $language,
        'duration' => $duration,
        'status' => 'processing'
    ]);
    
    if ($result === false) {
        throw new Exception("Failed to create script job record: " . $wpdb->last_error);
    }
    
    // Schedule immediate background processing
    wp_schedule_single_event(time() + 5, 'atm_process_script_background', [$job_id]);
    
    return $job_id;
}

/**
 * Process script generation in background
 */
public static function process_script_background($job_id) {
    // Set unlimited execution time for background processing
    set_time_limit(0);
    ignore_user_abort(true);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_script_jobs';
    
    try {
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ), ARRAY_A);
        
        if (!$job) {
            error_log("ATM: Background script job $job_id not found");
            return;
        }
        
        error_log("ATM: Starting background script processing for job $job_id");
        
        // Update status to indicate processing has started
        $wpdb->update($table_name, [
            'status' => 'generating',
            'progress' => 10
        ], ['job_id' => $job_id]);
        
        // Generate the script
        if ($job['duration'] === 'long') {
            // Use segmented generation for long scripts
            $script = self::generate_long_podcast_script_background(
                $job['article_title'],
                $job['article_content'],
                $job['language'],
                $job_id
            );
        } else {
            // Use single request for short/medium scripts
            $script = self::generate_advanced_podcast_script(
                $job['article_title'],
                $job['article_content'],
                $job['language'],
                $job['duration']
            );
        }
        
        // Update progress to 90%
        $wpdb->update($table_name, [
            'progress' => 90
        ], ['job_id' => $job_id]);
        
        // Save the generated script
        update_post_meta($job['post_id'], '_atm_podcast_script', $script);
        
        // Mark job complete
        $wpdb->update($table_name, [
            'status' => 'completed',
            'progress' => 100,
            'script' => $script
        ], ['job_id' => $job_id]);
        
        error_log("ATM: Background script job $job_id completed successfully");
        
    } catch (Exception $e) {
        error_log("ATM: Background script job $job_id failed: " . $e->getMessage());
        $wpdb->update($table_name, [
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ], ['job_id' => $job_id]);
    }
}

/**
 * Generate long script with progress updates
 */
private static function generate_long_podcast_script_background($title, $content, $language, $job_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_script_jobs';
    
    $segments = [
        'intro_and_context' => [
            'description' => 'Podcast introduction and background context',
            'target_words' => '800-1000 words',
            'time' => '5-6 minutes',
            'progress' => 25
        ],
        'main_discussion_part1' => [
            'description' => 'First half of main discussion covering primary aspects',
            'target_words' => '2000-2500 words', 
            'time' => '12-15 minutes',
            'progress' => 45
        ],
        'main_discussion_part2' => [
            'description' => 'Second half of main discussion with practical applications',
            'target_words' => '2000-2500 words',
            'time' => '12-15 minutes',
            'progress' => 70
        ],
        'conclusion_and_outro' => [
            'description' => 'Future outlook, practical advice, and podcast conclusion',
            'target_words' => '800-1000 words',
            'time' => '5-6 minutes',
            'progress' => 85
        ]
    ];

    $full_script = '';
    $model = get_option('atm_podcast_content_model', 'openai/gpt-4o');

    foreach ($segments as $segment_key => $segment_info) {
        error_log("ATM: Generating segment: $segment_key for job $job_id");
        
        // Update progress
        $wpdb->update($table_name, [
            'progress' => $segment_info['progress'],
            'current_segment' => $segment_key
        ], ['job_id' => $job_id]);
        
        $segment_prompt = self::create_segment_prompt(
            $title, 
            $content, 
            $language, 
            $segment_key, 
            $segment_info,
            $segment_key === 'intro_and_context'
        );

        $segment_script = self::enhance_content_with_openrouter(
            ['title' => $title, 'content' => $content],
            $segment_prompt,
            $model,
            false,
            true
        );

        $full_script .= $segment_script . "\n\n";

        // Add delay between requests to avoid rate limiting
        sleep(3);
    }

    return trim($full_script);
}

/**
 * Get script generation progress
 */
public static function get_script_progress($job_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_script_jobs';
    
    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE job_id = %s",
        $job_id
    ), ARRAY_A);
    
    if (!$job) {
        throw new Exception("Script job not found");
    }
    
    return [
        'status' => $job['status'],
        'progress' => intval($job['progress']),
        'current_segment' => $job['current_segment'] ?? '',
        'error_message' => $job['error_message'],
        'script' => $job['script'] ?? ''
    ];
}

    // Add this method to reduce TTS costs:
    private static function optimize_tts_costs($script, $voice_a, $voice_b, $provider) {
        $segments = self::parse_podcast_script($script);
        $audio_parts = [];
        $total_cost_estimate = 0;
        
        foreach ($segments as $segment) {
            $text = preg_replace('/\[([^\]]+)\]/', '', $segment['text']); // Remove stage directions
            $voice = ($segment['speaker'] === 'HOST_A') ? $voice_a : $voice_b;
            
            // Estimate cost before generating
            $char_count = strlen($text);
            if ($provider === 'openai') {
                $cost_estimate = ($char_count / 1000) * 0.015; // $15 per 1M characters
                $total_cost_estimate += $cost_estimate;
            }
            
            error_log("ATM: Segment cost estimate: $" . number_format($cost_estimate, 4) . " for $char_count characters");
            
            // Generate audio only for this specific segment
            if ($provider === 'elevenlabs') {
                $audio_content = self::generate_audio_with_elevenlabs($text, $voice);
            } else {
                $audio_content = self::generate_audio_with_openai_tts($text, $voice);
            }
            
            $audio_parts[] = $audio_content;
        }
        
        error_log("ATM: Total estimated TTS cost: $" . number_format($total_cost_estimate, 4));
        return implode('', $audio_parts);
    }

    // Add this method to class-atm-api.php for debugging:
    public static function debug_audio_segments($job_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ), ARRAY_A);
        
        if ($job && $job['temp_files']) {
            $temp_files = json_decode($job['temp_files'], true);
            foreach ($temp_files as $index => $filepath) {
                if (file_exists($filepath)) {
                    $size = filesize($filepath);
                    $first_bytes = bin2hex(substr(file_get_contents($filepath), 0, 10));
                    error_log("ATM Debug: Segment $index - Size: $size bytes, First bytes: $first_bytes");
                }
            }
        }
    }

    // Replace the combine_audio_segments_improved method in class-atm-api.php:
    private static function combine_audio_segments_improved($temp_files) {
        // Sort temp files by segment index
        ksort($temp_files);
        
        if (empty($temp_files)) {
            throw new Exception("No audio segments to combine");
        }
        
        error_log("ATM: Starting audio combination with " . count($temp_files) . " segments");
        
        // If only one segment, return it directly
        if (count($temp_files) === 1) {
            $filepath = reset($temp_files);
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                error_log("ATM: Single segment returned: " . strlen($content) . " bytes");
                return $content;
            }
            throw new Exception("Single audio segment file not found");
        }
        
        // For multiple segments, combine them
        $combined_audio = '';
        
        foreach ($temp_files as $index => $filepath) {
            if (file_exists($filepath)) {
                $segment_content = file_get_contents($filepath);
                if ($segment_content) {
                    $combined_audio .= $segment_content;
                    error_log("ATM: Added segment $index (" . strlen($segment_content) . " bytes)");
                } else {
                    error_log("ATM: Warning - segment $index is empty");
                }
            } else {
                error_log("ATM: Warning - segment file missing: $filepath");
            }
        }
        
        error_log("ATM: Final combined audio size: " . strlen($combined_audio) . " bytes");
        return $combined_audio;
    }

    // Add this helper method:
    private static function remove_mp3_headers($mp3_data) {
        // Very basic ID3 tag removal - look for ID3 at start
        if (substr($mp3_data, 0, 3) === 'ID3') {
            // Skip ID3v2 tag
            $size = (ord($mp3_data[6]) << 21) | (ord($mp3_data[7]) << 14) | (ord($mp3_data[8]) << 7) | ord($mp3_data[9]);
            $mp3_data = substr($mp3_data, 10 + $size);
        }
        return $mp3_data;
    }

    // Add to class-atm-api.php:
public static function queue_podcast_generation($post_id, $script, $voice_a, $voice_b, $provider) {
    global $wpdb;
    
    $job_id = uniqid('podcast_', true);
    $segments = self::split_script_into_segments($script, 1000);
    $table_name = $wpdb->prefix . 'atm_podcast_jobs';
    
    // Create job record
    $result = $wpdb->insert($table_name, [
        'post_id' => $post_id,
        'job_id' => $job_id,
        'script' => $script,
        'voice_a' => $voice_a,
        'voice_b' => $voice_b,
        'provider' => $provider,
        'total_segments' => count($segments),
        'status' => 'processing'
    ]);
    
    if ($result === false) {
        throw new Exception("Failed to create job record: " . $wpdb->last_error);
    }
    
    // Schedule immediate background processing
    wp_schedule_single_event(time() + 5, 'atm_process_podcast_background', [$job_id]);
    
    return $job_id;
}

public static function process_podcast_background($job_id) {
    // Set unlimited execution time for background processing
    set_time_limit(0);
    ignore_user_abort(true);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_podcast_jobs';
    
    try {
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ), ARRAY_A);
        
        if (!$job) {
            error_log("ATM: Background job $job_id not found");
            return;
        }
        
        error_log("ATM: Starting background processing for job $job_id");
        
        $segments = self::split_script_into_segments($job['script'], 500);
        $temp_files = [];
        
        // Process all segments
        for ($i = 0; $i < count($segments); $i++) {
            error_log("ATM: Background processing segment $i of " . count($segments));
            
            $segment_script = $segments[$i];
            $audio_content = self::generate_two_person_podcast_audio(
                $segment_script,
                $job['voice_a'],
                $job['voice_b'],
                $job['provider']
            );
            
            // Add validation for audio content
            if (strlen($audio_content) < 1000) { // Less than 1KB is probably an error
                throw new Exception("Generated audio segment $i is too small (possible generation failure): " . strlen($audio_content) . " bytes");
            }
            
            error_log("ATM: Generated segment $i audio: " . strlen($audio_content) . " bytes");
            
            $temp_file = self::save_temp_audio_segment($audio_content, $job_id, $i);
            
            // Validate temp file was saved correctly
            if (!file_exists($temp_file)) {
                throw new Exception("Failed to save audio segment $i - file does not exist");
            }
            
            $file_size = filesize($temp_file);
            if ($file_size < 1000) {
                throw new Exception("Saved audio segment $i is too small: $file_size bytes");
            }
            
            error_log("ATM: Saved segment $i to file: " . $file_size . " bytes");
            
            $temp_files[$i] = $temp_file;
            
            // Update progress
            $wpdb->update($table_name, [
                'completed_segments' => $i + 1,
                'temp_files' => json_encode($temp_files)
            ], ['job_id' => $job_id]);
            
            error_log("ATM: Background completed segment $i");
        }
        
        // Validate we have all segments before combining
        if (count($temp_files) !== count($segments)) {
            throw new Exception("Mismatch in segment count: expected " . count($segments) . ", got " . count($temp_files));
        }
        
        // Add debug information before combining
        self::debug_audio_segments($job_id);
        
        // Combine segments
        error_log("ATM: Combining segments for job $job_id");
        $final_audio = self::combine_audio_segments_improved($temp_files);
        
        // Validate final audio
        if (strlen($final_audio) < 10000) { // Less than 10KB is suspicious for a podcast
            throw new Exception("Final combined audio is too small: " . strlen($final_audio) . " bytes");
        }
        
        error_log("ATM: Final audio size: " . strlen($final_audio) . " bytes");
        
        // Save final podcast
        $upload_dir = wp_upload_dir();
        $filename = 'podcast-' . $job['post_id'] . '-' . time() . '.mp3';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($filepath, $final_audio) === false) {
            throw new Exception("Failed to save final podcast to: $filepath");
        }
        
        // Verify final file was saved
        if (!file_exists($filepath)) {
            throw new Exception("Final podcast file was not created: $filepath");
        }
        
        $final_file_size = filesize($filepath);
        if ($final_file_size < 10000) {
            throw new Exception("Final podcast file is too small: $final_file_size bytes");
        }
        
        error_log("ATM: Final podcast saved: $filepath ($final_file_size bytes)");
        
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        // Update post meta
        update_post_meta($job['post_id'], '_atm_podcast_url', $file_url);
        update_post_meta($job['post_id'], '_atm_podcast_script', $job['script']);
        update_post_meta($job['post_id'], '_atm_podcast_voice', $job['voice_a']);
        update_post_meta($job['post_id'], '_atm_podcast_host_b_voice', $job['voice_b']);
        update_post_meta($job['post_id'], '_atm_podcast_provider', $job['provider']);
        
        // Mark job complete
        $wpdb->update($table_name, ['status' => 'completed'], ['job_id' => $job_id]);
        
        // Clean up temp files
        foreach ($temp_files as $temp_file) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
                error_log("ATM: Cleaned up temp file: $temp_file");
            }
        }
        
        error_log("ATM: Background job $job_id completed successfully");
        
    } catch (Exception $e) {
        error_log("ATM: Background job $job_id failed: " . $e->getMessage());
        $wpdb->update($table_name, [
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ], ['job_id' => $job_id]);
    }
}

    /**
     * Start chunked podcast generation
     */
    public static function start_chunked_podcast_generation($post_id, $script, $voice_a, $voice_b, $provider) {
        global $wpdb;
        
        // Generate unique job ID
        $job_id = wp_generate_uuid4();
        
        // Split script into manageable segments (about 500 words each)
        $segments = self::split_script_into_segments($script, 500);
        
        // Create job record
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        $wpdb->insert($table_name, [
            'post_id' => $post_id,
            'job_id' => $job_id,
            'script' => $script,
            'voice_a' => $voice_a,
            'voice_b' => $voice_b,
            'provider' => $provider,
            'total_segments' => count($segments),
            'status' => 'processing'
        ]);
        
        // Schedule processing
        wp_schedule_single_event(time() + 5, 'atm_process_podcast_segment', [$job_id, 0]);
        
        return $job_id;
    }

    /**
     * Split script into segments of roughly equal word count
     */
    private static function split_script_into_segments($script, $target_words = 500) {
        // Add debug info about the original script
        $total_words = str_word_count($script);
        $total_lines = count(explode("\n", trim($script)));
        error_log("ATM: Original script - Words: $total_words, Lines: $total_lines");
        
        $lines = explode("\n", trim($script));
        $segments = [];
        $current_segment = '';
        $current_word_count = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $words_in_line = str_word_count($line);
            
            if ($current_word_count > 0 && ($current_word_count + $words_in_line) > $target_words) {
                if (!empty($current_segment)) {
                    $segments[] = trim($current_segment);
                    error_log("ATM: Created segment " . (count($segments)) . " with " . str_word_count($current_segment) . " words");
                }
                $current_segment = $line;
                $current_word_count = $words_in_line;
            } else {
                $current_segment .= "\n" . $line;
                $current_word_count += $words_in_line;
            }
        }
        
        if (!empty($current_segment)) {
            $segments[] = trim($current_segment);
            error_log("ATM: Created final segment " . count($segments) . " with " . str_word_count($current_segment) . " words");
        }
        
        error_log("ATM: Split into " . count($segments) . " segments, total words distributed: " . array_sum(array_map('str_word_count', $segments)));
        
        return $segments;
    }

    /**
     * Process a single segment
     */
    public static function process_podcast_segment($job_id, $segment_index) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        
        try {
            // Get job details
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE job_id = %s",
                $job_id
            ), ARRAY_A);
            
            if (!$job) {
                error_log("ATM: Job $job_id not found");
                return;
            }
            
            // Get segments
            $segments = self::split_script_into_segments($job['script'], 500);
            
            if (!isset($segments[$segment_index])) {
                error_log("ATM: Segment $segment_index not found for job $job_id");
                return;
            }
            
            $segment_script = $segments[$segment_index];
            
            // Generate audio for this segment
            $audio_content = self::generate_two_person_podcast_audio(
                $segment_script,
                $job['voice_a'],
                $job['voice_b'],
                $job['provider']
            );
            
            // Save segment to temporary file
            $temp_file = self::save_temp_audio_segment($audio_content, $job_id, $segment_index);
            
            // Update job progress
            $temp_files = $job['temp_files'] ? json_decode($job['temp_files'], true) : [];
            $temp_files[$segment_index] = $temp_file;
            
            $wpdb->update($table_name, [
                'completed_segments' => $segment_index + 1,
                'temp_files' => json_encode($temp_files),
                'updated_at' => current_time('mysql')
            ], ['job_id' => $job_id]);
            
            // Schedule next segment or finalize
            if ($segment_index + 1 < count($segments)) {
                // Process next segment
                wp_schedule_single_event(time() + 2, 'atm_process_podcast_segment', [$job_id, $segment_index + 1]);
            } else {
                // All segments complete, finalize
                wp_schedule_single_event(time() + 2, 'atm_finalize_podcast', [$job_id]);
            }
            
        } catch (Exception $e) {
            error_log("ATM: Segment processing failed for job $job_id, segment $segment_index: " . $e->getMessage());
            
            // Update job with error
            $wpdb->update($table_name, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ], ['job_id' => $job_id]);
        }
    }

    /**
     * Save temporary audio segment
     */
    private static function save_temp_audio_segment($audio_content, $job_id, $segment_index) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/podcast-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $filename = "segment-{$job_id}-{$segment_index}.mp3";
        $filepath = $temp_dir . '/' . $filename;
        
        if (file_put_contents($filepath, $audio_content) === false) {
            throw new Exception("Failed to save temporary audio segment");
        }
        
        return $filepath;
    }

    /**
     * Finalize podcast by combining all segments
     */
    public static function finalize_podcast($job_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        
        try {
            // Get job details
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE job_id = %s",
                $job_id
            ), ARRAY_A);
            
            if (!$job) {
                throw new Exception("Job not found");
            }
            
            $temp_files = json_decode($job['temp_files'], true) ?: [];

            // Add this line in finalize_podcast() before combining:
            self::debug_audio_segments($job_id);
            $final_audio = self::combine_audio_segments_improved($temp_files);
                        
            // Combine audio segments
            $final_audio = self::combine_audio_segments_improved($temp_files);
            
            // Save final podcast
            $upload_dir = wp_upload_dir();
            $filename = 'podcast-' . $job['post_id'] . '-' . time() . '.mp3';
            $filepath = $upload_dir['path'] . '/' . $filename;
            
            if (file_put_contents($filepath, $final_audio) === false) {
                throw new Exception("Failed to save final podcast");
            }
            
            $file_url = $upload_dir['url'] . '/' . $filename;
            
            // Update post meta
            update_post_meta($job['post_id'], '_atm_podcast_url', $file_url);
            update_post_meta($job['post_id'], '_atm_podcast_script', $job['script']);
            update_post_meta($job['post_id'], '_atm_podcast_voice', $job['voice_a']);
            update_post_meta($job['post_id'], '_atm_podcast_host_b_voice', $job['voice_b']);
            update_post_meta($job['post_id'], '_atm_podcast_provider', $job['provider']);
            
            // Update job status
            $wpdb->update($table_name, [
                'status' => 'completed'
            ], ['job_id' => $job_id]);
            
            // Clean up temporary files
            foreach ($temp_files as $temp_file) {
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }
            
            error_log("ATM: Podcast generation completed for job $job_id");
            
        } catch (Exception $e) {
            error_log("ATM: Finalization failed for job $job_id: " . $e->getMessage());
            
            $wpdb->update($table_name, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ], ['job_id' => $job_id]);
        }
    }

    // Replace the combine_audio_segments method in class-atm-api.php:
    private static function combine_audio_segments($temp_files) {
        $combined_audio = '';
        
        // REMOVED: Intro audio code - we'll add this back later once basic generation works
        
        // Sort temp files by segment index
        ksort($temp_files);
        
        foreach ($temp_files as $index => $filepath) {
            if (file_exists($filepath)) {
                $segment_content = file_get_contents($filepath);
                if ($segment_content) {
                    $combined_audio .= $segment_content;
                    error_log("ATM: Added segment $index (" . strlen($segment_content) . " bytes)");
                } else {
                    error_log("ATM: Warning - segment $index is empty");
                }
            } else {
                error_log("ATM: Warning - segment file missing: $filepath");
            }
        }
        
        error_log("ATM: Final combined audio size: " . strlen($combined_audio) . " bytes");
        return $combined_audio;
    }
    /**
     * Get podcast generation progress
     */
    public static function get_podcast_progress($job_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ), ARRAY_A);
        
        if (!$job) {
            throw new Exception("Job not found");
        }
        
        return [
            'status' => $job['status'],
            'total_segments' => intval($job['total_segments']),
            'completed_segments' => intval($job['completed_segments']),
            'progress_percentage' => $job['total_segments'] > 0 ? 
                round(($job['completed_segments'] / $job['total_segments']) * 100) : 0,
            'error_message' => $job['error_message']
        ];
    }

    public static function generate_advanced_podcast_script($title, $content, $language, $duration = 'medium') {
    
    // Get website name for podcast branding
    $site_name = get_bloginfo('name');
    $podcast_name = $site_name . ' Podcast';
    
    // Fixed duration mapping with proper word counts
    $duration_specs = [
        'short' => [
            'description' => '10-15 minutes',
            'word_count' => '2000-2500 words total',
            'segments' => 3
        ],
        'medium' => [
            'description' => '15-25 minutes', 
            'word_count' => '3500-4500 words total',
            'segments' => 4
        ],
        'long' => [
            'description' => '25-40 minutes',
            'word_count' => '6000-8000 words total', 
            'segments' => 6
        ]
    ];
    
    $spec = $duration_specs[$duration] ?? $duration_specs['medium'];
    $target_duration = $spec['description'];
    $target_words = $spec['word_count'];
    $segments = $spec['segments'];

    // Check if we need to split into multiple requests for long scripts
    if ($duration === 'long') {
        return self::generate_long_podcast_script($title, $content, $language, $spec);
    }

    $system_prompt = "You are creating a professional, engaging podcast script between two expert hosts discussing '{$title}' for {$podcast_name}. 

**CRITICAL DURATION REQUIREMENT**: 
Generate a script for exactly {$target_duration} duration ({$target_words}).
This is MANDATORY - the script must be long enough to fill this time when spoken at normal pace.

**HOST PERSONALITIES & SPEECH PATTERNS:**
- **ALEX CHEN**: Primary host - analytical, insightful, authoritative
  * Uses evidence-backed statements and research references
  * Employs thoughtful pauses and reflective questions
  * Transitions with phrases like 'What's particularly fascinating is...' or 'The research clearly shows...'
  
- **JORDAN RIVERA**: Co-host - relatable, dynamic, practical
  * Connects concepts to real-world applications
  * Uses analogies and metaphors to explain complex ideas
  * Adds personal perspective with phrases like 'In my experience...' or 'Think about it this way...'

**COMPREHENSIVE SCRIPT STRUCTURE:**

1. **PODCAST INTRO (1-2 minutes):**
ALEX: Welcome to {$podcast_name}, where we explore the ideas shaping our world. I'm your host, Alex Chen.
JORDAN: And I'm Jordan Rivera. Today, we're diving deep into {$title} - a topic that's incredibly relevant right now.
ALEX: This is something that affects many people, and we've researched this extensively to bring you the most comprehensive discussion.
JORDAN: We'll cover everything from the fundamentals to practical applications you can use right away. Let's get started!

2. **BACKGROUND CONTEXT (3-5 minutes):**
- Historical development of the topic (minimum 3 key milestones)
- Current landscape and importance (with specific statistics/data points)
- Why this matters now (timeliness, relevance, impact)

3. **MAIN DISCUSSION (70-75% of content - THIS IS THE BULK):**
- Minimum {$segments} distinct subtopics, each thoroughly explored
- For EACH subtopic include:
  * Detailed explanation with supporting evidence/research
  * At least 2 specific examples or case studies
  * Different perspectives/viewpoints (pros, cons, alternatives)
  * Real-world implications and applications
  * Extended back-and-forth discussion between hosts

4. **PRACTICAL SEGMENT (10-15% of content):**
- Actionable advice for listeners (minimum 5 specific recommendations)
- How to apply insights in different contexts
- Common challenges and solutions

5. **FUTURE OUTLOOK (3-5 minutes):**
- Emerging trends and developments
- Expert predictions with reasoning
- Potential impact on different stakeholders

6. **PODCAST OUTRO (1-2 minutes):**
ALEX: This has been such an insightful discussion on {$title}. Jordan, any final thoughts?
JORDAN: I think what stands out most is [key takeaway]. Listeners should really consider [final piece of advice].
ALEX: Thank you for joining us for this episode of {$podcast_name}.
JORDAN: For more episodes like this and additional resources, visit {$site_name}.
ALEX: If you enjoyed this discussion, remember to subscribe and share!
JORDAN: Until next time, I'm Jordan Rivera...
ALEX: And I'm Alex Chen. Stay curious!

**CONVERSATIONAL QUALITY REQUIREMENTS:**

1. **AUTHENTICITY:**
- Each host turn MUST be 4-8 sentences minimum for main discussion points
- Include natural speech patterns with occasional brief pauses
- Vary sentence length and structure to sound natural
- Include occasional brief agreements ('That's right,' 'Exactly,' 'Great point')

2. **DEPTH AND RICHNESS:**
- For each major point include:
  * Primary explanation (2-3 sentences)
  * Supporting evidence (research, statistics, expert opinions)
  * Real-world example or case study (detailed, not just mentioned)
  * Implications or applications (2-3 sentences)
  * Connection to broader themes
- Use specific details rather than generalizations

3. **EXTENDED DISCUSSIONS:**
- Create natural back-and-forth exchanges
- Each main topic should have 8-12 speaking turns between hosts
- Build on each other's points progressively
- Ask follow-up questions that lead to deeper exploration

**MANDATORY WORD COUNT:**
The final script MUST contain {$target_words}. This is not optional. Each main discussion point should be 400-600 words of dialogue to reach the target length.

**FINAL REQUIREMENTS:**
- Write entirely in {$language}
- Maintain consistent host personalities throughout
- NO sound effect references like [SOUND EFFECT:] - remove all such references
- Create a natural conversation flow rather than alternating monologues
- Include elements that would make for engaging listening
- Always reference the podcast as '{$podcast_name}' when mentioned

Remember, this script must be comprehensive, engaging, and substantive enough to fill exactly {$target_duration} without feeling rushed or padded.";

    $model = get_option('atm_podcast_content_model', 'openai/gpt-4o');
    
    $script = self::enhance_content_with_openrouter(
        ['title' => $title, 'content' => $content],
        $system_prompt,
        $model,
        false, // not JSON mode
        true   // enable web search for research
    );

    return $script;
}

// New method for handling long podcast scripts with multiple requests
private static function generate_long_podcast_script($title, $content, $language, $spec) {
    // For long scripts, generate in segments and combine
    $segments = [
        'intro_and_context' => [
            'description' => 'Podcast introduction and background context',
            'target_words' => '800-1000 words',
            'time' => '5-6 minutes'
        ],
        'main_discussion_part1' => [
            'description' => 'First half of main discussion covering primary aspects',
            'target_words' => '2000-2500 words', 
            'time' => '12-15 minutes'
        ],
        'main_discussion_part2' => [
            'description' => 'Second half of main discussion with practical applications',
            'target_words' => '2000-2500 words',
            'time' => '12-15 minutes'  
        ],
        'conclusion_and_outro' => [
            'description' => 'Future outlook, practical advice, and podcast conclusion',
            'target_words' => '800-1000 words',
            'time' => '5-6 minutes'
        ]
    ];

    $full_script = '';
    $model = get_option('atm_podcast_content_model', 'openai/gpt-4o');

    foreach ($segments as $segment_key => $segment_info) {
        $segment_prompt = self::create_segment_prompt(
            $title, 
            $content, 
            $language, 
            $segment_key, 
            $segment_info,
            $segment_key === 'intro_and_context' // is_first_segment
        );

        $segment_script = self::enhance_content_with_openrouter(
            ['title' => $title, 'content' => $content],
            $segment_prompt,
            $model,
            false,
            true
        );

        $full_script .= $segment_script . "\n\n";

        // Add small delay between requests to avoid rate limiting
        sleep(2);
    }

    return trim($full_script);
}

// Helper method to create segment-specific prompts
private static function create_segment_prompt($title, $content, $language, $segment_key, $segment_info, $is_first_segment) {
    $site_name = get_bloginfo('name');
    $podcast_name = $site_name . ' Podcast';
    
    $base_hosts = "
**HOST PERSONALITIES:**
- **ALEX CHEN**: Primary host - analytical, insightful, authoritative
- **JORDAN RIVERA**: Co-host - relatable, dynamic, practical

**SPEAKER FORMAT:**
- Use clean speaker names: ALEX: and JORDAN:
- Do NOT use markdown formatting like **ALEX:** 
- Each speaker line must start with SPEAKER_NAME: followed by their dialogue

**PODCAST NAME:** {$podcast_name}
";

    $continuation_note = $is_first_segment ? '' : "**NOTE:** This is a continuation of {$podcast_name}. Begin naturally without re-introducing the hosts or topic.";

    switch ($segment_key) {
        case 'intro_and_context':
            return "You are creating the introduction and background context section of {$podcast_name} about '{$title}'.

{$base_hosts}

**REQUIREMENTS:**
- Generate exactly {$segment_info['target_words']} for {$segment_info['time']}
- Include full podcast introduction with host introductions
- Mention the podcast name: {$podcast_name}
- Provide comprehensive background context
- NO sound effect references
- Write entirely in {$language}

**STRUCTURE:**
1. Welcome to {$podcast_name} and host introductions (1 minute)
2. Topic introduction and why it matters (2-3 minutes) 
3. Historical context and current landscape (2-3 minutes)

Generate ONLY the script dialogue, no stage directions or sound effects.";

        case 'conclusion_and_outro':
            return "You are creating the conclusion and outro segment of {$podcast_name} about '{$title}'.

{$base_hosts}

{$continuation_note}

**REQUIREMENTS:**
- Generate exactly {$segment_info['target_words']} for {$segment_info['time']}
- Provide future outlook and emerging trends
- Give actionable advice for listeners
- Include natural podcast conclusion mentioning {$podcast_name}
- Reference {$site_name} for more content
- NO sound effect references
- Write entirely in {$language}

**STRUCTURE:**
1. Future trends and predictions (2-3 minutes)
2. Practical takeaways for listeners (2 minutes)  
3. Natural conclusion thanking listeners for joining {$podcast_name} and sign-off (1 minute)

Generate ONLY the script dialogue, no stage directions.";

        case 'main_discussion_part1':
            return "You are creating the first main discussion segment of a professional podcast about '{$title}'.

{$base_hosts}

{$continuation_note}

**REQUIREMENTS:**
- Generate exactly {$segment_info['target_words']} for {$segment_info['time']}
- Cover the primary aspects and fundamentals of the topic
- Include detailed explanations, examples, and evidence
- Create natural back-and-forth discussion
- NO sound effect references
- Write entirely in {$language}

Generate ONLY the script dialogue, no stage directions.";

        case 'main_discussion_part2': 
            return "You are creating the second main discussion segment of a professional podcast about '{$title}'.

{$base_hosts}

{$continuation_note}

**REQUIREMENTS:**
- Generate exactly {$segment_info['target_words']} for {$segment_info['time']}
- Focus on practical applications and real-world implications
- Include case studies and detailed examples
- Address different perspectives and potential challenges
- NO sound effect references  
- Write entirely in {$language}

Generate ONLY the script dialogue, no stage directions.";

        case 'conclusion_and_outro':
            return "You are creating the conclusion and outro segment of a professional podcast about '{$title}'.

{$base_hosts}

{$continuation_note}

**REQUIREMENTS:**
- Generate exactly {$segment_info['target_words']} for {$segment_info['time']}
- Provide future outlook and emerging trends
- Give actionable advice for listeners
- Include natural podcast conclusion
- NO sound effect references
- Write entirely in {$language}

**STRUCTURE:**
1. Future trends and predictions (2-3 minutes)
2. Practical takeaways for listeners (2 minutes)  
3. Natural conclusion and sign-off (1 minute)

Generate ONLY the script dialogue, no stage directions.";

        default:
            return '';
    }
}

    /**
     * Generate listicle title
     */
    public static function generate_listicle_title($topic, $item_count, $category, $model_override = '') {
        $system_prompt = "You are an expert SEO content strategist. Your task is to generate a single, compelling, SEO-friendly title for a listicle article about '{$topic}' with {$item_count} items in the {$category} category.

    REQUIREMENTS:
    - Make it clickable and engaging
    - Include the number of items ({$item_count})
    - Use power words that drive engagement
    - Keep it under 60 characters for SEO
    - Make it specific and valuable to the target audience

    EXAMPLES:
    - '10 Best Project Management Tools for Small Teams in 2024'
    - '15 Proven Email Marketing Strategies That Boost Sales'
    - '7 Essential WordPress Plugins Every Blogger Needs'

    Return only the title, nothing else.";

        $model = !empty($model_override) ? $model_override : get_option('atm_article_model', 'openai/gpt-4o');
        
        $generated_title = self::enhance_content_with_openrouter(
            ['content' => $topic], 
            $system_prompt, 
            $model, 
            false, 
            true // Enable web search for current trends
        );
        
        return trim($generated_title, " \t\n\r\0\x0B\"");
    }

    /**
     * Generate listicle content
     */
    public static function generate_listicle_content($params) {
        extract($params);
        
        $pricing_instruction = $include_pricing ? "Include pricing information where relevant." : "";
        $rating_instruction = $include_ratings ? "Include star ratings or numerical scores for each item." : "";
        $custom_instruction = !empty($custom_prompt) ? "Additional instructions: {$custom_prompt}" : "";

        $system_prompt = "You are an expert content creator specializing in listicle articles. Your task is to create a comprehensive, engaging listicle about '{$topic}' with exactly {$item_count} items in the {$category} category.

        CRITICAL INSTRUCTIONS:
        1. Your entire response MUST be a single, valid JSON object.
        2. Structure the JSON response exactly as follows:
        {
            \"subtitle\": \"Brief engaging subtitle\",
            \"introduction\": \"Two comprehensive paragraphs about the topic. Make this detailed and informative, explaining the importance and context of the topic.\",
            \"overview\": \"Brief overview of what the list covers\",
            \"items\": [
                {
                    \"number\": 1,
                    \"title\": \"Item title\",
                    \"description\": \"Detailed description (3 comprehensive paragraphs with substantial content)\",
                    \"features\": [\"feature1\", \"feature2\", \"feature3\", \"feature4\", \"feature5\"],
                    \"pros\": [\"pro1\", \"pro2\", \"pro3\", \"pro4\", \"pro5\", \"pro6\"],
                    \"cons\": [\"con1\", \"con2\", \"con3\", \"con4\", \"con5\", \"con6\"],
                    \"rating\": 4.5,
                    \"price\": \"$99/month\",
                    \"why_its_great\": \"Comprehensive explanation of why this item made the list - make this substantial, detailed, and at least 3-4 sentences long\"
                }
            ],
            \"conclusion\": \"Compelling conclusion paragraph\"
        }

        CONTENT REQUIREMENTS:
        - Introduction must be exactly TWO substantial paragraphs
        - Each item description must be exactly THREE detailed paragraphs
        - Provide exactly 6 pros and 6 cons for each item (make them meaningful and specific)
        - Features should be 5 relevant items
        - 'why_its_great' should be substantial (3-4 sentences minimum)
        - Make each item valuable and detailed
        - Use engaging language but keep it informative
        - Focus on providing genuine value to readers
        - Ensure items are ranked logically (best to good, or most important to least)
        - {$pricing_instruction}
        - {$rating_instruction}
        - {$custom_instruction}

        Use your web search ability to ensure all information is current and accurate. Do not include any text outside the JSON object.";

        $model = !empty($model) ? $model : get_option('atm_article_model', 'openai/gpt-4o');

        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $topic],
            $system_prompt,
            $model,
            true, // JSON mode
            true  // Enable web search
        );
        
        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['items']) || !is_array($result['items'])) {
            error_log('Content AI Studio - Invalid JSON from Listicle API: ' . $raw_response);
            throw new Exception('The AI returned an invalid listicle structure. Please try again.');
        }

        return $result;
    }

    /**
     * Summarizes a large block of text using a fast AI model.
     * @param string $content The long content to summarize.
     * @return string The summarized content.
     */
    public static function summarize_content_for_rewrite($content) {
        $summary_prompt = 'You are a text summarization expert. Your task is to read the following article content and create a concise but comprehensive summary. The summary should capture all the main points, key facts, and the overall conclusion of the article. Output only the summary text. The article is below:';

        // Use a fast and cost-effective model specifically for this summarization task.
        return self::enhance_content_with_openrouter(
            ['content' => $content],
            $summary_prompt,
            'anthropic/claude-3-haiku',
            false, // json_mode
            false  // enable_web_search
        );
    }

    /**
     * Generate lifelike comments grounded in article content.
     * Returns a list of [{author_name, text, parent_index|null}]
     */
    public static function generate_lifelike_comments($title, $content, $count = 7, $threaded = true, $model_override = '', $post_id = 0) {
        // Allow up to 50
        $count = max(5, min(50, intval($count)));

        $threading_rule = $threaded
            ? "- Some comments should be direct replies to earlier ones. Use `parent_index` to reference the zero-based index of the parent comment. Replies MUST reference an earlier index.\n"
            : "- All comments must be top-level (no replies). Set `parent_index` to null.\n";

        // Create a unique seed based on post ID and title for name diversity
        $name_seed = $post_id ? "post_$post_id" : substr(md5($title), 0, 8);
        
        $system_prompt = "You are a diverse group of real readers reacting to an article.
    Produce a realistic discussion thread with natural, varied voices: short reactions, longer takes, questions, counterpoints, and follow-ups.

    CRITICAL RULES:
    - Read and internalize the article context below. Stay specific to it.
    - Vary tone and style (casual, thoughtful, skeptical, appreciative). Keep it conversational and human.
    - Avoid generic filler like 'Great post' or robotic phrasing. Be concrete and grounded in the article.
    - Mix lengths: 1–2 sentences up to short paragraphs. No walls of text.
    - Light use of emojis or slang is okay, but not overused. No profanity or hate.
    $threading_rule
    - NAME UNIQUENESS SEED: $name_seed - Use this to generate completely unique names for this specific content. Names should be globally diverse and distinctive.
    - REQUIRED: Each name must be unique and culturally diverse. Examples: Sakura Tanaka, Olumide Adebayo, Astrid Larsson, Joaquín Morales, Noor Al-Zahra, Raj Patel, Chloe Dubois, Aleksandr Volkov, Anya Kozlov, Liu Wei.
    - Avoid Western-only names. Include names from: Asia, Africa, Middle East, Latin America, Europe, etc.
    - Mix formats but ensure global representation: Full names, usernames with cultural elements, nicknames from various languages.
    - STRICTLY FORBIDDEN: any links, URLs, domain names, or markdown links. Do not include emails.
    - Do NOT include timestamps, likes, or extra fields.

    OUTPUT FORMAT (MANDATORY):
    Return ONLY a JSON object with a single key `comments`:
    {
    \"comments\": [
        { \"author_name\": \"...\", \"text\": \"...\", \"parent_index\": null|number },
        ...
    ]
    }
    Generate exactly $count items. No extra commentary or code fences.";

        $article_payload = [
            'title'   => (string) $title,
            'content' => (string) $content,
        ];

        $model = !empty($model_override) ? $model_override : get_option('atm_content_model', 'anthropic/claude-3-haiku');

        // IMPORTANT: disable web search for comments and force JSON mode
        $raw = self::enhance_content_with_openrouter(
            $article_payload,
            $system_prompt,
            $model,
            true,   // json_mode
            false   // enable_web_search
        );

        // Decode safely
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) {
            throw new Exception('AI returned invalid JSON for comments.');
        }

        $arr = isset($decoded['comments']) && is_array($decoded['comments']) ? $decoded['comments'] : (is_array($decoded) ? $decoded : []);
        if (empty($arr)) {
            throw new Exception('No comments were generated.');
        }

        // Normalization + link stripping
        $out = [];
        foreach ($arr as $i => $c) {
            if (count($out) >= $count) break;

            $author = isset($c['author_name']) ? sanitize_text_field($c['author_name']) : 'Guest';

            $text = isset($c['text']) ? wp_kses_post($c['text']) : '';
            // Strip any URLs and markdown links defensively
            // [label](url) -> label
            $text = preg_replace('/\[(.*?)\]\((https?:\/\/|www\.)[^\s)]+\)/i', '$1', $text);
            // raw URLs
            $text = preg_replace('/https?:\/\/\S+/i', '', $text);
            $text = preg_replace('/\bwww\.[^\s]+/i', '', $text);
            // collapse extra whitespace
            $text = trim(preg_replace('/\s{2,}/', ' ', $text));

            // Allow simple inline formatting
            $text = wp_kses($text, ['br' => [], 'em' => [], 'strong' => [], 'i' => [], 'b' => []]);

            if ($text === '') continue;

            $pi = null;
            if ($threaded && array_key_exists('parent_index', $c) && $c['parent_index'] !== null) {
                $val = intval($c['parent_index']);
                // Parent must be an earlier index
                $pi  = ($val >= 0 && $val < $count && $val < $i) ? $val : null;
            }

            $out[] = [
                'author_name'  => $author,
                'text'         => $text,
                'parent_index' => $threaded ? $pi : null,
            ];
        }

        return array_values(array_slice($out, 0, $count));
    }

    // --- MULTIPAGE ARTICLE FUNCTIONS ---
    public static function generate_multipage_title($keyword, $page_count, $model_override) {
        $system_prompt = "You are an expert SEO content strategist. Your task is to generate a single, compelling, SEO-friendly title for a {$page_count}-page article about '{$keyword}'. The title should be catchy and clearly indicate that the content is a comprehensive, multi-part guide. Return only the title itself, with no extra text or quotation marks.";
        
        $model = !empty($model_override) ? $model_override : get_option('atm_article_model', 'openai/gpt-4o');
        
        $generated_title = self::enhance_content_with_openrouter(['content' => $keyword], $system_prompt, $model, false, true);
        
        return trim($generated_title, " \t\n\r\0\x0B\"");
    }
    
    public static function generate_multipage_outline($params) {
        extract($params); // Extracts variables like $article_title, $page_count, etc.

        $subheadline_instruction = $include_subheadlines ? "Each page's content plan should include a list of 3-5 relevant subheadlines (H2s and H3s)." : "";

        $system_prompt = "You are an expert content architect. Your task is to create a logical and comprehensive outline for a {$page_count}-page article titled '{$article_title}'.
        
        CRITICAL INSTRUCTIONS:
        1. Your entire response MUST be a single, valid JSON object.
        2. The JSON object must contain one key: `pages`.
        3. The `pages` key must be an array of exactly {$page_count} objects.
        4. Each object in the `pages` array must have three keys:
           - `title`: A short, engaging title for that specific page/chapter.
           - `slug`: A URL-friendly slug for that page title (e.g., 'understanding-the-basics').
           - `content_plan`: A detailed 2-3 sentence plan outlining the specific topics, questions, and points to be covered on that page. {$subheadline_instruction}
        
        Do not include any text, explanations, or markdown code fences outside of the JSON object.";

        $model = !empty($model) ? $model : get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $article_title],
            $system_prompt,
            $model,
            true, // json_mode
            $enable_web_search
        );
        
        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['pages']) || !is_array($result['pages'])) {
            error_log('Content AI Studio - Invalid JSON from Multipage Outline API: ' . $raw_response);
            throw new Exception('The AI returned an invalid outline structure. Please try again.');
        }

        return $result;
    }
    
    public static function generate_multipage_content($params) {
        extract($params); // Extracts variables

        $writing_styles = self::get_writing_styles();
        $base_prompt = !empty($custom_prompt) 
            ? $custom_prompt 
            : ($writing_styles[$writing_style]['prompt'] ?? $writing_styles['default_seo']['prompt']);

        $user_content = "Please write page {$page_number} of a {$total_pages}-page article titled '{$article_title}'. Follow the content plan for this page precisely.\n\n" .
                      "**This Page's Title:** {$page_outline['title']}\n" .
                      "**Content Plan for this Page:**\n{$page_outline['content_plan']}\n\n" .
                      "**Instructions:**\n" .
                      "- Write approximately {$words_per_page} words.\n" .
                      "- The entire response should be ONLY the article content for this specific page, formatted in Markdown.\n" .
                      "- Do NOT repeat the main article title or this page's title within the content. Start directly with the first paragraph.";
        
        $model = !empty($model) ? $model : get_option('atm_article_model', 'openai/gpt-4o');

        return self::enhance_content_with_openrouter(
            ['content' => $user_content],
            $base_prompt,
            $model,
            false, // not json_mode
            $enable_web_search
        );
    }

    public static function extract_article_links_from_html($html_content, $base_url) {
        $system_prompt = "You are a web scraping expert. Analyze the following HTML content from the base URL `{$base_url}`. Your task is to extract all the hyperlinks (`<a>` tags) that appear to lead to individual news articles.

        CRITICAL RULES:
        1.  Return ONLY a newline-separated list of the absolute URLs.
        2.  If you find relative URLs (e.g., '/politics/story'), convert them to absolute URLs using the base URL.
        3.  Ignore links to categories, author pages, advertisements, social media, or the homepage.";

        $ai_response = self::enhance_content_with_openrouter(
            ['content' => $html_content],
            $system_prompt,
            'anthropic/claude-3-haiku', // Fast model
            false, false // No JSON mode, no web search needed
        );

        $urls = array_filter(explode("\n", trim($ai_response)));
        return array_map('trim', $urls);
    }

    public static function find_news_sources_for_keywords($keywords_string) {
        $system_prompt = "You are an expert news researcher. For the given comma-separated keywords, your task is to find the top 10 most authoritative and relevant online news sources.

        CRITICAL INSTRUCTIONS:
        1.  For each keyword, ALWAYS include the Google News search results page URL first.
        2.  Find homepages or specific category/topic pages (e.g., `https://www.reuters.com/world/europe/` not a specific article).
        3.  Return ONLY a newline-separated list of the URLs. Do not include any other text, titles, or explanations.";

        $ai_response = self::enhance_content_with_openrouter(
            ['content' => $keywords_string],
            $system_prompt,
            'anthropic/claude-3-haiku', // Use a fast model
            false,
            true // Enable web search
        );

        // Clean up the response to ensure it's just a list of URLs
        $urls = array_filter(explode("\n", trim($ai_response)));
        return array_map('trim', $urls);
    }
    
    public static function generate_takeaways_from_content($content, $model_override = '') {
        $system_prompt = "You are an expert editor. Your task is to analyze the following article and extract the 5 most important key takeaways.

        CRITICAL RULES:
        - Each takeaway must be a very concise sentence, ideally under 12 words.
        - Your entire response MUST consist ONLY of the takeaways.
        - Each takeaway must be on a new line.
        - DO NOT number the takeaways or use bullet points (e.g., '-', '*', '1.').
        - DO NOT include any introductory phrases like 'Here are the 5 most important key takeaways from the article:'. Your response must start directly with the first takeaway.";
        
        $model = !empty($model_override) ? $model_override : get_option('atm_content_model', 'anthropic/claude-3-haiku');

        return self::enhance_content_with_openrouter(
            ['content' => $content],
            $system_prompt,
            $model,
            false, // json_mode is false
            false  // enable_web_search is false
        );
    }
    
    public static function generate_image_with_blockflow($prompt, $model_override = '', $size_override = '') {
        $api_key = get_option('atm_blockflow_api_key');
        if (empty($api_key)) {
            throw new Exception('BlockFlow API key is not configured.');
        }

        $model = !empty($model_override) ? $model_override : get_option('atm_flux_model', 'flux-1-schnell');
        $size = !empty($size_override) ? $size_override : get_option('atm_image_size', '1024x1024');

        // Convert '1024x1024' to '1:1' for the API's aspect_ratio parameter
        $aspect_ratio_map = [
            '1024x1024' => '1:1',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16'
        ];
        $aspect_ratio = isset($aspect_ratio_map[$size]) ? $aspect_ratio_map[$size] : '1:1';

        // Initial Request to the Correct Endpoint
        $initial_response = wp_remote_post('https://api.bfl.ai/v1/' . $model, [
            'headers' => [
                'x-key' => $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'prompt' => self::enhance_image_prompt($prompt),
                'aspect_ratio' => $aspect_ratio
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($initial_response) || wp_remote_retrieve_response_code($initial_response) !== 200) {
            throw new Exception('BlockFlow API initial request failed: ' . wp_remote_retrieve_body($initial_response));
        }

        $initial_data = json_decode(wp_remote_retrieve_body($initial_response), true);
        if (!isset($initial_data['polling_url'])) {
            throw new Exception('BlockFlow API did not return a polling URL.');
        }
        $polling_url = $initial_data['polling_url'];

        // Polling for the Result
        $final_result = null;
        $max_attempts = 180; // Poll for a maximum of 90 seconds (180 * 0.5s)
        $attempts = 0;

        while ($attempts < $max_attempts) {
            usleep(500000); // Wait for 0.5 seconds before checking again

            $polling_response = wp_remote_get($polling_url, [
                'headers' => ['x-key' => $api_key],
                'timeout' => 15
            ]);

            if (is_wp_error($polling_response)) continue; // Try again on connection error

            $result_data = json_decode(wp_remote_retrieve_body($polling_response), true);

            if (isset($result_data['status']) && $result_data['status'] === 'Ready') {
                $final_result = $result_data;
                break;
            }

            if (isset($result_data['status']) && in_array($result_data['status'], ['Error', 'Failed'])) {
                throw new Exception('BlockFlow image generation failed with status: ' . $result_data['status']);
            }

            $attempts++;
        }

        if (is_null($final_result)) {
            throw new Exception('BlockFlow image generation timed out.');
        }

        if (!isset($final_result['result']['sample'])) {
            throw new Exception('BlockFlow API response did not contain a final image URL.');
        }

        $image_download_url = $final_result['result']['sample'];

        // Download the temporary image to our server
        $ajax_handler = new ATM_Ajax();
        $attachment_id = $ajax_handler->set_image_from_url($image_download_url, 0);

        if (is_wp_error($attachment_id)) {
            throw new Exception('Failed to download the final image from BlockFlow: ' . $attachment_id->get_error_message());
        }

        // We can't return a URL, so we must return the actual image data.
        $image_path = get_attached_file($attachment_id);
        $image_data = file_get_contents($image_path);
        wp_delete_attachment($attachment_id, true); // Clean up the temporary media library entry

        return $image_data;
    }

public static function generate_chart_config_from_prompt($prompt) {
        $system_prompt = "You are an expert data visualization assistant specializing in Apache ECharts. Your task is to generate a valid ECharts JSON configuration object based on the user's request.

Follow these rules strictly:
1.  Analyze the user's prompt to determine the most appropriate chart type. Use your web search ability to find relevant and realistic sample data for the chart.
2.  The generated JSON object MUST be complete and ready to be passed directly to `echarts.init()`.
3.  The design must be modern and futuristic. Use gradients, subtle shadows, and a clean aesthetic suitable for a light theme. Ensure all text is easily readable with high contrast.
4.  Tooltips, a legend, and data zoom features should be enabled and configured appropriately.
5.  CRITICAL: Do NOT generate `map` or `geo` type charts, as they require external map data files. You can use any other chart type like `bar`, `line`, `pie`, `scatter`, `treemap`, `sunburst`, etc.
6.  Your entire response MUST be ONLY the valid JSON configuration object. Do not include any extra text, explanations, comments, or markdown code fences like ```json. The response must start with { and end with }.";

        // A powerful model is needed for this complex task.
        $model = 'openai/gpt-4o'; 
        
        // We must use json_mode to ensure the AI returns a valid object
        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $prompt],
            $system_prompt,
            $model,
            true, // Enable JSON mode
            true  // Enable web search
        );

        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Content AI Studio - Invalid JSON from Chart Generation API: ' . $raw_response);
            throw new Exception('The AI returned an invalid JSON response. Please try again.');
        }
        
        return $raw_response; // Return the raw JSON string
    }

    public static function get_youtube_autocomplete_suggestions($query) {
        $url = 'https://suggestqueries.google.com/complete/search?client=firefox&ds=yt&q=' . urlencode($query);
        $response = wp_remote_get($url);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            throw new Exception('Could not fetch autocomplete suggestions.');
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $suggestions = isset($json[1]) && is_array($json[1]) ? $json[1] : [];

        return $suggestions;
    }

    public static function search_youtube_videos($query, $filters = []) {
        $api_key = get_option('atm_google_youtube_api_key');
        if (empty($api_key)) {
            throw new Exception('YouTube API key is not configured in settings.');
        }

        $api_params = [
            'part' => 'snippet',
            'q' => $query,
            'maxResults' => 10,
            'type' => 'video',
            'key' => $api_key
        ];
        
        // Merge filters into the API parameters
        if (!empty($filters['order']) && $filters['order'] !== 'relevance') {
            $api_params['order'] = $filters['order'];
        }
        if (!empty($filters['videoDuration']) && $filters['videoDuration'] !== 'any') {
            $api_params['videoDuration'] = $filters['videoDuration'];
        }

        // Date filter logic
        if (!empty($filters['publishedAfter'])) {
            $interval_map = [
                'hour'  => '-1 hour',
                'day'   => '-1 day',
                'week'  => '-1 week',
                'month' => '-1 month',
                'year'  => '-1 year',
            ];
            
            if (isset($interval_map[$filters['publishedAfter']])) {
                $interval = $interval_map[$filters['publishedAfter']];
                $timestamp = current_time('timestamp');
                $past_timestamp = strtotime($interval, $timestamp);
                $api_params['publishedAfter'] = wp_date(DateTime::RFC3339, $past_timestamp);
            }
        }
        
        $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query($api_params);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            throw new Exception('YouTube API call failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'An unknown API error occurred.';
            throw new Exception('YouTube API Error: ' . $error_message);
        }
        
        $results = [];
        if (!empty($body['items'])) {
            foreach ($body['items'] as $item) {
                $results[] = [
                    'id' => $item['id']['videoId'],
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'],
                    'thumbnail' => $item['snippet']['thumbnails']['medium']['url'],
                    'channel' => $item['snippet']['channelTitle'],
                    'date' => date('M j, Y', strtotime($item['snippet']['publishedAt'])),
                    'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId']
                ];
            }
        }
        
        return $results;
    }

    public static function translate_text($text, $target_language) {
        $system_prompt = "You are an expert multilingual translator. Your task is to automatically detect the source language of the text provided by the user and then translate it accurately into " . $target_language . ". Provide ONLY the translated text, with no extra commentary, introductions, or quotation marks.";
        
        $model = get_option('atm_translation_model', 'anthropic/claude-3-haiku');
        
        return self::enhance_content_with_openrouter(
            ['content' => $text],
            $system_prompt,
            $model,
            false, // json_mode
            false  // enable_web_search
        );
    }

    public static function translate_document($title, $content, $target_language) {
        $system_prompt = "You are an expert multilingual translator. Your task is to translate a JSON object containing an article's 'title' and 'content'.
    - The user's input will be a JSON string.
    - You must automatically detect the source language.
    - Translate the 'title' and 'content' values into " . $target_language . ".
    - Preserve all Markdown formatting (like headings, lists, bold text) in the translated content.
    - Your response MUST be ONLY the translated JSON object, with no other text, comments, or markdown code fences. The JSON object must contain two keys: `translated_title` and `translated_content`.";

        $user_content = json_encode(['title' => $title, 'content' => $content]);
        
        $model = get_option('atm_translation_model', 'anthropic/claude-3-haiku');

        $raw_response = self::enhance_content_with_openrouter(
            ['content' => $user_content],
            $system_prompt,
            $model,
            true, // Enable JSON mode
            false // disable web search for translation
        );

        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['translated_title']) || !isset($result['translated_content'])) {
            error_log('Content AI Studio - Invalid JSON from translation API: ' . $raw_response);
            throw new Exception('The AI returned an invalid response structure during translation. Try using a "High Quality" model from the settings for better reliability.');
        }
        
        return $result;
    }

    public static function transcribe_audio_with_whisper($audio_file_path) {
        $api_key = get_option('atm_openai_api_key');
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured.');
        }
    
        if (!file_exists($audio_file_path) || !is_readable($audio_file_path)) {
            throw new Exception('Audio file not found or not readable.');
        }
    
        $file_content = file_get_contents($audio_file_path);
        
        if ($file_content === false) {
            throw new Exception('Could not read audio file.');
        }
    
        $boundary = 'FormBoundary' . uniqid();
        $data = '';
        
        // Add model field
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
        $data .= 'whisper-1' . "\r\n";
        
        // Add file field
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="file"; filename="audio.webm"' . "\r\n";
        $data .= 'Content-Type: audio/webm' . "\r\n\r\n";
        $data .= $file_content . "\r\n";
        
        $data .= '--' . $boundary . '--' . "\r\n";
    
        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $data,
            'timeout' => 180,
        ]);
    
        if (is_wp_error($response)) {
            throw new Exception('Whisper API call failed: ' . $response->get_error_message());
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $result = json_decode($response_body, true);
            $error_message = 'HTTP ' . $response_code;
            if (json_last_error() === JSON_ERROR_NONE && isset($result['error']['message'])) {
                $error_message .= ': ' . $result['error']['message'];
            } else {
                $error_message .= ': ' . $response_body;
            }
            throw new Exception('Whisper API Error: ' . $error_message);
        }
        
        $json_check = json_decode($response_body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json_check['text'])) {
            return trim($json_check['text']);
        }
    
        throw new Exception('Empty or invalid transcription received from Whisper API');
    }
    
    public static function get_elevenlabs_voices() {
        $api_key = get_option('atm_elevenlabs_api_key');
        if (empty($api_key)) {
            return []; // Return empty if no key
        }

        $transient_key = 'atm_elevenlabs_voices';
        $cached_voices = get_transient($transient_key);
        if ($cached_voices) {
            return $cached_voices;
        }

        $response = wp_remote_get('https://api.elevenlabs.io/v1/voices', [
            'headers' => ['xi-api-key' => $api_key],
            'timeout' => 20
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('ElevenLabs API Error: Failed to fetch voices.');
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $voices = [];
        if (isset($body['voices'])) {
            foreach ($body['voices'] as $voice) {
                $voices[$voice['voice_id']] = $voice['name'];
            }
        }

        set_transient($transient_key, $voices, 6 * HOUR_IN_SECONDS); // Cache for 6 hours
        return $voices;
    }

    public static function generate_audio_with_elevenlabs($script_chunk, $voice_id) {
        $api_key = get_option('atm_elevenlabs_api_key');
        if (empty($api_key)) {
            throw new Exception('ElevenLabs API key not configured');
        }

        $endpoint_url = 'https://api.elevenlabs.io/v1/text-to-speech/' . $voice_id;

        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'xi-api-key'   => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'audio/mpeg'
            ],
            'body'    => json_encode([
                'text'     => $script_chunk,
                'model_id' => 'eleven_multilingual_v2'
            ]),
            'timeout' => 300
        ]);

        if (is_wp_error($response)) {
            throw new Exception('ElevenLabs API call failed: ' . $response->get_error_message());
        }

        $audio_content = wp_remote_retrieve_body($response);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            $error_body = json_decode($audio_content, true);
            $error_message = isset($error_body['detail']['message']) ? $error_body['detail']['message'] : 'An unknown API error occurred.';
            error_log('ElevenLabs API Error: ' . $audio_content);
            throw new Exception('ElevenLabs API Error: ' . $error_message);
        }

        return $audio_content;
    }

    public static function generate_image_with_google_imagen($prompt, $size_override = '') {
        $api_key = get_option('atm_google_api_key');
        if (empty($api_key)) {
            throw new Exception('Google AI API key is not configured.');
        }

        $model = 'imagen-4.0-generate-001';
        $endpoint_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':predict';

        $request_body = [
            'instances' => [
                [
                    'prompt' => $prompt
                ]
            ],
            'parameters' => [
                'sampleCount' => 1
            ]
        ];

        // Handle aspect ratio
        if (!empty($size_override)) {
            switch ($size_override) {
                case '1024x1024':
                    $request_body['parameters']['aspectRatio'] = '1:1';
                    break;
                case '1792x1024':
                    $request_body['parameters']['aspectRatio'] = '16:9';
                    break;
                case '1024x1792':
                    $request_body['parameters']['aspectRatio'] = '9:16';
                    break;
            }
        }

        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'x-goog-api-key' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Google Imagen API call failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $result = json_decode($body, true);
            $error_message = 'HTTP ' . $response_code;
            if (isset($result['error']['message'])) {
                $error_message .= ': ' . $result['error']['message'];
            }
            throw new Exception('Google Imagen Error: ' . $error_message);
        }

        $result = json_decode($body, true);

        if (isset($result['predictions'][0]['bytesBase64Encoded'])) {
            return base64_decode($result['predictions'][0]['bytesBase64Encoded']);
        }

        $error_details = isset($result['error']['message']) ? $result['error']['message'] : 'The API response did not contain image data.';
        throw new Exception('Google Imagen Error: ' . $error_details);
    }

    private static function enhance_image_prompt($prompt) {
        if (strlen($prompt) < 50) {
            return $prompt . ', high quality, detailed, professional';
        }
        return $prompt;
    }

    public static function generate_image_with_openai($prompt, $size_override = '', $quality_override = '') {
        $api_key = get_option('atm_openai_api_key');
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured in settings.');
        }

        $enhanced_prompt = self::enhance_image_prompt($prompt);
        error_log("Enhanced DALL-E Prompt: " . $enhanced_prompt);

        $image_size = !empty($size_override) ? $size_override : get_option('atm_image_size', '1792x1024');
        $image_quality = !empty($quality_override) ? $quality_override : get_option('atm_image_quality', 'hd');

        $post_data = json_encode([
            'model'   => 'dall-e-3',
            'prompt'  => $enhanced_prompt,
            'n'       => 1,
            'size'    => $image_size,
            'quality' => $image_quality,
            'style'   => 'vivid'
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $post_data,
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Image generation failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $error_message = 'Invalid response from OpenAI API (Code: ' . $response_code . ')';
            if (isset($result['error']['message'])) {
                $error_message = 'API Error: ' . $result['error']['message'];
            }
            error_log('OpenAI Image API Error: ' . $body);
            throw new Exception($error_message);
        }

        if (!isset($result['data'][0]['url'])) {
            error_log('OpenAI Image API Error: Malformed success response. ' . $body);
            throw new Exception('Image generation succeeded but the response was invalid.');
        }

        return $result['data'][0]['url'];
    }

    public static function resolve_redirect_url($url) {
        $response = wp_remote_head($url, array('redirection' => 0));
        $response_code = wp_remote_retrieve_response_code($response);
        
        if (!is_wp_error($response) && in_array($response_code, [301, 302, 307, 308])) {
            $final_url = wp_remote_retrieve_header($response, 'location');
            if (!empty($final_url)) {
                return $final_url;
            }
        }

        return $url;
    }

    public static function parse_rss_feeds($feed_urls_string, $post_id, $keyword = '') {
        return ATM_RSS_Parser::parse_rss_feeds_advanced($feed_urls_string, $post_id, $keyword);
    }
    
    public static function search_rss_feeds($feed_urls_string, $keyword, $use_scraping = false) {
        return ATM_RSS_Parser::enhanced_search_feeds($feed_urls_string, $keyword, $use_scraping);
    }

    public static function prepare_content_for_podcast($post) {
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $content = strip_shortcodes($content);
        $content = preg_replace('/\s+/', ' ', trim($content));
        if (empty($content)) {
            return false;
        }
        return ['title' => $title, 'content' => $content];
    }

    public static function fetch_news($keyword, $source = 'newsapi', $force_fresh = false) {
        switch ($source) {
            case 'gnews':
                return self::fetch_news_from_gnews($keyword, $force_fresh);
            case 'guardian':
                return self::fetch_news_from_guardian($keyword, $force_fresh);
            case 'newsapi':
            default:
                return self::fetch_news_from_newsapi($keyword, $force_fresh);
        }
    }

    private static function fetch_news_from_gnews($keyword, $force_fresh = false) {
        $api_key = get_option('atm_gnews_api_key');
        if (empty($api_key)) throw new Exception('GNews API key is not configured.');

        $transient_key = 'atm_gnews_' . sanitize_title($keyword);
        if (!$force_fresh) {
            $cached = get_transient($transient_key);
            if ($cached) return $cached;
        } else {
            delete_transient($transient_key);
        }

        $url = 'https://gnews.io/api/v4/search?' . http_build_query([
            'q' => '"' . $keyword . '"',
            'in' => 'title',
            'max' => 5,
            'lang' => 'en',
            'sortby' => 'publishedAt',
            'token' => $api_key,
        ]);
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to connect to GNews API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('Error from GNews API: ' . ($result['message'] ?? 'Unknown error'));
        }
        
        if (empty($result['articles'])) return '';
        
        $context = "LATEST NEWS SNIPPETS (GNews.io):\n\n";
        foreach ($result['articles'] as $article) {
            $context .= "Source: " . esc_html($article['source']['name']) . "\n";
            $context .= "Title: " . esc_html($article['title']) . "\n";
            $context .= "Description: " . esc_html($article['description']) . "\n";
            $context .= "---\n";
        }

        set_transient($transient_key, $context, 2 * HOUR_IN_SECONDS);
        return $context;
    }

    private static function fetch_news_from_guardian($keyword, $force_fresh = false) {
        $api_key = get_option('atm_guardian_api_key');
        if (empty($api_key)) throw new Exception('The Guardian API key is not configured.');

        $transient_key = 'atm_guardian_' . sanitize_title($keyword);
        if (!$force_fresh) {
            $cached = get_transient($transient_key);
            if ($cached) return $cached;
        } else {
            delete_transient($transient_key);
        }

        $url = 'https://content.guardianapis.com/search?' . http_build_query([
            'q' => '"' . $keyword . '"',
            'order-by' => 'newest',
            'page-size' => 5,
            'show-fields' => 'trailText',
            'api-key' => $api_key,
        ]);

        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to connect to Guardian API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('Error from Guardian API: ' . ($result['message'] ?? 'Unknown error'));
        }
        
        if (empty($result['response']['results'])) return '';

        $context = "LATEST NEWS SNIPPETS (The Guardian):\n\n";
        foreach ($result['response']['results'] as $article) {
            $context .= "Source: The Guardian\n";
            $context .= "Title: " . esc_html($article['webTitle']) . "\n";
            $context .= "Description: " . esc_html(strip_tags($article['fields']['trailText'])) . "\n";
            $context .= "---\n";
        }

        set_transient($transient_key, $context, 2 * HOUR_IN_SECONDS);
        return $context;
    }

    public static function fetch_news_from_newsapi($keyword, $force_fresh = false) {
        $api_key = get_option('atm_news_api_key');
        if (empty($api_key)) {
            throw new Exception('News API key is not configured in settings.');
        }

        $transient_key = 'atm_newsapi_' . sanitize_title($keyword);

        if ($force_fresh) {
            delete_transient($transient_key);
        } else {
            $cached_news = get_transient($transient_key);
            if ($cached_news) {
                return $cached_news;
            }
        }

        $from_date = date('Y-m-d\TH:i:s', strtotime('-48 hours'));

        $url = 'https://newsapi.org/v2/everything?' . http_build_query([
            'qInTitle' => $keyword,
            'from' => $from_date,
            'sortBy' => 'publishedAt',
            'pageSize' => 5,
            'language' => 'en',
            'apiKey' => $api_key
        ]);

        $response = wp_remote_get($url, [
            'headers' => ['User-Agent' => get_bloginfo('name') . ' WordPress Plugin']
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to connect to News API: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200 || $result['status'] !== 'ok') {
            throw new Exception('Error from News API: ' . ($result['message'] ?? 'Unknown error'));
        }

        if (empty($result['articles'])) {
            return '';
        }

        $context = "LATEST NEWS SNIPPETS (NewsAPI.org):\n\n";
        foreach ($result['articles'] as $article) {
            $context .= "Source: " . esc_html($article['source']['name']) . "\n";
            $context .= "Title: " . esc_html($article['title']) . "\n";
            $context .= "Description: " . esc_html($article['description']) . "\n";
            $context .= "---\n";
        }
        
        set_transient($transient_key, $context, 2 * HOUR_IN_SECONDS);
        
        return $context;
    }

    public static function enhance_content_with_openrouter($input, $system_prompt, $model, $json_mode = false, $enable_web_search = true, $creativity_level = 'medium') {
        $api_key = trim(get_option('atm_openrouter_api_key', ''));
        if (!$api_key) {
            throw new Exception('OpenRouter API key is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
            ],
        ];

        // Allow $input as string OR array/object (we often pass arrays)
        if (is_array($input) || is_object($input)) {
            $payload['messages'][] = ['role' => 'user', 'content' => wp_json_encode($input, JSON_UNESCAPED_SLASHES)];
        } else {
            $payload['messages'][] = ['role' => 'user', 'content' => (string) $input];
        }

        // Dynamic temperature based on creativity level and time
        $base_temperature = 0.8;
        $time_modifier = (time() % 100) / 100; // Adds 0.0-0.99 variation
        
        switch ($creativity_level) {
            case 'high':
                $temperature = min(1.4, $base_temperature + 0.4 + ($time_modifier * 0.2));
                $top_p = 0.95;
                break;
            case 'medium':
                $temperature = $base_temperature + ($time_modifier * 0.3);
                $top_p = 0.9;
                break;
            case 'low':
                $temperature = max(0.3, $base_temperature - 0.2 + ($time_modifier * 0.1));
                $top_p = 0.8;
                break;
            default:
                $temperature = $base_temperature + ($time_modifier * 0.3);
                $top_p = 0.9;
        }
        
        $payload['temperature'] = $temperature;
        $payload['top_p'] = $top_p;
        $payload['frequency_penalty'] = 0.1; // Reduce repetition
        $payload['presence_penalty'] = 0.1;  // Encourage new topics

        // Strict JSON mode if requested
        if ($json_mode) {
            $payload['response_format'] = [ 'type' => 'json_object' ];
        }

        // Web search plugin
        if ($enable_web_search) {
            $max_results = intval(get_option('atm_web_search_results', 5));
            $max_results = max(1, min(30, $max_results));
            
            $payload['plugins'] = [
                [
                    'id' => 'web',
                    'config' => [
                        'max_results' => $max_results,  // Use the correct variable name
                        'search_prompt' => 'Find the most recent and relevant information from ' . date('Y') . ' about: {query}. Focus on current events and latest developments.'
                    ]
                ]
            ];
            
            error_log("ATM: Using web search with max_results: " . $max_results);
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name'),
            ],
            'timeout' => 120,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            throw new Exception('OpenRouter error: ' . $body);
        }

        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['choices'][0]['message']['content'])) {
            throw new Exception('OpenRouter returned an invalid response.');
        }

        return $json['choices'][0]['message']['content'];
    }

    public static function fetch_headlines_from_serpapi($country_code, $language_code, $keyword = '') {
        $api_key = get_option('atm_serpapi_key');
        if (empty($api_key)) throw new Exception('SerpApi key is not configured.');

        $params = [
            'engine' => 'google_news',
            'gl' => $country_code,
            'hl' => $language_code,
            'api_key' => $api_key
        ];

        if (!empty($keyword)) {
            $params['q'] = $keyword;
        }

        $url = 'https://serpapi.com/search.json?' . http_build_query($params);

        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            throw new Exception('SerpApi request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (empty($result['news_results'])) return [];
        
        $headlines = [];
        foreach ($result['news_results'] as $article) {
            // Quality filter - skip results without proper source name or date
            if (empty($article['source']['name']) || empty($article['date'])) {
                continue;
            }

            $source_name = $article['source']['name'];
            
            $headlines[] = [
                'title' => esc_html($article['title']),
                'link' => esc_url($article['link']),
                'source' => esc_html($source_name),
                'date' => esc_html($article['date'])
            ];
            
            // Stop once we have 5 high-quality headlines
            if (count($headlines) >= 5) {
                break;
            }
        }
        return $headlines;
    }

    public static function fetch_full_article_content($url) {
        // Set reasonable timeout for the entire operation
        $start_time = time();
        $max_total_time = 60; // 1 minute total maximum
        
        $extraction_methods = [
            'jina' => [
                'function' => 'fetch_article_with_jina',
                'timeout' => 15,
                'description' => 'Jina Reader API'
            ],
            'native_readability' => [
                'function' => 'extract_with_wordpress_native',
                'timeout' => 20,
                'description' => 'WordPress Native + Readability'
            ],
            'mercury' => [
                'function' => 'fetch_article_with_mercury',
                'timeout' => 15,
                'description' => 'Mercury Parser',
                'skip_if_no_key' => true
            ],
            'basic_scraping' => [
                'function' => 'extract_with_basic_scraping',
                'timeout' => 10,
                'description' => 'Basic HTML Extraction'
            ]
        ];
        
        $last_error = '';
        
        foreach ($extraction_methods as $method_name => $config) {
            // Check if we've exceeded total time limit
            if ((time() - $start_time) > $max_total_time) {
                error_log("ATM: Extraction timeout reached, stopping attempts");
                break;
            }
            
            // Skip methods that require API keys if not configured
            if (!empty($config['skip_if_no_key'])) {
                $api_key = get_option('atm_mercury_api_key');
                if (empty($api_key)) {
                    error_log("ATM: Skipping {$method_name} - no API key configured");
                    continue;
                }
            }
            
            try {
                error_log("ATM: Attempting {$config['description']} extraction for " . parse_url($url, PHP_URL_HOST));
                
                $content = self::{$config['function']}($url, $config['timeout']);
                
                if (strlen($content) > 200) {
                    $word_count = str_word_count($content);
                    error_log("ATM: SUCCESS with {$config['description']} - extracted {$word_count} words");
                    return $content;
                } else {
                    throw new Exception("Content too short: " . strlen($content) . " characters");
                }
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                error_log("ATM: {$config['description']} failed: " . $last_error);
                
                // Add small delay between attempts to be respectful
                if (next($extraction_methods)) {
                    sleep(1);
                }
                continue;
            }
        }
        
        throw new Exception("All extraction methods failed. Last error: " . $last_error);
    }

    // Jina Reader (Free, Fast)
    private static function fetch_article_with_jina($url, $timeout = 15) {
        $jina_url = 'https://r.jina.ai/' . $url;
        
        $response = wp_remote_get($jina_url, [
            'timeout' => $timeout,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; ContentAIStudio/1.0)',
                'Accept' => 'text/plain'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Jina request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception("Jina returned status code: " . $status_code);
        }

        $content = wp_remote_retrieve_body($response);
        
        if (strlen($content) < 100) {
            throw new Exception('Jina returned insufficient content');
        }

        return trim($content);
    }

    // WordPress Native with improved extraction
    private static function extract_with_wordpress_native($url, $timeout = 20) {
        $response = wp_remote_get($url, [
            'timeout' => $timeout,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception("HTTP status code: " . $status_code);
        }

        $html = wp_remote_retrieve_body($response);
        
        // Clean HTML
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html);
        $html = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $html);
        $html = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html);
        $html = preg_replace('/<aside\b[^>]*>(.*?)<\/aside>/is', '', $html);
        
        // Try multiple extraction strategies
        $strategies = [
            'article_tag' => '/<article[^>]*>(.*?)<\/article>/is',
            'main_tag' => '/<main[^>]*>(.*?)<\/main>/is',
            'content_class' => '/<div[^>]*class="[^"]*(?:content|article|post|entry)[^"]*"[^>]*>(.*?)<\/div>/is',
            'content_id' => '/<div[^>]*id="[^"]*(?:content|article|post|entry)[^"]*"[^>]*>(.*?)<\/div>/is'
        ];
        
        foreach ($strategies as $strategy_name => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $content = wp_strip_all_tags($matches[1]);
                $content = preg_replace('/\s+/', ' ', trim($content));
                
                if (strlen($content) > 200) {
                    return $content;
                }
            }
        }
        
        throw new Exception('No content found with native extraction');
    }

    // Mercury Parser (if API key available)
    private static function fetch_article_with_mercury($url, $timeout = 15) {
        $api_key = get_option('atm_mercury_api_key');
        if (empty($api_key)) {
            throw new Exception('Mercury API key not configured');
        }
        
        $api_url = 'https://mercury.postlight.com/parser?url=' . urlencode($url);
        
        $response = wp_remote_get($api_url, [
            'timeout' => $timeout,
            'headers' => [
                'x-api-key' => $api_key,
                'User-Agent' => 'Mozilla/5.0 (compatible; ContentAIStudio/1.0)'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Mercury request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['content']) || empty($data['content'])) {
            throw new Exception('Mercury returned no content');
        }

        return wp_strip_all_tags($data['content']);
    }

    // Basic fallback
    private static function extract_with_basic_scraping($url, $timeout = 10) {
        $response = wp_remote_get($url, ['timeout' => $timeout]);
        
        if (is_wp_error($response)) {
            throw new Exception('Basic request failed: ' . $response->get_error_message());
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Very basic paragraph extraction
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $paragraphs);
        
        $content = '';
        foreach ($paragraphs[1] as $p) {
            $text = wp_strip_all_tags($p);
            if (strlen(trim($text)) > 50) {
                $content .= $text . "\n\n";
            }
        }
        
        if (strlen($content) < 200) {
            throw new Exception('Basic extraction found insufficient content');
        }
        
        return trim($content);
    }
    
    public static function generate_audio_with_openai_tts($script_chunk, $voice) {
        $api_key = get_option('atm_openai_api_key');
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $response = wp_remote_post('https://api.openai.com/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'tts-1',
                'input' => $script_chunk,
                'voice' => $voice,
                'response_format' => 'mp3'
            ]),
            'timeout' => 300
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Audio generation failed: ' . $response->get_error_message());
        }

        $audio_content = wp_remote_retrieve_body($response);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            $error_body = json_decode($audio_content, true);
            $error_message = 'An unknown API error occurred.';
            if (isset($error_body['error']['message'])) {
                $error_message = $error_body['error']['message'];
            }
            error_log('OpenAI TTS Error: ' . $audio_content);
            throw new Exception('OpenAI TTS API Error: ' . $error_message);
        }

        return $audio_content;
    }

    public static function generate_two_person_podcast_audio($script, $voice_a, $voice_b, $provider = 'openai') {
        // Add validation at the start
        if (empty($script) || empty($voice_a) || empty($voice_b)) {
            throw new Exception('Invalid parameters: script, voice_a, and voice_b are required');
        }
        
        // Parse the script to separate HOST_A and HOST_B lines
        $audio_segments = self::parse_podcast_script($script);
        
        // Add validation for parsed segments
        if (empty($audio_segments)) {
            error_log("ATM: Script parsing failed. Script preview: " . substr($script, 0, 500));
            throw new Exception('No valid audio segments found in script. Please check script format.');
        }
        
        error_log("ATM: Successfully parsed " . count($audio_segments) . " audio segments");
        
        $final_audio_parts = [];

        foreach ($audio_segments as $segment) {
            $voice = ($segment['speaker'] === 'HOST_A') ? $voice_a : $voice_b;
            $text = $segment['text'];
            
            // Clean up emotions and stage directions for TTS
            $clean_text = preg_replace('/\[([^\]]+)\]/', '', $text);
            $clean_text = trim($clean_text);
            
            if (empty($clean_text)) continue;

            // Split long segments into smaller chunks (OpenAI limit is 4096 chars)
            $max_chunk_size = $provider === 'openai' ? 3500 : 4000; // A conservative limit
            $text_chunks = self::split_text_into_chunks($clean_text, $max_chunk_size);
            
            foreach ($text_chunks as $chunk) {
                if (empty(trim($chunk))) continue;
                
                try {
                    // Log the chunk size for debugging
                    error_log('Content AI Studio - Processing chunk of ' . strlen($chunk) . ' characters');
                    
                    // Generate audio for this chunk
                    if ($provider === 'elevenlabs') {
                        $segment_audio = self::generate_audio_with_elevenlabs($chunk, $voice);
                    } else {
                        $segment_audio = self::generate_audio_with_openai_tts($chunk, $voice);
                    }
                    
                    $final_audio_parts[] = $segment_audio;
                    
                    // Add small pause between chunks from the same speaker
                    if (count($text_chunks) > 1) {
                        $final_audio_parts[] = self::generate_silence(200); // 200ms
                    }
                    
                } catch (Exception $e) {
                    error_log('Content AI Studio - Audio generation error for chunk: ' . $e->getMessage());
                    // Continue with the next chunk instead of failing completely
                    continue;
                }
            }
            
            // Add a pause between different speakers
            if (isset($segment['add_pause']) && $segment['add_pause']) {
                $final_audio_parts[] = self::generate_silence(500); // 500ms
            }
        }

        // Combine all audio parts into the final MP3 file content
        return implode('', $final_audio_parts);
    }

    private static function split_text_into_chunks($text, $max_length) {
        if (strlen($text) <= $max_length) {
            return [$text];
        }
        
        $chunks = [];
        
        // First try to split by sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $current_chunk = '';
        
        foreach ($sentences as $sentence) {
            $test_chunk = $current_chunk . ($current_chunk ? ' ' : '') . $sentence;
            
            if (strlen($test_chunk) > $max_length) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $sentence;
                    
                    // If single sentence is still too long, split by words
                    if (strlen($current_chunk) > $max_length) {
                        $word_chunks = self::split_by_words($current_chunk, $max_length);
                        $chunks = array_merge($chunks, array_slice($word_chunks, 0, -1));
                        $current_chunk = end($word_chunks);
                    }
                } else {
                    // Single sentence too long, split by words
                    $word_chunks = self::split_by_words($sentence, $max_length);
                    $chunks = array_merge($chunks, $word_chunks);
                    $current_chunk = '';
                }
            } else {
                $current_chunk = $test_chunk;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }
        
        return array_filter($chunks); // Remove empty chunks
    }

    private static function split_by_words($text, $max_length) {
        $chunks = [];
        $words = explode(' ', $text);
        $current_chunk = '';
        
        foreach ($words as $word) {
            $test_chunk = $current_chunk . ($current_chunk ? ' ' : '') . $word;
            
            if (strlen($test_chunk) > $max_length) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $word;
                } else {
                    // Single word is too long, force split
                    $chunks[] = substr($word, 0, $max_length);
                    $current_chunk = substr($word, $max_length);
                }
            } else {
                $current_chunk = $test_chunk;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }

    private static function parse_podcast_script($script) {
    $lines = explode("\n", trim($script));
    $segments = [];
    $current_segment = null;

    // Add debug logging to see what we're working with
    error_log("ATM: Parsing script with " . count($lines) . " lines. First 5 lines:");
    for ($i = 0; $i < min(5, count($lines)); $i++) {
        error_log("ATM: Line $i: " . trim($lines[$i]));
    }

    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Enhanced speaker detection - more flexible patterns
        $speaker_patterns = [
        '/^\*\*(ALEX|JORDAN|HOST_A|HOST_B)\s*[A-Z\s]*\*\*:\s*(.+)/i',  // **ALEX:** or **JORDAN:**
        '/^(ALEX|HOST_A):\s*(.+)/i',           // ALEX: or HOST_A:
        '/^(JORDAN|HOST_B):\s*(.+)/i',         // JORDAN: or HOST_B:
        '/^(Host\s*A|Host\s*1):\s*(.+)/i',     // Host A: or Host 1:
        '/^(Host\s*B|Host\s*2):\s*(.+)/i',     // Host B: or Host 2:
        '/^([A-Z][A-Z\s]{2,15}):\s*(.+)/',     // Any CAPS NAME: (3-16 chars)
    ];

        $matched = false;
        foreach ($speaker_patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                $speaker_raw = trim($matches[1]);
                $text = trim($matches[2]);
                
                // Normalize speaker names
                $speaker = self::normalize_speaker_name($speaker_raw);
                
                // If there's a current segment being built, save it first
                if ($current_segment) {
                    $segments[] = $current_segment;
                }

                // Determine if this is a new speaker (for pause insertion)
                $is_new_speaker = empty($segments) || (end($segments) && end($segments)['speaker'] !== $speaker);

                // Start a new segment
                $current_segment = [
                    'speaker' => $speaker,
                    'text' => $text,
                    'add_pause' => $is_new_speaker,
                ];
                
                $matched = true;
                error_log("ATM: Found speaker '$speaker_raw' -> '$speaker' with text: " . substr($text, 0, 50) . "...");
                break;
            }
        }

        // If no speaker pattern matched, this might be a continuation line
        if (!$matched && $current_segment) {
            // Skip lines that look like stage directions or section headers
            if (!self::is_stage_direction($line)) {
                $current_segment['text'] .= ' ' . $line;
            }
        }
    }

    // Add the very last segment after the loop finishes
    if ($current_segment) {
        $segments[] = $current_segment;
    }

    error_log("ATM: Parsed " . count($segments) . " segments from script");
    
    // Log first few segments for debugging
    foreach (array_slice($segments, 0, 3) as $i => $segment) {
        error_log("ATM: Segment $i - Speaker: {$segment['speaker']}, Text: " . substr($segment['text'], 0, 100) . "...");
    }

    return $segments;
}

/**
 * Normalize speaker names to consistent format
 */
private static function normalize_speaker_name($speaker_raw) {
    $speaker = strtoupper(trim($speaker_raw));
    
    // Normalize variations to standard format
    $mappings = [
        'ALEX' => 'HOST_A',
        'ALEX CHEN' => 'HOST_A', 
        'HOST A' => 'HOST_A',
        'HOST 1' => 'HOST_A',
        'JORDAN' => 'HOST_B',
        'JORDAN RIVERA' => 'HOST_B',
        'HOST B' => 'HOST_B', 
        'HOST 2' => 'HOST_B',
    ];
    
    return $mappings[$speaker] ?? $speaker;
}

/**
 * Check if a line is a stage direction that should be ignored
 */
private static function is_stage_direction($line) {
    $stage_patterns = [
        '/^\[.*\]$/',                    // [stage direction]
        '/^\(.*\)$/',                    // (stage direction)  
        '/^-{3,}/',                      // --- dividers
        '/^={3,}/',                      // === dividers
        '/^\*{3,}/',                     // *** dividers
        '/^##/',                         // ## headers
        '/^INTRO/',                      // INTRO/OUTRO sections
        '/^OUTRO/',
        '/^CONCLUSION/',
        '/^BACKGROUND/',
        '/^MAIN DISCUSSION/',
        '/^\d+\.\s*/',                   // 1. numbered sections
    ];
    
    foreach ($stage_patterns as $pattern) {
        if (preg_match($pattern, trim($line))) {
            return true;
        }
    }
    
    return false;
}

    private static function generate_silence($milliseconds) {
        // Generate brief silence - this is a simplified implementation
        // For a more sophisticated approach, you'd generate actual audio silence
        $silence_length = intval($milliseconds * 0.044); // Rough approximation for audio data
        return str_repeat(chr(0), $silence_length);
    }
    
    private static function save_audio_file($audio_content, $post_id, $extension) {
        $silent_intro_base64 = 'SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGllbmRhcmQgTG9wZXogaW4gT25lVHJpY2sBTQuelleAAAAANFaAAAAAAAAAAAAAAAAAAAAD/8AAAAAAAADw==';
        $final_audio_content = base64_decode($silent_intro_base64) . $audio_content;

        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/podcasts';
        if (!file_exists($podcast_dir)) wp_mkdir_p($podcast_dir);
        
        $filename = 'podcast-' . $post_id . '-' . time() . '.' . $extension;
        $filepath = $podcast_dir . '/' . $filename;
        
        if (!file_put_contents($filepath, $final_audio_content)) throw new Exception('Failed to save audio file');
        
        return $upload_dir['baseurl'] . '/podcasts/' . $filename;
    }

    public static function get_translated_prompt($language, $master_prompt) {
        if (strtolower($language) === 'english') return $master_prompt;

        $api_key = get_option('atm_openrouter_api_key');
        if (empty($api_key)) throw new Exception('OpenRouter API key not configured for translation');

        $translation_instruction = "You are an expert translator. Translate the following text into " . $language . ". Return ONLY the translated text, with no extra commentary, introductions, or quotation marks. The text to translate is below:\n\n" . $master_prompt;
        
        return self::enhance_content_with_openrouter(
            ['content' => $translation_instruction], 
            'You are a helpful assistant.', 
            'anthropic/claude-3-haiku',
            false, // json_mode
            false  // enable_web_search
        );
    }

    public static function replace_prompt_shortcodes($template, $post) {
        $site_name = get_bloginfo('name');
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $podcast_name = $site_name . ' Podcast';

        $shortcodes = [
            '[podcast_name]'  => esc_html($podcast_name),
            '[article_title]' => esc_html($post->post_title),
            '[site_name]'     => esc_html($site_name),
            '[site_url]'      => esc_html($site_url)
        ];

        return str_replace(array_keys($shortcodes), array_values($shortcodes), $template);
    }

    public static function get_default_master_prompt() {
        return 'You are an expert podcast host for "[podcast_name]". Your task is to transform the provided article into a compelling, insightful, and engaging podcast script. Your persona is an insightful, curious, and knowledgeable host with a conversational, authoritative, yet approachable tone. The script must sound like a human speaking naturally, not reading a script. Your script must follow this exact structure: First, for the hook which should be 15-20 seconds, you will start with a compelling question, a surprising fact, or a bold statement from the article to grab the listener\'s attention immediately. Next, for the introduction of 30-45 seconds, you will welcome listeners to "[podcast_name]." Then, briefly introduce the topic based on the article title: "[article_title]", and explain why this topic is important or relevant right now. Then, for the main discussion of 3-4 minutes, you will break down the article\'s main arguments into 2-3 key discussion points. For each point, first summarize the idea from the article. Then, you must add your own analysis by discussing the implications, offering a different perspective, or connecting it to a broader context. Use phrases like "What\'s really interesting here is...", "But if we look deeper...", or "This reminds me of...". You must use smooth transitions between points and ask rhetorical questions to keep the listener engaged. After the discussion, for the conclusion of about 30 seconds, you will summarize the key ideas and offer a final, thought-provoking statement. Finally, you must end the entire podcast script with this exact phrase, which should be about 20 seconds long: "That\'s all the time we have for today on \'[podcast_name]\'. Thanks for tuning in. For more content, visit [site_url]." Do not include any headings like "Introduction" or "Conclusion" or any text like "[Intro Music]" in your output. Just provide the raw, speakable script. The total speaking time should be approximately 4-5 minutes. Now, transform the following article into your podcast script.';
    }

    public static function get_default_article_prompt() {
        return 'You are an expert content creator specializing in [article_type] articles for a target audience in [country]. Your task is to generate a complete, high-quality article about the topic "[keyword]".

    The output MUST be a single, valid JSON object. This JSON object must have three keys:
    1. "title": A compelling, SEO-friendly title for the article.
    2. "subheadline": An engaging one-sentence subtitle that complements the main title.
    3. "content": A string containing the full article content, formatted using clean HTML.

    CRITICAL CONTENT RULES:
    - The `content` must begin directly with the first paragraph (the introduction), NOT with a heading.
    - Do NOT include a final heading like <h2>Conclusion</h2>. The article should end with the concluding paragraph itself.
    - Structure the article with appropriate <h2> and <h3> headings. Do not use <h1>.
    - Include at least one relevant external link using <a href="...">...</a>.
    - Your entire response must be ONLY the valid JSON object itself.

    Do not include any text, explanations, or markdown code fences outside of the JSON object. Your entire response must be only the JSON object itself, starting with { and ending with }. The `content` must NOT contain any top-level <h1> headings.';
    }

    public static function get_writing_styles() {
        $base_instructions = 'Your task is to generate a complete article based on the provided title, using your web search ability to ensure the information is up-to-date and factual. The content must be well-structured and formatted using Markdown (headings, lists, bold text). When you cite an external source, you MUST format it as a natural, contextual Markdown hyperlink. The anchor text for the link should be a relevant keyword or phrase (e.g., "a recent study showed that..."), not just the website\'s name. **IMPORTANT: Do not repeat the article title in the content of your response.**';

        return [
            'default_seo' => [
                'label' => 'Standard / SEO-Optimized',
                'prompt' => 'You are an expert SEO content writer. ' . $base_instructions
            ],
            'formal_academic' => [
                'label' => 'Formal / Academic',
                'prompt' => 'You are an academic writer. Adopt a serious, objective, and precise tone. Use complex sentence structures and avoid contractions. ' . $base_instructions
            ],
            'informal_conversational' => [
                'label' => 'Informal / Conversational',
                'prompt' => 'You are a friendly blogger. Adopt an engaging and approachable tone. Use shorter sentences, contractions, and address the reader directly using "you". Ask rhetorical questions to connect with the reader. ' . $base_instructions
            ],
            'journalistic_news' => [
                'label' => 'Journalistic / News Style',
                'prompt' => 'You are a professional journalist. Adopt an objective, fact-based tone. Structure the article using the "inverted pyramid" style, with the most important information first. Use short paragraphs. ' . $base_instructions
            ],
            'persuasive_marketing' => [
                'label' => 'Persuasive / Marketing',
                'prompt' => 'You are a persuasive copywriter. Adopt an energetic and convincing tone. Use benefit-focused language and include a clear call-to-action (CTA) at the end. ' . $base_instructions
            ],
            'storytelling_narrative' => [
                'label' => 'Storytelling / Narrative',
                'prompt' => 'You are a master storyteller. Adopt an emotional, descriptive, and immersive tone. Use strong imagery and pacing to tell a compelling story related to the title. ' . $base_instructions
            ],
            'technical_instructional' => [
                'label' => 'Technical / Instructional',
                'prompt' => 'You are a technical writer creating a guide. Adopt a clear, precise, and step-by-step tone. Use numbered or bulleted lists for instructions and avoid figurative language. ' . $base_instructions
            ],
            'analytical_expository' => [
                'label' => 'Analytical / Expository',
                'prompt' => 'You are a professional analyst. Adopt a logical, structured, and explanatory tone. Break down the complex topic into smaller, easy-to-understand parts, focusing on data and comparisons. ' . $base_instructions
            ]
        ];
    }

    /**
     * Generate news-specific image prompt optimized for different providers
     */
    public static function get_news_image_prompt($article_title) {
        $provider = get_option('atm_image_provider', 'openai');
        
        if ($provider === 'google') {
            // Simpler prompt for Google Imagen
            return "Professional news photograph about: {$article_title}. Realistic, high quality, natural lighting, documentary style journalism photo.";
        } else {
            // More detailed prompt for other providers
            return "Create a realistic, documentary-style photograph for a news article about: \"{$article_title}\". 

    REQUIREMENTS:
    - Style: Authentic photojournalism, NOT cinematic or dramatic
    - Lighting: Natural daylight or standard indoor lighting
    - Composition: Clear, straightforward, informative
    - Quality: High resolution, sharp focus
    - Tone: Professional, neutral, factual

    AVOID:
    - Dramatic lighting or effects
    - Cinematic composition
    - Overly artistic interpretation
    - Dark or moody atmosphere
    - Special effects or unrealistic elements

    The image should look like professional news photography from Reuters or AP.";
        }
    }

    public static function get_default_image_prompt() {
        return 'Create a highly realistic, professional-quality photograph to be used as a featured image for a news/blog article titled "[article_title]". The image should be directly relevant to the subject of the title, visually meaningful, and look like authentic editorial photography. Use cinematic lighting, sharp focus, and a natural color palette. Avoid text, watermarks, or artificial-looking elements. Maintain a serious and credible journalistic style suitable for a news website.';
    }
}

/**
 * Helper function to force-refresh the feed cache.
 * This is hooked temporarily by parse_rss_feeds().
 */
function atm_force_feed_refresh($feed) {
    $feed->force_feed(true);
    $feed->enable_cache(false);
}