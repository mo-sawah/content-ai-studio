<?php
// /includes/class-atm-twitter-api.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Twitter_API {
    
    /**
     * Search Twitter for credible news tweets using TwitterAPI.io
     */
    public static function search_twitter_news($keyword, $filters = []) {
        $api_key = get_option('atm_twitterapi_key');
        if (empty($api_key)) {
            throw new Exception('TwitterAPI.io key not configured. Please add your API key in the settings.');
        }
        
        $url = 'https://api.twitterapi.io/twitter/tweet/advanced_search';
        
        // Build simple query
        $query = $keyword;
        if ($filters['verified_only'] ?? false) {
            $query .= ' filter:verified';
        }
        
        $params = [
            'query' => $query,
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
            throw new Exception('Failed to connect to TwitterAPI.io: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            throw new Exception("TwitterAPI.io Error ($response_code): $body");
        }
        
        $data = json_decode($body, true);
        if ($data === null) {
            throw new Exception('Invalid JSON response from TwitterAPI.io');
        }
        
        // Debug: Log the ENTIRE response structure
        error_log("ATM Twitter FULL RESPONSE: " . print_r($data, true));
        
        // Extract tweets from response
        $tweets = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $tweets = $data['data'];
        } elseif (isset($data['tweets']) && is_array($data['tweets'])) {
            $tweets = $data['tweets'];
        } elseif (is_array($data)) {
            $tweets = $data;
        }
        
        if (empty($tweets)) {
            return ['results' => [], 'total' => 0];
        }
        
        // Debug: Log structure of first tweet
        error_log("ATM Twitter FIRST TWEET: " . print_r($tweets[0], true));
        
        $min_followers = $filters['min_followers'] ?? 1000;
        $credible_sources = self::get_credible_sources_array();
        
        $filtered_tweets = [];
        foreach ($tweets as $index => $tweet) {
            // Extract all data with aggressive fallbacks
            $extracted_data = self::extract_all_tweet_data($tweet);
            
            // Skip if no valid user data
            if (!$extracted_data['user_data']) {
                error_log("ATM Twitter: Skipping tweet $index - no user data");
                continue;
            }
            
            // Apply filters
            if ($extracted_data['user_data']['followers'] < $min_followers) {
                continue;
            }
            
            if (!self::appears_to_be_news($extracted_data['text'])) {
                continue;
            }
            
            // Check credible sources
            $is_credible = in_array('@' . $extracted_data['user_data']['screen_name'], $credible_sources, true);
            
            $filtered_tweets[] = [
                'id' => $extracted_data['id'],
                'text' => $extracted_data['text'],
                'user' => $extracted_data['user_data'],
                'created_at' => $extracted_data['created_at'],
                'formatted_date' => $extracted_data['formatted_date'],
                'metrics' => $extracted_data['metrics'],
                'urls' => $extracted_data['urls'],
                'media' => $extracted_data['media'],
                'is_credible_source' => $is_credible,
                'credibility_score' => self::calculate_credibility_score_fixed($extracted_data['user_data'], $extracted_data['metrics'], $is_credible)
            ];
        }
        
        // Sort by credibility score and engagement
        usort($filtered_tweets, function($a, $b) {
            if ($a['credibility_score'] !== $b['credibility_score']) {
                return $b['credibility_score'] - $a['credibility_score'];
            }
            return ($b['metrics']['retweets'] + $b['metrics']['likes']) - ($a['metrics']['retweets'] + $a['metrics']['likes']);
        });
        
        return [
            'results' => $filtered_tweets,
            'total' => count($filtered_tweets),
            'keyword' => $keyword
        ];
    }
    
    /**
     * Aggressively extract all possible data from a tweet with multiple fallbacks
     */
    private static function extract_all_tweet_data($tweet) {
        // Start with empty data structure
        $data = [
            'id' => '',
            'text' => '',
            'user_data' => null,
            'created_at' => '',
            'formatted_date' => '',
            'metrics' => ['retweets' => 0, 'likes' => 0, 'replies' => 0],
            'urls' => [],
            'media' => []
        ];
        
        // Extract ID
        $data['id'] = $tweet['id_str'] ?? $tweet['id'] ?? uniqid();
        
        // Extract text with ALL possible field names
        $text_fields = ['full_text', 'text', 'content', 'body', 'message'];
        foreach ($text_fields as $field) {
            if (!empty($tweet[$field])) {
                $data['text'] = $tweet[$field];
                break;
            }
        }
        
        // Extract user data - try EVERY possible structure
        $user_locations = ['user', 'author', 'account', 'profile', 'creator'];
        foreach ($user_locations as $location) {
            if (isset($tweet[$location]) && is_array($tweet[$location])) {
                $user = $tweet[$location];
                
                $data['user_data'] = [
                    'name' => self::get_first_available($user, ['name', 'display_name', 'full_name', 'username', 'screen_name']) ?: 'Unknown User',
                    'screen_name' => self::get_first_available($user, ['screen_name', 'username', 'handle', 'login']) ?: 'unknown',
                    'verified' => $user['verified'] ?? $user['is_verified'] ?? $user['blue_verified'] ?? false,
                    'followers' => intval($user['followers_count'] ?? $user['follower_count'] ?? $user['followers'] ?? 0),
                    'profile_image' => $user['profile_image_url_https'] ?? $user['profile_image_url'] ?? $user['avatar'] ?? $user['image'] ?? 'https://abs.twimg.com/sticky/default_profile_images/default_profile_normal.png'
                ];
                break;
            }
        }
        
        // Extract metrics with multiple fallbacks
        if (isset($tweet['public_metrics'])) {
            $metrics = $tweet['public_metrics'];
            $data['metrics'] = [
                'retweets' => intval($metrics['retweet_count'] ?? 0),
                'likes' => intval($metrics['like_count'] ?? 0),
                'replies' => intval($metrics['reply_count'] ?? 0)
            ];
        } else {
            $data['metrics'] = [
                'retweets' => intval($tweet['retweet_count'] ?? $tweet['retweets'] ?? 0),
                'likes' => intval($tweet['favorite_count'] ?? $tweet['like_count'] ?? $tweet['likes'] ?? $tweet['hearts'] ?? 0),
                'replies' => intval($tweet['reply_count'] ?? $tweet['replies'] ?? 0)
            ];
        }
        
        // Extract created_at and format it
        $created_at = $tweet['created_at'] ?? date('c');
        $data['created_at'] = $created_at;
        $data['formatted_date'] = self::format_twitter_date($created_at);
        
        // Extract URLs
        if (isset($tweet['entities']['urls'])) {
            foreach ($tweet['entities']['urls'] as $url) {
                $data['urls'][] = [
                    'url' => $url['url'] ?? '',
                    'expanded_url' => $url['expanded_url'] ?? $url['url'] ?? '',
                    'display_url' => $url['display_url'] ?? $url['url'] ?? ''
                ];
            }
        }
        
        // Extract media
        if (isset($tweet['entities']['media'])) {
            foreach ($tweet['entities']['media'] as $media) {
                $data['media'][] = [
                    'type' => $media['type'] ?? 'photo',
                    'url' => $media['media_url_https'] ?? $media['media_url'] ?? $media['url'] ?? '',
                    'sizes' => $media['sizes'] ?? []
                ];
            }
        }
        
        // If no media in entities, check attachments
        if (empty($data['media']) && isset($tweet['attachments']['media'])) {
            foreach ($tweet['attachments']['media'] as $media) {
                $data['media'][] = [
                    'type' => $media['type'] ?? 'photo',
                    'url' => $media['url'] ?? $media['media_url'] ?? '',
                    'sizes' => []
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Get first available value from array of possible keys
     */
    private static function get_first_available($array, $keys) {
        foreach ($keys as $key) {
            if (!empty($array[$key])) {
                return $array[$key];
            }
        }
        return null;
    }
    
    /**
     * Format Twitter date to readable format
     */
    private static function format_twitter_date($date_string) {
        if (empty($date_string)) {
            return date('M j, Y g:i A');
        }
        
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return date('M j, Y g:i A');
        }
        
        return date('M j, Y g:i A', $timestamp);
    }
    
    /**
     * Check if tweet appears to be news content
     */
    private static function appears_to_be_news($text) {
        $news_indicators = [
            // News language patterns
            'breaking', 'reports', 'according to', 'sources say', 'confirmed',
            'announced', 'statement', 'exclusive', 'developing', 'update',
            'just in', 'alert', 'happening now', 'live', 'urgent',
            // Time indicators
            'today', 'yesterday', 'this morning', 'tonight', 'now',
            'earlier today', 'moments ago', 'just now',
            // Authority indicators  
            'officials', 'spokesperson', 'investigation', 'study shows',
            'government', 'police', 'court', 'judge', 'president',
            // News formats
            'reuters', 'ap news', 'associated press', 'breaking news'
        ];
        
        $text_lower = strtolower($text);
        foreach ($news_indicators as $indicator) {
            if (strpos($text_lower, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for URL presence (news often includes links)
        if (preg_match('/https?:\/\/[^\s]+/', $text)) {
            return true;
        }
        
        // Check for news hashtags
        $news_hashtags = ['#breaking', '#news', '#update', '#alert', '#live'];
        foreach ($news_hashtags as $hashtag) {
            if (stripos($text, $hashtag) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fixed credibility scoring
     */
    private static function calculate_credibility_score_fixed($user_data, $metrics, $is_credible_source) {
        $score = 10; // Base score
        
        // Credible source bonus
        if ($is_credible_source) {
            $score += 40;
        }
        
        // Verification bonus
        if ($user_data['verified']) {
            $score += 25;
        }
        
        // Follower count scoring
        $followers = $user_data['followers'];
        if ($followers > 1000000) {
            $score += 20;
        } elseif ($followers > 100000) {
            $score += 15;
        } elseif ($followers > 10000) {
            $score += 10;
        } elseif ($followers > 1000) {
            $score += 5;
        }
        
        // Engagement scoring
        $engagement = $metrics['retweets'] + $metrics['likes'];
        if ($engagement > 1000) {
            $score += 15;
        } elseif ($engagement > 100) {
            $score += 10;
        } elseif ($engagement > 10) {
            $score += 5;
        }
        
        return min($score, 100);
    }
    
    /**
     * Get credible sources as array
     */
    private static function get_credible_sources_array() {
        $sources_string = get_option('atm_twitter_credible_sources', '');
        if (empty($sources_string)) {
            // Default credible sources if none configured
            $sources_string = "@CNN\n@BBCNews\n@Reuters\n@AP\n@nytimes\n@washingtonpost\n@guardian\n@WSJ\n@Bloomberg\n@NPR\n@ABCNews\n@CBSNews\n@NBCNews\n@FoxNews\n@USAToday\n@TIME\n@Newsweek\n@TheEconomist\n@politico\n@MSNBC";
        }
        return array_filter(array_map('trim', explode("\n", $sources_string)));
    }
    
    /**
     * Extract URLs from tweet with TwitterAPI.io format
     */
    private static function extract_urls_from_tweet($tweet) {
        $urls = [];
        
        // Check entities structure
        if (isset($tweet['entities']['urls'])) {
            foreach ($tweet['entities']['urls'] as $url) {
                $urls[] = [
                    'url' => $url['url'] ?? '',
                    'expanded_url' => $url['expanded_url'] ?? $url['url'] ?? '',
                    'display_url' => $url['display_url'] ?? $url['url'] ?? ''
                ];
            }
        }
        
        // Fallback: extract URLs from text
        if (empty($urls)) {
            $text = $tweet['text'] ?? $tweet['full_text'] ?? '';
            preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
            foreach ($matches[0] as $url) {
                $urls[] = [
                    'url' => $url,
                    'expanded_url' => $url,
                    'display_url' => $url
                ];
            }
        }
        
        return $urls;
    }
    
    /**
     * Extract media from tweet with TwitterAPI.io format
     */
    private static function extract_media_from_tweet($tweet) {
        $media = [];
        
        // Check entities structure for media
        if (isset($tweet['entities']['media'])) {
            foreach ($tweet['entities']['media'] as $item) {
                $media[] = [
                    'type' => $item['type'] ?? 'photo',
                    'url' => $item['media_url_https'] ?? $item['media_url'] ?? '',
                    'sizes' => $item['sizes'] ?? []
                ];
            }
        }
        
        // Check attachments structure (TwitterAPI v2 format)
        if (isset($tweet['attachments']['media_keys'])) {
            // This would require additional API call to get media details
            // For now, we'll skip this
        }
        
        return $media;
    }
    
    /**
     * Enhanced article generation with actual tweet quotes
     */
    public static function generate_article_from_tweets($keyword, $selected_tweets, $article_language = 'English') {
        if (empty($selected_tweets)) {
            throw new Exception('No tweets provided for article generation');
        }
        
        // Prepare detailed tweet content for analysis
        $tweets_content = "TWITTER/X NEWS SOURCES:\n\n";
        
        foreach ($selected_tweets as $index => $tweet) {
            $tweets_content .= "TWEET " . ($index + 1) . ":\n";
            $tweets_content .= "Account: @{$tweet['user']['screen_name']} ({$tweet['user']['name']})\n";
            $tweets_content .= "Verified: " . ($tweet['user']['verified'] ? 'Yes' : 'No') . "\n";
            $tweets_content .= "Followers: " . number_format($tweet['user']['followers']) . "\n";
            $tweets_content .= "Posted: {$tweet['formatted_date']}\n";
            $tweets_content .= "EXACT TWEET TEXT: \"{$tweet['text']}\"\n";
            
            if (!empty($tweet['urls'])) {
                $urls_list = array_map(function($url) {
                    return $url['expanded_url'];
                }, $tweet['urls']);
                $tweets_content .= "Links: " . implode(', ', $urls_list) . "\n";
            }
            
            $tweets_content .= "Engagement: {$tweet['metrics']['retweets']} retweets, {$tweet['metrics']['likes']} likes\n";
            $tweets_content .= "Credibility Score: {$tweet['credibility_score']}/100\n";
            $tweets_content .= "---\n\n";
        }
        
        $system_prompt = "You are a professional social media journalist. Create a comprehensive news article in {$article_language} about '{$keyword}' based on these Twitter/X posts.

**CRITICAL REQUIREMENTS:**
1. **Language**: Write entirely in {$article_language}
2. **Include Actual Tweets**: Quote the exact tweet text in your article
3. **Attribution**: Always attribute with full details: @username (Full Name) with X followers
4. **Verification**: Use web search to verify and add context from reliable news sources

**TWEET QUOTATION FORMAT:**
- Use exact tweet text in quotes
- Example: @username (Full Name) tweeted: \"[exact tweet text here]\"
- Include follower count and verification status for context
- Example: The verified account @CNN (5.2M followers) reported: \"Breaking: [tweet text]\"

**ARTICLE STRUCTURE:**
- Lead with the most newsworthy information
- Quote relevant tweets throughout the article
- Verify claims through additional research
- Provide broader context and implications
- 800-1200 words

**NO H1 HEADINGS** - Use H2 (##) only
Start directly with content, not title

**OUTPUT FORMAT (JSON):**
{
    \"title\": \"Breaking news headline in {$article_language}\",
    \"subtitle\": \"Brief subheadline in {$article_language}\", 
    \"content\": \"Full article in {$article_language} with embedded tweet quotes\"
}

**SOURCE TWEETS:**
{$tweets_content}";
        
        $model = get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = ATM_API::enhance_content_with_openrouter(
            ['content' => $tweets_content],
            $system_prompt,
            $model,
            true, // JSON mode
            true  // Enable web search
        );
        
        $result = json_decode(trim($raw_response), true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Plugin - Invalid JSON from Twitter article generation: ' . $raw_response);
            throw new Exception('Invalid response from article generation API. Please try again.');
        }
        
        return [
            'title' => sanitize_text_field($result['title']),
            'subtitle' => sanitize_text_field($result['subtitle'] ?? ''),
            'content' => wp_kses_post($result['content'])
        ];
    }
    
    /**
     * Test API connection method for debugging
     */
    public static function test_api_connection() {
        $api_key = get_option('atm_twitterapi_key');
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'No API key configured'];
        }
        
        // Simple test with a basic query
        $url = 'https://api.twitterapi.io/twitter/tweet/advanced_search';
        $params = [
            'query' => 'hello',
            'queryType' => 'Latest',
        ];
        
        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'headers' => [
                'X-API-Key' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Connection failed: ' . $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $data = json_decode($body, true);
        
        return [
            'success' => $response_code === 200,
            'response_code' => $response_code,
            'message' => $response_code === 200 ? 'Connection successful' : $body,
            'data_keys' => $response_code === 200 && $data ? array_keys($data) : [],
            'tweet_count' => $response_code === 200 && isset($data['data']) ? count($data['data']) : 0
        ];
    }
}