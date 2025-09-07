<?php
if (!defined('ABSPATH')) {
    exit;
}

class ATM_Theme_Subtitle_Manager {

    /**
     * A map of known theme slugs to their subtitle meta keys.
     * This allows for more reliable auto-detection.
     */
    private static function get_known_theme_keys() {
        return [
            'smart-mag' => '_bunyad_sub_title', // Your SmartMag Theme
            'kadence'   => '_kadence_post_subtitle',
            'astra'     => 'astra_subtitle',
            // Add more popular themes here over time
        ];
    }

    /**
     * Determines the correct subtitle meta key to use.
     * Priority: Manual Setting > Auto-Detected Theme > Fallback
     *
     * @return string The meta key to use.
     */
    public static function get_active_subtitle_key() {
        // Priority 1: Check for a user-defined manual key in settings.
        $manual_key = get_option('atm_theme_subtitle_key', '');
        if (!empty($manual_key)) {
            return $manual_key;
        }

        // Priority 2: Check if the current theme is in our known list.
        $current_theme_slug = get_template(); // Gets the directory name of the active theme, e.g., "smart-mag"
        $known_keys = self::get_known_theme_keys();

        if (isset($known_keys[$current_theme_slug])) {
            return $known_keys[$current_theme_slug];
        }

        // Priority 3: Return empty string to indicate no subtitle support
        return '';
    }

    /**
     * Saves the subtitle to the theme's field ONLY if a valid field is detected.
     * If no valid field is found, returns the original content unchanged.
     *
     * @param int    $post_id  The ID of the post.
     * @param string $subtitle The subtitle text.
     * @param string $content  The original article content.
     * @return string The final article content (always unmodified).
     */
    public static function save_subtitle($post_id, $subtitle, $content) {
        error_log('ATM Debug - save_subtitle called with: post_id=' . $post_id . ', subtitle=' . $subtitle);
        
        $active_key = self::get_active_subtitle_key();
        error_log('ATM Debug - Active subtitle key: "' . $active_key . '"');

        // Only save if we have a valid theme subtitle field
        if (!empty($active_key)) {
            $result = update_post_meta($post_id, $active_key, $subtitle);
            error_log('ATM Debug - update_post_meta result for key "' . $active_key . '": ' . ($result ? 'success' : 'failed'));
            
            // Verify it was saved
            $saved_value = get_post_meta($post_id, $active_key, true);
            error_log('ATM Debug - Verified saved value: "' . $saved_value . '"');
        } else {
            error_log('ATM Debug - No valid subtitle field found, skipping subtitle save');
        }

        // Always return the original content unchanged
        return $content;
    }
}