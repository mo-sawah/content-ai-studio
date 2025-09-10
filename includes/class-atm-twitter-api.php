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
        
        // Simple query - don't add complex filters that might cause -1 errors
        $query = $keyword;
        
        // Only add basic filters to avoid API errors
        if ($filters['verified_only'] ?? false) {
            $query .= ' filter:verified';
        }
        
        $params = [
            'query' => $query,
            'queryType' => 'Latest',
        ];
        
        error_log("ATM Twitter Fix - Query: $query");
        
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
        
        // Log the actual response structure to understand TwitterAPI.io format
        error_log("ATM Twitter Fix - Response keys: " . implode(', ', array_keys($data)));
        if (!empty($data)) {
            $first_key = array_key_first($data);
            if (is_array($data[$first_key]) && !empty($data[$first_key])) {
                error_log("ATM Twitter Fix - First item structure: " . print_r($data[$first_key][0], true));
            }
        }
        
        // Try all possible data locations
        $tweets = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $tweets = $data['data'];
        } elseif (isset($data['tweets']) && is_array($data['tweets'])) {
            $tweets = $data['tweets'];
        } elseif (isset($data['results']) && is_array($data['results'])) {
            $tweets = $data['results'];
        } elseif (is_array($data)) {
            // Sometimes the response is directly an array
            $tweets = $data;
        }
        
        if (empty($tweets)) {
            return ['results' => [], 'total' => 0];
        }
        
        // Process tweets with flexible data extraction
        $filtered_tweets = [];
        $min_followers = $filters['min_followers'] ?? 1000; // Lower default
        
        foreach ($tweets as $index => $tweet) {
            if (!is_array($tweet)) {
                error_log("ATM Twitter Fix - Tweet $index is not an array: " . gettype($tweet));
                continue;
            }
            
            // Log structure of first tweet for debugging
            if ($index === 0) {
                error_log("ATM Twitter Fix - Tweet structure keys: " . implode(', ', array_keys($tweet)));
                if (isset($tweet['user'])) {
                    error_log("ATM Twitter Fix - User structure keys: " . implode(', ', array_keys($tweet['user'])));
                }
            }
            
            // Extract user data with multiple fallbacks
            $user_data = self::extract_user_data($tweet);
            $tweet_text = self::extract_tweet_text($tweet);
            $metrics = self::extract_metrics($tweet);
            
            // Skip if no user data or doesn't meet follower threshold
            if (!$user_data || ($user_data['followers'] < $min_followers)) {
                continue;
            }
            
            // Skip non-news content if it doesn't look like news
            if (!self::appears_to_be_news($tweet_text)) {
                continue;
            }
            
            $filtered_tweets[] = [
                'id' => $tweet['id_str'] ?? $tweet['id'] ?? uniqid(),
                'text' => $tweet_text,
                'user' => $user_data,
                'created_at' => $tweet['created_at'] ?? date('c'),
                'formatted_date' => self::format_twitter_date($tweet['created_at'] ?? ''),
                'metrics' => $metrics,
                'urls' => self::extract_urls_from_tweet($tweet),
                'media' => self::extract_media_from_tweet($tweet),
                'is_credible_source' => self::is_credible_source($user_data['screen_name']),
                'credibility_score' => self::calculate_credibility_score_simple($user_data, $metrics)
            ];
        }
        
        // Sort by follower count and engagement if no credibility scores
        usort($filtered_tweets, function($a, $b) {
            $a_score = $a['user']['followers'] + $a['metrics']['retweets'] + $a['metrics']['likes'];
            $b_score = $b['user']['followers'] + $b['metrics']['retweets'] + $b['metrics']['likes'];
            return $b_score - $a_score;
        });
        
        error_log("ATM Twitter Fix - Final count: " . count($filtered_tweets));
        
        return [
            'results' => $filtered_tweets,
            'total' => count($filtered_tweets),
            'keyword' => $keyword
        ];
    }

    // Helper method to extract user data with multiple fallbacks
    private static function extract_user_data($tweet) {
        $user = null;
        
        // Try different possible user data locations
        if (isset($tweet['user'])) {
            $user = $tweet['user'];
        } elseif (isset($tweet['author'])) {
            $user = $tweet['author'];
        } elseif (isset($tweet['account'])) {
            $user = $tweet['account'];
        }
        
        if (!$user) {
            return null;
        }
        
        return [
            'name' => $user['name'] ?? $user['display_name'] ?? $user['username'] ?? 'Unknown User',
            'screen_name' => $user['screen_name'] ?? $user['username'] ?? $user['handle'] ?? 'unknown',
            'verified' => $user['verified'] ?? $user['is_verified'] ?? false,
            'followers' => $user['followers_count'] ?? $user['follower_count'] ?? $user['followers'] ?? 0,
            'profile_image' => $user['profile_image_url_https'] ?? $user['profile_image_url'] ?? $user['avatar'] ?? 'https://abs.twimg.com/sticky/default_profile_images/default_profile_normal.png'
        ];
    }

    // Helper method to extract tweet text
    private static function extract_tweet_text($tweet) {
        return $tweet['full_text'] ?? $tweet['text'] ?? $tweet['content'] ?? $tweet['body'] ?? 'No text available';
    }

    // Helper method to extract metrics
    private static function extract_metrics($tweet) {
        $metrics = [
            'retweets' => 0,
            'likes' => 0,
            'replies' => 0
        ];
        
        // Try different metric locations
        if (isset($tweet['public_metrics'])) {
            $metrics['retweets'] = $tweet['public_metrics']['retweet_count'] ?? 0;
            $metrics['likes'] = $tweet['public_metrics']['like_count'] ?? 0;
            $metrics['replies'] = $tweet['public_metrics']['reply_count'] ?? 0;
        } else {
            $metrics['retweets'] = $tweet['retweet_count'] ?? $tweet['retweets'] ?? 0;
            $metrics['likes'] = $tweet['favorite_count'] ?? $tweet['like_count'] ?? $tweet['likes'] ?? 0;
            $metrics['replies'] = $tweet['reply_count'] ?? $tweet['replies'] ?? 0;
        }
        
        return $metrics;
    }

    // Simplified credibility check
    private static function is_credible_source($screen_name) {
        $credible_sources = self::get_credible_sources_array();
        return in_array('@' . $screen_name, $credible_sources, true);
    }

    // Simple credibility scoring
    private static function calculate_credibility_score_simple($user_data, $metrics) {
        $score = 20; // Base score
        
        if ($user_data['verified']) {
            $score += 30;
        }
        
        if ($user_data['followers'] > 100000) {
            $score += 25;
        } elseif ($user_data['followers'] > 10000) {
            $score += 15;
        } elseif ($user_data['followers'] > 1000) {
            $score += 10;
        }
        
        $engagement = $metrics['retweets'] + $metrics['likes'];
        if ($engagement > 100) {
            $score += 15;
        } elseif ($engagement > 10) {
            $score += 10;
        }
        
        return min($score, 100);
    }
    
    /**
     * Filter tweets for credibility and relevance using TwitterAPI.io data structure
     */
    private static function filter_credible_tweets($tweets, $filters) {
        $filtered = [];
        $min_followers = $filters['min_followers'] ?? get_option('atm_twitter_min_followers', 10000);
        $credible_sources = self::get_credible_sources_array();
        
        foreach ($tweets as $tweet) {
            // Skip if no user data
            if (!isset($tweet['user'])) {
                continue;
            }
            
            $user = $tweet['user'];
            
            // Skip if user doesn't meet follower threshold
            if (($user['followers_count'] ?? 0) < $min_followers) {
                continue;
            }
            
            // Check for credible sources
            $username = '@' . ($user['screen_name'] ?? $user['username'] ?? '');
            $is_credible_source = in_array($username, $credible_sources, true);
            
            // Skip non-credible sources if filter is enabled
            if (($filters['credible_sources_only'] ?? false) && !$is_credible_source) {
                continue;
            }
            
            // Check for news indicators
            $tweet_text = $tweet['text'] ?? $tweet['full_text'] ?? '';
            if (!self::appears_to_be_news($tweet_text)) {
                continue;
            }
            
            // Extract metrics safely
            $metrics = [
                'retweets' => $tweet['retweet_count'] ?? $tweet['public_metrics']['retweet_count'] ?? 0,
                'likes' => $tweet['favorite_count'] ?? $tweet['public_metrics']['like_count'] ?? 0,
                'replies' => $tweet['reply_count'] ?? $tweet['public_metrics']['reply_count'] ?? 0
            ];
            
            $filtered[] = [
                'id' => $tweet['id_str'] ?? $tweet['id'] ?? uniqid(),
                'text' => $tweet_text,
                'user' => [
                    'name' => $user['name'] ?? 'Unknown User',
                    'screen_name' => $user['screen_name'] ?? $user['username'] ?? 'unknown',
                    'verified' => $user['verified'] ?? false,
                    'followers' => $user['followers_count'] ?? 0,
                    'profile_image' => $user['profile_image_url_https'] ?? $user['profile_image_url'] ?? 'https://abs.twimg.com/sticky/default_profile_images/default_profile_normal.png'
                ],
                'created_at' => $tweet['created_at'] ?? date('c'),
                'formatted_date' => self::format_twitter_date($tweet['created_at'] ?? ''),
                'metrics' => $metrics,
                'urls' => self::extract_urls_from_tweet($tweet),
                'media' => self::extract_media_from_tweet($tweet),
                'is_credible_source' => $is_credible_source,
                'credibility_score' => self::calculate_credibility_score_new($tweet, $user, $is_credible_source)
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
     * Calculate credibility score with new data structure
     */
    private static function calculate_credibility_score_new($tweet, $user, $is_credible_source) {
        $score = 0;
        
        // Base score for credible sources
        if ($is_credible_source) {
            $score += 50;
        }
        
        // Verification bonus
        if ($user['verified'] ?? false) {
            $score += 20;
        }
        
        // Follower count factor (logarithmic scaling)
        $followers = $user['followers_count'] ?? 0;
        if ($followers > 1000000) {
            $score += 20;
        } elseif ($followers > 100000) {
            $score += 15;
        } elseif ($followers > 50000) {
            $score += 10;
        } elseif ($followers > 10000) {
            $score += 5;
        }
        
        // Engagement factor
        $retweets = $tweet['retweet_count'] ?? $tweet['public_metrics']['retweet_count'] ?? 0;
        $likes = $tweet['favorite_count'] ?? $tweet['public_metrics']['like_count'] ?? 0;
        $engagement = $retweets + $likes;
        
        if ($engagement > 1000) {
            $score += 10;
        } elseif ($engagement > 100) {
            $score += 5;
        } elseif ($engagement > 10) {
            $score += 2;
        }
        
        // URL presence (news often includes sources)
        $text = $tweet['text'] ?? $tweet['full_text'] ?? '';
        if (preg_match('/https?:\/\/[^\s]+/', $text)) {
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
     * Generate article from selected tweets
     */
    public static function generate_article_from_tweets($keyword, $selected_tweets, $article_language = 'English') {
        if (empty($selected_tweets)) {
            throw new Exception('No tweets provided for article generation');
        }
        
        // Prepare tweet content for analysis
        $tweets_content = "TWITTER/X NEWS SOURCES:\n\n";
        
        foreach ($selected_tweets as $index => $tweet) {
            $tweets_content .= "TWEET " . ($index + 1) . ":\n";
            $tweets_content .= "From: @{$tweet['user']['screen_name']} ({$tweet['user']['name']})\n";
            $tweets_content .= "Verified: " . ($tweet['user']['verified'] ? 'Yes' : 'No') . "\n";
            $tweets_content .= "Followers: " . number_format($tweet['user']['followers']) . "\n";
            $tweets_content .= "Posted: {$tweet['formatted_date']}\n";
            $tweets_content .= "Content: {$tweet['text']}\n";
            if (!empty($tweet['urls'])) {
                $urls_list = array_map(function($url) {
                    return $url['expanded_url'];
                }, $tweet['urls']);
                $tweets_content .= "Links: " . implode(', ', $urls_list) . "\n";
            }
            $tweets_content .= "Engagement: {$tweet['metrics']['retweets']} retweets, {$tweet['metrics']['likes']} likes\n";
            $tweets_content .= "Credibility Score: {$tweet['credibility_score']}/100\n";
            $tweets_content .= "---\n";
        }
        
        $system_prompt = "You are a professional social media journalist. Your task is to analyze these Twitter/X posts and create a comprehensive news article in {$article_language} about the topic '{$keyword}'.

        **CRITICAL INSTRUCTIONS:**
        1. **Language**: Write the entire article in {$article_language}. This is mandatory.
        2. **Analysis**: Synthesize information from multiple tweets to identify the main story and verify facts
        3. **Verification**: Use your web search ability to verify claims and add context from reliable news sources
        4. **Attribution**: Properly attribute information to Twitter sources while maintaining journalistic standards
        
        **ARTICLE REQUIREMENTS:**
        - Write a 800-1200 word news article
        - Lead with the most important/breaking information
        - Include direct quotes from tweets where appropriate
        - Verify information through additional research
        - Provide context and background from reliable sources
        - End with implications or what comes next
        
        **FORMATTING RULES:**
        - NO H1 headings in content field
        - Start with engaging lead paragraph
        - Use H2 (##) for section headings only
        - No conclusion headings - end naturally
        
        **SOCIAL MEDIA ATTRIBUTION:**
        - Format: \"According to a tweet from @username, ...\"
        - Include follower count for context when relevant: \"@username (2.5M followers)\"
        - Note verification status of major sources
        - Always attribute information properly
        
        **LINK FORMATTING:**
        - When including external links, use descriptive anchor text
        - Example: [Reuters](https://reuters.com/specific-article) reported that...
        - Do NOT use URLs as anchor text
        - Keep anchor text concise (1-3 words)
        
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
            throw new Exception('Invalid response from article generation API. Please try again.');
        }
        
        return [
            'title' => sanitize_text_field($result['title']),
            'subtitle' => sanitize_text_field($result['subtitle'] ?? ''),
            'content' => wp_kses_post($result['content'])
        ];
    }
}