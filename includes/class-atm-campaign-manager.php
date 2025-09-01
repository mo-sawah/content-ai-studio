// Create new file: includes/class-atm-campaign-manager.php

class ATM_Campaign_Manager {

    public function __construct() {
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
    }
    
    // Allows for schedules like "every 5 minutes" or "every 2 hours"
    public function add_custom_cron_schedules($schedules) {
        // You can add more intervals here as needed
        $schedules['atm_1_minute'] = array('interval' => 60, 'display' => 'Every Minute');
        $schedules['atm_5_minutes'] = array('interval' => 300, 'display' => 'Every 5 Minutes');
        return $schedules;
    }

    public static function schedule_campaign($campaign_id, $interval_seconds) {
        $hook_name = 'atm_run_campaign_' . $campaign_id;
        if (!wp_next_scheduled($hook_name, array($campaign_id))) {
            wp_schedule_event(time(), 'atm_custom_' . $interval_seconds, $hook_name, array($campaign_id));
        }
    }

    public static function unschedule_campaign($campaign_id) {
        wp_clear_scheduled_hook('atm_run_campaign_' . $campaign_id, array($campaign_id));
    }

    public static function execute_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));

        if (!$campaign) return;

        // 1. De-duplication: Get recent titles to avoid repeats
        $recent_posts = get_posts(['post_type' => 'post', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC']);
        $existing_titles = wp_list_pluck($recent_posts, 'post_title');
        
        // 2. Construct the prompt
        $system_prompt = "You are an expert content creator specializing in '{$campaign->article_type}' articles for a target audience in '{$campaign->country}'. Your task is to generate a complete, high-quality article about '{$campaign->keyword}'.
        
        CRITICAL: The title you generate must be unique and not similar to any of the following existing titles: " . implode(', ', $existing_titles) . "
        
        " . ($campaign->custom_prompt ?: ATM_API::get_default_article_prompt());

        // 3. Generate content using existing API method
        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        $article_data = json_decode($generated_json, true);

        if (!$article_data || empty($article_data['title']) || post_exists($article_data['title'])) {
             // Abort if title is empty or already exists
            error_log('ATM Campaign ' . $campaign_id . ': Aborted due to duplicate or empty title.');
            return;
        }

        // 4. Create the new post
        $post_data = [
            'post_title'    => wp_strip_all_tags($article_data['title']),
            'post_content'  => $article_data['content'],
            'post_status'   => $campaign->post_status,
            'post_author'   => $campaign->author_id,
            'post_category' => array($campaign->category_id)
        ];
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !empty($article_data['subheadline'])) {
            ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $article_data['subheadline'], '');
        }

        // 5. Generate featured image if enabled
        if ($post_id && $campaign->generate_image) {
            // This reuses the logic from your AJAX handler
            $ajax_handler = new ATM_Ajax();
            $image_prompt = ATM_API::get_default_image_prompt();
            $processed_prompt = ATM_API::replace_prompt_shortcodes($image_prompt, get_post($post_id));
            $image_url = ATM_API::generate_image_with_openai($processed_prompt);
            $attachment_id = $ajax_handler->set_image_from_url($image_url, $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
        
        // 6. Update campaign run times
        $wpdb->update($table_name, ['last_run' => current_time('mysql')], ['id' => $campaign_id]);
    }
}