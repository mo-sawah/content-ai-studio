<?php
if (!defined('ABSPATH')) {
    exit;
}

class ATM_Takeaways {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_filter('the_content', array($this, 'display_takeaways_in_content'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Add a new submenu page under the main "AI Studio" menu.
     */
    public function add_settings_page() {
        add_submenu_page(
            'atm-settings',
            'Key Takeaways Settings',
            'Key Takeaways',
            'manage_options',
            'atm-takeaways-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render the settings page using the main settings class for consistent styling.
     */
    public function render_settings_page() {
        // We re-use a method from the main settings class to keep the style consistent
        $settings_renderer = new ATM_Settings();
        $settings_renderer->render_takeaways_settings_page();
    }

    /**
     * Enqueue scripts and styles for the frontend display.
     */
    public function enqueue_frontend_assets() {
        global $post;
        if (is_single() && is_a($post, 'WP_Post')) {
            $takeaways_enabled = get_option('atm_takeaways_enabled', 'yes') === 'yes';
            $takeaways_data = get_post_meta($post->ID, '_atm_key_takeaways', true);

            if ($takeaways_enabled && !empty($takeaways_data)) {
                wp_enqueue_style('atm-takeaways-style', ATM_PLUGIN_URL . 'assets/css/takeaways.css', [], ATM_VERSION);
                wp_enqueue_script('atm-takeaways-script', ATM_PLUGIN_URL . 'assets/js/takeaways.js', ['jquery'], ATM_VERSION, true);
            }
        }
    }

    /**
     * Display the takeaways box in the post content.
     */
    public function display_takeaways_in_content($content) {
        if (is_single() && in_the_loop() && is_main_query()) {
            $takeaways_enabled = get_option('atm_takeaways_enabled', 'yes') === 'yes';
            if (!$takeaways_enabled) {
                return $content;
            }

            $takeaways = get_post_meta(get_the_ID(), '_atm_key_takeaways', true);
            if (empty($takeaways)) {
                return $content;
            }

            $theme = get_option('atm_takeaways_theme', 'light');
            $list_items = '';
            foreach ($takeaways as $item) {
                $list_items .= '<li>' . esc_html($item) . '</li>';
            }

            $takeaways_html = '
            <div class="atm-takeaways-container akt-theme-' . esc_attr($theme) . '">
                <div class="atm-takeaways-header">
                    <h4>🔑 Key Takeaways</h4>
                </div>
                <div class="atm-takeaways-content">
                    <ul>' . $list_items . '</ul>
                </div>
            </div>';

            // Appends the takeaways to the end of the post content
            return $content . $takeaways_html;
        }
        return $content;
    }
}