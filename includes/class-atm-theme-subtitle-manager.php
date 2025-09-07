<?php
// /includes/class-atm-theme-subtitle-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Theme_Subtitle_Manager {
    
    /**
     * Save subtitle to appropriate theme fields
     */
    public static function save_subtitle($post_id, $subtitle) {
        if ($post_id <= 0 || empty($subtitle)) {
            return false;
        }
        
        $theme = get_template();
        $theme_subtitle_key = get_option('atm_theme_subtitle_key', '');
        
        // Always save to our plugin's field as backup
        update_post_meta($post_id, '_atm_subtitle', $subtitle);
        
        // Save to configured theme field if set
        if (!empty($theme_subtitle_key)) {
            update_post_meta($post_id, $theme_subtitle_key, $subtitle);
            error_log("ATM Plugin: Saved subtitle to configured field: {$theme_subtitle_key}");
        }
        
        // Auto-detect and save to theme-specific fields
        if (strpos($theme, 'smartmag') !== false) {
            update_post_meta($post_id, '_bunyad_sub_title', $subtitle);
            error_log("ATM Plugin: Saved subtitle to SmartMag field: _bunyad_sub_title");
        } elseif (strpos($theme, 'newspaper') !== false) {
            update_post_meta($post_id, '_td_subtitle', $subtitle);
        } elseif (strpos($theme, 'kadence') !== false) {
            update_post_meta($post_id, '_kadence_post_subtitle', $subtitle);
        } elseif (strpos($theme, 'genesis') !== false) {
            update_post_meta($post_id, '_genesis_subtitle', $subtitle);
        }
        
        return true;
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