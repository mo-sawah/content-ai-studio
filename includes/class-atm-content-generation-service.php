<?php
/**
 * ATM Content Generation Service
 * Unified service for all content generation (manual and automated)
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Content_Generation_Service {
    
    /**
     * Generate article content with angle intelligence
     * 
     * @param array $params Content generation parameters
     * @return array Result with success/error status and generated content
     */
    public static function generate_article_content($params) {
        try {
            // Validate required parameters
            $required_params = ['keyword', 'post_id'];
            foreach ($required_params as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            // Extract parameters with defaults
            $keyword = sanitize_text_field($params['keyword']);
            $article_title = isset($params['article_title']) ? sanitize_text_field($params['article_title']) : '';
            $post_id = intval($params['post_id']);
            $model_override = isset($params['model']) ? sanitize_text_field($params['model']) : '';
            $style_key = isset($params['writing_style']) ? sanitize_key($params['writing_style']) : 'default_seo';
            $custom_prompt = isset($params['custom_prompt']) ? wp_kses_post(stripslashes($params['custom_prompt'])) : '';
            $word_count = isset($params['word_count']) ? intval($params['word_count']) : 0;
            $creativity_level = isset($params['creativity_level']) ? sanitize_text_field($params['creativity_level']) : 'high';
            $is_automation = isset($params['is_automation']) ? (bool)$params['is_automation'] : false;
            
            if (empty($article_title) && empty($keyword)) {
                throw new Exception("Please provide a keyword or an article title.");
            }
            
            // Ensure the angles table exists
            ATM_Content_Generator_Utility::ensure_angles_table_exists();
            
            $final_title = $article_title;
            $angle_data = null;
            
            // STAGE 1: Generate intelligent angle if no title provided
            if (empty($article_title) && !empty($keyword)) {
                $tracking_keyword = $keyword;
                $previous_angles = ATM_Content_Generator_Utility::get_previous_angles($tracking_keyword);
                
                error_log("ATM Debug: Found " . count($previous_angles) . " previous angles for: " . $tracking_keyword);
                
                // Generate intelligent angle with classification
                $angle_data = ATM_Content_Generator_Utility::generate_intelligent_angle_classification($tracking_keyword, $previous_angles);
                
                error_log("ATM Debug: Generated intelligent angle: " . $angle_data['angle_description']);
                
                // Store the angle BEFORE content generation
                $angle_title = $is_automation ? '[Automation Generated]' : '[AI Generated]';
                ATM_Content_Generator_Utility::store_content_angle($tracking_keyword, $angle_data['angle_description'], $angle_title);
                
                // Clear final_title so Stage 2 knows to generate one
                $final_title = '';
            }
            
            // STAGE 2: Build comprehensive system prompt for title + content generation
            $writing_styles = method_exists('ATM_API', 'get_writing_styles') ? ATM_API::get_writing_styles() : [];
            if (empty($writing_styles)) {
                $writing_styles = ['default_seo' => ['prompt' => 'Write a professional, SEO-optimized article.']];
            }
            
            $base_prompt = isset($writing_styles[$style_key]) ? $writing_styles[$style_key]['prompt'] : $writing_styles['default_seo']['prompt'];
            if (!empty($custom_prompt)) {
                $base_prompt = $custom_prompt;
            }
            
            // Add intelligent angle context if generated
            if ($angle_data) {
                $base_prompt .= ATM_Content_Generator_Utility::build_comprehensive_angle_context($angle_data, $keyword);
            }
            
            $output_instructions = self::get_enhanced_output_instructions($final_title, $is_automation);
            $system_prompt = $base_prompt . "\n\n" . $output_instructions;
            
            // Apply post-specific shortcode replacements if post exists
            if ($post_id > 0) {
                $post = get_post($post_id);
                if ($post && class_exists('ATM_API') && method_exists('ATM_API', 'replace_prompt_shortcodes')) {
                    $system_prompt = ATM_API::replace_prompt_shortcodes($system_prompt, $post);
                }
            }
            
            if ($word_count > 0) {
                $system_prompt .= " The final article should be approximately " . $word_count . " words long.";
            }
            
            // STAGE 3: Single API call for title + content with web search
            $user_content = empty($final_title) ? $keyword : $final_title;
            
            error_log("ATM Debug: Making content generation API call");
            
            // Make API call with error handling
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'enhance_content_with_openrouter')) {
                throw new Exception('ATM_API class or enhance_content_with_openrouter method not available');
            }
            
            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $user_content], 
                $system_prompt, 
                $model_override ?: get_option('atm_article_model'), 
                true, // JSON mode
                true, // enable web search for current information
                $creativity_level
            );
            
            // Process response
            $json_string = trim($raw_response);
            if (!str_starts_with($json_string, '{')) {
                if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                    $json_string = $matches[0];
                }
            }

            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
                error_log('Content AI Studio - Invalid JSON from AI: ' . $raw_response);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }

            $generated_title = $result['title'] ?? $final_title;
            $subtitle = $result['subheadline'] ?? $result['subtitle'] ?? '';
            $final_content = trim($result['content']);

            // Update the stored angle with the actual generated title
            if ($angle_data && !empty($generated_title)) {
                ATM_Content_Generator_Utility::update_stored_angle($tracking_keyword, $angle_data['angle_description'], $generated_title);
            }

            // Save subtitle if post exists
            if ($post_id > 0 && !empty($subtitle)) {
                update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
                update_post_meta($post_id, '_atm_subtitle', $subtitle);
            }

            return [
                'success' => true,
                'article_title' => $generated_title,
                'article_content' => $final_content, 
                'subtitle' => $subtitle,
                'angle_data' => $angle_data
            ];
            
        } catch (Exception $e) {
            error_log('ATM Content Generation Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create WordPress post from generated content
     */
    public static function create_post_from_content($content_data, $post_params) {
        try {
            if (!$content_data['success']) {
                throw new Exception($content_data['message']);
            }
            
            // Extract post parameters with defaults
            $post_status = isset($post_params['post_status']) ? $post_params['post_status'] : 'draft';
            $post_author = isset($post_params['post_author']) ? intval($post_params['post_author']) : get_current_user_id();
            $post_category = isset($post_params['post_category']) ? $post_params['post_category'] : [];
            $campaign_id = isset($post_params['campaign_id']) ? intval($post_params['campaign_id']) : null;
            $generate_image = isset($post_params['generate_image']) ? (bool)$post_params['generate_image'] : false;
            
            // Prepare post data
            $post_data = [
                'post_title' => wp_strip_all_tags($content_data['article_title']),
                'post_content' => wp_kses_post($content_data['article_content']),
                'post_status' => $post_status,
                'post_author' => $post_author,
                'post_category' => is_array($post_category) ? $post_category : [$post_category]
            ];
            
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Save subtitle
            if (!empty($content_data['subtitle'])) {
                update_post_meta($post_id, '_bunyad_sub_title', $content_data['subtitle']);
                update_post_meta($post_id, '_atm_subtitle', $content_data['subtitle']);
            }
            
            // Save automation metadata if this is an automated post
            if ($campaign_id) {
                update_post_meta($post_id, '_atm_automation_generated', true);
                update_post_meta($post_id, '_atm_campaign_id', $campaign_id);
                update_post_meta($post_id, '_atm_generation_date', current_time('mysql'));
            }
            
            // Generate featured image if requested
            if ($generate_image) {
                self::generate_featured_image($post_id, $content_data['article_title']);
            }
            
            error_log("ATM Content Generation Service: Successfully created post ID {$post_id}");
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id)
            ];
            
        } catch (Exception $e) {
            error_log('ATM Content Generation Service - Post Creation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate article title only
     */
    public static function generate_article_title($keyword, $title_input = '', $model_override = '') {
        try {
            $topic = !empty($title_input) ? 'the article title: "' . $title_input . '"' : 'the keyword: "' . $keyword . '"';
            if (empty($topic)) {
                throw new Exception("Please provide a keyword or title.");
            }
            
            $system_prompt = 'You are an expert SEO content writer. Use your web search ability to understand the current context and popular phrasing for the given topic. Your task is to generate a single, compelling, SEO-friendly title. Return only the title itself, with no extra text or quotation marks.';
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'enhance_content_with_openrouter')) {
                throw new Exception('ATM_API class or enhance_content_with_openrouter method not available');
            }
            
            $generated_title = ATM_API::enhance_content_with_openrouter(
                ['content' => $topic], 
                $system_prompt, 
                $model_override ?: get_option('atm_article_model')
            );
            
            $cleaned_title = trim($generated_title, " \t\n\r\0\x0B\"");
            
            return [
                'success' => true,
                'article_title' => $cleaned_title
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate featured image for a post
     */
    public static function generate_featured_image($post_id, $title, $prompt_override = '') {
        try {
            if (!class_exists('ATM_API')) {
                throw new Exception('ATM_API class not available');
            }
            
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found.");
            }

            // If no prompt provided, get default
            if (empty(trim($prompt_override))) {
                if (method_exists('ATM_API', 'get_default_image_prompt')) {
                    $prompt = ATM_API::get_default_image_prompt();
                } else {
                    $prompt = 'Create a professional, high-quality featured image for an article titled: [article_title]';
                }
            } else {
                $prompt = $prompt_override;
            }

            // Replace shortcodes in prompt
            if (method_exists('ATM_API', 'replace_prompt_shortcodes')) {
                $final_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);
            } else {
                $final_prompt = str_replace('[article_title]', $title, $prompt);
            }

            // Get image generation settings
            $provider = get_option('atm_image_provider', 'openai');
            $size = get_option('atm_image_size', '1792x1024');
            $quality = get_option('atm_image_quality', 'hd');
            
            $image_data = null;
            $is_url = false;

            // Generate image based on provider
            switch ($provider) {
                case 'google':
                    if (method_exists('ATM_API', 'generate_image_with_google_imagen')) {
                        $image_data = ATM_API::generate_image_with_google_imagen($final_prompt, $size);
                        $is_url = false;
                    }
                    break;
                case 'nanobanana':
                    if (method_exists('ATM_API', 'generate_image_with_gemini_nanobanana')) {
                        $image_data = ATM_API::generate_image_with_gemini_nanobanana($final_prompt, $size);
                        $is_url = false;
                    }
                    break;
                case 'blockflow':
                    if (method_exists('ATM_API', 'generate_image_with_blockflow')) {
                        $image_data = ATM_API::generate_image_with_blockflow($final_prompt, '', $size);
                        $is_url = false;
                    }
                    break;
                case 'openai':
                default:
                    if (method_exists('ATM_API', 'generate_image_with_openai')) {
                        $image_data = ATM_API::generate_image_with_openai($final_prompt, $size, $quality);
                        $is_url = true;
                    }
                    break;
            }
            
            if (!$image_data) {
                throw new Exception('Failed to generate image with provider: ' . $provider);
            }

            // Set image as attachment
            if ($is_url) {
                $attachment_id = self::set_image_from_url($image_data, $post_id);
            } else {
                $attachment_id = self::set_image_from_data($image_data, $post_id, $final_prompt);
            }

            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }

            set_post_thumbnail($post_id, $attachment_id);
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'generated_prompt' => $final_prompt
            ];

        } catch (Exception $e) {
            error_log('ATM Content Generation Service - Featured Image Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enhanced output instructions for content generation
     */
    private static function get_enhanced_output_instructions($final_title, $is_automation = false) {
        $context = $is_automation ? 'AUTOMATION' : 'MANUAL';
        
        return "**{$context} CONTENT GENERATION INSTRUCTIONS:**

**Final Output Format:**
Your entire output MUST be a single, valid JSON object with three keys:
1. \"title\": " . (empty($final_title) ? 'A compelling, specific title that perfectly matches the required angle and keyword. Use the title guidance provided above.' : '"' . $final_title . '"') . "
2. \"subheadline\": A creative and engaging one-sentence subtitle that complements the main title.
3. \"content\": The full article text, formatted using clean HTML.

**CRITICAL CONTENT RULES:**
- The `content` field must NOT contain any top-level H1 headings (formatted as `<h1>`). Use `<h2>` for all main section headings.
- The `content` field must NOT start with a title or any heading. It must begin directly with the first paragraph of the introduction.
- Do NOT include a final heading titled \"Conclusion\", \"Summary\", \"Final Thoughts\", \"In Summary\", \"To Conclude\", \"Wrapping Up\", \"Looking Ahead\", \"What's Next\", \"The Bottom Line\", \"Key Takeaways\", or any similar conclusory heading.
- Do NOT start with generic section headers like \"Introduction\", \"Overview\", \"Background\".
- End with a natural concluding paragraph that has no heading above it.
- Write in a natural, flowing manner without artificial structure markers.

**TITLE REQUIREMENTS (if generating):**
- Must be compelling and clickable (8-18 words)
- Should perfectly reflect the specific angle provided
- Include the keyword naturally
- Use power words and emotional triggers appropriate to the topic
- Avoid generic phrases and make it specific to the angle

**LINK FORMATTING RULES:**
- When including external links, NEVER use the website URL as the anchor text
- Use ONLY 1-3 descriptive words as anchor text
- Keep anchor text extremely concise (maximum 2 words)
- Make links feel natural within the sentence flow
- Ensure all information is current and accurate using web search data

**CONTENT REQUIREMENTS:**
- Must target the exact angle specified above
- Begin with an engaging hook paragraph that relates to the angle
- Use natural transitions between sections
- Include current, factual information from web search
- Focus on providing genuine value to the target audience
- Maintain the specific perspective throughout the entire article";
    }
    
    /**
     * Helper: Set image from URL
     */
    private static function set_image_from_url($url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = array();
        preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }
        
        return $id;
    }

    /**
     * Helper: Set image from binary data
     */
    private static function set_image_from_data($image_data, $post_id, $prompt) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $upload_dir = wp_upload_dir();
        $filename = 'ai-image-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents($filepath, $image_data);
        
        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename($filepath),
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field($prompt),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }
}