<?php
// Theme-aware subtitle system that respects original theme positions
class ATM_Frontend {
    
    public function __construct() {
        add_action('wp', array($this, 'init_theme_subtitle_hooks'));
    }
    
    public function init_theme_subtitle_hooks() {
        if (!is_single()) {
            return;
        }
        
        $theme = get_template();
        
        // Hook into specific themes at their proper subtitle locations
        if (strpos($theme, 'smartmag') !== false) {
            $this->init_smartmag_subtitle_hooks();
        } elseif (strpos($theme, 'newspaper') !== false) {
            $this->init_newspaper_subtitle_hooks();
        } elseif (strpos($theme, 'kadence') !== false) {
            $this->init_kadence_subtitle_hooks();
        } else {
            // Generic theme support - try to detect common hooks
            $this->init_generic_subtitle_hooks();
        }
    }
    
    private function init_smartmag_subtitle_hooks() {
        // Hook into SmartMag's subtitle system
        add_filter('bunyad_post_meta', array($this, 'provide_smartmag_subtitle'), 10, 2);
        
        // Alternative: Hook into the specific template location
        add_action('bunyad_single_content_wrap', array($this, 'display_smartmag_subtitle'), 5);
        
        // Ensure SmartMag can access our subtitle via its meta system
        add_filter('get_post_metadata', array($this, 'intercept_smartmag_subtitle_request'), 10, 4);
    }
    
    public function provide_smartmag_subtitle($meta_value, $key) {
        if ($key === '_bunyad_sub_title' && empty($meta_value)) {
            $post_id = get_the_ID();
            return $this->get_subtitle_for_post($post_id);
        }
        return $meta_value;
    }
    
    public function intercept_smartmag_subtitle_request($value, $post_id, $meta_key, $single) {
        if ($meta_key === '_bunyad_sub_title' && $single) {
            $existing_value = get_post_meta($post_id, '_bunyad_sub_title', true);
            if (empty($existing_value)) {
                // Return our subtitle if SmartMag's field is empty
                $our_subtitle = get_post_meta($post_id, '_atm_subtitle', true);
                if (!empty($our_subtitle)) {
                    return $our_subtitle;
                }
            }
        }
        return $value;
    }
    
    public function display_smartmag_subtitle() {
        $subtitle = $this->get_subtitle_for_post(get_the_ID());
        if (!empty($subtitle)) {
            // Use SmartMag's subtitle HTML structure
            echo '<div class="post-subtitle">' . esc_html($subtitle) . '</div>';
        }
    }
    
    private function init_newspaper_subtitle_hooks() {
        // Hook into Newspaper theme's subtitle display
        add_filter('td_post_subtitle', array($this, 'provide_newspaper_subtitle'));
    }
    
    public function provide_newspaper_subtitle($subtitle) {
        if (empty($subtitle)) {
            return $this->get_subtitle_for_post(get_the_ID());
        }
        return $subtitle;
    }
    
    private function init_kadence_subtitle_hooks() {
        // Hook into Kadence's subtitle system
        add_filter('kadence_post_subtitle', array($this, 'provide_kadence_subtitle'));
        add_action('kadence_single_before_inner_content', array($this, 'display_kadence_subtitle'), 5);
    }
    
    public function provide_kadence_subtitle($subtitle) {
        if (empty($subtitle)) {
            return $this->get_subtitle_for_post(get_the_ID());
        }
        return $subtitle;
    }
    
    public function display_kadence_subtitle() {
        $subtitle = $this->get_subtitle_for_post(get_the_ID());
        if (!empty($subtitle)) {
            echo '<div class="entry-subtitle">' . esc_html($subtitle) . '</div>';
        }
    }
    
    private function init_generic_subtitle_hooks() {
        // For themes without specific subtitle support, try common hook locations
        $common_hooks = [
            'genesis_entry_header',           // Genesis framework
            'astra_entry_content_before',     // Astra theme  
            'generate_after_entry_title',     // GeneratePress
            'twentytwentyone_entry_header_after', // WordPress default themes
        ];
        
        foreach ($common_hooks as $hook) {
            if (has_action($hook)) {
                add_action($hook, array($this, 'display_generic_subtitle'), 15);
                break; // Only hook into one location
            }
        }
        
        // Fallback: Hook into template hierarchy
        add_action('wp_head', array($this, 'add_subtitle_via_template_hooks'));
    }
    
    public function add_subtitle_via_template_hooks() {
        // Try to find where the theme outputs post titles and hook after that
        add_filter('the_title', array($this, 'append_subtitle_to_title'), 10, 2);
    }
    
    public function append_subtitle_to_title($title, $post_id) {
        if (is_single() && in_the_loop() && is_main_query() && $post_id == get_the_ID()) {
            $subtitle = $this->get_subtitle_for_post($post_id);
            if (!empty($subtitle)) {
                $title .= '<div class="post-subtitle atm-subtitle">' . esc_html($subtitle) . '</div>';
            }
        }
        return $title;
    }
    
    public function display_generic_subtitle() {
        $subtitle = $this->get_subtitle_for_post(get_the_ID());
        if (!empty($subtitle)) {
            echo '<div class="post-subtitle entry-subtitle atm-subtitle">' . esc_html($subtitle) . '</div>';
        }
    }
    
    private function get_subtitle_for_post($post_id) {
        // Priority: configured theme field > our field
        $theme_subtitle_key = get_option('atm_theme_subtitle_key', '');
        
        if (!empty($theme_subtitle_key)) {
            $subtitle = get_post_meta($post_id, $theme_subtitle_key, true);
            if (!empty($subtitle)) {
                return $subtitle;
            }
        }
        
        return get_post_meta($post_id, '_atm_subtitle', true);
    }
    
    // Enhanced CSS that respects theme positioning
    public function enqueue_subtitle_styles() {
        if (is_single()) {
            $theme = get_template();
            $css = $this->get_theme_specific_subtitle_css($theme);
            wp_add_inline_style('atm-frontend-style', $css);
        }
    }
    
    private function get_theme_specific_subtitle_css($theme) {
        $base_css = '';
        
        if (strpos($theme, 'smartmag') !== false) {
            $base_css = '
            .post-subtitle, .atm-subtitle {
                font-size: 14px;
                color: #7a7a7a;
                margin: 8px 0 15px 0;
                font-weight: 400;
                line-height: 1.4;
            }';
        } elseif (strpos($theme, 'newspaper') !== false) {
            $base_css = '
            .td-post-subtitle, .atm-subtitle {
                font-size: 14px;
                color: #999;
                margin-bottom: 20px;
                font-style: italic;
            }';
        } else {
            // Generic styling that adapts to most themes
            $base_css = '
            .post-subtitle, .entry-subtitle, .atm-subtitle {
                font-size: 1.1em;
                color: #666;
                margin: 0.5rem 0 1rem 0;
                font-weight: 400;
                line-height: 1.4;
                opacity: 0.9;
            }
            
            @media (max-width: 768px) {
                .post-subtitle, .entry-subtitle, .atm-subtitle {
                    font-size: 1em;
                    margin: 0.3rem 0 0.8rem 0;
                }
            }';
        }
        
        return $base_css;
    }
}