<?php
/**
 * Frontend helpers to consume podcast settings.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('atm_podcast_get_settings')) {
    function atm_podcast_get_settings(): array {
        if (class_exists('ATM_Podcast_Settings')) {
            return ATM_Podcast_Settings::get_settings();
        }
        return [];
    }
}

if (!function_exists('atm_podcast_theme_mode')) {
    function atm_podcast_theme_mode(): string {
        if (class_exists('ATM_Podcast_Settings')) {
            return ATM_Podcast_Settings::theme_mode();
        }
        return 'light';
    }
}

/**
 * Build inline attributes for the podcast wrapper.
 * Example:
 *   echo '<div class="atm-podcast" ' . atm_podcast_inline_attrs() . '>';
 */
if (!function_exists('atm_podcast_inline_attrs')) {
    function atm_podcast_inline_attrs(?string $force_theme = null): string {
        if (class_exists('ATM_Podcast_Settings')) {
            return ATM_Podcast_Settings::build_css_vars_inline($force_theme);
        }
        return '';
    }
}