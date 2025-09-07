<?php
// Add to your main plugin file or create a separate listicle handler

class ATM_Listicle_Generator {
    
    private static $ajax_registered = false;
    
    public function __construct() {
        // Prevent multiple AJAX registrations
        if (!self::$ajax_registered) {
            add_action('wp_ajax_generate_listicle_title', array($this, 'generate_listicle_title'));
            add_action('wp_ajax_generate_listicle_content', array($this, 'generate_listicle_content'));
            self::$ajax_registered = true;
        }
    }

    /**
     * Generate a compelling listicle title
     */
    public function generate_listicle_title() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        $topic = sanitize_text_field($_POST['topic']);
        $item_count = intval($_POST['item_count']);
        $category = sanitize_text_field($_POST['category']);
        $model = sanitize_text_field($_POST['model']);
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            return;
        }

        try {
            $title = ATM_API::generate_listicle_title($topic, $item_count, $category, $model);
            
            wp_send_json_success(array(
                'article_title' => $title
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Title generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate full listicle content
     */
    public function generate_listicle_content() {
        check_ajax_referer('atm_nonce', 'nonce');
        
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

        try {
            $params = array(
                'topic' => $topic,
                'item_count' => $item_count,
                'category' => $category,
                'include_pricing' => $include_pricing,
                'include_ratings' => $include_ratings,
                'model' => $model,
                'custom_prompt' => $custom_prompt
            );
            
            $result = ATM_API::generate_listicle_content($params);
            
            // Generate the formatted HTML content
            $html_content = $this->format_listicle_html($result, $title);
            
            wp_send_json_success(array(
                'article_content' => $html_content,
                'subtitle' => $result['subtitle'] ?? ''
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Content generation failed: ' . $e->getMessage());
        }
    }

private function format_listicle_html($data, $title) {
    if (!is_array($data) || !isset($data['items'])) {
        throw new Exception('Invalid data format for listicle');
    }

    $html = '<div class="atm-listicle-container">';
    
    // REMOVED: Hero Header section completely
    
    // Enhanced Overview - Two paragraphs
    if (!empty($data['introduction'])) {
        $html .= '<div class="atm-listicle-overview">';
        $html .= '<div class="atm-overview-header">';
        $html .= '<div class="atm-overview-icon">💡</div>';
        $html .= '<h3>Quick Overview</h3>';
        $html .= '</div>';
        
        // Split introduction into paragraphs if it's not already
        $intro_text = $data['introduction'];
        $paragraphs = explode("\n\n", $intro_text);
        if (count($paragraphs) == 1) {
            // If it's one long paragraph, split it roughly in half
            $sentences = explode('. ', $intro_text);
            $mid = ceil(count($sentences) / 2);
            $first_half = implode('. ', array_slice($sentences, 0, $mid));
            $second_half = implode('. ', array_slice($sentences, $mid));
            $paragraphs = [$first_half, $second_half];
        }
        
        foreach ($paragraphs as $paragraph) {
            if (!empty(trim($paragraph))) {
                $html .= '<p class="atm-overview-text">' . esc_html(trim($paragraph)) . '</p>';
            }
        }
        $html .= '</div>';
    }

    // Table of Contents - Always open, two columns
    $html .= '<div class="atm-listicle-toc atm-toc-always-open">';
    $html .= '<h3>🗂️ What\'s in This List</h3>';
    $html .= '<ol class="atm-listicle-toc-list atm-toc-two-columns">';
    foreach ($data['items'] as $item) {
        $html .= '<li class="atm-listicle-toc-item">';
        $html .= '<a href="#item-' . $item['number'] . '" class="atm-listicle-toc-link">';
        $html .= '<span class="atm-toc-number">' . $item['number'] . '</span>';
        $html .= '<span>' . esc_html($item['title']) . '</span>';
        $html .= '</a>';
        $html .= '</li>';
    }
    $html .= '</ol>';
    $html .= '</div>';

    // List Items
    $html .= '<div class="atm-listicle-items">';
    
    foreach ($data['items'] as $item) {
        $html .= '<div class="atm-listicle-item" id="item-' . $item['number'] . '">';
        
        // Header with number and title on same line + description under title
        $html .= '<div class="atm-listicle-item-header">';
        $html .= '<div class="atm-listicle-item-number">' . $item['number'] . '</div>';
        $html .= '<div>';
        $html .= '<h2 class="atm-listicle-item-title">' . esc_html($item['title']) . '</h2>';
        if (!empty($item['subtitle'])) {
            $html .= '<p class="atm-listicle-item-subtitle">' . esc_html($item['subtitle']) . '</p>';
        } else {
            $short_desc = substr(esc_html($item['description']), 0, 100) . '...';
            $html .= '<p class="atm-listicle-item-subtitle">' . $short_desc . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Item Content
        $html .= '<div class="atm-listicle-item-content">';
        
        // Rating and Price on same line with bigger stars
        if (!empty($item['rating']) || !empty($item['price'])) {
            $html .= '<div class="atm-rating-price-container">';
            
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

            if (!empty($item['price'])) {
                $html .= '<div class="atm-listicle-price">';
                $html .= '<div class="atm-listicle-price-amount">' . esc_html($item['price']) . '</div>';
                $html .= '<div class="atm-listicle-price-note">Starting price</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        // Enhanced Description - 3 paragraphs
        $html .= '<div class="atm-listicle-item-description">';
        
        // Split description into 3 paragraphs
        $description = $item['description'];
        $paragraphs = explode("\n\n", $description);
        
        // Ensure we have exactly 3 paragraphs
        if (count($paragraphs) < 3) {
            // If we have fewer paragraphs, split existing ones
            $sentences = explode('. ', $description);
            $third = ceil(count($sentences) / 3);
            $paragraphs = [
                implode('. ', array_slice($sentences, 0, $third)),
                implode('. ', array_slice($sentences, $third, $third)),
                implode('. ', array_slice($sentences, $third * 2))
            ];
        } elseif (count($paragraphs) > 3) {
            // If we have more than 3, combine them
            $paragraphs = array_slice($paragraphs, 0, 3);
        }
        
        foreach ($paragraphs as $paragraph) {
            if (!empty(trim($paragraph))) {
                $html .= '<p>' . esc_html(trim($paragraph)) . '</p>';
            }
        }
        $html .= '</div>';

        // Features in one box with two columns
        if (!empty($item['features'])) {
            $html .= '<div class="atm-listicle-features">';
            $html .= '<h4>Key Features</h4>';
            $html .= '<div class="atm-features-grid">';
            foreach ($item['features'] as $feature) {
                $html .= '<div class="atm-feature-item">';
                $html .= '<span class="atm-feature-bullet">•</span>';
                $html .= '<span>' . esc_html($feature) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Enhanced Pros and Cons (6 each)
        if (!empty($item['pros']) || !empty($item['cons'])) {
            $html .= '<div class="atm-listicle-pros-cons">';
            
            if (!empty($item['pros'])) {
                $html .= '<div class="atm-listicle-pros">';
                $html .= '<h4>✅ Pros</h4>';
                $html .= '<ul>';
                foreach (array_slice($item['pros'], 0, 6) as $pro) {
                    $html .= '<li>' . esc_html($pro) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }

            if (!empty($item['cons'])) {
                $html .= '<div class="atm-listicle-cons">';
                $html .= '<h4>❌ Cons</h4>';
                $html .= '<ul>';
                foreach (array_slice($item['cons'], 0, 6) as $con) {
                    $html .= '<li>' . esc_html($con) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        // Enhanced Why it's great
        if (!empty($item['why_its_great'])) {
            $html .= '<div class="atm-why-great">';
            $html .= '<div class="atm-why-great-label">Why It Made Our List</div>';
            $html .= '<div class="atm-why-great-text">' . esc_html($item['why_its_great']) . '</div>';
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
}