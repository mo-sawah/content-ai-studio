<?php
// /includes/class-atm-frontend.php
// Updated with modern podcast player matching your design

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Frontend {

    private static $sprite_printed = false;

    public function enqueue_frontend_scripts() {
        if (is_single()) {
            // Existing assets if needed elsewhere
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
                array('dashicons'),
                ATM_VERSION
            );

            // NEW modern podcast player assets
            wp_enqueue_style(
                'atm-podcast-style',
                ATM_PLUGIN_URL . 'assets/css/podcast-player.css',
                array(),
                ATM_VERSION
            );
            wp_enqueue_script(
                'atm-podcast-script',
                ATM_PLUGIN_URL . 'assets/js/podcast-player.js',
                array(),
                ATM_VERSION,
                true
            );

            // Pass settings to JavaScript
            $this->localize_podcast_settings();

            // Charts / Multipage unchanged...
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'atm_chart')) {
                wp_enqueue_script(
                    'atm-frontend-charts',
                    ATM_PLUGIN_URL . 'assets/js/frontend-charts.js',
                    array('wp-api-fetch'),
                    ATM_VERSION,
                    true
                );
                wp_localize_script('atm-frontend-charts', 'atm_charts_data', [
                    'nonce'          => wp_create_nonce('wp_rest'),
                    'chart_api_base' => rest_url('atm/v1/charts/'),
                    'theme_mode'     => 'light'
                ]);
            }

            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'atm_multipage_article')) {
                wp_enqueue_script('atm-frontend-multipage', ATM_PLUGIN_URL . 'assets/js/frontend-multipage.js', ['jquery'], ATM_VERSION, true);
                wp_localize_script('atm-frontend-multipage', 'atm_multipage_data', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('atm_multipage_nonce'),
                ]);
            }
        }
    }

    private function localize_podcast_settings() {
        // Get all settings from database with fallbacks
        $settings = array(
            'theme' => get_option('atm_podcast_default_theme', 'light'),
            'accent_color' => get_option('atm_podcast_accent_color', '#2979ff'),
            'gradient_end' => get_option('atm_podcast_gradient_end', '#1d63d6'),
            
            // Light theme colors
            'light_card_bg' => get_option('atm_podcast_light_card_bg', '#ffffff'),
            'light_text' => get_option('atm_podcast_light_text', '#1f2933'),
            'light_subtext' => get_option('atm_podcast_light_subtext', '#616d79'),
            'light_border' => get_option('atm_podcast_light_border', '#dfe3e8'),
            'light_bg_alt' => get_option('atm_podcast_light_bg_alt', '#f9fafb'),
            
            // Dark theme colors  
            'dark_card_bg' => get_option('atm_podcast_dark_card_bg', '#1f2732'),
            'dark_text' => get_option('atm_podcast_dark_text', '#f2f6fa'),
            'dark_subtext' => get_option('atm_podcast_dark_subtext', '#a5b1bc'),
            'dark_border' => get_option('atm_podcast_dark_border', '#2b3541'),
            'dark_bg_alt' => get_option('atm_podcast_dark_bg_alt', '#1a2330'),
        );

        wp_localize_script('atm-podcast-script', 'atm_podcast_settings', $settings);
    }

    // Restored so filters work
    public function embed_takeaways_in_content($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) { return $content; }

        $post_id = get_the_ID();
        $takeaways_meta = get_post_meta($post_id, '_atm_key_takeaways', true);
        if (empty($takeaways_meta)) { return $content; }

        $theme = get_post_meta($post_id, '_atm_takeaways_theme', true);
        if (empty($theme)) { $theme = 'dark'; }
        $theme_class = 'atm-theme-' . esc_attr($theme);

        $list = array_filter(array_map('trim', explode("\n", $takeaways_meta)));
        ob_start();
        ?>
        <details class="atm-takeaways-wrapper <?php echo esc_attr($theme_class); ?>">
            <summary class="atm-takeaways-summary">✨ SHOW KEY TAKEAWAYS</summary>
            <div class="atm-takeaways-content">
                <h4>🔑&nbsp; Key Takeaways</h4>
                <ul>
                    <?php foreach ($list as $t) : ?>
                        <li><?php echo esc_html($t); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
        <?php
        return ob_get_clean() . $content;
    }

    public function embed_podcast_in_content($content) {
        if (!is_single() || !in_the_loop() || !is_main_query() || !get_option('atm_auto_embed', 1)) {
            return $content;
        }

        global $post;
        $podcast_url = get_post_meta($post->ID, '_atm_podcast_url', true);

        if ($podcast_url) {
            $podcast_image = get_post_meta($post->ID, '_atm_podcast_image', true);
            $asset_default = ATM_PLUGIN_URL . 'assets/images/pody.jpg';
            if (empty($podcast_image)) {
                $opt_default   = get_option('atm_default_image', '');
                $podcast_image = !empty($opt_default) ? $opt_default : $asset_default;
            }

            $player_html = $this->get_player_html($post, $podcast_url, $podcast_image);
            return $player_html . $content;
        }

        return $content;
    }

    private function print_icon_sprite_once() {
        if (self::$sprite_printed) return;
        
        // Modern SVG sprite with all needed icons
        echo '<div style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <symbol id="atm-play" viewBox="0 0 24 24">
                        <path d="M8 5v14l10-7z" fill="currentColor"/>
                    </symbol>
                    <symbol id="atm-pause" viewBox="0 0 24 24">
                        <path d="M9 5v14M15 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-prev" viewBox="0 0 24 24">
                        <path d="M6 5v14M18 7l-7 5 7 5V7Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-next" viewBox="0 0 24 24">
                        <path d="M18 19V5M6 17l7-5-7-5v10Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-shuffle" viewBox="0 0 24 24">
                        <path d="M3 5h3.5a4 4 0 0 1 3.2 1.6l4.6 6A4 4 0 0 0 17.5 15H21M3 19h3.5a4 4 0 0 0 3.2-1.6l.3-.4m3.2-4.4.6.8A4 4 0 0 0 17.5 15H21M21 5h-3.5a4 4 0 0 0-3.2 1.6l-.6.8M18 2l3 3-3 3M18 14l3 3-3 3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-repeat" viewBox="0 0 24 24">
                        <path d="M4 7v6a5 5 0 0 0 5 5h6m0 0-2-2m2 2-2 2M20 17V11a5 5 0 0 0-5-5H9m0 0 2 2M9 6l2-2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-volume" viewBox="0 0 24 24">
                        <path d="M11 5 6 9H3v6h3l5 4V5Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <path d="M16 9a3 3 0 0 1 0 6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-volume-mute" viewBox="0 0 24 24">
                        <path d="M11 5 6 9H3v6h3l5 4V5Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <path d="m16 9 4 6M20 9l-4 6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-heart" viewBox="0 0 24 24">
                        <path d="M16.5 3a5.5 5.5 0 0 0-4.5 2.4A5.5 5.5 0 0 0 7.5 3 5.5 5.5 0 0 0 2 8.5c0 7 9 12.5 10 12.5s10-5.5 10-12.5A5.5 5.5 0 0 0 16.5 3Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-heart-fill" viewBox="0 0 24 24">
                        <path d="M11.998 21.003c-.516 0-9.998-5.727-9.998-12.503A5.5 5.5 0 0 1 7.5 3a5.5 5.5 0 0 1 4.498 2.4A5.5 5.5 0 0 1 16.496 3 5.5 5.5 0 0 1 22 8.5c0 6.776-9.482 12.503-10 12.503Z" fill="currentColor"/>
                    </symbol>
                    <symbol id="atm-share" viewBox="0 0 24 24">
                        <path d="M7 17a4 4 0 0 1 0-8M17 21a4 4 0 0 0 0-8M12 7V3m0 0 3 3m-3-3L9 6m3 4v6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-download" viewBox="0 0 24 24">
                        <path d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-list" viewBox="0 0 24 24">
                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" fill="none"/>
                    </symbol>
                    <symbol id="atm-headphones" viewBox="0 0 24 24">
                        <path d="M4 13v5a3 3 0 0 0 3 3h1v-8H7a3 3 0 0 0-3 3Zm13 0h-1v8h1a3 3 0 0 0 3-3v-5a3 3 0 0 0-3-3ZM6 13V11A6 6 0 0 1 12 5v0a6 6 0 0 1 6 6v2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-mic" viewBox="0 0 24 24">
                        <path d="M12 15a4 4 0 0 0 4-4V7a4 4 0 1 0-8 0v4a4 4 0 0 0 4 4Z" stroke="currentColor" fill="none"/>
                        <path d="M19 11a7 7 0 0 1-14 0M12 18v3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-briefcase" viewBox="0 0 24 24">
                        <path d="M3 10h18v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7Z" stroke="currentColor" fill="none"/>
                        <path d="M3 10a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                    <symbol id="atm-rocket" viewBox="0 0 24 24">
                        <path d="M5 15c-1-4 1-9 5-12 4 3 6 8 5 12M10 6v3m0 4h0M9 19c1.6 1 4.4 1 6 0M8 15h8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </symbol>
                </defs>
            </svg>
        </div>';
        
        self::$sprite_printed = true;
    }

    private function get_recent_podcasts($current_post_id, $limit = 8) {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'post__not_in'   => array($current_post_id),
            'meta_query'     => array(
                array(
                    'key'     => '_atm_podcast_url',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $q = new WP_Query($args);
        $items = array();
        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $pid  = get_the_ID();
                $url  = get_post_meta($pid, '_atm_podcast_url', true);
                if (empty($url)) { continue; }
                $cover = get_post_meta($pid, '_atm_podcast_image', true);
                if (empty($cover)) { $cover = ATM_PLUGIN_URL . 'assets/images/pody.jpg'; }

                // Get duration from audio file if possible
                $duration = $this->get_audio_duration($url);

                $items[] = array(
                    'title' => get_the_title($pid),
                    'url'   => $url,
                    'cover' => $cover,
                    'duration' => $duration,
                    'author' => get_bloginfo('name')
                );
            }
            wp_reset_postdata();
        }
        return $items;
    }

    private function get_audio_duration($url) {
        // Try to get duration from WordPress attachment if it's a local file
        if (strpos($url, home_url()) === 0) {
            $attachment_id = attachment_url_to_postid($url);
            if ($attachment_id) {
                $metadata = wp_get_attachment_metadata($attachment_id);
                if (isset($metadata['length_formatted'])) {
                    return $metadata['length_formatted'];
                }
                if (isset($metadata['length'])) {
                    return $this->format_duration($metadata['length']);
                }
            }
        }
        return '0:00'; // Default fallback
    }

    private function format_duration($seconds) {
        $minutes = floor($seconds / 60);
        $seconds = floor($seconds % 60);
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function get_playlist_icon($index) {
        $icons = ['headphones', 'mic', 'briefcase', 'rocket'];
        return $icons[$index % count($icons)];
    }
    
    private function get_player_html($post, $podcast_url, $cover_image) {
        $this->print_icon_sprite_once();

        $site_name = get_bloginfo('name');
        $theme = get_option('atm_podcast_default_theme', 'light');
        
        $playlist = $this->get_recent_podcasts($post->ID, 4); // Get 4 recent episodes

        ob_start();
        ?>
        <div class="demo-container">
            <main class="podcast-player" data-theme="<?php echo esc_attr($theme); ?>" aria-label="Podcast Player">
                <section class="player-header">
                    <div class="podcast-artwork" id="artwork" aria-hidden="true">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8">
                            <path d="M4 13v5a3 3 0 0 0 3 3h1v-8H7a3 3 0 0 0-3 3Zm13 0h-1v8h1a3 3 0 0 0 3-3v-5a3 3 0 0 0-3-3ZM6 13V11A6 6 0 0 1 12 5v0a6 6 0 0 1 6 6v2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="artwork-pulse" id="artworkPulse"></div>
                    </div>
                    <h1 class="podcast-title" id="episodeTitle"><?php echo esc_html($post->post_title); ?></h1>
                    <p class="podcast-author" id="episodeAuthor">Powered by Content AI Studio</p>
                </section>

                <div class="waveform-container"
                     id="waveformContainer"
                     role="slider"
                     aria-label="Seek"
                     aria-valuemin="0"
                     aria-valuemax="0"
                     aria-valuenow="0"
                     tabindex="0">
                    <div class="progress-overlay" id="progressOverlay"></div>
                    <div class="scrub-handle" id="scrubHandle"></div>
                    <div class="waveform" id="waveform"></div>
                </div>

                <div class="time-display">
                    <span id="currentTimeLabel">0:00</span>
                    <span id="totalTimeLabel">0:00</span>
                </div>

                <div class="player-controls" aria-label="Playback controls">
                    <button class="icon-btn" id="btnShuffle" aria-label="Shuffle (off)" data-state="off">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                            <use href="#atm-shuffle"></use>
                        </svg>
                    </button>
                    <button class="icon-btn" id="btnPrev" aria-label="Previous episode">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                            <use href="#atm-prev"></use>
                        </svg>
                    </button>
                    <button class="icon-btn" data-role="primary" id="btnPlayPause" aria-label="Play">
                        <svg id="iconPlay" viewBox="0 0 24 24" stroke="currentColor" fill="currentColor">
                            <use href="#atm-play"></use>
                        </svg>
                        <svg id="iconPause" viewBox="0 0 24 24" stroke="currentColor" fill="none" style="display:none;">
                            <use href="#atm-pause"></use>
                        </svg>
                    </button>
                    <button class="icon-btn" id="btnNext" aria-label="Next episode">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                            <use href="#atm-next"></use>
                        </svg>
                    </button>
                    <button class="icon-btn" id="btnRepeat" aria-label="Repeat (off)" data-mode="off">
                        <svg id="repeatIcon" viewBox="0 0 24 24" stroke="currentColor" fill="none">
                            <use href="#atm-repeat"></use>
                        </svg>
                    </button>
                </div>

                <div class="secondary-controls">
                    <div class="volume-wrapper">
                        <button class="action-btn" id="btnMute" aria-label="Mute">
                            <svg id="iconVolume" viewBox="0 0 24 24" stroke="currentColor" fill="none">
                                <use href="#atm-volume"></use>
                            </svg>
                        </button>
                        <div class="volume-track" id="volumeTrack" aria-label="Volume" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70" tabindex="0">
                            <div class="volume-fill" id="volumeFill"></div>
                            <div class="volume-handle" id="volumeHandle"></div>
                        </div>
                    </div>

                    <div class="actions-wrapper">
                        <button class="action-btn" id="btnLike" aria-label="Like">
                            <svg id="iconHeartOutline" viewBox="0 0 24 24" stroke="currentColor" fill="none">
                                <use href="#atm-heart"></use>
                            </svg>
                            <svg id="iconHeartFilled" viewBox="0 0 24 24" stroke="none" fill="currentColor" style="display:none;">
                                <use href="#atm-heart-fill"></use>
                            </svg>
                        </button>
                        <button class="action-btn" id="btnShare" aria-label="Share">
                            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                                <use href="#atm-share"></use>
                            </svg>
                        </button>
                        <button class="action-btn" id="btnDownload" aria-label="Download">
                            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                                <use href="#atm-download"></use>
                            </svg>
                        </button>
                    </div>
                </div>

                <button class="playlist-toggle-floating" id="playlistToggleBtn" aria-expanded="false" aria-controls="playlistContainer" aria-label="Toggle playlist">
                    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none">
                        <use href="#atm-list"></use>
                    </svg>
                </button>

                <?php if (!empty($playlist)) : ?>
                <section class="playlist-container" id="playlistContainer" aria-label="Playlist">
                    <h2 class="playlist-header">Up Next</h2>
                    <ul class="playlist-list" id="playlistList">
                        <?php foreach ($playlist as $index => $item) : ?>
                        <li class="playlist-item" data-url="<?php echo esc_url($item['url']); ?>" data-title="<?php echo esc_attr($item['title']); ?>">
                            <div class="playlist-artwork">
                                <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8">
                                    <use href="#atm-<?php echo esc_attr($this->get_playlist_icon($index)); ?>"></use>
                                </svg>
                            </div>
                            <div class="playlist-meta">
                                <div class="playlist-title"><?php echo esc_html($item['title']); ?></div>
                                <div class="playlist-author"><?php echo esc_html($item['author']); ?></div>
                            </div>
                            <div class="playlist-duration"><?php echo esc_html($item['duration']); ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <audio id="audioEl" src="<?php echo esc_url($podcast_url); ?>" preload="metadata" hidden></audio>
            </main>
        </div>

        <style id="atm-podcast-dynamic-styles">
        :root {
            --color-accent: <?php echo esc_attr(get_option('atm_podcast_accent_color', '#2979ff')); ?>;
            --color-accent-hover: <?php echo esc_attr(get_option('atm_podcast_gradient_end', '#1d63d6')); ?>;
        }
        
        <?php if ($theme === 'light') : ?>
        .podcast-player[data-theme="light"] {
            --color-bg: <?php echo esc_attr(get_option('atm_podcast_light_card_bg', '#f5f7fa')); ?>;
            --color-bg-alt: <?php echo esc_attr(get_option('atm_podcast_light_bg_alt', '#ffffff')); ?>;
            --color-bg-alt2: <?php echo esc_attr(get_option('atm_podcast_light_bg_alt', '#f9fafb')); ?>;
            --color-border: <?php echo esc_attr(get_option('atm_podcast_light_border', '#dfe3e8')); ?>;
            --color-text: <?php echo esc_attr(get_option('atm_podcast_light_text', '#1f2933')); ?>;
            --color-text-soft: <?php echo esc_attr(get_option('atm_podcast_light_subtext', '#616d79')); ?>;
        }
        <?php else : ?>
        .podcast-player[data-theme="dark"] {
            --color-bg: <?php echo esc_attr(get_option('atm_podcast_dark_card_bg', '#111827')); ?>;
            --color-bg-alt: <?php echo esc_attr(get_option('atm_podcast_dark_bg_alt', '#1f2732')); ?>;
            --color-bg-alt2: <?php echo esc_attr(get_option('atm_podcast_dark_bg_alt', '#1a2330')); ?>;
            --color-border: <?php echo esc_attr(get_option('atm_podcast_dark_border', '#2b3541')); ?>;
            --color-text: <?php echo esc_attr(get_option('atm_podcast_dark_text', '#f2f6fa')); ?>;
            --color-text-soft: <?php echo esc_attr(get_option('atm_podcast_dark_subtext', '#a5b1bc')); ?>;
        }
        <?php endif; ?>
        </style>
        <?php
        return ob_get_clean();
    }
}