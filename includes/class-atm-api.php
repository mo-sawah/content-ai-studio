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

    public static function generate_advanced_podcast_script($title, $content, $language, $duration = 'medium') {
    
        // Much more conservative duration mapping to fit TTS limits
        $duration_specs = [
            'short' => '5-7 minutes (approximately 750-1050 words total)',
            'medium' => '8-12 minutes (approximately 1200-1800 words total)', 
            'long' => '15-18 minutes (approximately 2250-2700 words total)'
        ];
        
        $target_duration = $duration_specs[$duration] ?? $duration_specs['medium'];

        $system_prompt = "You are creating a professional, engaging podcast script between two experienced hosts discussing '{$title}'. 

    **HOST PERSONALITIES:**
    - **ALEX CHEN**: The primary presenter - analytical, well-researched, guides the conversation
    - **JORDAN RIVERA**: The co-host - enthusiastic, relatable, provides real-world examples

    **CRITICAL REQUIREMENTS:**

    1. **Script Length**: Target {$target_duration}
    - IMPORTANT: Keep individual speaking segments SHORT (maximum 2-3 sentences each)
    - Frequent back-and-forth between hosts
    - No long monologues - break up longer thoughts into multiple exchanges

    2. **Conversation Structure**:
    - **Opening** (60 seconds): Brief greeting and topic introduction
    - **Main Discussion** (80% of content): Multiple short exchanges covering key points
    - **Closing** (60 seconds): Quick summary and sign-off

    3. **Speaking Patterns**:
    - Each speaker turn should be 1-3 sentences maximum
    - Frequent interruptions and natural flow: [INTERRUPTING], [CHUCKLES]
    - Short reactions: 'Exactly!', 'That's interesting', 'I see what you mean'
    - Keep responses conversational and concise

    4. **Content Guidelines**:
    - Cover the main points from the article
    - Include current context from web research
    - Keep explanations clear but brief
    - Use natural transitions between topics

    5. **Output Format**:
    ALEX: [ENTHUSIASTIC] Welcome to Deep Dive Discussions! I'm Alex Chen...
    JORDAN: [FRIENDLY] And I'm Jordan Rivera. Today we're looking at...
    ALEX: [NODDING] Right. So let's start with the basics...
    JORDAN: [AGREEMENT] That makes sense. What's really interesting is...

    6. **Language**: Write entirely in {$language}

    **IMPORTANT**: Focus on creating natural conversation with SHORT exchanges. Avoid long paragraphs or speeches. Think of it as a real conversation where people interrupt and respond quickly.

    Research the topic thoroughly but present information in bite-sized, conversational chunks.";

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
    public static function generate_lifelike_comments($title, $content, $count = 7, $threaded = true, $model_override = '') {
        // Allow up to 50
        $count = max(5, min(50, intval($count)));

        $threading_rule = $threaded
            ? "- Some comments should be direct replies to earlier ones. Use `parent_index` to reference the zero-based index of the parent comment. Replies MUST reference an earlier index.\n"
            : "- All comments must be top-level (no replies). Set `parent_index` to null.\n";

        // Strong realism, variety, and no links
        $system_prompt = "You are a diverse group of real readers reacting to an article.
Produce a realistic discussion thread with natural, varied voices: short reactions, longer takes, questions, counterpoints, and follow-ups.

CRITICAL RULES:
- Read and internalize the article context below. Stay specific to it.
- Vary tone and style (casual, thoughtful, skeptical, appreciative). Keep it conversational and human.
- Avoid generic filler like 'Great post' or robotic phrasing. Be concrete and grounded in the article.
- Mix lengths: 1–2 sentences up to short paragraphs. No walls of text.
- Light use of emojis or slang is okay, but not overused. No profanity or hate.
$threading_rule
- Names must look organic and varied. Use a mix of formats: single names (\"Maya\"), nicknames/usernames (\"el_rondo\", \"N3ll\"), hyphenated (\"Sam-Lee\"), two names (\"João Pereira\"), initials-before (\"K. Martinez\"), or first+last.
- DO NOT repeat the same naming pattern across all comments. At most ~30% may be 'Firstname + initial'.
- STRICTLY FORBIDDEN: any links, URLs, domain names, or markdown links. Do not include emails. If referencing sources, paraphrase without URLs.
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

    public static function enhance_content_with_openrouter($input, $system_prompt, $model, $json_mode = false, $enable_web_search = true) {
        $api_key = trim(get_option('atm_openrouter_api_key', ''));
        if (!$api_key) {
            throw new Exception('OpenRouter API key is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
            ],
            'temperature' => 0.8,
        ];

        // Allow $input as string OR array/object (we often pass arrays)
        if (is_array($input) || is_object($input)) {
            $payload['messages'][] = ['role' => 'user', 'content' => wp_json_encode($input, JSON_UNESCAPED_SLASHES)];
        } else {
            $payload['messages'][] = ['role' => 'user', 'content' => (string) $input];
        }

        // Strict JSON mode if requested
        if ($json_mode) {
            $payload['response_format'] = [ 'type' => 'json_object' ];
        }

        // UPDATED: Use OpenRouter's web search according to their documentation
        if ($enable_web_search) {
            // Add web search instruction to the system prompt
            $web_search_instruction = "\n\nIMPORTANT: Use your web search capabilities to find current, accurate information about this topic before responding. Search for recent developments, statistics, and relevant context.";
            $payload['messages'][0]['content'] .= $web_search_instruction;
            
            // Enable the provider's web search tools
            $payload['provider'] = [
                'allow_fallbacks' => false,
                'require_parameters' => true,
                'data_collection' => 'deny'
            ];
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer' => home_url(), // Required by OpenRouter
                'X-Title' => get_bloginfo('name'), // Optional but recommended
            ],
            'timeout' => 120, // Increased timeout for web search
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

        if (is_wp_error($response)) {
            throw new Exception('Scraping API Connection Error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_detail = isset($error_data['detail']) ? $error_data['detail'] : 'Please check your ScrapingAnt account.';
            throw new Exception('Scraping API Error (Code: ' . $response_code . '): ' . $error_detail);
        }

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($result['content'])) {
            throw new Exception('Scraping service returned an invalid or empty response.');
        }

        $html_content = $result['content'];

        // Clean up HTML content
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

        return $audio_content;
    }

    public static function generate_two_person_podcast_audio($script, $voice_a, $voice_b, $provider = 'openai') {
        // Parse the script to separate HOST_A and HOST_B lines
        $audio_segments = self::parse_podcast_script($script);
        $final_audio_parts = [];
        
        foreach ($audio_segments as $segment) {
            $voice = ($segment['speaker'] === 'HOST_A') ? $voice_a : $voice_b;
            $text = $segment['text'];
            
            // Clean up emotions and stage directions for TTS
            $clean_text = preg_replace('/\[([^\]]+)\]/', '', $text);
            $clean_text = trim($clean_text);
            
            if (empty($clean_text)) continue;

            // Split long segments into smaller chunks (OpenAI limit is 4096 chars)
            $max_chunk_size = $provider === 'openai' ? 3500 : 4000; // Much more conservative
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
                    // Continue with next chunk instead of failing completely
                    continue;
                }
            }
            
            // Add pause between different speakers
            if (isset($segment['add_pause']) && $segment['add_pause']) {
                $final_audio_parts[] = self::generate_silence(500); // 500ms
            }
        }

        // Combine all audio parts
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

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if the line indicates a new speaker
            if (preg_match('/^(HOST_[AB]|ALEX|JORDAN):\s*(.+)/i', $line, $matches)) {
                // If there's a current segment being built, save it first.
                if ($current_segment) {
                    $segments[] = $current_segment;
                }

                $speaker = strtoupper($matches[1]);
                $text = trim($matches[2]);

                // Map names to voice assignments
                if ($speaker === 'ALEX') $speaker = 'HOST_A';
                if ($speaker === 'JORDAN') $speaker = 'HOST_B';

                $is_new_speaker = empty($segments) || end($segments)['speaker'] !== $speaker;

                // Start a new segment
                $current_segment = [
                    'speaker' => $speaker,
                    'text' => $text,
                    'add_pause' => $is_new_speaker,
                ];
            } elseif ($current_segment) {
                // This line is a continuation of the previous speaker's text
                $current_segment['text'] .= ' ' . $line;
            }
        }

        // Add the very last segment after the loop finishes
        if ($current_segment) {
            $segments[] = $current_segment;
        }

        return $segments;
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