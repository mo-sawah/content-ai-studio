<?php
// /includes/class-atm-main.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Main {
    
    public function register_chart_post_type() {
        $args = array(
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'show_in_rest'        => true, // Important for REST API
            'rest_base'           => 'charts',
            'supports'            => array('title', 'custom-fields'),
        );
        register_post_type('atm_chart', $args);
    }

    // --- ADD THIS NEW FUNCTION to register the REST routes ---
    public function register_chart_rest_routes() {
        // This route will handle getting chart data for the frontend
        register_rest_route('atm/v1', '/charts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chart_data'),
            'permission_callback' => '__return_true', // Publicly viewable
        ));
    }

    // --- ADD THIS NEW FUNCTION to handle the REST callback ---
    public function get_chart_data($request) {
        $post_id = $request['id'];
        $chart_config = get_post_meta($post_id, '_atm_chart_config', true);

        if (empty($chart_config)) {
            return new WP_Error('no_config', 'Chart configuration not found.', array('status' => 404));
        }

        // The config is stored as a JSON string, so we decode it
        return new WP_REST_Response(json_decode($chart_config), 200);
    }
    
    public function register_rest_routes() {
        register_rest_route('atm/v1', '/generate-inline-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_inline_image_rest'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ));
        // --- ADD THIS NEW ROUTE ---
        register_rest_route('atm/v1', '/generate-featured-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_featured_image_rest'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
    ));

    }

    public function handle_featured_image_rest($request) {
    $prompt = sanitize_textarea_field($request['prompt']);
    $post_id = intval($request['post_id']);

    if (empty($prompt) || empty($post_id)) {
        return new WP_Error('bad_request', 'Missing prompt or post ID.', array('status' => 400));
    }

    try {
        $ajax_handler = new ATM_Ajax();
        $post = get_post($post_id);
        $processed_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);

        // This reuses your existing OpenAI image generation logic
        $image_url = ATM_API::generate_image_with_openai($processed_prompt);

        // This reuses your existing logic for saving the image and setting it as featured
        $attachment_id = $ajax_handler->set_image_from_url($image_url, $post_id);
        if(is_wp_error($attachment_id)) {
            return new WP_Error('generation_failed', $attachment_id->get_error_message(), array('status' => 500));
        }

        set_post_thumbnail($post_id, $attachment_id);

        $image_url = get_the_post_thumbnail_url($post_id, 'medium');
            return new WP_REST_Response([
                'success' => true,
                'featured_media_id' => $attachment_id,
                'featured_media_url' => $image_url
            ], 200);

    } catch (Exception $e) {
        return new WP_Error('generation_failed', $e->getMessage(), array('status' => 500));
    }
}

    public function handle_inline_image_rest($request) {
        $prompt = sanitize_textarea_field($request['prompt']);
        $post_id = intval($request['post_id']);

        if (empty($prompt) || empty($post_id)) {
            return new WP_Error('bad_request', 'Missing prompt or post ID.', array('status' => 400));
        }

        try {
            $ajax_handler = new ATM_Ajax(); // To reuse the set_image_from_url method
            $post = get_post($post_id);
            $processed_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);
            $image_url = ATM_API::generate_image_with_openai($processed_prompt);
            $attachment_id = $ajax_handler->set_image_from_url($image_url, $post_id);
            if(is_wp_error($attachment_id)) {
                return new WP_Error('generation_failed', $attachment_id->get_error_message(), array('status' => 500));
            }
            $image_data = wp_get_attachment_image_src($attachment_id, 'large');
            return new WP_REST_Response(['url' => $image_data[0], 'alt' => $prompt], 200);
        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Admin-related files
        require_once ATM_PLUGIN_PATH . 'includes/admin/class-atm-meta-box.php';
        require_once ATM_PLUGIN_PATH . 'includes/admin/class-atm-settings.php';
        
        // Core logic files
        require_once ATM_PLUGIN_PATH . 'includes/class-atm-ajax.php';
        require_once ATM_PLUGIN_PATH . 'includes/class-atm-api.php';
        require_once ATM_PLUGIN_PATH . 'includes/class-atm-frontend.php';
        require_once ATM_PLUGIN_PATH . 'includes/class-atm-licensing.php';
    }

    private function init_hooks() {
        $meta_box = new ATM_Meta_Box();
        $settings = new ATM_Settings();
        $ajax = new ATM_Ajax();
        $frontend = new ATM_Frontend();

        // Admin hooks
        add_action('admin_menu', array($settings, 'add_admin_menu'));
        add_action('add_meta_boxes', array($meta_box, 'add_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_head', array($this, 'register_tinymce_button'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'register_chart_post_type'));
        add_action('rest_api_init', array($this, 'register_chart_rest_routes'));

        // --- LICENSE CHECK ---
        if (ATM_Licensing::is_license_active()) {
            add_action('add_meta_boxes', array($meta_box, 'add_meta_boxes'));
        }
        // --- END CHECK ---

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($frontend, 'enqueue_frontend_scripts'));
        add_filter('the_content', array($frontend, 'embed_podcast_in_content'));
    }
    
    public function enqueue_admin_scripts($hook) {
    $screen = get_current_screen();
    $is_plugin_page = false;
    if ($screen) {
        if ($screen->id === 'toplevel_page_content-ai-studio' || strpos($screen->id, 'ai-studio_page_') === 0) {
             $is_plugin_page = true;
        }
    }

    if ($hook !== 'post.php' && $hook !== 'post-new.php' && !$is_plugin_page) {
        return;
    }

    wp_enqueue_media();

    // Register the 'marked' library from a CDN
    wp_register_script(
        'marked-library',
        'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
        array(),
        '4.0.12',
        true
    );

    // Enqueue the Gutenberg sidebar script
    $script_asset_path = ATM_PLUGIN_PATH . 'build/index.asset.php';
    if (file_exists($script_asset_path)) {
        $script_asset = require($script_asset_path);
        wp_enqueue_script(
            'atm-gutenberg-sidebar',
            ATM_PLUGIN_URL . 'build/index.js',
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        $style_path = ATM_PLUGIN_PATH . 'build/index.css';
        if (file_exists($style_path)) {
            wp_enqueue_style(
                'atm-gutenberg-sidebar-style',
                ATM_PLUGIN_URL . 'build/index.css',
                array(),
                $script_asset['version']
            );
        }
    }

    // Enqueue the new Studio App for the meta box
    $studio_asset_path = ATM_PLUGIN_PATH . 'build/studio.asset.php';
    if (file_exists($studio_asset_path)) {
        $studio_asset = require($studio_asset_path);
        wp_enqueue_script(
            'atm-studio-app',
            ATM_PLUGIN_URL . 'build/studio.js',
            $studio_asset['dependencies'],
            $studio_asset['version'],
            true
        );
        // Also enqueue the corresponding stylesheet
        $studio_style_path = ATM_PLUGIN_PATH . 'build/studio.css';
        if (file_exists($studio_style_path)) {
            wp_enqueue_style(
                'atm-studio-style',
                ATM_PLUGIN_URL . 'build/studio.css',
                array(),
                $studio_asset['version']
            );
        }
    }

    // The old admin script (can be removed later, but keep for now)
    $dependencies = array('jquery', 'wp-blocks', 'wp-data', 'wp-element', 'wp-editor', 'wp-components', 'marked-library');
    wp_enqueue_script(
        'atm-admin-script',
        ATM_PLUGIN_URL . 'assets/js/admin.js',
        $dependencies,
        ATM_VERSION,
        true
    );

    wp_enqueue_style(
        'atm-admin-style',
        ATM_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        ATM_VERSION
    );

    // --- THIS IS THE NEW PART ---
    // Pass data to both the old admin.js and the new studio.js
    $settings_class = new ATM_Settings();
    $settings = $settings_class->get_settings();
    $api_class = new ATM_API();
    $writing_styles = $api_class->get_writing_styles();

    $localized_data = array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('atm_nonce'),
    'article_models' => $settings['article_models'],
    'plugin_url' => ATM_PLUGIN_URL, // New
    'writing_styles' => $writing_styles,
    'image_provider' => $settings['image_provider'],
    'audio_provider' => $settings['audio_provider'], // New
    'tts_voices' => ['alloy' => 'Alloy', 'echo' => 'Echo', 'fable' => 'Fable', 'onyx' => 'Onyx', 'nova' => 'Nova', 'shimmer' => 'Shimmer'],
    'elevenlabs_voices' => ATM_API::get_elevenlabs_voices(), // New
);

$post_id = get_the_ID();
if ($post_id) {
    $localized_data['existing_podcast_url'] = get_post_meta($post_id, '_atm_podcast_url', true);
    $localized_data['existing_podcast_script'] = get_post_meta($post_id, '_atm_podcast_script', true);
}

    wp_localize_script('atm-admin-script', 'atm_ajax', $localized_data);
    wp_localize_script('atm-studio-app', 'atm_studio_data', $localized_data); // Pass data to our React app
}

    public function register_tinymce_button() {
        // Check if the user can edit posts and is using the rich text editor
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') !== 'true') {
            return;
        }
        // Add the JS plugin
        add_filter('mce_external_plugins', function($plugins) {
            $plugins['atm_button'] = ATM_PLUGIN_URL . 'assets/js/editor-button.js';
            return $plugins;
        });
        // Add the button to the toolbar
        add_filter('mce_buttons', function($buttons) {
            array_push($buttons, 'atm_generate_image');
            return $buttons;
        });
    }

    public static function activate() {
        $dirs = [
            'css' => ATM_PLUGIN_PATH . 'assets/css',
            'js'  => ATM_PLUGIN_PATH . 'assets/js'
        ];
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
}