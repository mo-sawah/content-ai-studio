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
        'anthropic/claude-3-haiku' 
    );
}
    
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
     * Fetch RSS content with cURL for better control
     */
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
        
        // ...
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

        // --- FIX: Add fallback to use GUID if link is missing ---
        if (empty($link) && filter_var($guid, FILTER_VALIDATE_URL)) {
            $link = $guid;
        }
        // --- END FIX ---
        // ...
        
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

    /**
     * Resolves a redirect URL to find its final destination.
     *
     * @param string $url The initial URL to check.
     * @return string The final destination URL, or the original URL if not a redirect.
     */

    /**
 * Enhances a simple image prompt into a detailed one, like ChatGPT.
 */
public static function generate_image_with_google_imagen($prompt, $size_override = '') {
    $api_key = get_option('atm_google_api_key');
    if (empty($api_key)) {
        throw new Exception('Google AI API key is not configured.');
    }

    // CORRECT: Use the :predict endpoint for the specific Imagen model
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
            'x-goog-api-key' => $api_key, // Use the correct header for this endpoint
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

    // Add a fallback for unexpected responses
    $error_details = isset($result['error']['message']) ? $result['error']['message'] : 'The API response did not contain image data.';
    throw new Exception('Google Imagen Error: ' . $error_details);
}

// STEP 2: Add this helper function for prompt enhancement (optional but recommended)
private static function enhance_image_prompt($prompt) {
    // Simple prompt enhancement - you can make this more sophisticated
    if (strlen($prompt) < 50) {
        return $prompt . ', high quality, detailed, professional';
    }
    return $prompt;
}

