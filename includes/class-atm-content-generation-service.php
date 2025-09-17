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
            // For automation, post_id is not required initially
            $is_automation = isset($params['is_automation']) && $params['is_automation'] === true;
            $is_title_based = isset($params['is_title_based']) && $params['is_title_based'] === true;
            
            // Validate required parameters
            $required_params = $is_automation ? ['keyword'] : ['keyword', 'post_id'];
            foreach ($required_params as $param) {
                if (empty($params[$param])) {
                    throw new Exception("Missing required parameter: $param");
                }
            }
            
            // Extract parameters with defaults
            $keyword = sanitize_text_field($params['keyword']);
            $article_title = isset($params['article_title']) ? sanitize_text_field($params['article_title']) : '';
            $post_id = intval($params['post_id'] ?? 0); // Allow 0 for automation
            $model_override = isset($params['model']) ? sanitize_text_field($params['model']) : '';
            $style_key = isset($params['writing_style']) ? sanitize_key($params['writing_style']) : 'default_seo';
            $custom_prompt = isset($params['custom_prompt']) ? wp_kses_post(stripslashes($params['custom_prompt'])) : '';
            $word_count = isset($params['word_count']) ? intval($params['word_count']) : 0;
            $creativity_level = isset($params['creativity_level']) ? sanitize_text_field($params['creativity_level']) : 'high';
            
            if (empty($article_title) && empty($keyword)) {
                throw new Exception("Please provide a keyword or an article title.");
            }
            
            // NEW: Handle title-based generation differently
            if ($is_title_based && !empty($article_title)) {
                error_log("ATM Title-Based: Generating content for specific title: {$article_title}");
                return self::generate_title_based_content($params);
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

            // Convert Markdown to HTML
            $final_content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $final_content);
            $final_content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $final_content);
            $final_content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $final_content);
            // Convert headers if any slip through
            $final_content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $final_content);
            $final_content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $final_content);

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
     * NEW METHOD: Generate content based on a specific pre-generated title
     */
    private static function generate_title_based_content($params) {
        $keyword = sanitize_text_field($params['keyword']);
        $article_title = sanitize_text_field($params['article_title']);
        $post_id = intval($params['post_id'] ?? 0);
        $model_override = isset($params['model']) ? sanitize_text_field($params['model']) : '';
        $style_key = isset($params['writing_style']) ? sanitize_key($params['writing_style']) : 'default_seo';
        $custom_prompt = isset($params['custom_prompt']) ? wp_kses_post(stripslashes($params['custom_prompt'])) : '';
        $word_count = isset($params['word_count']) ? intval($params['word_count']) : 0;
        $creativity_level = isset($params['creativity_level']) ? sanitize_text_field($params['creativity_level']) : 'high';
        $enable_web_search = isset($params['enable_web_search']) ? $params['enable_web_search'] : true;
        $include_subheadlines = isset($params['include_subheadlines']) ? $params['include_subheadlines'] : true;
        
        error_log("ATM Title-Based Content: Generating for title: {$article_title}");
        
        // Perform web search for the specific title/topic if enabled
        $web_research_context = '';
        if ($enable_web_search) {
            try {
                if (class_exists('ATM_API') && method_exists('ATM_API', 'perform_web_search')) {
                    $search_results = ATM_API::perform_web_search($article_title, 5);
                    if (!empty($search_results)) {
                        $research_info = [];
                        foreach (array_slice($search_results, 0, 3) as $result) {
                            if (isset($result['title']) && isset($result['snippet'])) {
                                $research_info[] = $result['title'] . ': ' . $result['snippet'];
                            }
                        }
                        $web_research_context = "\n\nRecent information about this topic:\n" . implode("\n", $research_info);
                    }
                }
            } catch (Exception $e) {
                error_log("ATM Title-Based: Web search failed: " . $e->getMessage());
            }
        }
        
        // Get writing style template
        $writing_styles = method_exists('ATM_API', 'get_writing_styles') ? ATM_API::get_writing_styles() : [];
        if (empty($writing_styles)) {
            $writing_styles = ['default_seo' => ['prompt' => 'Write a professional, SEO-optimized article.']];
        }
        
        $base_prompt = isset($writing_styles[$style_key]) ? $writing_styles[$style_key]['prompt'] : $writing_styles['default_seo']['prompt'];
        if (!empty($custom_prompt)) {
            $base_prompt = $custom_prompt;
        }
        
        // Build the final prompt for title-based generation
        $word_count_instruction = $word_count > 0 ? " Target length: approximately {$word_count} words." : "";
        $subheadline_instruction = $include_subheadlines ? " Include clear subheadings (H2, H3) to structure the content." : "";
        
        $final_prompt = "Write a comprehensive article with the exact title: '{$article_title}'

Main keyword focus: {$keyword}
{$word_count_instruction}
{$subheadline_instruction}

Writing requirements:
{$base_prompt}

{$web_research_context}

IMPORTANT FORMATTING RULES:
- Use the exact title provided above as the article title
- The content should NOT start with the title - begin with the introductory paragraph
- Use H2 (##) for main section headings, never H1 (#)
- Do NOT include conclusion headings like 'Conclusion', 'Summary', 'Final Thoughts'
- End naturally with a concluding paragraph without any heading above it
- Make the article informative, engaging, and valuable to readers interested in {$keyword}

Return the response as JSON:
{
    \"title\": \"{$article_title}\",
    \"content\": \"Complete article content in markdown format\",
    \"word_count\": estimated_word_count
}";

        // Apply post-specific shortcode replacements if post exists
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && class_exists('ATM_API') && method_exists('ATM_API', 'replace_prompt_shortcodes')) {
                $final_prompt = ATM_API::replace_prompt_shortcodes($final_prompt, $post);
            }
        }

        // Generate content using AI
        if (!class_exists('ATM_API') || !method_exists('ATM_API', 'enhance_content_with_openrouter')) {
            throw new Exception('ATM_API class or enhance_content_with_openrouter method not available');
        }
        
        $raw_response = ATM_API::enhance_content_with_openrouter(
            ['content' => $article_title],
            $final_prompt,
            $model_override ?: get_option('atm_article_model'),
            true, // JSON mode
            $enable_web_search,
            $creativity_level
        );
        
        // Parse JSON response
        $json_string = trim($raw_response);
        if (!str_starts_with($json_string, '{')) {
            if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
                $json_string = $matches[0];
            } else {
                throw new Exception('The AI returned a non-JSON response for title-based generation.');
            }
        }

        $result = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            error_log('ATM Title-Based - Invalid JSON from AI: ' . $json_string);
            throw new Exception('The AI returned an invalid response structure for title-based generation.');
        }

        $generated_title = $result['title'] ?? $article_title;
        $article_content = trim($result['content']);

        if (empty($article_content)) {
            throw new Exception('Generated content is empty for title-based generation.');
        }

        // Convert Markdown to HTML
        $article_content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $article_content);
        $article_content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $article_content);
        $article_content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $article_content);
        $article_content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $article_content);
        $article_content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $article_content);

        // Save subtitle if post exists (title-based usually doesn't need subtitles)
        if ($post_id > 0) {
            update_post_meta($post_id, '_atm_title_based_automation', true);
            update_post_meta($post_id, '_atm_source_title', $article_title);
        }

        error_log("ATM Title-Based Content: Successfully generated " . str_word_count(strip_tags($article_content)) . " words for: {$article_title}");

        return [
            'success' => true,
            'article_title' => $generated_title,
            'article_content' => $article_content,
            'subtitle' => '', // Title-based doesn't typically need subtitles since title is pre-defined
            'word_count' => $result['word_count'] ?? str_word_count(strip_tags($article_content)),
            'generation_method' => 'title_based',
            'source_title' => $article_title
        ];
    }

    /**
     * ADD this helper method to your ATM_Content_Generation_Service class
     * Get writing style template for title-based content
     */
    private static function get_writing_style_template($style) {
        $templates = [
            'default_seo' => 'Write in a clear, SEO-optimized style. Use natural keyword integration, provide valuable information, and structure content for readability. Include actionable insights and practical advice.',
            'professional' => 'Use a professional, business-oriented tone. Write formally but accessibly, focusing on expertise and credibility. Include industry insights and professional perspectives.',
            'conversational' => 'Write in a friendly, conversational tone as if talking to a friend. Use "you" to address readers directly, include personal touches, and make complex topics easy to understand.',
            'technical' => 'Use precise technical language appropriate for expert audiences. Include detailed explanations, technical specifications, and in-depth analysis.',
            'news' => 'Write in a journalistic style with clear, factual reporting. Start with the most important information, use short paragraphs, and maintain objectivity.',
            'educational' => 'Focus on teaching and learning outcomes. Use clear explanations, examples, and step-by-step guidance. Structure content for progressive understanding.'
        ];
        
        return $templates[$style] ?? $templates['default_seo'];
    }

    /**
     * ADD this helper method to your ATM_Content_Generation_Service class
     * Basic markdown to HTML conversion (fallback)
     */
    private static function basic_markdown_to_html($markdown) {
        $html = $markdown;
        
        // Convert headers
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        
        // Convert bold and italic
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        
        // Convert links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Convert line breaks to paragraphs
        $html = wpautop($html);
        
        return $html;
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
                case 'openrouter':
                    if (method_exists('ATM_API', 'generate_image_with_openrouter')) {
                        $image_data = ATM_API::generate_image_with_openrouter($final_prompt, $size);
                        $is_url = false; // Changed to false since we get binary data
                    }
                    break;
                case 'nanobanana':
                    if (method_exists('ATM_API', 'generate_image_with_gemini_nanobanana_vertex')) {
                        $image_data = ATM_API::generate_image_with_gemini_nanobanana_vertex($final_prompt, $size);
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
    3. \"content\": The full article text, formatted using clean HTML with proper HTML tags.

    **CRITICAL CONTENT RULES:**
    - The `content` field must NOT contain any top-level H1 headings (formatted as `<h1>`). Use `<h2>` for all main section headings.
    - The `content` field must NOT start with a title or any heading. It must begin directly with the first paragraph of the introduction.
    - Do NOT include a final heading titled \"Conclusion\", \"Summary\", \"Final Thoughts\", \"In Summary\", \"To Conclude\", \"Wrapping Up\", \"Looking Ahead\", \"What's Next\", \"The Bottom Line\", \"Key Takeaways\", or any similar conclusory heading.
    - Do NOT start with generic section headers like \"Introduction\", \"Overview\", \"Background\".
    - End with a natural concluding paragraph that has no heading above it.
    - Write in a natural, flowing manner without artificial structure markers.

    **HTML FORMATTING REQUIREMENTS:**
    - Use proper HTML tags: <p> for paragraphs, <h2> for headings, <strong> for bold, <em> for italics
    - Format all links as proper HTML: <a href=\"URL\">anchor text</a>
    - NEVER use Markdown syntax like [text](url) - always use HTML <a> tags
    - Use <ul> and <li> for lists if needed
    - Ensure all HTML is valid and properly closed

    **LINK FORMATTING RULES:**
    - Format links as: <a href=\"https://example.com/article\">descriptive text</a>
    - Use ONLY 1-3 descriptive words as anchor text
    - Keep anchor text extremely concise (maximum 2 words)
    - Make links feel natural within the sentence flow
    - Example: According to <a href=\"https://bbc.com/article\">BBC News</a>, the incident...
    - Example: <a href=\"https://reuters.com/report\">Reuters</a> reported that...

    **TITLE REQUIREMENTS (if generating):**
    - Must be compelling and clickable (8-18 words)
    - Should perfectly reflect the specific angle provided
    - Include the keyword naturally
    - Use power words and emotional triggers appropriate to the topic
    - Avoid generic phrases and make it specific to the angle

    **CONTENT REQUIREMENTS:**
    - Must target the exact angle specified above
    - Begin with an engaging hook paragraph that relates to the angle
    - Use natural transitions between sections
    - Include current, factual information from web search
    - Focus on providing genuine value to the target audience
    - Maintain the specific perspective throughout the entire article

    Please return your response as a properly formatted JSON object with HTML-formatted content.";
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

     /**
     * Generate trending articles
     */
    public static function generate_trending_articles($params) {
        try {
            $topics = isset($params['trending_topics']) ? $params['trending_topics'] : [];
            $settings = isset($params['settings']) ? $params['settings'] : [];
            $language = sanitize_text_field($params['language'] ?? 'English');
            
            if (empty($topics) || empty($settings)) {
                throw new Exception('Missing topics or settings.');
            }
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_article_from_trend')) {
                throw new Exception('ATM_API trending generation not available');
            }

            $successful_count = 0;
            $created_posts = [];
            
            foreach ($topics as $topic) {
                try {
                    $article_data = ATM_API::generate_article_from_trend($topic, $settings, $language);
                    $post_status = ($settings['autoPublish'] ?? false) ? 'publish' : 'draft';
                    
                    // Convert markdown to HTML if needed
                    if (class_exists('Parsedown')) {
                        $Parsedown = new Parsedown();
                        $html_content = $Parsedown->text($article_data['content']);
                    } else {
                        $html_content = $article_data['content'];
                    }

                    $post_id = wp_insert_post([
                        'post_title'   => sanitize_text_field($article_data['title']),
                        'post_content' => wp_kses_post($html_content),
                        'post_status'  => $post_status,
                        'post_author'  => get_current_user_id(),
                    ]);

                    if (!is_wp_error($post_id)) {
                        // Save subtitle if provided
                        if (!empty($article_data['subheadline'])) {
                            $subtitle_key = get_option('atm_theme_subtitle_key', '_bunyad_sub_title');
                            update_post_meta($post_id, $subtitle_key, sanitize_text_field($article_data['subheadline']));
                            update_post_meta($post_id, '_atm_subtitle', sanitize_text_field($article_data['subheadline']));
                        }
                        
                        $successful_count++;
                        $created_posts[] = $post_id;
                    }

                } catch (Exception $e) {
                    error_log("ATM Trending Article Failed for '{$topic['title']}': " . $e->getMessage());
                    continue;
                }
            }

            if ($successful_count === 0) {
                throw new Exception('Could not generate any articles. Please check API logs or try again.');
            }

            return [
                'success' => true,
                'successful_count' => $successful_count,
                'created_posts' => $created_posts
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate single trending article
     */
    public static function generate_single_trending_article($params) {
        try {
            $topic = isset($params['trending_topic']) ? $params['trending_topic'] : [];
            $settings = isset($params['settings']) ? $params['settings'] : [];
            $language = sanitize_text_field($params['language'] ?? 'English');

            if (empty($topic)) {
                throw new Exception('Missing topic for article generation.');
            }
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_article_from_trend')) {
                throw new Exception('ATM_API trending generation not available');
            }
            
            $article_data = ATM_API::generate_article_from_trend($topic, $settings, $language);
            
            return [
                'success' => true,
                'article_title' => $article_data['title'],
                'article_content' => $article_data['content'],
                'subtitle' => $article_data['subheadline'] ?? ''
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate multipage article title
     */
    public static function generate_multipage_title($params) {
        try {
            $keyword = sanitize_text_field($params['keyword'] ?? '');
            $page_count = intval($params['page_count'] ?? 5);
            $model = sanitize_text_field($params['model'] ?? '');
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_multipage_title')) {
                throw new Exception('ATM_API multipage generation not available');
            }
            
            $title = ATM_API::generate_multipage_title($keyword, $page_count, $model);
            
            return [
                'success' => true,
                'article_title' => $title
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate multipage outline
     */
    public static function generate_multipage_outline($params) {
        try {
            $outline_params = [
                'article_title' => sanitize_text_field($params['article_title'] ?? ''),
                'page_count' => intval($params['page_count'] ?? 5),
                'model' => sanitize_text_field($params['model'] ?? ''),
                'writing_style' => sanitize_text_field($params['writing_style'] ?? ''),
                'include_subheadlines' => filter_var($params['include_subheadlines'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'enable_web_search' => filter_var($params['enable_web_search'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_multipage_outline')) {
                throw new Exception('ATM_API multipage generation not available');
            }
            
            $outline = ATM_API::generate_multipage_outline($outline_params);
            
            return [
                'success' => true,
                'outline' => $outline
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate multipage content
     */
    public static function generate_multipage_content($params) {
        try {
            $content_params = [
                'article_title' => sanitize_text_field($params['article_title'] ?? ''),
                'page_number' => intval($params['page_number'] ?? 1),
                'total_pages' => intval($params['total_pages'] ?? 5),
                'page_outline' => wp_kses_post_deep($params['page_outline'] ?? []),
                'words_per_page' => intval($params['words_per_page'] ?? 500),
                'model' => sanitize_text_field($params['model'] ?? ''),
                'writing_style' => sanitize_text_field($params['writing_style'] ?? ''),
                'custom_prompt' => wp_kses_post(stripslashes($params['custom_prompt'] ?? '')),
                'include_subheadlines' => filter_var($params['include_subheadlines'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'enable_web_search' => filter_var($params['enable_web_search'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
            
            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'generate_multipage_content')) {
                throw new Exception('ATM_API multipage generation not available');
            }
            
            $content = ATM_API::generate_multipage_content($content_params);
            
            return [
                'success' => true,
                'page_content' => $content
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
}
/**
 * Create multipage article
 */
public static function create_multipage_article($params) {
    try {
        $post_id = intval($params['post_id'] ?? 0);
        $main_title = sanitize_text_field($params['main_title'] ?? '');
        $pages_data = $params['pages'] ?? [];

        if (!$post_id || empty($main_title) || empty($pages_data)) {
            throw new Exception('Missing required data for multipage creation.');
        }
        
        // Sanitize the pages data
        $sanitized_pages = [];
        if (class_exists('Parsedown')) {
            $Parsedown = new Parsedown();
        }
        
        foreach ($pages_data as $page) {
            $content_html = isset($page['content']) ? $page['content'] : '';
            if (isset($Parsedown)) {
                $content_html = wp_kses_post($Parsedown->text($content_html));
            }
            
            $sanitized_pages[] = [
                'title' => sanitize_text_field($page['title'] ?? ''),
                'content_html' => $content_html
            ];
        }

        // Save all pages data to a single post meta field
        update_post_meta($post_id, '_atm_multipage_data', $sanitized_pages);

        // Prepare the content for the editor, which is just the shortcode
        $editor_content = '[atm_multipage_article]';

        return [
            'success' => true,
            'message' => 'Multipage article data saved.',
            'editor_content' => $editor_content
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Fetch trending topics
 */
public static function fetch_trending_topics($params) {
    try {
        $keyword = sanitize_text_field($params['keyword'] ?? '');
        $region = sanitize_text_field($params['region'] ?? 'US');
        $language = sanitize_text_field($params['language'] ?? 'en');
        $date = sanitize_text_field($params['date'] ?? 'now 7-d');
        $force_fresh = isset($params['force_fresh']) && $params['force_fresh'] === true;

        if (!class_exists('ATM_API') || !method_exists('ATM_API', 'fetch_trending_topics')) {
            throw new Exception('ATM_API trending topics fetch not available');
        }

        $result = ATM_API::fetch_trending_topics($keyword, $region, $language, $date, $force_fresh);

        return [
            'success' => true,
            'topics' => $result
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
}