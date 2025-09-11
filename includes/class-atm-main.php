<?php
// /includes/class-atm-main.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Main {
    private static $instance = null;
    private static $hooks_initialized = false;

    public static function cleanup_old_angles() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        // Keep only last 50 angles per keyword
        $wpdb->query("
            DELETE t1 FROM $table_name t1
            INNER JOIN (
                SELECT keyword, 
                    ROW_NUMBER() OVER (PARTITION BY keyword ORDER BY created_at DESC) as rn
                FROM $table_name
            ) t2 ON t1.keyword = t2.keyword
            WHERE t2.rn > 50
        ");
    }

    
    public static function cleanup_old_used_articles() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        
        // Delete entries older than configured cache time
        $cache_hours = get_option('atm_used_articles_cache_hours', 48);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE used_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $cache_hours
        ));
    }

    public static function create_podcast_progress_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'atm_podcast_jobs';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            job_id varchar(32) NOT NULL,
            script longtext NOT NULL,
            voice_a varchar(50) NOT NULL,
            voice_b varchar(50) NOT NULL,
            provider varchar(20) NOT NULL,
            total_segments int(11) NOT NULL,
            completed_segments int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            temp_files longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function __construct() {
        // Prevent multiple instances
        if (self::$instance !== null) {
            return self::$instance;
        }
        if (!wp_next_scheduled('atm_cleanup_angles')) {
            wp_schedule_event(time(), 'daily', 'atm_cleanup_angles');
        }
        self::$instance = $this;
        
        $this->load_dependencies();
        $this->init_hooks();
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function enqueue_listicle_styles() {
        // Only enqueue on single posts/pages where listicle content might appear
        if (is_singular()) {
            wp_enqueue_style(
                'atm-listicle-frontend',
                ATM_PLUGIN_URL . 'assets/css/listicle-frontend.css',
                array(),
                ATM_VERSION
            );
        }
    }

    // When a post is published, schedule comment generation if enabled
    public function maybe_schedule_comments_on_publish($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        if ($post->post_type !== 'post') {
            return;
        }
        if (!get_option('atm_comments_auto_on_publish', false)) {
            return;
        }

        // Schedule a single-run event shortly after publish
        $delay = rand(20, 120); // seconds
        wp_schedule_single_event(time() + $delay, 'atm_generate_comments_for_post', [$post->ID]);
    }

    public function handle_generate_comments_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') return;

        // Settings defaults
        $count     = max(5, min(50, intval(get_option('atm_comments_default_count', 10))));
        $threaded  = (bool) get_option('atm_comments_threaded', true);
        $approve   = (bool) get_option('atm_comments_approve', false);
        $model     = sanitize_text_field(get_option('atm_comments_model', ''));
        $window    = max(1, intval(get_option('atm_comments_randomize_window_days', 3)));

        try {
            $comments = ATM_API::generate_lifelike_comments($post->post_title, $post->post_content, $count, $threaded, $model);

            // Insert comments (sharing logic with AJAX method)
            $index_to_id = [];
            $index_to_time = [];
            $now_ts = time();
            $start_ts = max(strtotime($post->post_date_gmt ?: $post->post_date), $now_ts - ($window * DAY_IN_SECONDS));

            foreach ($comments as $idx => $c) {
                $author = isset($c['author_name']) ? sanitize_text_field($c['author_name']) : 'Guest';

                // Sanitize content and remove links
                $text = isset($c['text']) ? wp_kses_post($c['text']) : '';
                $text = preg_replace('/\[(.*?)\]\((https?:\/\/|www\.)[^\s)]+\)/i', '$1', $text);
                $text = preg_replace('/https?:\/\/\S+/i', '', $text);
                $text = preg_replace('/\bwww\.[^\s]+/i', '', $text);
                $text = trim(preg_replace('/\s{2,}/', ' ', $text));
                $text = wp_kses($text, ['br' => [], 'em' => [], 'strong' => [], 'i' => [], 'b' => []]);
                if ($text === '') continue;

                $parent_index = isset($c['parent_index']) && $c['parent_index'] !== '' ? intval($c['parent_index']) : -1;
                $parent_id    = ($parent_index >= 0 && isset($index_to_id[$parent_index])) ? intval($index_to_id[$parent_index]) : 0;

                if ($parent_index >= 0 && isset($index_to_time[$parent_index])) {
                    $base = $index_to_time[$parent_index] + rand(2 * MINUTE_IN_SECONDS, 60 * MINUTE_IN_SECONDS);
                    $ts = min($now_ts, $base + rand(0, 45 * MINUTE_IN_SECONDS));
                } else {
                    $ts = rand($start_ts, $now_ts);
                }
                $date_mysql = gmdate('Y-m-d H:i:s', $ts);
                $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
                $date_local = gmdate('Y-m-d H:i:s', $ts + $offset);

                $commentdata = [
                    'comment_post_ID'      => $post->ID,
                    'comment_author'       => $author,
                    'comment_author_email' => '',
                    'comment_author_url'   => '',
                    'comment_content'      => $text,
                    'comment_type'         => '',
                    'comment_parent'       => $parent_id,
                    'user_id'              => 0,
                    'comment_approved'     => $approve ? 1 : 0,
                    'comment_date'         => $date_local,
                    'comment_date_gmt'     => $date_mysql,
                ];
                $cid = wp_insert_comment(wp_slash($commentdata));
                if ($cid && !is_wp_error($cid)) {
                    $index_to_id[$idx] = $cid;
                    $index_to_time[$idx] = $ts;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the site
            error_log('ATM Auto comments failed: ' . $e->getMessage());
        }
    }
    
    public function render_multipage_shortcode($atts) {
        $post_id = get_the_ID();
        $multipage_data = get_post_meta($post_id, '_atm_multipage_data', true);

        if (empty($multipage_data) || !is_array($multipage_data)) {
            return '<!-- Multipage data not found -->';
        }

        $total_pages = count($multipage_data);
        $current_page = 0; // Always starts at 0 for the initial load

        ob_start();
        ?>
        <div class="atm-multipage-container" data-post-id="<?php echo esc_attr($post_id); ?>">
            <div class="atm-multipage-content">
                <?php echo $multipage_data[0]['content_html']; // Output the first page's content ?>
            </div>
            
            <nav class="atm-post-navigation">
                <button class="atm-nav-item prev" data-page-index="<?php echo $current_page - 1; ?>" <?php disabled($current_page, 0); ?>>
                    <span class="arrow">←</span>
                    <span class="text">Previous</span>
                </button>
                
                <div class="atm-nav-pages">
                    <?php for ($i = 0; $i < $total_pages; $i++): ?>
                        <button class="atm-page-number <?php echo ($i === $current_page) ? 'active' : ''; ?>" data-page-index="<?php echo esc_attr($i); ?>">
                            <?php echo esc_html($i + 1); ?>
                        </button>
                    <?php endfor; ?>
                </div>
                
                <button class="atm-nav-item next" data-page-index="<?php echo $current_page + 1; ?>" <?php disabled($current_page, $total_pages - 1); ?>>
                    <span class="text">Next</span>
                    <span class="arrow">→</span>
                </button>
            </nav>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_shortcodes() {
        add_shortcode('atm_chart', array($this, 'render_chart_shortcode'));
        add_shortcode('atm_multipage_article', array($this, 'render_multipage_shortcode'));
    }

    public function render_chart_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'atm_chart');
        if (!intval($atts['id'])) {
            return '';
        }

        return sprintf(
            '<div id="atm-chart-wrapper-%1$s" class="atm-chart-wrapper">
                <div class="atm-chart" id="atm-chart-%1$s"></div>
            </div>',
            esc_attr($atts['id'])
        );
    }
    
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
            'show_in_rest'        => true,
            'rest_base'           => 'charts',
            'supports'            => array('title', 'custom-fields'),
        );
        register_post_type('atm_chart', $args);
    }

    public function register_chart_rest_routes() {
        register_rest_route('atm/v1', '/charts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chart_data'),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_chart_data($request) {
        $post_id = $request['id'];
        $chart_config = get_post_meta($post_id, '_atm_chart_config', true);

        if (empty($chart_config)) {
            return new WP_Error('no_config', 'Chart configuration not found.', array('status' => 404));
        }

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

            $image_url = ATM_API::generate_image_with_openai($processed_prompt);
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
            $ajax_handler = new ATM_Ajax();
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

    public function add_module_type_to_script($tag, $handle, $src) {
        if ('atm-frontend-charts' === $handle) {
            $tag = '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
        }
        return $tag;
    }

    private function load_dependencies() {
        $required_files = [
            'includes/admin/class-atm-meta-box.php',
            'includes/admin/class-atm-settings.php',
            'includes/class-atm-ajax.php',
            'includes/class-atm-api.php',
            'includes/class-atm-twitter-api.php',  // ADD THIS LINE
            'includes/class-atm-theme-subtitle-manager.php',
            'includes/class-atm-frontend.php',
            'includes/class-atm-licensing.php',
            'includes/class-atm-campaign-manager.php',
            'includes/lib/Parsedown.php',
            'includes/class-atm-listicle.php',
            'includes/class-atm-humanize.php'
        ];

        foreach ($required_files as $file) {
            $file_path = ATM_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("ATM Plugin: Missing required file - $file");
            }
        }
    }

    private function init_hooks() {
        // Prevent multiple hook initialization
        if (self::$hooks_initialized) {
            return;
        }
        self::$hooks_initialized = true;

        // Only initialize classes if they exist
        $meta_box = class_exists('ATM_Meta_Box') ? new ATM_Meta_Box() : null;
        $settings = class_exists('ATM_Settings') ? new ATM_Settings() : null;
        $ajax = class_exists('ATM_Ajax') ? new ATM_Ajax() : null;
        $frontend = class_exists('ATM_Frontend') ? new ATM_Frontend() : null;
        $campaign_manager = class_exists('ATM_Campaign_Manager') ? new ATM_Campaign_Manager() : null;
        $listicle = class_exists('ATM_Listicle_Generator') ? new ATM_Listicle_Generator() : null;
        $humanize = class_exists('ATM_Humanize') ? new ATM_Humanize() : null;

        // Admin hooks
        if ($settings) {
            add_action('admin_menu', array($settings, 'add_admin_menu'));
        }
        
        if ($meta_box) {
            add_action('add_meta_boxes', array($meta_box, 'add_meta_boxes'));
        }

        if (!wp_next_scheduled('atm_cleanup_used_articles')) {
            wp_schedule_event(time(), 'daily', 'atm_cleanup_used_articles');
        }

        if (!wp_next_scheduled('atm_cleanup_angles')) {
            wp_schedule_event(time(), 'daily', 'atm_cleanup_angles');
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_head', array($this, 'register_tinymce_button'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'register_chart_post_type'));
        add_action('rest_api_init', array($this, 'register_chart_rest_routes'));
        add_action('init', array($this, 'register_shortcodes'));
        add_action('transition_post_status', array($this, 'maybe_schedule_comments_on_publish'), 10, 3);
        add_action('atm_generate_comments_for_post', array($this, 'handle_generate_comments_for_post'));
        add_action('atm_process_podcast_segment', array('ATM_API', 'process_podcast_segment'), 10, 2);
        add_action('atm_finalize_podcast', array('ATM_API', 'finalize_podcast'));
        add_action('atm_cleanup_podcast_jobs', array('ATM_Main', 'cleanup_old_podcast_jobs'));
        add_action('atm_process_podcast_background', array('ATM_API', 'process_podcast_background'));
        add_action('wp_ajax_check_podcast_progress', array($this, 'check_podcast_progress'));
        add_action('atm_process_script_background', array('ATM_API', 'process_script_background'));
        add_action('wp_ajax_check_script_progress', array($ajax, 'check_script_progress'));
        add_action('atm_cleanup_used_articles', array('ATM_Main', 'cleanup_old_used_articles'));
        add_action('atm_cleanup_angles', array('ATM_Main', 'cleanup_old_angles'));


        // Add cleanup for script jobs
        add_action('atm_cleanup_script_jobs', array('ATM_Main', 'cleanup_old_script_jobs'));


        // License check - only add meta boxes if licensed and meta box class exists
        if (class_exists('ATM_Licensing') && ATM_Licensing::is_license_active() && $meta_box) {
            add_action('add_meta_boxes', array($meta_box, 'add_meta_boxes'));
        }

        // Load podcast settings if file exists
        $podcast_settings_file = ATM_PLUGIN_PATH . 'includes/class-atm-podcast-settings.php';
        if (file_exists($podcast_settings_file)) {
            require_once $podcast_settings_file;
        }

        // Frontend hooks
        if ($frontend) {
            add_action('wp_enqueue_scripts', array($frontend, 'enqueue_frontend_scripts'), 99);
            add_filter('the_content', array($frontend, 'embed_takeaways_in_content'));
            add_filter('the_content', array($frontend, 'embed_podcast_in_content'));
        }

        // Schedule cleanup to run daily
        if (!wp_next_scheduled('atm_cleanup_podcast_jobs')) {
            wp_schedule_event(time(), 'daily', 'atm_cleanup_podcast_jobs');
        }
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_listicle_styles'), 100);
        add_filter('script_loader_tag', array($this, 'add_module_type_to_script'), 10, 3);

        // Campaign manager hooks
        if (class_exists('ATM_Campaign_Manager')) {
            ATM_Campaign_Manager::schedule_main_cron();
        }
    }

    public static function cleanup_old_script_jobs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_script_jobs';
        
        // Delete jobs older than 24 hours
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
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

    // Register the marked library from CDN
    wp_register_script(
        'marked-library',
        'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
        array(),
        '4.0.12',
        true
    );

    // Enqueue admin script FIRST (contains global block utilities)
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

    // Enqueue Gutenberg sidebar script
    $script_asset_path = ATM_PLUGIN_PATH . 'build/index.asset.php';
    if (file_exists($script_asset_path)) {
        $script_asset = require($script_asset_path);
        wp_enqueue_script(
            'atm-gutenberg-sidebar',
            ATM_PLUGIN_URL . 'build/index.js',
            array_merge($script_asset['dependencies'], ['atm-admin-script']), // Add dependency
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

    // Enqueue Studio App for meta box (depends on admin script)
    $studio_asset_path = ATM_PLUGIN_PATH . 'build/studio.asset.php';
    if (file_exists($studio_asset_path)) {
        $studio_asset = require($studio_asset_path);
        wp_enqueue_script(
            'atm-studio-app',
            ATM_PLUGIN_URL . 'build/studio.js',
            array_merge($studio_asset['dependencies'], ['atm-admin-script']), // Add dependency
            $studio_asset['version'],
            true
        );
        
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

    // Prepare localized data
    $localized_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('atm_nonce'),
        'plugin_url' => ATM_PLUGIN_URL,
        'tts_voices' => ['alloy' => 'Alloy', 'echo' => 'Echo', 'fabel' => 'Fable', 'onyx' => 'Onyx', 'nova' => 'Nova', 'shimmer' => 'Shimmer'],
    );

    // Add settings data if available
    if (class_exists('ATM_Settings')) {
        $settings_class = new ATM_Settings();
        $settings = $settings_class->get_settings();
        $localized_data['article_models'] = $settings['article_models'];
        $localized_data['content_models'] = $settings['content_models'];
        $localized_data['image_provider'] = $settings['image_provider'];
        $localized_data['audio_provider'] = $settings['audio_provider'];
    }

    // Add API data if available
    if (class_exists('ATM_API')) {
        $api_class = new ATM_API();
        $localized_data['writing_styles'] = $api_class->get_writing_styles();
        $localized_data['elevenlabs_voices'] = ATM_API::get_elevenlabs_voices();
    }

    // Add post-specific data
    $post_id = get_the_ID();
    if ($post_id) {
        $localized_data['existing_podcast_url'] = get_post_meta($post_id, '_atm_podcast_url', true);
        $localized_data['existing_podcast_script'] = get_post_meta($post_id, '_atm_podcast_script', true);
    }

    wp_localize_script('atm-admin-script', 'atm_ajax', $localized_data);
    wp_localize_script('atm-studio-app', 'atm_studio_data', $localized_data);
}

    public function register_tinymce_button() {
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') !== 'true') {
            return;
        }
        
        add_filter('mce_external_plugins', function($plugins) {
            $plugins['atm_button'] = ATM_PLUGIN_URL . 'assets/js/editor-button.js';
            return $plugins;
        });
        
        add_filter('mce_buttons', function($buttons) {
            array_push($buttons, 'atm_generate_image');
            return $buttons;
        });
    }

    public static function create_campaigns_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name_campaigns = $wpdb->prefix . 'content_ai_campaigns';
        $sql_campaigns = "CREATE TABLE $table_name_campaigns (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            country varchar(100) DEFAULT '' NOT NULL,
            article_type varchar(50) NOT NULL,
            custom_prompt longtext,
            generate_image tinyint(1) DEFAULT 0 NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            author_id bigint(20) unsigned NOT NULL,
            post_status varchar(20) DEFAULT 'draft' NOT NULL,
            frequency_value int(11) NOT NULL,
            frequency_unit varchar(10) NOT NULL,
            source_keywords text,
            source_urls longtext,
            strict_keyword_matching tinyint(1) DEFAULT 1 NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            last_run datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            next_run datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_campaigns);

        $table_name_used_links = $wpdb->prefix . 'content_ai_used_links';
        $sql_used_links = "CREATE TABLE $table_name_used_links (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            url_hash char(32) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_url (campaign_id, url_hash)
        ) $charset_collate;";
        dbDelta($sql_used_links);
    }

    public static function create_used_articles_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'atm_used_news_articles';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            article_url varchar(500) NOT NULL,
            article_title text NOT NULL,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            post_id bigint(20),
            PRIMARY KEY (id),
            UNIQUE KEY url_hash (article_url(191)),
            KEY used_at (used_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
}

    // Add this to your activate() method in class-atm-main.php:
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
        
        self::create_campaigns_table();
        self::create_podcast_progress_table(); // Add this line
        self::create_script_jobs_table(); // Add this line
        self::create_used_articles_table(); // Add this line
        self::create_content_angles_table(); // Add this line

        // Force table creation check
        self::verify_content_angles_table();

    }

    public static function create_content_angles_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'atm_content_angles';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            angle varchar(500) NOT NULL,
            title varchar(500) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword (keyword),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function verify_content_angles_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        // Check if table exists, if not create it
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            self::create_content_angles_table();
            error_log("ATM: Content angles table created");
        }
    }

    /**
 * Create script jobs table
 */
public static function create_script_jobs_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_name = $wpdb->prefix . 'atm_script_jobs';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        job_id varchar(32) NOT NULL,
        article_title text NOT NULL,
        article_content longtext NOT NULL,
        language varchar(20) NOT NULL,
        duration varchar(20) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        progress int(11) DEFAULT 0,
        current_segment varchar(50),
        script longtext,
        error_message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY job_id (job_id),
        KEY post_id (post_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Also add a cleanup function to remove old temp files:
public static function cleanup_old_podcast_jobs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'atm_podcast_jobs';
    
    // Delete jobs older than 24 hours
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE created_at < %s",
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    ));
    
    // Clean up temp directory
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/podcast-temp';
    
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < strtotime('-24 hours')) {
                unlink($file);
            }
        }
    }
}



    
}