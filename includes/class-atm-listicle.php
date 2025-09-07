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
    
    // Progress Bar
    $html .= '<div class="atm-listicle-progress-container">';
    $html .= '<div class="atm-listicle-progress-bar" id="atm-progress-bar"></div>';
    $html .= '</div>';
    
    // Main Grid
    $html .= '<div class="atm-listicle-grid">';
    
    // Sidebar with TOC
    $html .= '<div class="atm-listicle-sidebar">';
    $html .= '<div class="atm-listicle-toc">';
    $html .= '<div class="atm-toc-header">';
    $html .= '<h3>In This Article</h3>';
    $html .= '<p>Jump to any section</p>';
    $html .= '</div>';
    $html .= '<ul class="atm-toc-list">';
    
    // Introduction link
    $html .= '<li><a href="#intro"><span class="atm-toc-icon">ℹ️</span> Introduction</a></li>';
    
    // Items
    foreach ($data['items'] as $item) {
        $html .= '<li><a href="#item-' . $item['number'] . '">';
        $html .= '<span class="atm-toc-number">' . $item['number'] . '</span>';
        $html .= esc_html($item['title']);
        $html .= '</a></li>';
    }
    
    // Conclusion link
    $html .= '<li><a href="#conclusion"><span class="atm-toc-icon">✅</span> Conclusion</a></li>';
    $html .= '</ul>';
    
    // Share buttons
    $html .= '<div class="atm-listicle-share">';
    $html .= '<p>Share this list</p>';
    $html .= '<div class="atm-share-buttons">';
    $html .= '<a href="#" class="atm-share-btn facebook">f</a>';
    $html .= '<a href="#" class="atm-share-btn twitter">t</a>';
    $html .= '<a href="#" class="atm-share-btn linkedin">in</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // Close toc
    $html .= '</div>'; // Close sidebar
    
    // Main Content
    $html .= '<div class="atm-listicle-content">';
    
    // Introduction
    if (!empty($data['introduction'])) {
        $html .= '<div class="atm-listicle-intro" id="intro">';
        $html .= '<p class="atm-listicle-lead">' . esc_html($data['introduction']) . '</p>';
        $html .= '<div class="atm-listicle-overview">';
        $html .= '<div class="atm-overview-header">';
        $html .= '<span class="atm-overview-icon">💡</span>';
        $html .= '<h3>What You\'ll Learn</h3>';
        $html .= '</div>';
        $html .= '<p class="atm-overview-text">This comprehensive guide covers the essential tools you need to optimize your workflow and achieve better results.</p>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // List Items (keep your existing item rendering logic but use new CSS classes)
    foreach ($data['items'] as $item) {
        $html .= '<div class="atm-listicle-item" id="item-' . $item['number'] . '">';
        // ... rest of your existing item HTML but with updated class names
        $html .= '</div>';
    }
    
    // Conclusion
    if (!empty($data['conclusion'])) {
        $html .= '<div class="atm-listicle-conclusion" id="conclusion">';
        $html .= '<div class="atm-conclusion-header">';
        $html .= '<i class="fas fa-check-circle"></i>';
        $html .= '<h3>Final Thoughts</h3>';
        $html .= '</div>';
        $html .= '<p>' . esc_html($data['conclusion']) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // Close content
    $html .= '</div>'; // Close grid
    $html .= '</div>'; // Close container
    
    return $html;
}
}