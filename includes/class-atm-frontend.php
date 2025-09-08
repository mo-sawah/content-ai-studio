<?php
// /includes/class-atm-frontend.php
// Updated: default theme = light; uses settings; unchanged functionality otherwise.

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