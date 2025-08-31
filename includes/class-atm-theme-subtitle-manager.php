<?php
// /includes/class-atm-theme-subtitle-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Theme_Subtitle_Manager {
    
    /**
     * Saves the subtitle to the theme's field or prepends it to the content as a fallback.
     *
     * @param int    $post_id  The ID of the post.
     * @param string $subtitle The subtitle text.
     * @param string $content  The original article content.
     * @return string The final article content (modified if fallback is used).
     */
    public static function save_subtitle($post_id, $subtitle, $content) {
        $active_key = self::get_active_subtitle_key($post_id);

        // If a theme-specific key is found, use it and return the original content.
        if ($active_key !== '_atm_subtitle') {
            update_post_meta($post_id, $active_key, $subtitle);
            return $content;
        }

        // Fallback: Prepend the subtitle as a Markdown H2 to the content.
        return "## " . $subtitle . "\n\n" . $content;
    }
    
    /**
     * Get subtitle from the most appropriate field
     */
    public static function get_subtitle($post_id) {
        // Priority: configured theme field > theme-specific field > our field
        $theme_subtitle_key = get_option('atm_theme_subtitle_key', '');
        
        if (!empty($theme_subtitle_key)) {
            $subtitle = get_post_meta($post_id, $theme_subtitle_key, true);
            if (!empty($subtitle)) {
                return $subtitle;
            }
        }
        
        // Check theme-specific fields
        $theme = get_template();
        if (strpos($theme, 'smartmag') !== false) {
            $subtitle = get_post_meta($post_id, '_bunyad_sub_title', true);
            if (!empty($subtitle)) {
                return $subtitle;
            }
        } elseif (strpos($theme, 'newspaper') !== false) {
            $subtitle = get_post_meta($post_id, '_td_subtitle', true);
            if (!empty($subtitle)) {
                return $subtitle;
            }
        } elseif (strpos($theme, 'kadence') !== false) {
            $subtitle = get_post_meta($post_id, '_kadence_post_subtitle', true);
            if (!empty($subtitle)) {
                return $subtitle;
            }
        }
        
        // Fallback to our field
        return get_post_meta($post_id, '_atm_subtitle', true);
    }
    
    /**
     * Get the active subtitle key for a post
     */
    public static function get_active_subtitle_key($post_id) {
        $theme_subtitle_key = get_option('atm_theme_subtitle_key', '');
        
        if (!empty($theme_subtitle_key)) {
            $subtitle = get_post_meta($post_id, $theme_subtitle_key, true);
            if (!empty($subtitle)) {
                return $theme_subtitle_key;
            }
        }
        
        // Check theme-specific fields
        $theme = get_template();
        if (strpos($theme, 'smartmag') !== false) {
            $subtitle = get_post_meta($post_id, '_bunyad_sub_title', true);
            if (!empty($subtitle)) {
                return '_bunyad_sub_title';
            }
        } elseif (strpos($theme, 'newspaper') !== false) {
            $subtitle = get_post_meta($post_id, '_td_subtitle', true);
            if (!empty($subtitle)) {
                return '_td_subtitle';
            }
        } elseif (strpos($theme, 'kadence') !== false) {
            $subtitle = get_post_meta($post_id, '_kadence_post_subtitle', true);
            if (!empty($subtitle)) {
                return '_kadence_post_subtitle';
            }
        }
        
        // Fallback to our field
        return '_atm_subtitle';
    }
    
    /**
     * Check if theme handles subtitles natively
     */
    public static function theme_handles_subtitle() {
        $theme = get_template();
        
        $themes_with_subtitle_support = [
            'smartmag' => 'bunyad_post_subtitle',
            'newspaper' => 'td_subtitle', 
            'kadence' => 'kadence_post_subtitle',
            'genesis' => 'genesis_subtitle',
            'astra' => 'astra_subtitle'
        ];
        
        foreach ($themes_with_subtitle_support as $theme_name => $function_check) {
            if (strpos($theme, $theme_name) !== false) {
                // Check if theme function exists
                if (function_exists($function_check) || 
                    function_exists('get_' . $function_check) ||
                    has_action('bunyad_single_content_wrap')) { // SmartMag specific
                    return true;
                }
            }
        }
        
        return false;
    }
}