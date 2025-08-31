<?php
// /includes/class-atm-frontend.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Frontend {

    public function enqueue_frontend_scripts() {
        if (is_single()) {
            // Enqueue the main player script and style on all single posts
            wp_enqueue_script(
                'atm-frontend-script',
                ATM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                ATM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'atm-frontend-style',
                ATM_PLUGIN_URL . 'assets/css/frontend.css',
                array('dashicons'), // Add dashicons as a dependency for the toggle button
                ATM_VERSION
            );

            // --- NEW CHART SCRIPT LOGIC ---
            // Check if the current post content has our chart shortcode
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'atm_chart')) {
                // Only load chart scripts if the shortcode exists
                wp_enqueue_script(
                    'atm-frontend-charts',
                    ATM_PLUGIN_URL . 'assets/js/frontend-charts.js',
                    array('wp-api-fetch'),
                    ATM_VERSION,
                    true
                );
                
                // Pass data for the REST API and theme
                wp_localize_script('atm-frontend-charts', 'atm_charts_data', [
                    'nonce'          => wp_create_nonce('wp_rest'),
                    'chart_api_base' => rest_url('atm/v1/charts/'),
                    'theme_mode'     => 'light'
                ]);
            }
        }
    }
    
    public function embed_podcast_in_content($content) {
        if (!is_single() || !in_the_loop() || !is_main_query() || !get_option('atm_auto_embed', 1)) {
            return $content;
        }
        
        global $post;
        $podcast_url = get_post_meta($post->ID, '_atm_podcast_url', true);

        if ($podcast_url) {
            $podcast_image = get_post_meta($post->ID, '_atm_podcast_image', true);
            $default_image = get_option('atm_default_image', '');
            $cover_image = $podcast_image ?: ($default_image ?: ATM_PLUGIN_URL . 'assets/default-cover.svg');
            
            $player_html = $this->get_player_html($post, $podcast_url, $cover_image);
            return $player_html . $content;
        }
        
        return $content;
    }

    private function get_player_html($post, $podcast_url, $cover_image) {
        $site_name = get_bloginfo('name');
        ob_start();
        ?>
        <div class="atm-wrapper">
            <div class="atm-modern-player">
                <div class="atm-player-container">
                    <div class="atm-player-cover">
                        <img src="<?php echo esc_url($cover_image); ?>" alt="Podcast Cover" class="atm-cover-image" />
                        <div class="atm-play-button">
                            <img src="https://sawahsolutions.com/img-assets/play001.svg" class="atm-play-svg-icon atm-play-icon" alt="Play" />
                            <img src="https://sawahsolutions.com/img-assets/pause001.svg" class="atm-play-svg-icon atm-pause-icon" alt="Pause" />
                        </div>
                    </div>
                    <div class="atm-player-info">
                        <h4 class="atm-episode-title"><?php echo esc_html($post->post_title); ?></h4>
                        <p class="atm-podcast-name"><?php echo esc_html($site_name); ?> Podcast</p>
                        <div class="atm-player-controls">
                            <div class="atm-progress-container">
                                <div class="atm-progress-bar"><div class="atm-progress-fill"></div></div>
                                <div class="atm-time-display"><span class="atm-current-time">0:00</span> / <span class="atm-duration">0:00</span></div>
                            </div>
                            <div class="atm-control-buttons">
                                <button class="atm-speed-btn" title="Playback Speed">1x</button>
                                <button class="atm-volume-btn" title="Volume"><span class="atm-volume-icon">🔊</span></button>
                                <button class="atm-download-btn" data-download-url="<?php echo esc_url($podcast_url); ?>" title="Download Podcast"><img src="https://sawahsolutions.com/img-assets/down001.svg" class="atm-btn-icon" alt="Download" /> Download</button>
                            </div>
                        </div>
                    </div>
                </div>
                <audio class="atm-audio-element" src="<?php echo esc_url($podcast_url); ?>" preload="metadata" style="display: none;"></audio>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function display_subtitle_before_content($content) {
        $post_id = get_the_ID();

        // Let the manager decide which key is active
        $active_key = ATM_Theme_Subtitle_Manager::get_active_subtitle_key($post_id);

        // Only display our own subtitle if the active key is our fallback key (_atm_subtitle).
        // Otherwise, we assume the theme is handling it.
        if ($active_key === '_atm_subtitle' && is_single() && in_the_loop() && is_main_query()) {
            $subtitle = get_post_meta($post_id, '_atm_subtitle', true);
            if (!empty($subtitle)) {
                $subtitle_html = '<h2 class="atm-post-subtitle">' . esc_html($subtitle) . '</h2>';
                return $subtitle_html . $content;
            }
        }

        return $content;
    }
}