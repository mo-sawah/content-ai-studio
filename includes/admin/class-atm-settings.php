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
        // The SVG icon for our menu, colorized with the branding gradient
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="atm-grad" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse"><stop stop-color="#8E2DE2" /><stop offset="1" stop-color="#4A00E0" /></linearGradient></defs><rect x="2" y="13" width="20" height="9" rx="2" fill="url(#atm-grad)" opacity="0.6" /><path d="M6 16H18 M6 18H15" stroke="white" stroke-width="1.2" stroke-linecap="round" /><rect x="2" y="8" width="20" height="9" rx="2" fill="url(#atm-grad)" opacity="0.8" /><path d="M6 12.5H8L10 11L12 14L14 11L16 12.5H18" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" /><rect x="2" y="3" width="20" height="9" rx="2" fill="url(#atm-grad)" /><circle cx="8" cy="7" r="1" fill="white" /><path d="M6 10L9 8L13 9.5L18 7" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" /></svg>');

        // Add the main menu page
        add_menu_page(
            'Content AI Studio',
            'AI Studio',
            'manage_options',
            'content-ai-studio', // This is the main slug
            array($this, 'render_settings_page'),
            $icon_svg,
            25 // Position in the menu
        );

        // Add the "Settings" submenu page
        add_submenu_page(
            'content-ai-studio', // Parent slug
            'Settings',
            'Settings',
            'manage_options',
            'content-ai-studio', // This makes it the default page for the menu
            array($this, 'render_settings_page')
        );

        // Add the "About" submenu page
        add_submenu_page(
            'content-ai-studio', // Parent slug
            'About Content AI Studio',
            'About',
            'manage_options',
            'content-ai-studio-about', // Page slug
            array($this, 'render_about_page')
        );

        // Add the "Support" submenu page
        add_submenu_page(
            'content-ai-studio', // Parent slug
            'Support',
            'Support',
            'manage_options',
            'content-ai-studio-support', // Page slug
            array($this, 'render_support_page')
        );
    }

    public function render_about_page() {
        ?>
        <div class="wrap atm-settings atm-about-page">
            <div class="atm-header">
                <h1>👋 About Content AI Studio</h1>
                <p class="atm-subtitle">Your all-in-one AI toolkit for content creation</p>
            </div>
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
             <div class="atm-header">
                <h1>🚀 Support & Documentation</h1>
                <p class="atm-subtitle">We're here to help you get the most out of Content AI Studio</p>
            </div>
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
            <div class="atm-header">
                <h1>🎙️ Content AI Studio Settings</h1>
                <p class="atm-subtitle">by Sawah Solutions | <a href="https://sawahsolutions.com/content-ai-studio" target="_blank">Plugin Website</a></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('atm_settings_update'); ?>
                
                <?php $this->render_license_section(); // THIS IS THE NEW LINE YOU ADD ?>

                <div class="atm-settings-grid">
                    <div class="atm-settings-card">
                        <h2>🔑 API Configuration</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">OpenRouter API Key</th>
                                <td>
                                    <input type="password" name="atm_openrouter_api_key" value="<?php echo esc_attr($options['openrouter_key']); ?>" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">OpenAI API Key</th>
                                <td>
                                    <input type="password" name="atm_openai_api_key" value="<?php echo esc_attr($options['openai_key']); ?>" class="regular-text" />
                                    <p class="description">Used for podcast generation.</p>
                                </td>
                            </tr>
                            <table class="form-table">
    <tr>
        <th scope="row">Stability AI API Key</th>
        <td>
            <input type="password" name="atm_stability_api_key" value="<?php echo esc_attr($options['stability_key']); ?>" class="regular-text" />
            <p class="description">Required to use the Stable Diffusion image generator. Get a key from <a href="https://platform.stability.ai/" target="_blank">Stability AI</a>.</p>
        </td>
    </tr>
</table>
                            <tr>
    <th scope="row">News API Key</th>
    <td>
        <input type="password" name="atm_news_api_key" value="<?php echo esc_attr($options['news_api_key']); ?>" class="regular-text" />
        <p class="description">Get a free key from <a href="https://newsapi.org/" target="_blank">NewsAPI.org</a> for the "Latest News" feature.</p>
    </td>
</tr>
                            <tr>
        <th scope="row">GNews API Key</th>
        <td>
            <input type="password" name="atm_gnews_api_key" value="<?php echo esc_attr($options['gnews_api_key']); ?>" class="regular-text" />
            <p class="description">Get a free key from <a href="https://gnews.io/" target="_blank">GNews.io</a>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">The Guardian API Key</th>
        <td>
            <input type="password" name="atm_guardian_api_key" value="<?php echo esc_attr($options['guardian_api_key']); ?>" class="regular-text" />
            <p class="description">Get a free key from <a href="https://open-platform.theguardian.com/" target="_blank">The Guardian</a>.</p>
        </td>
    </tr>
    <tr>
    <th scope="row">ScrapingAnt API Key</th>
    <td>
        <input type="password" name="atm_scrapingant_api_key" value="<?php echo esc_attr($options['scrapingant_key']); ?>" class="regular-text" />
        <p class="description">Required for the RSS scraping feature. Get a free key from <a href="https://scrapingant.com/" target="_blank">ScrapingAnt.com</a>.</p>
    </td>
</tr>
                        </table>
                    </div>
                    
                    <div class="atm-settings-card">
                        <h2>🎯 AI Model Defaults</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Default Article Model</th>
                                <td>
                                    <select name="atm_article_model">
                                        <?php foreach ($options['article_models'] as $model_id => $model_name): ?>
                                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['article_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Default Podcast Model</th>
                                <td>
                                    <select name="atm_content_model">
                                        <?php foreach ($options['content_models'] as $model_id => $model_name): ?>
                                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($options['content_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        </div>

<div class="atm-settings-card">
    <h2>🖼️ Image Generation Defaults</h2>
    <table class="form-table">
        <tr>
            <th scope="row">Default Image Quality</th>
            <td>
                <select name="atm_image_quality">
                    <option value="standard" <?php selected($options['image_quality'], 'standard'); ?>>Standard</option>
                    <option value="hd" <?php selected($options['image_quality'], 'hd'); ?>>HD</option>
                </select>
                <p class="description">"HD" creates images with finer details and greater consistency, but may have a higher cost.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Default Image Size</th>
            <td>
                <select name="atm_image_size">
                    <option value="1792x1024" <?php selected($options['image_size'], '1792x1024'); ?>>16:9 Landscape</option>
                    <option value="1024x1024" <?php selected($options['image_size'], '1024x1024'); ?>>1:1 Square</option>
                    <option value="1024x1792" <?php selected($options['image_size'], '1024x1792'); ?>>9:16 Portrait</option>
                </select>
                <p class="description">Select the default aspect ratio for generated images.</p>
            </td>
        </tr>
    </table>
</div>

<div class="atm-settings-card">
                    </div>
                </div>

                <div class="atm-settings-card">
                    <h2>RSS Feeds</h2>
                    <table class="form-table">
                         <tr>
    <th scope="row">RSS Feed URLs</th>
    <td>
        <textarea name="atm_rss_feeds" rows="8" style="width:100%;"><?php echo esc_textarea($options['rss_feeds']); ?></textarea>
        <p class="description">Add one RSS feed URL per line. These will be used for the "Generate from RSS" feature.</p>
    </td>
</tr>
                    </table>
                </div>

                <div class="atm-settings-card">
                    <h2>🤖 Default Prompt Templates</h2>
                    <table class="form-table">
                         <tr>
                            <th scope="row">Article Prompt</th>
                            <td>
                                <textarea name="atm_article_prompt" rows="10"><?php echo esc_textarea($options['article_prompt']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Image Prompt</th>
                            <td>
                                <textarea name="atm_image_prompt" rows="5"><?php echo esc_textarea($options['image_prompt']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Podcast Prompt</th>
                            <td>
                                <textarea name="atm_podcast_prompt" rows="15"><?php echo esc_textarea($options['podcast_prompt']); ?></textarea>
                            </td>
                        </tr>
                    </table>
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
        update_option('atm_article_model', sanitize_text_field($_POST['atm_article_model']));
        update_option('atm_content_model', sanitize_text_field($_POST['atm_content_model']));
        update_option('atm_article_prompt', wp_kses_post($_POST['atm_article_prompt']));
        update_option('atm_image_prompt', wp_kses_post($_POST['atm_image_prompt']));
        update_option('atm_podcast_prompt', wp_kses_post($_POST['atm_podcast_prompt']));
        update_option('atm_news_api_key', sanitize_text_field($_POST['atm_news_api_key']));
        update_option('atm_gnews_api_key', sanitize_text_field($_POST['atm_gnews_api_key']));
        update_option('atm_guardian_api_key', sanitize_text_field($_POST['atm_guardian_api_key']));
        update_option('atm_rss_feeds', sanitize_textarea_field($_POST['atm_rss_feeds']));
        update_option('atm_scrapingant_api_key', sanitize_text_field($_POST['atm_scrapingant_api_key']));
        update_option('atm_image_quality', sanitize_text_field($_POST['atm_image_quality']));
        update_option('atm_image_size', sanitize_text_field($_POST['atm_image_size']));
        update_option('atm_stability_api_key', sanitize_text_field($_POST['atm_stability_api_key']));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }

    public function get_settings() {
        return [
            'openrouter_key' => get_option('atm_openrouter_api_key', ''),
            'openai_key'     => get_option('atm_openai_api_key', ''),
            'news_api_key' => get_option('atm_news_api_key', ''),
            'gnews_api_key' => get_option('atm_gnews_api_key', ''),
            'rss_feeds' => get_option('atm_rss_feeds', ''),
            'scrapingant_key' => get_option('atm_scrapingant_api_key', ''),
            'guardian_api_key' => get_option('atm_guardian_api_key', ''),
            'article_model'  => get_option('atm_article_model', 'openai/gpt-4o'),
            'content_model'  => get_option('atm_content_model', 'anthropic/claude-3-haiku'),
            'article_prompt' => get_option('atm_article_prompt', ATM_API::get_default_article_prompt()),
            'image_prompt'   => get_option('atm_image_prompt', ATM_API::get_default_image_prompt()),
            'podcast_prompt' => get_option('atm_podcast_prompt', ATM_API::get_default_master_prompt()),
            'image_quality'  => get_option('atm_image_quality', 'hd'),
            'image_size'     => get_option('atm_image_size', '1792x1024'),
            'stability_key'  => get_option('atm_stability_api_key', ''),
            'article_models' => [
                'openai/gpt-4o' => 'OpenAI: GPT-4o (Best All-Around)',
                'anthropic/claude-3-opus' => 'Anthropic: Claude 3 Opus (Top-Tier Writing)',
                'google/gemini-flash-1.5' => 'Google: Gemini 1.5 Flash (Fast & Capable)',
                'meta-llama/llama-3-70b-instruct' => 'Meta: Llama 3 70B (Great Open Source)',
                'mistralai/mistral-large' => 'Mistral: Large (Strong Competitor)',
                'microsoft/wizardlm-2-8x22b' => 'Microsoft: WizardLM-2 (Highly Capable)',
                'databricks/dbrx-instruct' => 'Databricks: DBRX Instruct (Strong Open Model)',
                'cognitivecomputations/dolphin-mixtral-8x7b' => 'Dolphin Mixtral (Creative)',
                'nousresearch/nous-hermes-2-mixtral-8x7b-dpo' => 'Nous Hermes 2 (Dialogue)',
            ],
            'content_models' => [
                'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & Cheap)',
                'openai/gpt-4o' => 'GPT-4o (Highest Quality)',
                'google/gemini-flash-1.5' => 'Google Gemini 1.5 Flash',
            ]
        ];
    }
}