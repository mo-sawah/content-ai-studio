<?php
// /includes/admin/class-atm-meta-box.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Meta_Box {

    public function add_meta_boxes() {
        add_meta_box(
            'article-to-media',
            'Content and Media Generator',
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('atm_meta_box', 'atm_meta_box_nonce');
        
        echo '<div class="atm-meta-box">';
        
        if (empty(get_option('atm_openrouter_api_key'))) {
            echo '<div class="atm-error">⚠️ Please configure your OpenRouter API key in <a href="' . admin_url('options-general.php?page=article-to-media') . '">Settings</a> to use generators.</div>';
        }

        $this->render_article_generator($post);
        $this->render_image_generator($post);
        $this->render_podcast_generator($post);
        
        echo '</div>'; // .atm-meta-box
    }

    private function render_article_generator($post) {
    // Get available models and the default one for display
    $settings_models = new ATM_Settings();
    $options = $settings_models->get_settings();
    $available_models = $options['article_models'];
    $default_model = $options['article_model'];
    $default_model_display = isset($available_models[$default_model]) ? $available_models[$default_model] : $default_model;

    // Get available writing styles
    $writing_styles = ATM_API::get_writing_styles();
    ?>
    <div class="atm-section">
        <h4>📝 Generate Article</h4>

        <div class="atm-form-group">
            <label for="atm-article-type-select">Article Type</label>
            <div class="atm-select-wrapper">
                <select id="atm-article-type-select">
                    <option value="creative">Creative Article</option>
                    <option value="news">Latest News Article</option>
                    <option value="rss_feed">Article from RSS Feed</option>
                </select>
            </div>
        </div>

<div id="atm-rss-feed-wrapper" style="display: none;">
    <p class="description" style="margin-bottom: 15px;">
        Fetch articles from your configured RSS feeds and generate content from them.
    </p>

    <!-- Search Controls -->
    <div class="atm-form-group">
        <label for="atm-rss-keyword">Search by Keyword</label>
        <input type="text" id="atm-rss-keyword" class="atm-input" placeholder="e.g., Trump, AI, Healthcare">
        <p class="description">Leave empty to fetch the most recent articles from all feeds.</p>
    </div>

    <!-- Enhanced Options -->
    <div class="atm-form-group">
        <label>
            <input type="checkbox" id="atm-rss-deep-search" style="width: auto; margin-right: 8px;">
            Deep Content Search
        </label>
        <p class="description" style="margin-top: 4px;">
            Scrapes full article content for more accurate keyword matching (slower but more precise).
        </p>
    </div>

    <div class="atm-form-group">
        <label>
            <input type="checkbox" id="atm-rss-use-full-content" style="width: auto; margin-right: 8px;" checked>
            Use Full Article Content
        </label>
        <p class="description" style="margin-top: 4px;">
            Scrapes the complete article content for better AI rewriting (recommended).
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="atm-grid-2">
        <button type="button" class="atm-button" id="atm-search-rss-btn">
            Search Feeds
        </button>
        <button type="button" class="atm-button" id="atm-fetch-rss-btn">
            Fetch Latest Articles
        </button>
    </div>

    <!-- Results Container -->
    <div id="atm-rss-results" style="margin-top: 20px;"></div>

</div>

        <div id="atm-standard-article-wrapper">
            <div id="atm-news-options-wrapper">
                <div class="atm-form-group" id="atm-news-source-wrapper" style="display: none;">
                    <label for="atm-news-source-select">News Source</label>
                    <div class="atm-select-wrapper">
                        <select id="atm-news-source-select">
                            <option value="newsapi">NewsAPI.org</option>
                            <option value="gnews">GNews.io</option>
                            <option value="guardian">The Guardian</option>
                        </select>
                    </div>
                </div>

                <div class="atm-form-group" id="atm-force-fresh-wrapper" style="display: none;">
                    <label for="atm-force-fresh-search">
                        <input type="checkbox" id="atm-force-fresh-search" style="width: auto; margin-right: 8px;">
                        Force fresh search (bypasses cache)
                    </label>
                    <p class="description" style="margin-top: 4px;">Check this if you want to generate multiple unique articles on the same topic.</p>
                </div>
            </div>

            <div class="atm-grid-2">
                <div class="atm-form-group">
                    <label for="atm-article-keyword">Keyword</label>
                    <input type="text" id="atm-article-keyword" class="atm-input" placeholder="e.g., AI in digital marketing">
                </div>
                <div class="atm-form-group">
                    <label for="atm-article-title-input">or Article Title</label>
                    <input type="text" id="atm-article-title-input" class="atm-input" placeholder="e.g., 5 Ways AI is Revolutionizing Marketing">
                </div>
            </div>

            <div class="atm-grid-3">
                <div class="atm-form-group">
                    <label for="atm-article-model-select">Article Model (Default: <?php echo esc_html($default_model_display); ?>)</label>
                    <div class="atm-select-wrapper">
                        <select id="atm-article-model-select">
                            <option value="">Use Default</option>
                            <?php foreach ($available_models as $model_id => $model_name): ?>
                                <option value="<?php echo esc_attr($model_id); ?>"><?php echo esc_html($model_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="atm-form-group">
                    <label for="atm-writing-style-select">Writing Style</label>
                    <div class="atm-select-wrapper">
                        <select id="atm-writing-style-select">
                            <?php foreach ($writing_styles as $style_key => $style_data): ?>
                                <option value="<?php echo esc_attr($style_key); ?>"><?php echo esc_html($style_data['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="atm-form-group">
                    <label for="atm-word-count-select">Article Length</label>
                    <div class="atm-select-wrapper">
                        <select id="atm-word-count-select">
                            <option value="">Default</option>
                            <option value="500">Short (~500 words)</option>
                            <option value="800">Standard (~800 words)</option>
                            <option value="1200">Medium (~1200 words)</option>
                            <option value="2000">Long (~2000 words)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="atm-form-group">
                <label for="atm-custom-article-prompt">Custom Prompt (Optional)</label>
                <textarea id="atm-custom-article-prompt" class="atm-textarea" rows="8" placeholder="Leave empty to use the selected Writing Style above. If you write a prompt here, it will be used instead."></textarea>
            </div>
            
            </div>

            <div class="atm-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" id="atm-generate-image-with-article" style="width: auto; margin-right: 8px;">
                    Also generate a featured image
                </label>
                <p class="description" style="margin-top: 4px;">Generates an image based on the article's new title and content after the article is created.</p>
            </div>
            
            <button type="button" class="atm-button atm-primary" id="atm-generate-article-btn" data-post-id="<?php echo $post->ID; ?>">Generate Article</button>
        
        </div> </div>
    <?php
}

// NOTE: To make the get_settings() method accessible, you must change its visibility in class-atm-settings.php
// from 'private function get_settings()' to 'public function get_settings()'.

    private function render_image_generator($post) {
        ?>
        <div class="atm-section">
            <h4>🖼️ Generate Featured Image</h4>
            <div class="atm-form-group">
                <label for="atm-image-prompt">Image Prompt (Optional)</label>
                <textarea id="atm-image-prompt" class="atm-textarea" rows="3" placeholder="Leave empty to automatically generate a prompt based on the article's title and content."></textarea>
                <p class="description">
                    Available shortcodes: <code>[article_title]</code>, <code>[site_name]</code>, <code>[site_url]</code>
                </p>
            </div>
            <button type="button" class="atm-button atm-primary" id="atm-generate-image-btn" data-post-id="<?php echo $post->ID; ?>">Generate & Set Featured Image</button>
        </div>
        <?php
    }

    private function render_podcast_generator($post) {
        $podcast_url = get_post_meta($post->ID, '_atm_podcast_url', true);
        $podcast_status = get_post_meta($post->ID, '_atm_podcast_status', true);
        ?>
        <div class="atm-section">
            <h4>🎙️ Generate Podcast</h4>
            <?php
            $this->render_podcast_form_fields();

            if ($podcast_url) {
                $this->render_podcast_completed_view($post, $podcast_url);
            } elseif ($podcast_status === 'generating') {
                echo '<div class="atm-generating"><div class="atm-spinner"></div><span>Creating your podcast...</span></div>';
            } else {
                echo '<button type="button" class="atm-button atm-primary" id="atm-generate-podcast-btn" data-post-id="' . $post->ID . '">Generate Podcast</button>';
            }
            ?>
        </div>
        <?php
    }

    private function render_podcast_form_fields() {
        ?>
        <div class="atm-form-group">
            <label for="atm-language-select">Podcast Language</label>
            <div class="atm-select-wrapper">
                <select id="atm-language-select">
                    <option value="English">English</option>
                    <option value="Spanish">Spanish</option>
                    <option value="French">French</option>
                    <option value="German">German</option>
                    <option value="Italian">Italian</option>
                    <option value="Portuguese">Portuguese</option>
                    <option value="Dutch">Dutch</option>
                    <option value="Russian">Russian</option>
                    <option value="Mandarin Chinese">Mandarin Chinese</option>
                    <option value="Japanese">Japanese</option>
                    <option value="Korean">Korean</option>
                    <option value="Arabic">Arabic</option>
                    <option value="Hindi">Hindi</option>
                    <option value="Bengali">Bengali</option>
                    <option value="Turkish">Turkish</option>
                    <option value="Indonesian">Indonesian</option>
                    <option value="Polish">Polish</option>
                    <option value="Swedish">Swedish</option>
                    <option value="Norwegian">Norwegian</option>
                    <option value="Danish">Danish</option>
                </select>
            </div>
        </div>

        <div class="atm-form-group">
            <label for="atm-podcast-script">
                Podcast Script
                <button type="button" id="atm-generate-script-btn" class="atm-button-small" style="margin-left: 10px; vertical-align: middle; padding: 3px 12px;">
                    <span class="atm-btn-text">Generate Script</span>
                    <div class="atm-spinner" style="display: none; margin-left: 5px;"></div>
                </button>
            </label>
            <textarea id="atm-podcast-script" class="atm-textarea" rows="12" placeholder="Click 'Generate Script' to create a podcast script from your article..."></textarea>
            <p class="description">This script will be used to generate the final podcast audio.</p>
        </div>

        <?php
        $voice_names = get_option('atm_voice_names', []);
        $default_voice = get_option('atm_voice_selection', 'alloy');
        $available_voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
        $default_display_name = $default_voice === 'random' ? 'Random' : (isset($voice_names[$default_voice]) && !empty($voice_names[$default_voice]) ? esc_html($voice_names[$default_voice]) : ucfirst($default_voice));
        ?>
        <div class="atm-form-group">
            <label for="atm-voice-select">Voice (Default: <?php echo $default_display_name; ?>):</label>
            <div class="atm-select-wrapper">
                <select id="atm-voice-select">
                    <option value="">Use Default</option>
                    <option value="random">Random</option>
                    <?php
                    foreach ($available_voices as $voice) {
                        $display_name = isset($voice_names[$voice]) && !empty($voice_names[$voice]) ? esc_html($voice_names[$voice]) : ucfirst($voice);
                        echo '<option value="' . esc_attr($voice) . '">' . $display_name . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
        <?php
    }

    private function render_podcast_completed_view($post, $podcast_url) {
        $podcast_image = get_post_meta($post->ID, '_atm_podcast_image', true);
        ?>
        <div class="atm-success">✅ Podcast Ready!</div>
        <div class="atm-image-section">
            <label><strong>Podcast Cover Image:</strong></label>
            <?php if ($podcast_image): ?>
                <img src="<?php echo esc_url($podcast_image); ?>" class="atm-podcast-preview" style="width: 100%; max-width: 200px; border-radius: 8px; margin: 10px 0;" />
                <button type="button" class="atm-button-small atm-change-image" data-post-id="<?php echo $post->ID; ?>">Change Image</button>
            <?php else: ?>
                <button type="button" class="atm-button-small atm-upload-image" data-post-id="<?php echo $post->ID; ?>">Upload Cover Image</button>
            <?php endif; ?>
        </div>
        <audio controls style="width: 100%; margin: 10px 0;">
            <source src="<?php echo esc_url($podcast_url); ?>" type="audio/mpeg">
        </audio>
        <button type="button" class="atm-button atm-regenerate" data-post-id="<?php echo $post->ID; ?>" data-type="podcast">
            🔄 Regenerate Podcast
        </button>
        <?php
    }
}