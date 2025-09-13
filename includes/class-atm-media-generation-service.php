<?php
/**
 * ATM Media Generation Service
 * Handles podcast and video content generation
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Media_Generation_Service {
    
    /**
     * Generate podcast script
     */
    public static function generate_podcast_script($params) {
        try {
            $content = wp_strip_all_tags(stripslashes($params['content'] ?? ''));
            $language = sanitize_text_field($params['language'] ?? 'English');
            $post_id = intval($params['post_id'] ?? 0);
            $duration = sanitize_text_field($params['duration'] ?? 'medium');

            if (empty($content)) {
                throw new Exception("Article content is empty. Please write your article first.");
            }

            // For long scripts, use background processing
            if ($duration === 'long') {
                if (!class_exists('ATM_API') || !method_exists('ATM_API', 'queue_script_generation')) {
                    throw new Exception('ATM_API script generation not available');
                }
                
                $job_id = ATM_API::queue_script_generation($post_id, $language, $duration);
                
                return [
                    'success' => true,
                    'job_id' => $job_id,
                    'message' => 'Long script generation started in background...',
                    'status' => 'processing'
                ];
            }

            // For short/medium scripts, process immediately
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_advanced_podcast_script')) {
                throw new Exception('ATM_API podcast script generation not available');
            }
            
            $post = get_post($post_id);
            $article_title = $post ? $post->post_title : 'Article';

            $generated_script = ATM_API::generate_advanced_podcast_script(
                $article_title,
                $content,
                $language,
                $duration
            );

            return [
                'success' => true,
                'script' => $generated_script
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate podcast audio
     */
    public static function generate_podcast_audio($params) {
        try {
            $post_id = intval($params['post_id'] ?? 0);
            $script = wp_unslash($params['script'] ?? '');
            $provider = sanitize_text_field($params['provider'] ?? 'openai');
            $host_a_voice = sanitize_text_field($params['host_a_voice'] ?? 'alloy');
            $host_b_voice = sanitize_text_field($params['host_b_voice'] ?? 'nova');
            
            if (empty($script) || empty($post_id)) {
                throw new Exception("Script and Post ID are required.");
            }

            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'queue_podcast_generation')) {
                throw new Exception('ATM_API podcast generation not available');
            }

            // Start background processing with WordPress cron
            $job_id = ATM_API::queue_podcast_generation($post_id, $script, $host_a_voice, $host_b_voice, $provider);

            return [
                'success' => true,
                'job_id' => $job_id,
                'message' => 'Podcast generation started in background...',
                'status' => 'processing'
            ];

        } catch (Exception $e) {
            error_log('ATM Media Generation Service - Podcast Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search YouTube videos
     */
    public static function search_youtube_videos($params) {
        try {
            $query = sanitize_text_field($params['query'] ?? '');
            $filters = isset($params['filters']) ? (array) $params['filters'] : [];
            
            if (empty($query)) {
                throw new Exception('Search query is required.');
            }
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'search_youtube_videos')) {
                throw new Exception('ATM_API YouTube search not available');
            }
            
            $results = ATM_API::search_youtube_videos($query);
            
            return [
                'success' => true,
                'results' => $results,
                'query' => $query
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get YouTube autocomplete suggestions
     */
    public static function get_youtube_suggestions($params) {
        try {
            $query = sanitize_text_field($params['query'] ?? '');
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'get_youtube_autocomplete_suggestions')) {
                throw new Exception('ATM_API YouTube suggestions not available');
            }
            
            $suggestions = ATM_API::get_youtube_autocomplete_suggestions($query);
            
            return [
                'success' => true,
                'suggestions' => $suggestions
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create video post with embedded content
     */
    public static function create_video_post($params) {
        try {
            $video_data = $params['video_data'] ?? [];
            $post_params = $params['post_params'] ?? [];
            
            if (empty($video_data)) {
                throw new Exception('Video data is required');
            }
            
            // Create embed code
            $embed_code = sprintf(
                '<div class="wp-video" style="width: 100%;"><iframe src="https://www.youtube.com/embed/%s" width="500" height="281" style="width: 100%%; aspect-ratio: 16/9; height: auto;" frameborder="0" allowfullscreen></iframe></div>',
                $video_data['id']
            );
            
            $content = sprintf(
                "<p>%s</p>\n\n%s\n\n<p><a href=\"%s\" target=\"_blank\" rel=\"noopener\">Watch on YouTube</a></p>",
                esc_html($video_data['description']),
                $embed_code,
                esc_url($video_data['url'])
            );
            
            $post_data = [
                'post_title' => wp_strip_all_tags($video_data['title']),
                'post_content' => $content,
                'post_status' => $post_params['post_status'] ?? 'draft',
                'post_author' => $post_params['post_author'] ?? get_current_user_id(),
                'post_category' => $post_params['post_category'] ?? []
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Set video thumbnail as featured image if available and requested
            if (!empty($video_data['thumbnail']) && ($post_params['generate_image'] ?? false)) {
                self::set_featured_image_from_url($post_id, $video_data['thumbnail']);
            }
            
            // Save automation metadata if this is an automated post
            if ($post_params['campaign_id'] ?? false) {
                update_post_meta($post_id, '_atm_automation_generated', true);
                update_post_meta($post_id, '_atm_campaign_id', $post_params['campaign_id']);
                update_post_meta($post_id, '_atm_generation_date', current_time('mysql'));
            }
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check podcast generation progress
     */
    public static function check_podcast_progress($job_id) {
        try {
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'get_podcast_progress')) {
                throw new Exception('ATM_API podcast progress check not available');
            }
            
            $progress = ATM_API::get_podcast_progress($job_id);
            
            return [
                'success' => true,
                'progress' => $progress
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check script generation progress
     */
    public static function check_script_progress($job_id) {
        try {
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'get_script_progress')) {
                throw new Exception('ATM_API script progress check not available');
            }
            
            $progress = ATM_API::get_script_progress($job_id);
            
            return [
                'success' => true,
                'progress' => $progress
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Helper: Set featured image from URL
     */
    private static function set_featured_image_from_url($post_id, $image_url) {
        $upload_dir = wp_upload_dir();
        $image_data = wp_remote_get($image_url);
        
        if (is_wp_error($image_data)) {
            return false;
        }
        
        $filename = basename($image_url);
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.jpg';
        }
        
        $file = $upload_dir['path'] . '/' . $filename;
        file_put_contents($file, wp_remote_retrieve_body($image_data));
        
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
}