// STEP 3: Update your generate_image_with_openai function to use prompt enhancement too
public static function generate_image_with_openai($prompt, $size_override = '', $quality_override = '') {
    $api_key = get_option('atm_openai_api_key');
    if (empty($api_key)) {
        throw new Exception('OpenAI API key not configured in settings.');
    }

    // Enhance the prompt for better results
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
        // Use wp_remote_head for efficiency as we only need headers.
        // 'redirection' => 0 prevents WordPress from following the redirect automatically.
        $response = wp_remote_head($url, array('redirection' => 0));

        // Check if the request was successful and returned a redirect status code.
        $response_code = wp_remote_retrieve_response_code($response);
        if (!is_wp_error($response) && in_array($response_code, [301, 302, 307, 308])) {
            // Retrieve the 'Location' header which contains the destination URL.
            $final_url = wp_remote_retrieve_header($response, 'location');
            
            // If we found a new URL, return it.
            if (!empty($final_url)) {
                return $final_url;
            }
        }

        // If it's not a redirect or something went wrong, return the original URL.
        return $url;
    }

    /**
     * Updated RSS parsing method that uses the advanced parser
     */
    public static function parse_rss_feeds($feed_urls_string, $post_id, $keyword = '') {
        return ATM_RSS_Parser::parse_rss_feeds_advanced($feed_urls_string, $post_id, $keyword);
    }
    
    /**
     * New method for enhanced RSS search
     */
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

    // New Dispatcher Function
    public static function fetch_news($keyword, $source = 'newsapi', $force_fresh = false) {
        switch ($source) {
            case 'gnews':
                return self::fetch_news_from_gnews($keyword, $force_fresh);
            case 'guardian':
                return self::fetch_news_from_guardian($keyword, $force_fresh);
            case 'newsapi':
            default:
                // This function should already be in your file, renamed and updated
                return self::fetch_news_from_newsapi($keyword, $force_fresh);
        }
    }

    // New Function for GNews.io
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
            'q' => '"' . $keyword . '"', // Use quotes for exact phrase matching
            'in' => 'title',
            'max' => 5,
            'lang' => 'en',
            'sortby' => 'publishedAt',
            'token' => $api_key,
        ]);
        
        $response = wp_remote_get($url); // GNews doesn't require User-Agent
        // ... (Error handling similar to newsapi) ...
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
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

    // New Function for The Guardian
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
            'show-fields' => 'trailText', // trailText is like a description
            'api-key' => $api_key,
        ]);

        $response = wp_remote_get($url);
        // ... (Error handling similar to newsapi) ...
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
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

    // Renamed and updated to fetch news from the last 48 hours
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

        // 1. Switched back to the /everything endpoint
        // 2. Added a 'from' date to specify the 48-hour window
        $from_date = date('Y-m-d\TH:i:s', strtotime('-48 hours'));

        $url = 'https://newsapi.org/v2/everything?' . http_build_query([
            'qInTitle' => $keyword, // Use qInTitle for strong relevance
            'from' => $from_date,
            'sortBy' => 'publishedAt', // Get the newest first within the window
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

    /**
     * Enhance content using OpenRouter, with an option for forced JSON output.
     *
     * @param array $content_data The content to be processed.
     * @param string $system_prompt The system prompt for the AI.
     * @param string $model_override Optional model override.
     * @param bool $json_mode If true, requests JSON output from the API.
     * @return string The AI's response.
     * @throws Exception On API error.
     */
    public static function enhance_content_with_openrouter($content_data, $system_prompt, $model_override = '', $json_mode = false) {
        $api_key = get_option('atm_openrouter_api_key');
        $model = !empty($model_override) ? $model_override : get_option('atm_content_model', 'anthropic/claude-3-haiku');

        if (empty($api_key)) throw new Exception('OpenRouter API key not configured');

        $body_data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $content_data['content']]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.7
        ];

        // **SOLUTION**: Add the response_format parameter to enforce JSON output when requested.
        // This is the most reliable way to fix the article generation error.
        if ($json_mode) {
            $body_data['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ],
            'body' => json_encode($body_data),
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) throw new Exception('Content enhancement failed: ' . $response->get_error_message());
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200 || !isset($result['choices'][0]['message']['content'])) {
            error_log('OpenRouter API Error: ' . $body);
            throw new Exception('Invalid response from content enhancement API. Raw response: ' . $body);
        }
        return $result['choices'][0]['message']['content'];
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
        // ... (Error handling) ...
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (empty($result['news_results'])) return [];
        
        $headlines = [];
        foreach ($result['news_results'] as $article) {
            // --- NEW QUALITY FILTER ---
            // 1. Skip any result that doesn't have a clear source name OR a date.
            // This effectively filters out the generic topic pages.
            if (empty($article['source']['name']) || empty($article['date'])) {
                continue;
            }

            $source_name = $article['source']['name'];
            
            $headlines[] = [
                'title' => esc_html($article['title']),
                'link' => esc_url($article['link']),
                'source' => esc_html($source_name),
                'date' => esc_html($article['date']) // 2. Capture the date
            ];
            
            // Stop once we have 5 high-quality headlines
            if (count($headlines) >= 5) {
                break;
            }
        }
        return $headlines;
    }

    public static function fetch_full_article_content($url) {
        $api_key = get_option('atm_scrapingant_api_key');

        if (empty($api_key)) {
            throw new Exception('ScrapingAnt API key is not configured in settings. This is required for the RSS feature.');
        }

        $scraper_url = 'https://api.scrapingant.com/v2/general?' . http_build_query([
            'url' => $url,
            'x-api-key' => $api_key,
            'browser' => 'true'
        ]);
        
        $response = wp_remote_get($scraper_url, ['timeout' => 120]);

        // --- NEW: Improved Error Handling ---
        if (is_wp_error($response)) {
            // This catches server-level connection errors (like a firewall block).
            throw new Exception('Scraping API Connection Error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            // This catches API-level errors (like a bad key or quota limit).
            $error_data = json_decode($body, true);
            $error_detail = isset($error_data['detail']) ? $error_data['detail'] : 'Please check your ScrapingAnt account.';
            throw new Exception('Scraping API Error (Code: ' . $response_code . '): ' . $error_detail);
        }
        // --- END NEW ---

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($result['content'])) {
            throw new Exception('Scraping service returned an invalid or empty response.');
        }

        $html_content = $result['content'];

        $html_content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html_content);
        $html_content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $html_content);
        $html_content = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', "", $html_content);
        $html_content = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', "", $html_content);
        $html_content = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', "", $html_content);
        $html_content = preg_replace('/<aside\b[^>]*>(.*?)<\/aside>/is', "", $html_content);

        $plain_text = wp_strip_all_tags($html_content);
        $cleaned_text = preg_replace('/\s+/', ' ', trim($plain_text));
        
        if (strlen($cleaned_text) < 200) {
            return '';
        }
        
        return $cleaned_text;
    }
    
    public static function generate_audio_with_openai_tts($script, $post_id, $voice, $is_preview = false) {
        $api_key = get_option('atm_openai_api_key');
        if (empty($api_key)) throw new Exception('OpenAI API key not configured');
        
        $response = wp_remote_post('https://api.openai.com/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'tts-1',
                'input' => $script,
                'voice' => $voice,
                'response_format' => 'mp3'
            ]),
            'timeout' => 300
        ]);
        
        if (is_wp_error($response)) throw new Exception('Audio generation failed: ' . $response->get_error_message());

        $audio_content = wp_remote_retrieve_body($response);
        if (wp_remote_retrieve_response_code($response) !== 200 || empty($audio_content)) {
            error_log('OpenAI TTS Error: ' . $audio_content);
            throw new Exception('Audio generation API error');
        }
        
        if ($is_preview) {
            return $audio_content;
        }
        
        return self::save_audio_file($audio_content, $post_id, 'mp3');
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
        
        return self::enhance_content_with_openrouter(['content' => $translation_instruction], 'You are a helpful assistant.', 'anthropic/claude-3-haiku');
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
        return 'You are an expert SEO content writer. Your task is to generate a complete article based on the provided topic.

The output MUST be a single, valid JSON object. This JSON object must have two keys:
1. "title": A string containing a compelling, SEO-friendly title for the article.
2. "content": A string containing the full article content, formatted using Markdown.

The article content must be well-structured, using Markdown for:
- Headings (##) and subheadings (###).
- Bulleted or numbered lists where appropriate.
- Bold text (**) for emphasis on key terms.
- Hyperlinks in the format `[link text](URL)`. You should include at least one relevant external link.

Do not include any text, explanations, or markdown code fences outside of the JSON object. Your entire response must be only the JSON object itself.';
    }

    public static function get_writing_styles() {
        // Base instructions to ensure consistency across all styles
        $base_instructions = 'Your task is to generate a complete article based on the provided title. The content should be well-structured and formatted using Markdown. Use headings (##), subheadings (###), bullet points, and bold text for emphasis. Ensure the article is comprehensive and engaging. **IMPORTANT: Do not repeat the article title in the content of your response.**';

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

    public static function get_default_image_prompt() {
        return 'A photorealistic, high-resolution image of [topic], suitable for a featured blog post image. Cinematic lighting, professional quality, 16:9 aspect ratio.';
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