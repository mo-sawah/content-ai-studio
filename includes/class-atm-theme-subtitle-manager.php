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

        // Priority 3: Return the plugin's default fallback key.
        return '_atm_subtitle';
    }

    /**
     * Saves the subtitle to the theme's field or prepends it to the content as a fallback.
     *
     * @param int    $post_id  The ID of the post.
     * @param string $subtitle The subtitle text.
     * @param string $content  The original article content.
     * @return string The final article content (modified only if fallback is used).
     */
    public static function save_subtitle($post_id, $subtitle, $content) {
        $active_key = self::get_active_subtitle_key();

        // If a theme-specific key is found (manual or auto-detected), use it.
        if ($active_key !== '_atm_subtitle') {
            update_post_meta($post_id, $active_key, $subtitle);
            // Return the original, unmodified content.
            return $content;
        }

        // Fallback: Prepend the subtitle as a bolded paragraph to the content.
        return "**Sub Title:** " . $subtitle . "\n\n" . $content;
    }
}