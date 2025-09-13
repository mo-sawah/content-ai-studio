<?php
/**
 * ATM Automation Admin
 * Handles admin pages and UI for automation system
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Admin {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add automation submenu to AI Studio
     */
    public function add_admin_menu() {
        add_submenu_page(
            'content-ai-studio',
            'AI Automation',
            'Automation',
            'manage_options',
            'content-ai-studio-automation',
            array($this, 'render_automation_page')
        );
        
        add_submenu_page(
            'content-ai-studio',
            'Automation Campaigns',
            'Campaigns',
            'manage_options',
            'content-ai-studio-automation-campaigns',
            array($this, 'render_campaigns_page')
        );
    }
    
    /**
     * Enqueue admin scripts for automation pages
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'content-ai-studio-automation') === false) {
            return;
        }
        
        // IMPORTANT: Enqueue the automation CSS first
        wp_enqueue_style(
            'atm-automation-style',
            ATM_PLUGIN_URL . 'assets/css/automation.css',
            array(),
            ATM_VERSION
        );
        
        // Then enqueue the React app
        $automation_asset_path = ATM_PLUGIN_PATH . 'build/automation.asset.php';
        if (file_exists($automation_asset_path)) {
            $automation_asset = require($automation_asset_path);
            
            wp_enqueue_script(
                'atm-automation-app',
                ATM_PLUGIN_URL . 'build/automation.js',
                $automation_asset['dependencies'],
                $automation_asset['version'],
                true
            );
            
            // Only enqueue build CSS if it exists and doesn't conflict
            $build_css = ATM_PLUGIN_PATH . 'build/automation.css';
            if (file_exists($build_css)) {
                wp_enqueue_style(
                    'atm-automation-build-style',
                    ATM_PLUGIN_URL . 'build/automation.css',
                    array('atm-automation-style'), // Make it depend on our main CSS
                    $automation_asset['version']
                );
            }
        }
        
        // Localize script data with all required data
        $localized_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('atm_nonce'),
            'plugin_url' => ATM_PLUGIN_URL,
            'categories' => $this->get_categories_for_js(),
            'authors' => $this->get_authors_for_js(),
            'automation_types' => $this->get_automation_types(),
            'schedule_options' => $this->get_schedule_options(),
            'content_modes' => $this->get_content_modes(),
            'tts_voices' => [
                'alloy' => 'Alloy', 
                'echo' => 'Echo', 
                'fabel' => 'Fable', 
                'onyx' => 'Onyx', 
                'nova' => 'Nova', 
                'shimmer' => 'Shimmer'
            ]
        );
        
        // Add settings data with proper fallbacks
        if (class_exists('ATM_Settings')) {
            $settings_class = new ATM_Settings();
            $settings = $settings_class->get_settings();
            
            // Ensure we always have model options
            $article_models = $settings['article_models'] ?? [];
            if (empty($article_models)) {
                $article_models = [
                    'openai/gpt-4o' => 'GPT-4 Omni',
                    'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
                    'google/gemini-pro' => 'Gemini Pro'
                ];
            }
            
            $localized_data['article_models'] = $article_models;
            $localized_data['content_models'] = $settings['content_models'] ?? $article_models;
            $localized_data['image_provider'] = $settings['image_provider'] ?? 'openai';
            $localized_data['audio_provider'] = $settings['audio_provider'] ?? 'openai';
        }
        
        // Add API data with fallbacks
        if (class_exists('ATM_API')) {
            $writing_styles = method_exists('ATM_API', 'get_writing_styles') ? 
                ATM_API::get_writing_styles() : 
                ['default_seo' => ['label' => 'Standard SEO', 'prompt' => 'Write a professional, SEO-optimized article.']];
                
            $localized_data['writing_styles'] = $writing_styles;
            
            if (method_exists('ATM_API', 'get_elevenlabs_voices')) {
                $localized_data['elevenlabs_voices'] = ATM_API::get_elevenlabs_voices();
            }
        }
        
        wp_localize_script('atm-automation-app', 'atm_automation_data', $localized_data);
    }
    
    /**
     * Render automation main page
     */
    public function render_automation_page() {
        ?>
        <style>
        /* Quick automation styling fix */
        .atm-campaigns-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 24px 32px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .atm-campaigns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .atm-campaign-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            overflow: hidden;
        }
        
        .atm-campaign-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .atm-campaign-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .atm-campaign-content {
            padding: 24px;
        }
        
        .atm-campaign-content h4 {
            margin: 0 0 12px 0;
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.3;
        }
        
        .atm-campaign-keyword {
            margin: 0 0 20px 0;
            color: #6b7280;
            font-size: 14px;
            font-style: italic;
            background: #f3f4f6;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .atm-campaign-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .atm-campaign-meta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .atm-campaign-meta-item:last-child {
            border-bottom: none;
        }
        
        .atm-campaign-meta-item .label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .atm-campaign-meta-item .value {
            color: #1f2937;
            font-weight: 600;
            text-align: right;
        }
        
        .atm-campaign-actions {
            display: flex;
            gap: 8px;
            padding: 20px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .atm-campaign-actions .components-button {
            flex: 1;
            justify-content: center;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .atm-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .atm-status-badge.active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .atm-status-badge.paused {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .atm-status-badge.error {
            background: #fecaca;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .atm-status-badge.running {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .atm-empty-state {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .atm-empty-state h3 {
            margin: 0 0 12px 0;
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .atm-empty-state p {
            margin: 0 0 24px 0;
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .atm-form-container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .atm-form-container h4 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .atm-form-container h4:not(:first-child) {
            margin-top: 32px;
        }
        
        .atm-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .atm-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .atm-form-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            padding-top: 32px;
            border-top: 1px solid #e5e7eb;
            margin-top: 32px;
        }
        
        .atm-status-message {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid;
        }
        
        .atm-status-message.success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        
        .atm-status-message.error {
            background: #fecaca;
            color: #991b1b;
            border-color: #fca5a5;
        }
        
        .atm-status-message.info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }
        
        /* Responsive fixes */
        @media (max-width: 768px) {
            .atm-campaigns-grid {
                grid-template-columns: 1fr;
            }
            
            .atm-campaigns-header {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }
            
            .atm-grid-2,
            .atm-grid-3 {
                grid-template-columns: 1fr;
            }
            
            .atm-campaign-actions {
                flex-wrap: wrap;
            }
            
            .atm-campaign-actions .components-button {
                flex: 1;
                min-width: calc(50% - 4px);
            }
        }
        </style>
        
        <div class="wrap">
            <div id="atm-automation-root" data-page="main"></div>
        </div>
        <?php
    }
    
    /**
     * Render campaigns management page
     */
    public function render_campaigns_page() {
        ?>
        <div class="wrap">
            <div id="atm-automation-root" data-page="campaigns"></div>
        </div>
        <?php
    }
    
    /**
     * Get categories for JavaScript
     */
    private function get_categories_for_js() {
        $categories = get_categories(array('hide_empty' => false));
        $result = array();
        
        foreach ($categories as $category) {
            $result[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            );
        }
        
        return $result;
    }
    
    /**
     * Get authors for JavaScript
     */
    private function get_authors_for_js() {
        $authors = get_users(array('capability' => 'edit_posts'));
        $result = array();
        
        foreach ($authors as $author) {
            $result[] = array(
                'id' => $author->ID,
                'name' => $author->display_name,
                'login' => $author->user_login
            );
        }
        
        return $result;
    }
    
    /**
     * Get automation types
     */
    private function get_automation_types() {
        return array(
            'articles' => array(
                'label' => 'General Articles',
                'description' => 'Generate articles using AI with custom prompts and settings',
                'icon' => 'edit-post',
                'color' => '#6366f1'
            ),
            'news' => array(
                'label' => 'Automated News',
                'description' => 'Generate articles from latest news sources and RSS feeds',
                'icon' => 'rss',
                'color' => '#059669'
            ),
            'videos' => array(
                'label' => 'Auto Videos',
                'description' => 'Find and embed YouTube videos with automated descriptions',
                'icon' => 'video-alt3',
                'color' => '#dc2626'
            ),
            'podcasts' => array(
                'label' => 'Auto Podcast',
                'description' => 'Generate articles with automated podcast audio creation',
                'icon' => 'microphone',
                'color' => '#f97316'
            )
        );
    }
    
    /**
     * Get schedule options
     */
    private function get_schedule_options() {
        return array(
            'interval' => array(
                'label' => 'Fixed Interval',
                'description' => 'Run every X minutes/hours/days/weeks',
                'units' => array(
                    'minute' => 'Minutes',
                    'hour' => 'Hours', 
                    'day' => 'Days',
                    'week' => 'Weeks'
                ),
                'min_values' => array(
                    'minute' => 10, // Minimum 10 minutes
                    'hour' => 1,
                    'day' => 1,
                    'week' => 1
                )
            ),
            'daily' => array(
                'label' => 'Daily at Specific Time',
                'description' => 'Run once per day at a specific time',
                'units' => array(),
                'min_values' => array()
            ),
            'weekly' => array(
                'label' => 'Weekly on Specific Day',
                'description' => 'Run once per week on a specific day and time',
                'units' => array(),
                'min_values' => array()
            )
        );
    }
    
    /**
     * Get content modes
     */
    private function get_content_modes() {
        return array(
            'publish' => array(
                'label' => 'Auto-publish Immediately',
                'description' => 'Posts are published automatically when generated',
                'icon' => 'yes-alt',
                'color' => '#16a34a'
            ),
            'draft' => array(
                'label' => 'Save as Drafts',
                'description' => 'Posts are saved as drafts for manual review',
                'icon' => 'edit',
                'color' => '#eab308'
            ),
            'queue' => array(
                'label' => 'Queue for Later',
                'description' => 'Posts are queued for scheduled publishing',
                'icon' => 'clock',
                'color' => '#3b82f6'
            )
        );
    }
}