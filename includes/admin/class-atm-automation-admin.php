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
        
        // Enqueue automation React app
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
            
            wp_enqueue_style(
                'atm-automation-style',
                ATM_PLUGIN_URL . 'build/automation.css',
                array(),
                $automation_asset['version']
            );
        }
        
        // Localize script data
        $localized_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('atm_nonce'),
            'plugin_url' => ATM_PLUGIN_URL,
            'categories' => $this->get_categories_for_js(),
            'authors' => $this->get_authors_for_js(),
            'automation_types' => $this->get_automation_types(),
            'schedule_options' => $this->get_schedule_options(),
            'content_modes' => $this->get_content_modes()
        );
        
        // Add settings data if available
        if (class_exists('ATM_Settings')) {
            $settings_class = new ATM_Settings();
            $settings = $settings_class->get_settings();
            $localized_data = array_merge($localized_data, [
                'article_models' => $settings['article_models'],
                'content_models' => $settings['content_models'],
                'image_provider' => $settings['image_provider'],
                'audio_provider' => $settings['audio_provider']
            ]);
        }
        
        // Add API data if available
        if (class_exists('ATM_API')) {
            $localized_data['writing_styles'] = ATM_API::get_writing_styles();
            $localized_data['elevenlabs_voices'] = ATM_API::get_elevenlabs_voices();
            $localized_data['tts_voices'] = [
                'alloy' => 'Alloy', 
                'echo' => 'Echo', 
                'fabel' => 'Fable', 
                'onyx' => 'Onyx', 
                'nova' => 'Nova', 
                'shimmer' => 'Shimmer'
            ];
        }
        
        wp_localize_script('atm-automation-app', 'atm_automation_data', $localized_data);
    }
    
    /**
     * Render automation main page
     */
    public function render_automation_page() {
        ?>
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