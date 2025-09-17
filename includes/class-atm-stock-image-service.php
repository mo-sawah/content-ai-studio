<?php
/**
 * ATM Stock Image Service
 * Handles stock photo API integrations with duplicate prevention
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Stock_Image_Service {
    
    /**
     * Get stock image with smart search and duplicate prevention
     */
    public static function get_stock_featured_image($post_id, $title, $keyword) {
        try {
            // Generate optimized search queries
            $search_queries = self::generate_search_queries($title, $keyword);
            
            // Try each API provider in order of preference
            $providers = ['pexels', 'unsplash', 'pixabay'];
            
            foreach ($providers as $provider) {
                foreach ($search_queries as $query) {
                    $result = self::search_and_download_image($provider, $query, $post_id);
                    if ($result['success']) {
                        // Save image metadata
                        self::save_image_metadata($post_id, $result, $provider, $query);
                        return $result;
                    }
                }
            }
            
            throw new Exception('No suitable stock images found from any provider');
            
        } catch (Exception $e) {
            error_log('ATM Stock Image Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Generate optimized search queries from title and keyword
     */
    private static function generate_search_queries($title, $keyword) {
        $queries = [];
        
        // Extract main concepts from title
        $title_concepts = self::extract_concepts_from_title($title);
        
        // Priority 1: Combine keyword with main title concepts
        if (!empty($title_concepts)) {
            $queries[] = $keyword . ' ' . implode(' ', array_slice($title_concepts, 0, 2));
        }
        
        // Priority 2: Just the keyword
        $queries[] = $keyword;
        
        // Priority 3: Main title concepts only
        if (!empty($title_concepts)) {
            $queries[] = implode(' ', array_slice($title_concepts, 0, 3));
        }
        
        // Priority 4: Broader related terms
        $related_terms = self::get_related_terms($keyword);
        if (!empty($related_terms)) {
            $queries[] = $related_terms[0];
        }
        
        return array_unique($queries);
    }
    
    /**
     * Extract meaningful concepts from article title
     */
    private static function extract_concepts_from_title($title) {
        // Remove common stop words
        $stop_words = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'how', 'what', 'why', 'when', 'where', 'best', 
            'top', 'guide', 'tips', 'ways', 'things', 'you', 'your', 'this', 'that'
        ];
        
        // Clean and split title
        $words = preg_split('/\W+/', strtolower($title));
        $concepts = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stop_words) && !is_numeric($word)) {
                $concepts[] = $word;
            }
        }
        
        return array_slice($concepts, 0, 5); // Return top 5 concepts
    }
    
    /**
     * Get related search terms for broader results
     */
    private static function get_related_terms($keyword) {
        // Simple related terms mapping - you could expand this or use an API
        $related_map = [
            'technology' => ['computers', 'digital', 'innovation'],
            'business' => ['office', 'professional', 'corporate'],
            'health' => ['medical', 'wellness', 'fitness'],
            'education' => ['learning', 'school', 'study'],
            'travel' => ['vacation', 'tourism', 'journey'],
            'food' => ['cooking', 'restaurant', 'cuisine'],
            'finance' => ['money', 'investment', 'banking'],
        ];
        
        $keyword_lower = strtolower($keyword);
        foreach ($related_map as $category => $terms) {
            if (strpos($keyword_lower, $category) !== false) {
                return $terms;
            }
        }
        
        return [];
    }
    
    /**
     * Search and download image from specific provider
     */
    private static function search_and_download_image($provider, $query, $post_id) {
        $image_data = self::search_stock_image($provider, $query);
        
        if (!$image_data) {
            return ['success' => false, 'message' => "No results from {$provider}"];
        }
        
        // Check if this image was already used
        if (self::is_image_already_used($image_data['url'])) {
            return ['success' => false, 'message' => 'Image already used'];
        }
        
        // Download and set as featured image
        $attachment_id = self::download_and_attach_image($image_data['url'], $post_id, $image_data);
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            
            // Mark image as used
            self::mark_image_as_used($image_data['url'], $post_id, $provider);
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'provider' => $provider,
                'image_data' => $image_data,
                'search_query' => $query
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to download image'];
    }
    
    /**
     * Search stock images by provider
     */
    private static function search_stock_image($provider, $query) {
        switch ($provider) {
            case 'pexels':
                return self::search_pexels($query);
            case 'unsplash':
                return self::search_unsplash($query);
            case 'pixabay':
                return self::search_pixabay($query);
            default:
                return null;
        }
    }
    
    /**
     * Search Pexels API
     */
    private static function search_pexels($query) {
        $api_key = get_option('atm_pexels_api_key');
        if (empty($api_key)) {
            return null;
        }
        
        $url = "https://api.pexels.com/v1/search?" . http_build_query([
            'query' => $query,
            'per_page' => 10,
            'orientation' => 'landscape',
            'size' => 'large'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => $api_key],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['photos'][0])) {
            $photo = $body['photos'][0];
            return [
                'url' => $photo['src']['large'],
                'photographer' => $photo['photographer'],
                'photographer_url' => $photo['photographer_url'],
                'source_url' => $photo['url'],
                'alt' => $photo['alt'] ?? $query
            ];
        }
        
        return null;
    }
    
    /**
     * Search Unsplash API
     */
    private static function search_unsplash($query) {
        $api_key = get_option('atm_unsplash_api_key');
        if (empty($api_key)) {
            return null;
        }
        
        $url = "https://api.unsplash.com/search/photos?" . http_build_query([
            'query' => $query,
            'per_page' => 10,
            'orientation' => 'landscape'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Client-ID ' . $api_key],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['results'][0])) {
            $photo = $body['results'][0];
            return [
                'url' => $photo['urls']['regular'],
                'photographer' => $photo['user']['name'],
                'photographer_url' => $photo['user']['links']['html'],
                'source_url' => $photo['links']['html'],
                'alt' => $photo['alt_description'] ?? $query
            ];
        }
        
        return null;
    }
    
    /**
     * Search Pixabay API
     */
    private static function search_pixabay($query) {
        $api_key = get_option('atm_pixabay_api_key');
        if (empty($api_key)) {
            return null;
        }
        
        $url = "https://pixabay.com/api/?" . http_build_query([
            'key' => $api_key,
            'q' => $query,
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'min_width' => 1920,
            'per_page' => 10
        ]);
        
        $response = wp_remote_get($url, ['timeout' => 15]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['hits'][0])) {
            $photo = $body['hits'][0];
            return [
                'url' => $photo['webformatURL'],
                'photographer' => $photo['user'],
                'photographer_url' => 'https://pixabay.com/users/' . $photo['user'] . '-' . $photo['user_id'] . '/',
                'source_url' => $photo['pageURL'],
                'alt' => $photo['tags']
            ];
        }
        
        return null;
    }
    
    /**
     * Check if image URL was already used
     */
    private static function is_image_already_used($image_url) {
        global $wpdb;
        
        $url_hash = md5($image_url);
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_atm_stock_image_hash' AND meta_value = %s",
            $url_hash
        ));
        
        return $count > 0;
    }
    
    /**
     * Mark image as used
     */
    private static function mark_image_as_used($image_url, $post_id, $provider) {
        $url_hash = md5($image_url);
        update_post_meta($post_id, '_atm_stock_image_hash', $url_hash);
        update_post_meta($post_id, '_atm_stock_image_provider', $provider);
        update_post_meta($post_id, '_atm_stock_image_url', $image_url);
        update_post_meta($post_id, '_atm_image_used_at', current_time('mysql'));
    }
    
    /**
     * Download image and create attachment
     */
    private static function download_and_attach_image($image_url, $post_id, $image_data) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = [];
        $file_array['name'] = 'stock-image-' . $post_id . '-' . time() . '.jpg';
        $file_array['tmp_name'] = $tmp;
        
        $attachment_id = media_handle_sideload($file_array, $post_id, $image_data['alt']);
        
        if (!is_wp_error($attachment_id)) {
            // Set alt text and description
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt']);
        }
        
        return $attachment_id;
    }
    
    /**
     * Save comprehensive image metadata
     */
    private static function save_image_metadata($post_id, $result, $provider, $query) {
        $image_data = $result['image_data'];
        
        // Save detailed metadata
        update_post_meta($post_id, '_atm_image_source', 'stock');
        update_post_meta($post_id, '_atm_stock_provider', $provider);
        update_post_meta($post_id, '_atm_stock_search_query', $query);
        update_post_meta($post_id, '_atm_stock_photographer', $image_data['photographer']);
        update_post_meta($post_id, '_atm_stock_photographer_url', $image_data['photographer_url']);
        update_post_meta($post_id, '_atm_stock_source_url', $image_data['source_url']);
        
        // Generate attribution text
        $attribution = self::generate_attribution($image_data, $provider);
        update_post_meta($post_id, '_atm_image_attribution', $attribution);
    }
    
    /**
     * Generate proper attribution text
     */
    private static function generate_attribution($image_data, $provider) {
        $photographer = $image_data['photographer'];
        $photographer_url = $image_data['photographer_url'];
        $provider_name = ucfirst($provider);
        
        return "Photo by <a href=\"{$photographer_url}\" target=\"_blank\" rel=\"noopener\">{$photographer}</a> on {$provider_name}";
    }
    
    /**
     * Get image attribution for display
     */
    public static function get_image_attribution($post_id) {
        return get_post_meta($post_id, '_atm_image_attribution', true);
    }
    
    /**
     * Clean up old unused image records
     */
    public static function cleanup_old_image_records() {
        global $wpdb;
        
        // Remove records older than 30 days for posts that no longer exist
        $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_atm_stock_image_hash' 
             AND (p.ID IS NULL OR pm.meta_value IN (
                 SELECT meta_value FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_atm_image_used_at' 
                 AND meta_value < %s
             ))",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
    }
}