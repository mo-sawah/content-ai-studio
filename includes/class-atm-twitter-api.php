<?php
// /includes/class-atm-twitter-api.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Twitter_API {
    
    /**
     * Search Twitter for credible news tweets
     */
    public static function search_twitter_news($keyword, $filters = []) {
        $api_key = get_option('atm_twitterapi_key');
        if (empty($api_key)) {
            throw new Exception('TwitterAPI.io key not configured. Please add your API key in the settings.');
        }
        
        // Build search query with credibility filters
        $search_query = self::build_credible_search_query($keyword, $filters);
        
        $url = 'https://api.twitterapi.io/v1/search/tweets';
        $params = [
            'query' => $search_query,
            'max_results' => $filters['max_results'] ?? 20,
            'tweet_mode' => 'extended',
            'result_type' => 'recent', // or 'popular' for trending
            'include_rts' => false, // Exclude retweets for original content
        ];
        
        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to connect to TwitterAPI: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            throw new Exception('TwitterAPI Error (' . $response_code . '): ' . $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data'])) {
            return ['results' => [], 'total' => 0];
        }
        
        // Process and filter results for credibility
        $filtered_tweets = self::filter_credible_tweets($data['data'], $filters);
        
        return [
            'results' => $filtered_tweets,
            'total' => count($filtered_tweets),
            'keyword' => $keyword
        ];
    }
    
    /**
     * Build search query targeting credible sources
     */
    private static function build_credible_search_query($keyword, $filters) {
        $query_parts = [$keyword];
        
        // Add verified account filter
        if ($filters['verified_only'] ?? true) {
            $query_parts[] = 'filter:verified';
        }
        
        // Add credible sources filter
        $credible_sources = self::get_credible_sources_array();
        if (!empty($credible_sources) && ($filters['credible_sources_only'] ?? true)) {
            $sources_query = '(' . implode(' OR ', array_map(function($source) {
                return 'from:' . ltrim($source, '@');
            }, array_slice($credible_sources, 0, 10))) . ')'; // Limit to avoid query length issues
            
            $query_parts[] = $sources_query;
        }
        
        // Add language filter
        if (!empty($filters['language'])) {
            $query_parts[] = 'lang:' . $filters['language'];
        }
        
        // Exclude replies and quotes for cleaner content
        $query_parts[] = '-filter:replies';
        $query_parts[] = '-filter:quotes';
        
        return implode(' ', $query_parts);
    }
    
    /**
     * Filter tweets for credibility and relevance
     */
    private static function filter_credible_tweets($tweets, $filters) {
        $filtered = [];
        $min_followers = $filters['min_followers'] ?? get_option('atm_twitter_min_followers', 10000);
        $credible_sources = self::get_credible_sources_array();
        
        foreach ($tweets as $tweet) {
            // Skip if user doesn't meet follower threshold
            if ($tweet['user']['followers_count'] < $min_followers) {
                continue;
            }
            
            // Prioritize tweets from known credible sources
            $username = '@' . $tweet['user']['screen_name'];
            $is_credible_source = in_array($username, $credible_sources, true);
            
            // Skip non-credible sources if filter is enabled
            if (($filters['credible_sources_only'] ?? true) && !$is_credible_source) {
                continue;
            }
            
            // Check for news indicators in the tweet
            if (!self::appears_to_be_news($tweet['full_text'])) {
                continue;
            }
            
            $filtered[] = [
                'id' => $tweet['id_str'],
                'text' => $tweet['full_text'],
                'user' => [
                    'name' => $tweet['user']['name'],
                    'screen_name' => $tweet['user']['screen_name'],
                    'verified' => $tweet['user']['verified'],
                    'followers' => $tweet['user']['followers_count'],
                    'profile_image' => $tweet['user']['profile_image_url_https']
                ],
                'created_at' => $tweet['created_at'],
                'formatted_date' => date('M j, Y g:i A', strtotime($tweet['created_at'])),
                'metrics' => [
                    'retweets' => $tweet['retweet_count'],
                    'likes' => $tweet['favorite_count'],
                    'replies' => $tweet['reply_count'] ?? 0
                ],
                'urls' => self::extract_urls($tweet),
                'media' => self::extract_media($tweet),
                'is_credible_source' => $is_credible_source,
                'credibility_score' => self::calculate_credibility_score($tweet, $is_credible_source)
            ];
        }
        
        // Sort by credibility score and engagement
        usort($filtered, function($a, $b) {
            if ($a['credibility_score'] === $b['credibility_score']) {
                return ($b['metrics']['retweets'] + $b['metrics']['likes']) - 
                       ($a['metrics']['retweets'] + $a['metrics']['likes']);
            }
            return $b['credibility_score'] - $a['credibility_score'];
        });
        
        return $filtered;
    }
    
    /**
     * Check if tweet appears to be news content
     */
    private static function appears_to_be_news($text) {
        $news_indicators = [
            // News language patterns
            'breaking', 'reports', 'according to', 'sources say', 'confirmed',
            'announced', 'statement', 'exclusive', 'developing', 'update',
            // Time indicators
            'today', 'yesterday', 'this morning', 'tonight', 'now',
            // Authority indicators  
            'officials', 'spokesperson', 'investigation', 'study shows'
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
        
        return false;
    }
    
    /**
     * Calculate credibility score based on multiple factors
     */
    private static function calculate_credibility_score($tweet, $is_credible_source) {
        $score = 0;
        
        // Base score for credible sources
        if ($is_credible_source) {
            $score += 50;
        }
        
        // Verification bonus
        if ($tweet['user']['verified']) {
            $score += 20;
        }
        
        // Follower count factor (logarithmic scaling)
        $followers = $tweet['user']['followers_count'];
        if ($followers > 100000) {
            $score += 15;
        } elseif ($followers > 50000) {
            $score += 10;
        } elseif ($followers > 10000) {
            $score += 5;
        }
        
        // Engagement factor
        $engagement = $tweet['retweet_count'] + $tweet['favorite_count'];
        if ($engagement > 1000) {
            $score += 10;
        } elseif ($engagement > 100) {
            $score += 5;
        }
        
        // URL presence (news often includes sources)
        if (preg_match('/https?:\/\/[^\s]+/', $tweet['full_text'])) {
            $score += 5;
        }
        
        return min($score, 100); // Cap at 100
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
     * Extract URLs from tweet entities
     */
    private static function extract_urls($tweet) {
        $urls = [];
        if (isset($tweet['entities']['urls'])) {
            foreach ($tweet['entities']['urls'] as $url) {
                $urls[] = [
                    'url' => $url['url'],
                    'expanded_url' => $url['expanded_url'],
                    'display_url' => $url['display_url']
                ];
            }
        }
        return $urls;
    }
    
    /**
     * Extract media from tweet entities
     */
    private static function extract_media($tweet) {
        $media = [];
        if (isset($tweet['entities']['media'])) {
            foreach ($tweet['entities']['media'] as $item) {
                $media[] = [
                    'type' => $item['type'],
                    'url' => $item['media_url_https'],
                    'sizes' => $item['sizes']
                ];
            }
        }
        return $media;
    }
    
    /**
     * Generate article from selected tweets
     */
    public static function generate_article_from_tweets($keyword, $selected_tweets, $article_language = 'English') {
        if (empty($selected_tweets)) {
            throw new Exception('No tweets provided for article generation');
        }
        
        // Prepare tweet content for analysis
        $tweets_content = "TWITTER NEWS SOURCES:\n\n";
        
        foreach ($selected_tweets as $index => $tweet) {
            $tweets_content .= "TWEET " . ($index + 1) . ":\n";
            $tweets_content .= "From: @{$tweet['user']['screen_name']} ({$tweet['user']['name']})\n";
            $tweets_content .= "Verified: " . ($tweet['user']['verified'] ? 'Yes' : 'No') . "\n";
            $tweets_content .= "Followers: " . number_format($tweet['user']['followers']) . "\n";
            $tweets_content .= "Posted: {$tweet['formatted_date']}\n";
            $tweets_content .= "Content: {$tweet['text']}\n";
            if (!empty($tweet['urls'])) {
                $tweets_content .= "Links: " . implode(', ', array_column($tweet['urls'], 'expanded_url')) . "\n";
            }
            $tweets_content .= "Engagement: {$tweet['metrics']['retweets']} retweets, {$tweet['metrics']['likes']} likes\n";
            $tweets_content .= "---\n";
        }
        
        $system_prompt = "You are a professional social media journalist. Your task is to analyze these Twitter/X posts and create a comprehensive news article in {$article_language} about the topic '{$keyword}'.

        **CRITICAL INSTRUCTIONS:**
        1. **Language**: Write the entire article in {$article_language}. This is mandatory.
        2. **Analysis**: Synthesize information from multiple tweets to identify the main story
        3. **Verification**: Use your web search ability to verify claims and add context from reliable news sources
        4. **Attribution**: Properly attribute information to the Twitter sources while maintaining journalistic standards
        
        **ARTICLE REQUIREMENTS:**
        - Write a 800-1200 word news article
        - Lead with the most important/breaking information
        - Include direct quotes from tweets where appropriate
        - Verify information through additional research
        - Provide context and background
        - End with implications or what comes next
        
        **FORMATTING RULES:**
        - NO H1 headings in content field
        - Start with engaging lead paragraph
        - Use H2 (##) for section headings only
        - No conclusion headings - end naturally
        
        **SOCIAL MEDIA ATTRIBUTION:**
        - Format: \"According to a tweet from @username, ...\"
        - Include follower count for context when relevant
        - Note verification status of major sources
        
        **OUTPUT FORMAT (JSON):**
        {
            \"title\": \"Breaking news headline in {$article_language}\",
            \"subtitle\": \"Brief subheadline in {$article_language}\",
            \"content\": \"Full article in {$article_language} using Markdown\"
        }
        
        **SOURCE TWEETS:**
        {$tweets_content}";
        
        $model = get_option('atm_article_model', 'openai/gpt-4o');
        
        $raw_response = ATM_API::enhance_content_with_openrouter(
            ['content' => $tweets_content],
            $system_prompt,
            $model,
            true, // JSON mode
            true  // Enable web search for verification
        );
        
        // Parse and validate response
        $result = json_decode(trim($raw_response), true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Plugin - Invalid JSON from Twitter article generation: ' . $raw_response);
            throw new Exception('Invalid response from article generation API');
        }
        
        return [
            'title' => sanitize_text_field($result['title']),
            'subtitle' => sanitize_text_field($result['subtitle'] ?? ''),
            'content' => wp_kses_post($result['content'])
        ];
    }
}