<?php
// /includes/admin/class-atm-settings.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Settings {

    private function render_license_section() {
        $license_data = ATM_Licensing::get_license_data();
        $is_active = ATM_Licensing::is_license_active();
        ?>
        <div class="atm-settings-card">
            <h2>🛡️ License Key</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">License Key</th>
                    <td>
                        <input type="text" name="atm_license_key" value="<?php echo esc_attr($license_data['key']); ?>" class="regular-text" <?php echo $is_active ? 'disabled' : ''; ?> />
                        <?php if ($is_active): ?>
                            <p class="description" style="color: #2f855a;">✅ Your license is active.</p>
                        <?php else: ?>
                            <p class="description">Enter your license key to activate the plugin.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php if ($is_active): ?>
                <?php submit_button('Deactivate License', 'secondary', 'submit_deactivate_license', false); ?>
            <?php else: ?>
                <?php submit_button('Activate License', 'primary', 'submit_activate_license', false); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_admin_menu() {
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="atm-grad" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse"><stop stop-color="#8E2DE2" /><stop offset="1" stop-color="#4A00E0" /></linearGradient></defs><rect x="2" y="13" width="20" height="9" rx="2" fill="url(#atm-grad)" opacity="0.6" /><path d="M6 16H18 M6 18H15" stroke="white" stroke-width="1.2" stroke-linecap="round" /><rect x="2" y="8" width="20" height="9" rx="2" fill="url(#atm-grad)" opacity="0.8" /><path d="M6 12.5H8L10 11L12 14L14 11L16 12.5H18" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" /><rect x="2" y="3" width="20" height="9" rx="2" fill="url(#atm-grad)" /><circle cx="8" cy="7" r="1" fill="white" /><path d="M6 10L9 8L13 9.5L18 7" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" /></svg>');

        add_menu_page('Content AI Studio', 'AI Studio', 'manage_options', 'content-ai-studio', array($this, 'render_settings_page'), $icon_svg, 25);
        add_submenu_page('content-ai-studio', 'Settings', 'Settings', 'manage_options', 'content-ai-studio', array($this, 'render_settings_page'));
        add_submenu_page('content-ai-studio', 'Automatic', 'Automatic', 'manage_options', 'content-ai-studio-automatic', array($this, 'render_automatic_page'));
        add_submenu_page('content-ai-studio', 'About Content AI Studio', 'About', 'manage_options', 'content-ai-studio-about', array($this, 'render_about_page'));
        add_submenu_page('content-ai-studio', 'Support', 'Support', 'manage_options', 'content-ai-studio-support', array($this, 'render_support_page'));
    }

    public function render_automatic_page() {
        // This function will decide whether to show the list or the add/edit form
        $action = $_GET['action'] ?? 'list';
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        echo '<div class="wrap atm-settings">';
        
        if ('edit' === $action || 'add' === $action) {
            $this->render_campaign_form($campaign_id);
        } else {
            $this->render_campaigns_list();
        }

        echo '</div>';
    }

    // Add these two new functions to class-atm-settings.php

    private function render_campaigns_list() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaigns = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
        ?>
        <div class="atm-header">
            <h1>⚙️ Automatic Campaigns</h1>
            <p class="atm-subtitle">Create and manage automated content generation schedules.</p>
        </div>
        <a href="?page=content-ai-studio-automatic&action=add" class="page-title-action">Add New Campaign</a>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Frequency</th>
                    <th>Next Run</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td><strong><?php echo esc_html($campaign->keyword); ?></strong></td>
                        <td>Every <?php echo esc_html($campaign->frequency_value . ' ' . $campaign->frequency_unit); ?>(s)</td>
                        <td><?php echo esc_html(wp_next_scheduled('atm_run_campaign_' . $campaign->id, [$campaign->id]) ? date('Y-m-d H:i:s', wp_next_scheduled('atm_run_campaign_' . $campaign->id, [$campaign->id])) : 'Not Scheduled'); ?></td>
                        <td><?php echo $campaign->is_active ? 'Active' : 'Paused'; ?></td>
                        <td>
                            <a href="?page=content-ai-studio-automatic&action=edit&id=<?php echo $campaign->id; ?>">Edit</a> | 
                            <a href="#" class="atm-delete-campaign" data-id="<?php echo $campaign->id; ?>">Delete</a> | 
                            <a href="#" class="atm-run-campaign" data-id="<?php echo $campaign->id; ?>">Run Now</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_campaign_form($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign = null;
        $is_editing = $campaign_id > 0;

        if ($is_editing) {
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
        }

        // Set default values for a new campaign
        $defaults = [
        'keyword' => '', 'country' => '', 'article_type' => 'Informative',
        'custom_prompt' => ATM_API::get_default_article_prompt(),
        'generate_image' => 0, 'category_id' => get_option('default_category'),
        'author_id' => get_current_user_id(), 'post_status' => 'draft',
        'frequency_value' => 1, 'frequency_unit' => 'day',
        'source_keywords' => '',           // <-- Add this
        'source_urls' => '',               // <-- Add this
        'strict_keyword_matching' => 1     // <-- Add this
        ];

        // Merge campaign data with defaults
        $data = (object) wp_parse_args($campaign, $defaults);
        ?>
        <div class="atm-header">
            <h1><?php echo $is_editing ? 'Edit Campaign' : 'Add New Campaign'; ?></h1>
            <?php if ($is_editing): ?>
                <a href="?page=content-ai-studio-automatic" class="page-title-action">Add New</a>
            <?php endif; ?>
        </div>
        <form method="post" id="atm-campaign-form">
            <input type="hidden" name="action" value="atm_save_campaign">
            <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
            <div class="atm-settings-card">
                <h2>Campaign Details</h2>
                <div class="atm-settings-grid" style="grid-template-columns: 1fr 1fr 1fr; align-items: end;">
                    <div class="form-group">
                        <label for="atm-keyword">Keyword</label>
                        <input type="text" id="atm-keyword" name="keyword" value="<?php echo esc_attr($data->keyword); ?>" class="regular-text" required>
                    </div>
                    <div class="form-group">
                        <label for="atm-country">Country</label>
                        <input type="text" id="atm-country" name="country" value="<?php echo esc_attr($data->country); ?>" class="regular-text" placeholder="e.g., United States">
                    </div>
                    <div class="form-group">
                        <label for="atm-article-type">Article Type</label>
                        <select id="atm-article-type" name="article_type">
                            <?php $types = ['News', 'Informative', 'Review', 'How-To', 'Opinion', 'Tutorial', 'Listicle']; ?>
                            <?php foreach($types as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($data->article_type, $type); ?>><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label for="atm-custom-prompt">Custom Prompt (Optional)</label>
                    <textarea id="atm-custom-prompt" name="custom_prompt" rows="8"><?php echo esc_textarea($data->custom_prompt); ?></textarea>
                    <p class="description">This prompt will be used to generate the article. The keyword, country, and article type will be automatically included.</p>
                </div>
            </div>

            <div class="atm-settings-card">
            <h2>📰 Sources</h2>
            <div class="form-group">
                <label for="atm-source-keywords">Source Keywords</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="text" id="atm-source-keywords" name="source_keywords" value="<?php echo esc_attr($data->source_keywords ?? ''); ?>" class="regular-text" style="flex-grow: 1;" placeholder="us politics, tech reviews, etc.">
                    
                    <?php // This button will submit the form to save and trigger the source finding logic ?>
                    <button type="submit" name="find_sources" value="1" class="button button-secondary">Find Sources</button>
                </div>
                <p class="description">Enter comma-separated keywords. The AI will find the top 10 most relevant news source pages for each. Google News is always included.</p>
            </div>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label for="atm-source-urls">Source URLs (one per line)</label>
                <textarea id="atm-source-urls" name="source_urls" rows="10"><?php echo esc_textarea($data->source_urls ?? ''); ?></textarea>
                <p class="description">The AI will crawl these pages to find the latest articles. You can add, edit, or remove URLs.</p>
            </div>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label>
                    <input type="checkbox" name="strict_keyword_matching" value="1" <?php checked($data->strict_keyword_matching ?? 1, 1); ?>>
                    <strong>Strict Keyword Matching:</strong> Only use articles from these sources if their title contains one of your main keywords.
                </label>
            </div>
        </div>

            <div class="atm-settings-card">
                <h2>Post Settings</h2>
                <div class="form-group">
                    <label><input type="checkbox" name="generate_image" value="1" <?php checked($data->generate_image, 1); ?>> Generate Featured Image</label>
                </div>
                <div class="atm-settings-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-top: 1.5rem; align-items: end;">
                    <div class="form-group">
                        <label for="atm-category">Default Category</label>
                        <?php wp_dropdown_categories(['name' => 'category_id', 'id' => 'atm-category', 'selected' => $data->category_id, 'show_option_none' => 'Select Category', 'option_none_value' => '0', 'hide_empty' => 0, 'class' => '']); ?>
                    </div>
                    <div class="form-group">
                        <label for="atm-author">Author</label>
                        <?php wp_dropdown_users(['name' => 'author_id', 'id' => 'atm-author', 'selected' => $data->author_id, 'capability' => 'edit_posts']); ?>
                    </div>
                    <div class="form-group">
                        <label for="atm-post-status">Post Status</label>
                        <select id="atm-post-status" name="post_status">
                            <option value="draft" <?php selected($data->post_status, 'draft'); ?>>Draft</option>
                            <option value="pending" <?php selected($data->post_status, 'pending'); ?>>Pending Review</option>
                            <option value="publish" <?php selected($data->post_status, 'publish'); ?>>Publish</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="atm-settings-card">
                <h2>Schedule</h2>
                <div style="display: flex; gap: 1rem; align-items: end;">
                    <div class="form-group" style="flex: 1;">
                        <label for="atm-frequency-value">Run Every</label>
                        <input type="number" id="atm-frequency-value" name="frequency_value" value="<?php echo esc_attr($data->frequency_value); ?>" min="1" style="width: 100px;">
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <select id="atm-frequency-unit" name="frequency_unit">
                            <option value="minute" <?php selected($data->frequency_unit, 'minute'); ?>>Minute(s)</option>
                            <option value="hour" <?php selected($data->frequency_unit, 'hour'); ?>>Hour(s)</option>
                            <option value="day" <?php selected($data->frequency_unit, 'day'); ?>>Day(s)</option>
                            <option value="week" <?php selected($data->frequency_unit, 'week'); ?>>Week(s)</option>
                            <option value="month" <?php selected($data->frequency_unit, 'month'); ?>>Month(s)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php submit_button($is_editing ? 'Update Campaign' : 'Publish Campaign', 'primary', 'submit', false, ['id' => 'atm-save-campaign-btn']); ?>
            
            <?php if ($is_editing): ?>
                <button type="button" class="button button-secondary" id="atm-run-now-btn" data-id="<?php echo $campaign_id; ?>">Generate Article</button>
                <span id="atm-run-now-status" style="margin-left: 10px; font-style: italic;"></span>
            <?php endif; ?>
        </form>
        <?php
    }
    
    public function render_about_page() {
        ?>
        <div class="wrap atm-settings atm-about-page">
            <div class="atm-header"><h1>👋 About Content AI Studio</h1><p class="atm-subtitle">Your all-in-one AI toolkit for content creation</p></div>
            <div class="atm-settings-card">
                <h2>Brought to You by Sawah Solutions</h2>
                <div class="atm-about-content">
                    <p><strong>Content AI Studio</strong> was designed and developed by the team at <strong>Sawah Solutions</strong>, led by founder <strong>Mohamed Sawah</strong>. Our mission is to build powerful, intuitive tools that help creators and businesses save time and amplify their message.</p>
                    <p>We believe in the transformative power of AI to streamline workflows and unlock new creative possibilities. This plugin is our contribution to the vibrant WordPress community.</p>
                    <div class="atm-about-links">
                        <a href="https://sawahsolutions.com/" class="atm-button atm-primary" target="_blank" rel="noopener">Visit Our Website</a>
                        <a href="https://sawahsolutions.com/contact/" class="atm-button" target="_blank" rel="noopener">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_support_page() {
        ?>
        <div class="wrap atm-settings atm-support-page">
             <div class="atm-header"><h1>🚀 Support & Documentation</h1><p class="atm-subtitle">We're here to help you get the most out of Content AI Studio</p></div>
            <div class="atm-settings-card">
                <h2>Get Help</h2>
                <p>For detailed guides, tutorials, and frequently asked questions, please visit our official plugin support page. You'll find everything you need to get started and master the advanced features of the plugin.</p>
                <a href="https://sawahsolutions.com/content-ai-studio/" class="atm-button atm-primary" target="_blank" rel="noopener">Go to Support Page</a>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
    if (isset($_POST['submit_activate_license'])) {
        $result = ATM_Licensing::activate_license(sanitize_text_field($_POST['atm_license_key']));
        echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    } elseif (isset($_POST['submit_deactivate_license'])) {
        $result = ATM_Licensing::deactivate_license();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    } elseif (isset($_POST['submit'])) {
        $this->save_settings();
    }

    $options = $this->get_settings();
    ?>
    <div class="wrap atm-settings">
        <div class="atm-header"><h1>🎙️ Content AI Studio Settings</h1><p class="atm-subtitle">by Sawah Solutions | <a href="https://sawahsolutions.com/content-ai-studio" target="_blank">Plugin Website</a></p></div>
        <form method="post" action="">
            <?php wp_nonce_field('atm_settings_update'); ?>
            <?php $this->render_license_section(); ?>

            <div class="atm-settings-card">
                <h2>🔑 API Configuration</h2>
                <table class="form-table">
                    <tr><th scope="row">OpenRouter API Key</th><td><input type="password" name="atm_openrouter_api_key" value="<?php echo esc_attr($options['openrouter_key']); ?>" class="regular-text" required /></td></tr>
                    <tr><th scope="row">OpenAI API Key</th><td><input type="password" name="atm_openai_api_key" value="<?php echo esc_attr($options['openai_key']); ?>" class="regular-text" /><p class="description">Used for DALL-E 3 image generation and OpenAI TTS voices.</p></td></tr>
                    <tr><th scope="row">Google AI API Key</th><td><input type="password" name="atm_google_api_key" value="<?php echo esc_attr($options['google_key']); ?>" class="regular-text" /><p class="description">Used for Imagen 4 image generation. Get a key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</p></td></tr>
                    <tr><th scope="row">BlockFlow API Key</th><td><input type="password" name="atm_blockflow_api_key" value="<?php echo esc_attr($options['blockflow_key']); ?>" class="regular-text" /><p class="description">Required for FLUX image generation. Get a key from <a href="https://app.blockflow.ai/" target="_blank">BlockFlow.ai</a>.</p></td></tr>
                    <tr><th scope="row">ElevenLabs API Key</th><td><input type="password" name="atm_elevenlabs_api_key" value="<?php echo esc_attr($options['elevenlabs_key']); ?>" class="regular-text" /><p class="description">Get a key from <a href="https://elevenlabs.io/" target="_blank">ElevenLabs</a> for additional high-quality voices.</p></td></tr>
                    <tr><th scope="row">News API Key</th><td><input type="password" name="atm_news_api_key" value="<?php echo esc_attr($options['news_api_key']); ?>" class="regular-text" /><p class="description">Get a free key from <a href="https://newsapi.org/" target="_blank">NewsAPI.org</a>.</p></td></tr>
                    <tr><th scope="row">GNews API Key</th><td><input type="password" name="atm_gnews_api_key" value="<?php echo esc_attr($options['gnews_api_key']); ?>" class="regular-text" /><p class="description">Get a free key from <a href="https://gnews.io/" target="_blank">GNews.io</a>.</p></td></tr>
                    <tr><th scope="row">The Guardian API Key</th><td><input type="password" name="atm_guardian_api_key" value="<?php echo esc_attr($options['guardian_api_key']); ?>" class="regular-text" /><p class="description">Get a free key from <a href="https://open-platform.theguardian.com/" target="_blank">The Guardian</a>.</p></td></tr>
                    <tr><th scope="row">Google API Key (for YouTube Search)</th><td><input type="password" name="atm_google_youtube_api_key" value="<?php echo esc_attr($options['google_youtube_key']); ?>" class="regular-text" /><p class="description">Required for the Video Search feature. Get a key from the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</p></td></tr>
                    <tr><th scope="row">ScrapingAnt API Key</th><td><input type="password" name="atm_scrapingant_api_key" value="<?php echo esc_attr($options['scrapingant_key']); ?>" class="regular-text" /><p class="description">Required for RSS scraping. Get a free key from <a href="https://scrapingant.com/" target="_blank">ScrapingAnt.com</a>.</p></td></tr>
                </table>
            </div>

            <div class="atm-settings-grid">
                <div class="atm-settings-card">
                    <h2>🎯 AI Model Defaults</h2>
                    <table class="form-table">
                        <tr><th scope="row">Default Article Model</th><td><select name="atm_article_model"><?php foreach ($options['article_models'] as $model_id => $model_name): ?><option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['article_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th scope="row">Default Podcast Content Model</th><td><select name="atm_content_model"><?php foreach ($options['content_models'] as $model_id => $model_name): ?><option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['content_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th scope="row">Default Podcast Audio Provider</th><td><select name="atm_audio_provider"><option value="openai" <?php selected($options['audio_provider'], 'openai'); ?>>OpenAI TTS</option><option value="elevenlabs" <?php selected($options['audio_provider'], 'elevenlabs'); ?>>ElevenLabs</option></select></td></tr>
                        <tr><th scope="row">Translation Quality Model</th><td><select name="atm_translation_model"><?php foreach ($options['translation_models'] as $model_id => $model_name): ?><option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['translation_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option><?php endforeach; ?></select><p class="description">"High Quality" is more accurate but slower and more expensive. "Fast" is good for quick drafts.</p></td></tr>
                        <tr>
                            <th scope="row">Web Search Results</th>
                            <td>
                                <select name="atm_web_search_results">
                                    <option value="1" <?php selected($options['web_search_results'], '1'); ?>>1 Result (Fastest & Cheapest)</option>
                                    <option value="2" <?php selected($options['web_search_results'], '2'); ?>>2 Results</option>
                                    <option value="3" <?php selected($options['web_search_results'], '3'); ?>>3 Results</option>
                                    <option value="4" <?php selected($options['web_search_results'], '4'); ?>>4 Results</option>
                                    <option value="5" <?php selected($options['web_search_results'], '5'); ?>>5 Results (Balanced)</option>
                                    <option value="6" <?php selected($options['web_search_results'], '6'); ?>>6 Results</option>
                                    <option value="7" <?php selected($options['web_search_results'], '7'); ?>>7 Results</option>
                                    <option value="8" <?php selected($options['web_search_results'], '8'); ?>>8 Results</option>
                                    <option value="9" <?php selected($options['web_search_results'], '9'); ?>>9 Results</option>
                                    <option value="10" <?php selected($options['web_search_results'], '10'); ?>>10 Results (Most Detailed)</option>
                                </select>
                                <p class="description">Control the number of web search results used. Each search result costs $0.004, so 1 result = $0.004, 5 results = $0.02, 10 results = $0.04 per request.</p>
                            </td>
                        </tr>
                        <tr>
                        <th scope="row">Theme's Subtitle Field Key</th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="text" id="atm_theme_subtitle_key_field" name="atm_theme_subtitle_key" value="<?php echo esc_attr($options['theme_subtitle_key']); ?>" class="regular-text" style="flex-grow: 1;" />
                                <button type="button" id="atm-detect-subtitle-key-btn" class="button">Smart Scan</button>
                            </div>
                            <span id="atm-scan-status" style="font-style: italic; font-size: 13px; color: #718096; display: block; margin-top: 8px;"></span>
                            <p class="description">Optional. Click "Smart Scan" to try and auto-detect your theme's subtitle field, or enter its custom field name (meta key) manually.</p>
                        </td>
                    </tr>
                    </table>
                </div>
                <div class="atm-settings-card">
                    <h2>🖼️ Image Generation Defaults</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Default Image Provider</th>
                            <td>
                                <select name="atm_image_provider">
                                    <option value="openai" <?php selected($options['image_provider'], 'openai'); ?>>OpenAI (DALL-E 3)</option>
                                    <option value="google" <?php selected($options['image_provider'], 'google'); ?>>Google (Imagen 4)</option>
                                    <option value="blockflow" <?php selected($options['image_provider'], 'blockflow'); ?>>BlockFlow (FLUX)</option> </select>
                            </td>
                        </tr>
                        <tr><th scope="row">Default FLUX Image Model</th><td><select name="atm_flux_model"><?php foreach ($options['flux_models'] as $model_id => $model_name): ?><option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['flux_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option><?php endforeach; ?></select><p class="description">Select the default FLUX model for realistic image generation.</p></td></tr>
                        <tr><th scope="row">Default Image Quality</th><td><select name="atm_image_quality"><option value="standard" <?php selected($options['image_quality'], 'standard'); ?>>Standard</option><option value="hd" <?php selected($options['image_quality'], 'hd'); ?>>HD</option></select><p class="description">"HD" is only for OpenAI/DALL-E 3.</p></td></tr>
                        <tr><th scope="row">Default Image Size</th><td><select name="atm_image_size"><option value="1792x1024" <?php selected($options['image_size'], '1792x1024'); ?>>16:9 Landscape</option><option value="1024x1024" <?php selected($options['image_size'], '1024x1024'); ?>>1:1 Square</option><option value="1024x1792" <?php selected($options['image_size'], '1024x1792'); ?>>9:16 Portrait</option></select></td></tr>
                    </table>
                </div>
            </div>

            <!-- NEW: Comments Generator Settings -->
            <div class="atm-settings-card">
                <h2>💬 Comments Generator</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Generate on Publish</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atm_comments_auto_on_publish" value="1" <?php checked((bool)$options['comments_auto_on_publish'], true); ?> />
                                Enable background generation of comments when a post is published.
                            </label>
                            <p class="description">A background job runs shortly after publish so it never blocks the editor.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Count</th>
                        <td>
                            <input type="number" name="atm_comments_default_count" min="5" max="50" value="<?php echo esc_attr($options['comments_default_count']); ?>" />
                            <p class="description">How many comments to generate automatically on publish.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Allow Threaded Replies</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atm_comments_threaded" value="1" <?php checked((bool)$options['comments_threaded'], true); ?> />
                                Let some comments reply to others for realistic threads.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Approve Immediately</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atm_comments_approve" value="1" <?php checked((bool)$options['comments_approve'], false); ?> />
                                Approve comments upon creation (otherwise saved as Pending).
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model (optional)</th>
                        <td>
                            <select name="atm_comments_model">
                                <option value="" <?php selected($options['comments_model'], ''); ?>>Use Default Content Model</option>
                                <?php foreach ($options['content_models'] as $model_id => $model_name): ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['comments_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Leave empty to use the default Content Model above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Timestamp Window (days)</th>
                        <td>
                            <input type="number" name="atm_comments_randomize_window_days" min="1" max="30" value="<?php echo esc_attr($options['comments_randomize_window_days']); ?>" />
                            <p class="description">Randomize comment dates between post publish time and now, capped by this window.</p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- /Comments Generator Settings -->

            <div class="atm-settings-card">
                <h2>RSS Feeds</h2>
                <table class="form-table"><tr><th scope="row">RSS Feed URLs</th><td><textarea name="atm_rss_feeds" rows="8" style="width:100%;"><?php echo esc_textarea($options['rss_feeds']); ?></textarea><p class="description">Add one RSS feed URL per line.</p></td></tr></table>
            </div>

            <?php submit_button('Save Settings', 'primary', 'submit', false, ['class' => 'atm-save-button']); ?>
        </form>
    </div>
    <?php
}

    private function save_settings() {
    check_admin_referer('atm_settings_update');
    update_option('atm_openrouter_api_key', sanitize_text_field($_POST['atm_openrouter_api_key']));
    update_option('atm_openai_api_key', sanitize_text_field($_POST['atm_openai_api_key']));
    update_option('atm_google_api_key', sanitize_text_field($_POST['atm_google_api_key']));
    update_option('atm_blockflow_api_key', sanitize_text_field($_POST['atm_blockflow_api_key'])); // <-- ADD THIS
    update_option('atm_flux_model', sanitize_text_field($_POST['atm_flux_model'])); // <-- ADD THIS
    update_option('atm_elevenlabs_api_key', sanitize_text_field($_POST['atm_elevenlabs_api_key'])); // New
    update_option('atm_article_model', sanitize_text_field($_POST['atm_article_model']));
    update_option('atm_content_model', sanitize_text_field($_POST['atm_content_model']));
    update_option('atm_audio_provider', sanitize_text_field($_POST['atm_audio_provider'])); // New
    update_option('atm_news_api_key', sanitize_text_field($_POST['atm_news_api_key']));
    update_option('atm_gnews_api_key', sanitize_text_field($_POST['atm_gnews_api_key']));
    update_option('atm_guardian_api_key', sanitize_text_field($_POST['atm_guardian_api_key']));
    update_option('atm_rss_feeds', sanitize_textarea_field($_POST['atm_rss_feeds']));
    update_option('atm_scrapingant_api_key', sanitize_text_field($_POST['atm_scrapingant_api_key']));
    update_option('atm_image_quality', sanitize_text_field($_POST['atm_image_quality']));
    update_option('atm_image_size', sanitize_text_field($_POST['atm_image_size']));
    update_option('atm_image_provider', sanitize_text_field($_POST['atm_image_provider']));
    update_option('atm_translation_model', sanitize_text_field($_POST['atm_translation_model']));
    update_option('atm_google_youtube_api_key', sanitize_text_field($_POST['atm_google_youtube_api_key']));
    update_option('atm_web_search_results', intval($_POST['atm_web_search_results'])); // <-- ADD THIS
    update_option('atm_theme_subtitle_key', sanitize_text_field($_POST['atm_theme_subtitle_key']));

    // NEW: Comments Generator options (no register_settings needed)
    update_option('atm_comments_auto_on_publish', isset($_POST['atm_comments_auto_on_publish']) ? 1 : 0);
    update_option('atm_comments_default_count', max(5, min(50, intval($_POST['atm_comments_default_count'] ?? 10))));
    update_option('atm_comments_threaded', isset($_POST['atm_comments_threaded']) ? 1 : 0);
    update_option('atm_comments_approve', isset($_POST['atm_comments_approve']) ? 1 : 0);
    update_option('atm_comments_model', sanitize_text_field($_POST['atm_comments_model'] ?? ''));
    update_option('atm_comments_randomize_window_days', max(1, min(30, intval($_POST['atm_comments_randomize_window_days'] ?? 3))));

    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

    public function get_settings() {
    return [
        'openrouter_key'   => get_option('atm_openrouter_api_key', ''),
        'openai_key'       => get_option('atm_openai_api_key', ''),
        'google_key'       => get_option('atm_google_api_key', ''),
        'blockflow_key'    => get_option('atm_blockflow_api_key', ''), // <-- ADD THIS
        'elevenlabs_key'   => get_option('atm_elevenlabs_api_key', ''), // New
        'news_api_key'     => get_option('atm_news_api_key', ''),
        'gnews_api_key'    => get_option('atm_gnews_api_key', ''),
        'web_search_results' => get_option('atm_web_search_results', 5), // <-- ADD THIS
        'theme_subtitle_key' => get_option('atm_theme_subtitle_key', ''),
        'rss_feeds'        => get_option('atm_rss_feeds', ''),
        'scrapingant_key'  => get_option('atm_scrapingant_api_key', ''),
        'guardian_api_key' => get_option('atm_guardian_api_key', ''),
        'article_model'    => get_option('atm_article_model', 'openai/gpt-4o'),
        'content_model'    => get_option('atm_content_model', 'anthropic/claude-3-haiku'),
        'audio_provider'   => get_option('atm_audio_provider', 'openai'), // New
        'image_quality'    => get_option('atm_image_quality', 'hd'),
        'image_size'       => get_option('atm_image_size', '1792x1024'),
        'image_provider'   => get_option('atm_image_provider', 'openai'),
        'flux_model'       => get_option('atm_flux_model', 'flux-1-schnell'), // <-- FIX IS HERE
        'google_youtube_key' => get_option('atm_google_youtube_api_key', ''),
        'translation_model' => get_option('atm_translation_model', 'anthropic/claude-3-haiku'),

        // NEW: Comments Generator defaults
        'comments_auto_on_publish'        => (bool) get_option('atm_comments_auto_on_publish', 0),
        'comments_default_count'          => intval(get_option('atm_comments_default_count', 10)),
        'comments_threaded'               => (bool) get_option('atm_comments_threaded', 1),
        'comments_approve'                => (bool) get_option('atm_comments_approve', 0),
        'comments_model'                  => get_option('atm_comments_model', ''),
        'comments_randomize_window_days'  => intval(get_option('atm_comments_randomize_window_days', 3)),

        'flux_models' => [
            'flux-1-schnell'        => 'FLUX 1 Schnell',
            'flux-1-dev'            => 'FLUX 1 Dev (Fast)',
            'flux-pro'              => 'FLUX Pro',
            'flux-pro-1.1'          => 'FLUX Pro 1.1',
            'flux-pro-1.1-ultra'    => 'FLUX Pro 1.1 Ultra (High Quality)',
            'flux-kontext-pro'      => 'FLUX Kontext Pro',
            'flux-kontext-max'      => 'FLUX Kontext Max (Best Quality)',
         ],
        'article_models'   => [
            'openai/gpt-4o' => 'OpenAI: GPT-4o (Best All-Around)',
            'anthropic/claude-3-opus' => 'Anthropic: Claude 3 Opus (Top-Tier Writing)',
            'google/gemini-flash-1.5' => 'Google: Gemini 1.5 Flash (Fast & Capable)',
            'meta-llama/llama-3-70b-instruct' => 'Meta: Llama 3 70B (Great Open Source)',
        ],
        'content_models'   => [
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & Cheap)',
            'openai/gpt-4o' => 'GPT-4o (Highest Quality)',
            'google/gemini-flash-1.5' => 'Google Gemini 1.5 Flash',
        ],
        'translation_models' => [
            'anthropic/claude-3-haiku' => 'Fast (Claude 3 Haiku)',
            'openai/gpt-4o' => 'High Quality (GPT-4o)',
            'anthropic/claude-3-opus' => 'Highest Quality (Claude 3 Opus)',
        ],
    ];
}

    // Default Prompts
    public static function get_default_article_prompt() {
        return "Please write a high-quality, engaging, and unique article based on the provided details.
        **Article Title:** [article_title]
        **Primary Keyword:** [primary_keyword]
        **Approximate Word Count:** [word_count]
        **Writing Style:** [writing_style]
        **Instructions & Context:**
        [custom_prompt]
        ---
        **Output Guidelines:**
        - The article must be well-structured with clear headings (H2, H3) and paragraphs.
        - Ensure the content is original, informative, and provides value to the reader.
        - Naturally incorporate the primary keyword throughout the article.
        - Maintain a consistent tone and style as specified.
        - The output should only be the article content itself, without any introductory or concluding remarks about the writing process.";
    }

    public static function get_default_image_prompt() {
        return "Create a photorealistic and visually stunning featured image for an article titled '[article_title]'.
        The image should be high-resolution, captivating, and directly relevant to the article's main themes.
        Consider the following context from the article: [article_excerpt]
        Style: [image_style]
        Avoid text and watermarks.";
    }

    public static function get_default_podcast_prompt() {
        return "Please generate a podcast script from the following article content.
        The script should be engaging, conversational, and suitable for an audio format.
        It should be approximately [duration] minutes long.
        --- ARTICLE CONTENT ---
        [article_content]";
    }
}