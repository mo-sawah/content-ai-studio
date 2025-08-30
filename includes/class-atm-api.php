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
            'image' => self::extract_image_from_rss_item($item), // <-- ADD THIS LINE
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
     * 
     */

    public static function generate_prompt_from_content($title, $content) {
    // Prepare a clean, full version of the content for the AI
    $clean_content = wp_strip_all_tags($content);

    // This is your new, more detailed prompt
    $system_prompt = 'You are an expert prompt engineer for AI image generators like DALL-E 3. Your task is to create a single, concise, and powerful prompt that visually represents the provided article.

Follow these rules:
1. Read the title and body to understand the core theme, mood, and key subjects.
2. Condense the main idea into a single, visual scene. Do not describe multiple scenes.
3. The prompt MUST be a single paragraph and under 100 words.
4. Structure the prompt as a series of descriptive phrases separated by commas.
5. Start with the main subject, then add details about the setting, atmosphere, lighting, and style.
6. Example format: "A high-angle shot of a modern city skyline at dusk, cinematic lighting, photorealistic, reflecting themes of progress and tension."
7. Your entire response MUST be only the generated prompt text, with no extra words or quotation marks.';

    // Use the full, cleaned content instead of the excerpt
    $content_for_ai = "Article Title: " . $title . "\n\nArticle Body:\n" . $clean_content;

    // Use a fast and creative model for this task
    $model = 'anthropic/claude-3-haiku';

    $generated_prompt = self::enhance_content_with_openrouter(
        ['content' => $content_for_ai],
        $system_prompt,
        $model
    );

    return trim($generated_prompt);
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

    // --- 1. Initial Request to the Correct Endpoint ---
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

    // --- 2. Polling for the Result ---
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

    // --- 3. Download the temporary image to our server ---
    // This reuses the existing function in your plugin for downloading images.
    // We are passing a fake post ID of 0 because we just need the raw image data.
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
            true // Enable JSON mode
        );

        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Content AI Studio - Invalid JSON from Chart Generation API: ' . $raw_response);
            throw new Exception('The AI returned an invalid JSON response. Please try again.');
        }
        
        return $raw_response; // Return the raw JSON string
    }

    public static function get_youtube_autocomplete_suggestions($query) {
        // Caching has been removed.
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
        
        // Caching has been removed to ensure fresh results.

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

        // --- THIS IS THE FIX for the date filter logic ---
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
                // Use current_time to get a timestamp respecting the WordPress timezone setting.
                $timestamp = current_time('timestamp');
                $past_timestamp = strtotime($interval, $timestamp);
                // wp_date() ensures the final format is also timezone-aware.
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
        
        // We can use a fast and efficient model for this task.
        $model = get_option('atm_translation_model', 'anthropic/claude-3-haiku');
        
        return self::enhance_content_with_openrouter(
            ['content' => $text],
            $system_prompt,
            $model
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
            true // Enable JSON mode
        );

        $result = json_decode($raw_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['translated_title']) || !isset($result['translated_content'])) {
            error_log('Content AI Studio - Invalid JSON from translation API: ' . $raw_response);
            throw new Exception('The AI returned an invalid response structure during translation. Try using a "High Quality" model from the settings for better reliability.');
        }
        
        return $result;
    }

    /**
 * Transcribes an audio file using the OpenAI Whisper API.
 * @param string $audio_file_path The temporary path to the uploaded audio file.
 * @return string The transcribed text.
 * @throws Exception On API error.
 */
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
        
        // Prompt logic has been removed
        
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
    /**
 * Enhance content using OpenRouter, with an option for forced JSON output.
 * Web search is now enabled by default for all calls.
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
        if (empty($api_key)) {
            throw new Exception('OpenRouter API key not configured');
        }

        // Determine the base model.
        // Note: This logic assumes a sensible default; individual calls might provide a specific model.
        $model = !empty($model_override) ? $model_override : get_option('atm_article_model', 'openai/gpt-4o');

        // --- THIS IS THE CORE CHANGE ---
        // Always append the :online suffix to enable web search for any model.
        $model .= ':online';
        // --- END OF CORE CHANGE ---

        $body_data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $content_data['content']]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.7
        ];

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
            'timeout' => 300
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Content enhancement failed: ' . $response->get_error_message());
        }

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

    // Return the raw audio data for this chunk
    return $audio_content;
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