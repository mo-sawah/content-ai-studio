<?php
// /includes/class-atm-frontend.php
// Updated: default theme = light; uses settings; unchanged functionality otherwise.

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Frontend {

  private function get_modern_player_html() {
      // This is the HTML structure from your pody-test.html file
      ob_start();
      ?>
      <div class="demo-container">
          <div class="theme-toggle">
              <button class="toggle-btn" id="themeToggleBtn" aria-label="Toggle dark mode">
                  <span id="themeToggleIcon"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path stroke-linecap="round" d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07 7.07-1.42-1.42M8.35 8.35 6.93 6.93m0 10.14 1.42-1.42m9.3-9.3 1.42 1.42"/></svg></span>
                  <span id="themeToggleText">Dark Mode</span>
              </button>
          </div>
          <main class="podcast-player" aria-label="Podcast Player">
              <section class="player-header">
                  <div class="podcast-artwork" id="artwork" aria-hidden="true">
                      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8"><path d="M4 13v5a3 3 0 0 0 3 3h1v-8H7a3 3 0 0 0-3 3Zm13 0h-1v8h1a3 3 0 0 0 3-3v-5a3 3 0 0 0-3-3ZM6 13V11A6 6 0 0 1 12 5v0a6 6 0 0 1 6 6v2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      <div class="artwork-pulse" id="artworkPulse"></div>
                  </div>
                  <h1 class="podcast-title" id="episodeTitle"></h1>
                  <p class="podcast-author" id="episodeAuthor"></p>
              </section>
              <div class="waveform-container" id="waveformContainer" role="slider" aria-label="Seek" aria-valuemin="0" aria-valuemax="0" aria-valuenow="0" tabindex="0">
                  <div class="progress-overlay" id="progressOverlay"></div>
                  <div class="scrub-handle" id="scrubHandle"></div>
                  <div class="waveform" id="waveform"></div>
              </div>
              <div class="time-display">
                  <span id="currentTimeLabel">0:00</span>
                  <span id="totalTimeLabel">0:00</span>
              </div>
              <div class="player-controls" aria-label="Playback controls">
                  <button class="icon-btn" id="btnShuffle" aria-label="Shuffle (off)" data-state="off"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M3 5h3.5a4 4 0 0 1 3.2 1.6l4.6 6A4 4 0 0 0 17.5 15H21M3 19h3.5a4 4 0 0 0 3.2-1.6l.3-.4m3.2-4.4.6.8A4 4 0 0 0 17.5 15H21M21 5h-3.5a4 4 0 0 0-3.2 1.6l-.6.8M18 2l3 3-3 3M18 14l3 3-3 3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                  <button class="icon-btn" id="btnPrev" aria-label="Previous episode"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M6 5v14M18 7l-7 5 7 5V7Z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                  <button class="icon-btn" data-role="primary" id="btnPlayPause" aria-label="Play">
                      <svg id="iconPlay" viewBox="0 0 24 24" stroke="currentColor" fill="currentColor"><path d="M8 5v14l10-7z"/></svg>
                      <svg id="iconPause" viewBox="0 0 24 24" stroke="currentColor" fill="none" style="display:none;"><path d="M9 5v14M15 5v14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <button class="icon-btn" id="btnNext" aria-label="Next episode"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M18 19V5M6 17l7-5-7-5v10Z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                  <button class="icon-btn" id="btnRepeat" aria-label="Repeat (off)" data-mode="off"><svg id="repeatIcon" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 7v6a5 5 0 0 0 5 5h6m0 0-2-2m2 2-2 2M20 17V11a5 5 0 0 0-5-5H9m0 0 2 2M9 6l2-2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
              </div>
              <div class="secondary-controls">
                  <div class="volume-wrapper">
                      <button class="action-btn" id="btnMute" aria-label="Mute"><svg id="iconVolume" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 9a3 3 0 0 1 0 6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                      <div class="volume-track" id="volumeTrack" aria-label="Volume" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70" tabindex="0">
                          <div class="volume-fill" id="volumeFill"></div>
                          <div class="volume-handle" id="volumeHandle"></div>
                      </div>
                  </div>
                  <div class="actions-wrapper">
                      <button class="action-btn" id="btnLike" aria-label="Like"><svg id="iconHeartOutline" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M16.5 3a5.5 5.5 0 0 0-4.5 2.4A5.5 5.5 0 0 0 7.5 3 5.5 5.5 0 0 0 2 8.5c0 7 9 12.5 10 12.5s10-5.5 10-12.5A5.5 5.5 0 0 0 16.5 3Z" stroke-linecap="round" stroke-linejoin="round"/></svg><svg id="iconHeartFilled" viewBox="0 0 24 24" stroke="none" fill="currentColor" style="display:none;"><path d="M11.998 21.003c-.516 0-9.998-5.727-9.998-12.503A5.5 5.5 0 0 1 7.5 3a5.5 5.5 0 0 1 4.498 2.4A5.5 5.5 0 0 1 16.496 3 5.5 5.5 0 0 1 22 8.5c0 6.776-9.482 12.503-10 12.503Z"/></svg></button>
                      <button class="action-btn" id="btnShare" aria-label="Share"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M7 17a4 4 0 0 1 0-8M17 21a4 4 0 0 0 0-8M12 7V3m0 0 3 3m-3-3L9 6m3 4v6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                      <button class="action-btn" id="btnDownload" aria-label="Download"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                  </div>
              </div>
              <button class="playlist-toggle-floating" id="playlistToggleBtn" aria-expanded="false" aria-controls="playlistContainer" aria-label="Toggle playlist"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg></button>
              <section class="playlist-container" id="playlistContainer" aria-label="Playlist">
                  <h2 class="playlist-header">Up Next</h2>
                  <ul class="playlist-list" id="playlistList"></ul>
              </section>
              <audio id="audioEl" preload="metadata" hidden></audio>
          </main>
      </div>
      <?php
      return ob_get_clean();
  }


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

            // NEW podcast player assets
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

            // Settings to JS (optional)
            $default_theme = get_option('atm_podcast_default_theme', 'light'); // default = light
            $accent_color  = get_option('atm_podcast_accent', '#3b82f6');

            wp_localize_script('atm-podcast-script', 'atm_podcast_settings', array(
                'theme'  => $default_theme,
                'accent' => $accent_color,
            ));

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

    // In includes/class-atm-frontend.php

public function add_podcast_player_to_content($content) {
    if (!is_singular() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    $podcast_url = get_post_meta($post_id, '_atm_podcast_url', true);

    // Only proceed if a podcast URL exists for this post
    if (empty($podcast_url)) {
        return $content;
    }

    // --- ENQUEUE ASSETS AND PASS DATA ---
    // We now use the existing file names.
    wp_enqueue_style('atm-podcast-player', ATM_PLUGIN_URL . 'assets/css/podcast-player.css', [], ATM_VERSION);
    wp_enqueue_script('atm-podcast-player', ATM_PLUGIN_URL . 'assets/js/podcast-player.js', [], ATM_VERSION, true);

    $post = get_post($post_id);
    $episodes = [
        [
            'id' => 0,
            'title' => esc_html($post->post_title),
            'author' => 'Powered by Content AI Studio',
            'src' => esc_url($podcast_url),
            'duration' => 0, // You can store and retrieve this from post meta if available
            'icon' => 'headphones'
        ]
    ];

    // Pass the episode data to our JavaScript file
    wp_localize_script('atm-podcast-player', 'casPlayerData', ['episodes' => $episodes]);
    // --- END ENQUEUE ---


    // Get the new player HTML
    $player_html = $this->get_modern_player_html();

    // Get the default theme from plugin settings
    $theme_class = get_option('atm_podcast_theme', 'light') === 'dark' ? 'dark-theme' : '';

    // Wrap the player HTML in our high-specificity, isolated container
    $player_wrapper = '<div class="cas-player-modern ' . esc_attr($theme_class) . '">' . $player_html . '</div>';

    // **IMPORTANT**: Append the player to the end of the content
    return $content . $player_wrapper;
}

    private function print_icon_sprite_once() {
        if (self::$sprite_printed) return;
        $sprite_path = ATM_PLUGIN_PATH . 'assets/img/atm-podcast-icons.svg';
        if (file_exists($sprite_path)) {
            $svg = file_get_contents($sprite_path);
            echo '<div class="atm-icon-sprite" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">'.$svg.'</div>';
        }
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

                $items[] = array(
                    'title' => get_the_title($pid),
                    'url'   => $url,
                    'cover' => $cover,
                );
            }
            wp_reset_postdata();
        }
        return $items;
    }

    private function generate_podcast_custom_css($theme) {
        $css = "<style>";
        $css .= ".atm-podcast[data-theme='$theme'] {";
        $css .= "--atm-accent: " . get_option('atm_podcast_accent_color', '#3b82f6') . ";";
        $css .= "--atm-grad-end: " . get_option('atm_podcast_gradient_end', '#7c3aed') . ";";
        
        if ($theme === 'light') {
            $css .= "--atm-card-bg: " . get_option('atm_podcast_light_card_bg', '#ffffff') . ";";
            $css .= "--atm-card-text: " . get_option('atm_podcast_light_text', '#0f172a') . ";";
            $css .= "--atm-subtext: " . get_option('atm_podcast_light_subtext', '#64748b') . ";";
            // Add other light theme variables...
        } else {
            $css .= "--atm-card-bg: " . get_option('atm_podcast_dark_card_bg', '#0f172a') . ";";
            $css .= "--atm-card-text: " . get_option('atm_podcast_dark_text', '#e2e8f0') . ";";
            $css .= "--atm-subtext: " . get_option('atm_podcast_dark_subtext', '#94a3b8') . ";";
            // Add other dark theme variables...
        }
        
        $css .= "}";
        $css .= "</style>";
        
        return $css;
    }
    
    private function get_player_html($post, $podcast_url, $cover_image) {
        $this->print_icon_sprite_once();

        $site_name = get_bloginfo('name');
        // Replace the hardcoded theme and accent values with:
        $theme = get_option('atm_podcast_default_theme', 'light');
        $accent = get_option('atm_podcast_accent_color', '#3b82f6');
        $gradient_end = get_option('atm_podcast_gradient_end', '#7c3aed');

        // Add CSS custom properties for the advanced colors
        $custom_css = $this->generate_podcast_custom_css($theme);

        $playlist  = $this->get_recent_podcasts($post->ID, 8);

        ob_start();
        ?>
        <div class="atm-podcast" data-theme="<?php echo esc_attr($theme); ?>" style="--atm-accent: <?php echo esc_attr($accent); ?>;">
          <div class="atm-card" data-current-title="<?php echo esc_attr($post->post_title); ?>" data-current-cover="<?php echo esc_url($cover_image); ?>">
            <div class="atm-header">
              <div class="atm-left">
                <div class="atm-ep" style="--cover:url('<?php echo esc_url($cover_image); ?>');"><span>EP</span></div>
                <div class="atm-head-meta">
                  <div class="atm-show"><?php echo esc_html($site_name); ?> Podcast</div>
                  <div class="atm-season"><?php echo esc_html(get_option('atm_podcast_season_text', 'Season 1')); ?></div>
                </div>
              </div>
              <div class="atm-right">
                <button class="atm-icon-btn atm-like" aria-label="Like"><svg class="atm-ico"><use href="#atm-i-heart"/></svg></button>
                <button class="atm-icon-btn atm-share" aria-label="Share"><svg class="atm-ico"><use href="#atm-i-share"/></svg></button>
                <button class="atm-icon-btn atm-download" aria-label="Download"><svg class="atm-ico"><use href="#atm-i-download"/></svg></button>
              </div>
              <div class="atm-head-title"><?php echo esc_html($post->post_title); ?></div>
              <div class="atm-head-sub">Powered by Content AI Studio</div>
            </div>

            <div class="atm-progress">
              <div class="atm-rail">
                <div class="atm-rail-bg"></div>
                <div class="atm-rail-fill" style="width:0%"></div>
                <div class="atm-rail-knob" style="left:0%"></div>
              </div>
              <div class="atm-times">
                <span class="atm-tl">0:00</span>
                <span class="atm-tr">0:00</span>
              </div>
            </div>

            <div class="atm-transport">
              <button class="atm-ctrl atm-toggle atm-loop" aria-label="Loop"><svg class="atm-ico"><use href="#atm-i-repeat"/></svg></button>
              <button class="atm-ctrl atm-prev" aria-label="Previous"><svg class="atm-ico"><use href="#atm-i-prev"/></svg></button>
              <button class="atm-play-btn" aria-label="Play"><svg class="atm-ico"><use href="#atm-i-play"/></svg></button>
              <button class="atm-ctrl atm-next" aria-label="Next"><svg class="atm-ico"><use href="#atm-i-next"/></svg></button>
              <button class="atm-ctrl atm-toggle atm-shuffle" aria-label="Shuffle"><svg class="atm-ico"><use href="#atm-i-shuffle"/></svg></button>
            </div>

            <div class="atm-bottom">
              <div class="atm-volume">
                <svg class="atm-ico atm-vol-ico"><use href="#atm-i-volume"/></svg>
                <div class="atm-vol-track">
                  <input type="range" min="0" max="100" value="75" class="atm-vol-range" />
                  <div class="atm-vol-fill" style="width:75%"></div>
                </div>
                <span class="atm-vol-val">75%</span>
              </div>
              <div class="atm-speed">
                <select class="atm-speed-select">
                  <option>0.75x</option>
                  <option selected>1x</option>
                  <option>1.25x</option>
                  <option>1.5x</option>
                  <option>2x</option>
                </select>
              </div>
              <button class="atm-icon-btn atm-pl-toggle" aria-label="Toggle playlist"><svg class="atm-ico"><use href="#atm-i-list"/></svg></button>
            </div>

            <?php if (!empty($playlist)) : ?>
            <div class="atm-playlist" hidden>
              <div class="atm-pl-head">Latest Podcasts</div>
              <ul class="atm-pl-list">
                <?php foreach ($playlist as $item) : ?>
                  <li class="atm-pl-item" data-url="<?php echo esc_url($item['url']); ?>" data-title="<?php echo esc_attr($item['title']); ?>">
                    <img src="<?php echo esc_url($item['cover']); ?>" alt="" />
                    <div class="atm-pl-meta">
                      <div class="atm-pl-title"><?php echo esc_html($item['title']); ?></div>
                      <div class="atm-pl-sub"><?php echo esc_html($site_name); ?> Podcast</div>
                    </div>
                    <button class="atm-pl-more" aria-label="More"></button>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>

            <audio class="atm-audio" src="<?php echo esc_url($podcast_url); ?>" preload="metadata"></audio>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}