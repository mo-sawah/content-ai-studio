<?php
// Add to your main plugin file or create a separate listicle handler

class ATM_Listicle_Generator {
    
    public function __construct() {
        error_log('ATM: Listicle class constructor called');
        add_action('wp_ajax_generate_listicle_title', array($this, 'generate_listicle_title'));
        add_action('wp_ajax_generate_listicle_content', array($this, 'generate_listicle_content'));
        error_log('ATM: Listicle AJAX actions registered');
    }

    /**
     * Generate a compelling listicle title
     */
    public function generate_listicle_title() {
        check_ajax_referer('atm_studio_nonce', 'nonce');
        
        $topic = sanitize_text_field($_POST['topic']);
        $item_count = intval($_POST['item_count']);
        $category = sanitize_text_field($_POST['category']);
        $model = sanitize_text_field($_POST['model']);
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            return;
        }

        // Create title generation prompt
        $prompt = $this->build_title_prompt($topic, $item_count, $category);
        
        try {
            $ai_response = $this->call_ai_service($prompt, $model);
            
            if ($ai_response && !empty($ai_response['title'])) {
                wp_send_json_success(array(
                    'article_title' => $ai_response['title']
                ));
            } else {
                throw new Exception('Failed to generate title');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Title generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate full listicle content
     */
    public function generate_listicle_content() {
        check_ajax_referer('atm_studio_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $title = sanitize_text_field($_POST['article_title']);
        $topic = sanitize_text_field($_POST['topic']);
        $item_count = intval($_POST['item_count']);
        $category = sanitize_text_field($_POST['category']);
        $include_pricing = $_POST['include_pricing'] === 'true';
        $include_ratings = $_POST['include_ratings'] === 'true';
        $model = sanitize_text_field($_POST['model']);
        $custom_prompt = sanitize_textarea_field($_POST['custom_prompt']);

        if (empty($title) && empty($topic)) {
            wp_send_json_error('Title or topic is required');
            return;
        }

        // Build comprehensive listicle prompt
        $prompt = $this->build_content_prompt($title, $topic, $item_count, $category, $include_pricing, $include_ratings, $custom_prompt);
        
        try {
            $ai_response = $this->call_ai_service($prompt, $model);
            
            if ($ai_response && !empty($ai_response['content'])) {
                // Generate the formatted HTML content
                $html_content = $this->format_listicle_html($ai_response, $title);
                
                wp_send_json_success(array(
                    'article_content' => $html_content,
                    'subtitle' => $ai_response['subtitle'] ?? ''
                ));
            } else {
                throw new Exception('Failed to generate content');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Content generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Build title generation prompt
     */
    private function build_title_prompt($topic, $item_count, $category) {
        return "Create a compelling listicle title for the following:

Topic: {$topic}
Number of items: {$item_count}
Category: {$category}

Requirements:
- Make it clickable and SEO-friendly
- Include the number of items
- Use power words that drive engagement
- Keep it under 60 characters for SEO
- Make it specific and valuable

Examples of good listicle titles:
- '10 Best Project Management Tools for Small Teams in 2024'
- '15 Proven Email Marketing Strategies That Boost Sales'
- '7 Essential WordPress Plugins Every Blogger Needs'

Return only the title, nothing else.";
    }

    /**
     * Build content generation prompt
     */
    private function build_content_prompt($title, $topic, $item_count, $category, $include_pricing, $include_ratings, $custom_prompt) {
        $pricing_instruction = $include_pricing ? "Include pricing information where relevant." : "";
        $rating_instruction = $include_ratings ? "Include star ratings or numerical scores for each item." : "";
        $custom_instruction = !empty($custom_prompt) ? "Additional instructions: {$custom_prompt}" : "";

        return "Create a comprehensive listicle article with the following specifications:

Title: {$title}
Topic: {$topic}
Category: {$category}
Number of items: {$item_count}
{$pricing_instruction}
{$rating_instruction}
{$custom_instruction}

Structure the content as a JSON response with this format:
{
    \"subtitle\": \"Brief engaging subtitle\",
    \"introduction\": \"Compelling introduction paragraph\",
    \"overview\": \"Brief overview of what the list covers\",
    \"items\": [
        {
            \"number\": 1,
            \"title\": \"Item title\",
            \"description\": \"Detailed description\",
            \"features\": [\"feature1\", \"feature2\", \"feature3\"],
            \"pros\": [\"pro1\", \"pro2\"],
            \"cons\": [\"con1\", \"con2\"],
            \"rating\": 4.5,
            \"price\": \"$99/month\",
            \"why_its_great\": \"Explanation of why this item made the list\"
        }
    ],
    \"conclusion\": \"Compelling conclusion paragraph\"
}

Make each item valuable and detailed. Focus on providing genuine value to readers. Use engaging language but keep it informative and helpful.";
    }

    /**
     * Format the AI response into beautiful HTML
     */
    private function format_listicle_html($ai_response, $title) {
        $data = json_decode($ai_response['content'], true);
        
        if (!$data) {
            throw new Exception('Invalid AI response format');
        }

        $html = '<div class="atm-listicle-container">';
        
        // Header
        $html .= '<div class="atm-listicle-header">';
        $html .= '<h1>' . esc_html($title) . '</h1>';
        $html .= '<div class="atm-listicle-meta">';
        $html .= '<div class="atm-listicle-meta-item">';
        $html .= '<span class="atm-listicle-meta-icon">📝</span>';
        $html .= '<span>' . count($data['items']) . ' Items</span>';
        $html .= '</div>';
        $html .= '<div class="atm-listicle-meta-item">';
        $html .= '<span class="atm-listicle-meta-icon">⏱️</span>';
        $html .= '<span>' . ceil(count($data['items']) * 2) . ' min read</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Introduction
        if (!empty($data['introduction'])) {
            $html .= '<div class="atm-listicle-overview">';
            $html .= '<h3>Overview</h3>';
            $html .= '<p>' . esc_html($data['introduction']) . '</p>';
            $html .= '</div>';
        }

        // Table of Contents
        $html .= '<div class="atm-listicle-toc">';
        $html .= '<h3>What\'s in This List</h3>';
        $html .= '<ol class="atm-listicle-toc-list">';
        foreach ($data['items'] as $item) {
            $html .= '<li class="atm-listicle-toc-item">';
            $html .= '<a href="#item-' . $item['number'] . '" class="atm-listicle-toc-link">';
            $html .= esc_html($item['title']);
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ol>';
        $html .= '</div>';

        // List Items
        $html .= '<div class="atm-listicle-items">';
        
        foreach ($data['items'] as $item) {
            $html .= '<div class="atm-listicle-item" id="item-' . $item['number'] . '">';
            
            // Item Header
            $html .= '<div class="atm-listicle-item-header">';
            $html .= '<div class="atm-listicle-item-number">' . $item['number'] . '</div>';
            $html .= '<h2 class="atm-listicle-item-title">' . esc_html($item['title']) . '</h2>';
            $html .= '</div>';

            // Item Content
            $html .= '<div class="atm-listicle-item-content">';
            
            // Rating
            if (!empty($item['rating'])) {
                $html .= '<div class="atm-listicle-rating">';
                $html .= '<div class="atm-listicle-stars">';
                $full_stars = floor($item['rating']);
                $half_star = ($item['rating'] - $full_stars) >= 0.5;
                
                for ($i = 0; $i < $full_stars; $i++) {
                    $html .= '<span class="atm-listicle-star">★</span>';
                }
                if ($half_star) {
                    $html .= '<span class="atm-listicle-star">☆</span>';
                }
                $html .= '</div>';
                $html .= '<span class="atm-listicle-rating-text">' . $item['rating'] . '/5</span>';
                $html .= '</div>';
            }

            // Price
            if (!empty($item['price'])) {
                $html .= '<div class="atm-listicle-price">';
                $html .= '<div class="atm-listicle-price-amount">' . esc_html($item['price']) . '</div>';
                $html .= '<div class="atm-listicle-price-note">Starting price</div>';
                $html .= '</div>';
            }

            // Description
            $html .= '<div class="atm-listicle-item-description">';
            $html .= '<p>' . esc_html($item['description']) . '</p>';
            $html .= '</div>';

            // Features
            if (!empty($item['features'])) {
                $html .= '<div class="atm-listicle-features">';
                foreach ($item['features'] as $feature) {
                    $html .= '<div class="atm-listicle-feature">';
                    $html .= '<div class="atm-listicle-feature-label">Key Feature</div>';
                    $html .= '<div class="atm-listicle-feature-value">' . esc_html($feature) . '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }

            // Pros and Cons
            if (!empty($item['pros']) || !empty($item['cons'])) {
                $html .= '<div class="atm-listicle-pros-cons">';
                
                if (!empty($item['pros'])) {
                    $html .= '<div class="atm-listicle-pros">';
                    $html .= '<h4>✅ Pros</h4>';
                    $html .= '<ul>';
                    foreach ($item['pros'] as $pro) {
                        $html .= '<li>' . esc_html($pro) . '</li>';
                    }
                    $html .= '</ul>';
                    $html .= '</div>';
                }

                if (!empty($item['cons'])) {
                    $html .= '<div class="atm-listicle-cons">';
                    $html .= '<h4>❌ Cons</h4>';
                    $html .= '<ul>';
                    foreach ($item['cons'] as $con) {
                        $html .= '<li>' . esc_html($con) . '</li>';
                    }
                    $html .= '</ul>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }

            // Why it's great
            if (!empty($item['why_its_great'])) {
                $html .= '<div class="atm-listicle-feature">';
                $html .= '<div class="atm-listicle-feature-label">Why It Made Our List</div>';
                $html .= '<div class="atm-listicle-feature-value">' . esc_html($item['why_its_great']) . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>'; // Close item content
            $html .= '</div>'; // Close item
        }
        
        $html .= '</div>'; // Close items container

        // Conclusion
        if (!empty($data['conclusion'])) {
            $html .= '<div class="atm-listicle-conclusion">';
            $html .= '<h3>Final Thoughts</h3>';
            $html .= '<p>' . esc_html($data['conclusion']) . '</p>';
            $html .= '</div>';
        }

        $html .= '</div>'; // Close container

        return $html;
    }

    /**
     * Call AI service (implement based on your AI provider)
     */
    private function call_ai_service($prompt, $model = '') {
        // Implement your AI service call here
        // This should return an array with 'content' and optionally 'subtitle'
        
        // Example implementation (replace with your actual AI service):
        /*
        $api_key = get_option('your_ai_api_key');
        $response = wp_remote_post('https://api.your-ai-service.com/generate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'prompt' => $prompt,
                'model' => $model,
                'max_tokens' => 4000
            ])
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return [
            'content' => $data['choices'][0]['message']['content'],
            'subtitle' => 'Generated subtitle if available'
        ];
        */
        
        // Temporary mock response for testing
        return [
            'content' => json_encode([
                'subtitle' => 'Discover the best tools for your needs',
                'introduction' => 'This comprehensive list covers the top options in this category.',
                'items' => [
                    [
                        'number' => 1,
                        'title' => 'Sample Item 1',
                        'description' => 'This is a sample description for testing.',
                        'features' => ['Feature 1', 'Feature 2'],
                        'pros' => ['Great feature', 'Easy to use'],
                        'cons' => ['Could be cheaper'],
                        'rating' => 4.5,
                        'price' => '$99/month',
                        'why_its_great' => 'Excellent overall value'
                    ]
                ],
                'conclusion' => 'This list provides excellent options for your consideration.'
            ])
        ];
    }
}

// Initialize the listicle generator
new ATM_Listicle_Generator();