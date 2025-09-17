<?php
/**
 * ATM Automation AJAX Handler
 * Handles all AJAX requests for the automation system
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Ajax {
    
    public function __construct() {
        $this->init_hooks();
    }

    function atm_handle_save_automation_campaign() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $campaign_data = json_decode(stripslashes($_POST['campaign_data']), true);
            $campaign_id = intval($_POST['campaign_id'] ?? 0);
            
            // Debug logging
            error_log("ATM Campaign Save - Raw campaign_data: " . $_POST['campaign_data']);
            error_log("ATM Campaign Save - Decoded: " . print_r($campaign_data, true));
            error_log("ATM Campaign Save - RSS URLs: " . ($campaign_data['settings']['rss_urls'] ?? 'NOT SET'));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data: ' . json_last_error_msg());
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'content_ai_campaigns';
            
            // Prepare data for database
            $data = [
                'name' => sanitize_text_field($campaign_data['name']),
                'keyword' => sanitize_text_field($campaign_data['keyword'] ?? ''),
                'type' => sanitize_text_field($campaign_data['type']),
                'sub_type' => sanitize_text_field($campaign_data['sub_type']),
                'generation_mode' => sanitize_text_field($campaign_data['generation_mode'] ?? 'smart'), // Add this
                'settings' => wp_json_encode($campaign_data['settings']), // Use wp_json_encode
                'schedule_value' => intval($campaign_data['schedule_value']),
                'schedule_unit' => sanitize_text_field($campaign_data['schedule_unit']),
                'content_mode' => sanitize_text_field($campaign_data['content_mode']),
                'author_id' => intval($campaign_data['author_id']),
                'is_active' => $campaign_data['is_active'] ? 1 : 0,
                'updated_at' => current_time('mysql')
            ];
            
            error_log("ATM Campaign Save - Data to save: " . print_r($data, true));
            
            if ($campaign_id > 0) {
                // Update existing campaign
                $result = $wpdb->update($table_name, $data, ['id' => $campaign_id]);
                $final_id = $campaign_id;
            } else {
                // Create new campaign
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
                $final_id = $wpdb->insert_id;
            }
            
            if ($result === false) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }
            
            // Verify the save worked
            $saved_campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $final_id));
            error_log("ATM Campaign Save - Verification: " . print_r($saved_campaign, true));
            
            wp_send_json_success([
                'message' => 'Campaign saved successfully',
                'campaign_id' => $final_id
            ]);
            
        } catch (Exception $e) {
            error_log('ATM Campaign Save Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Automation Campaign Management
        add_action('wp_ajax_atm_save_automation_campaign', array($this, 'save_automation_campaign'));
        add_action('wp_ajax_atm_delete_automation_campaign', array($this, 'delete_automation_campaign'));
        add_action('wp_ajax_atm_get_automation_campaigns', array($this, 'get_automation_campaigns'));
        add_action('wp_ajax_atm_get_automation_campaign', array($this, 'get_automation_campaign'));
        add_action('wp_ajax_atm_toggle_automation_campaign', array($this, 'toggle_automation_campaign'));
        add_action('wp_ajax_atm_run_automation_campaign_now', array($this, 'run_automation_campaign_now'));
        add_action('wp_ajax_atm_generate_article_titles', array('ATM_Title_Generation', 'generate_article_titles'));
        
        // Campaign Execution Logs
        add_action('wp_ajax_atm_get_automation_logs', array($this, 'get_automation_logs'));
        add_action('wp_ajax_atm_clear_automation_logs', array($this, 'clear_automation_logs'));
    }
    
    /**
     * Save automation campaign (create or update)
     */
    public function save_automation_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce'); // Ensure nonce matches what you send from JS

        try {
            $campaign_data = isset($_POST['campaign_data']) ? json_decode(stripslashes($_POST['campaign_data']), true) : null;
            if (json_last_error() !== JSON_ERROR_NONE || !$campaign_data) {
                throw new Exception('Invalid campaign data received.');
            }

            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
            
            // Sanitize and prepare data for DB insertion/update
            $data = [
                'name'           => sanitize_text_field($campaign_data['name']),
                'type'           => sanitize_text_field($campaign_data['type']),
                'sub_type'       => sanitize_text_field($campaign_data['sub_type']),
                'keyword'        => sanitize_text_field($campaign_data['keyword']),
                'settings'       => wp_json_encode($this->sanitize_automation_settings($campaign_data['settings'])),
                'schedule_value' => intval($campaign_data['schedule_value']),
                'schedule_unit'  => sanitize_text_field($campaign_data['schedule_unit']),
                'content_mode'   => sanitize_text_field($campaign_data['content_mode']),
                'author_id'      => intval($campaign_data['author_id']),
                'is_active'      => !empty($campaign_data['is_active']) ? 1 : 0,
                // Fix: Handle category_ids from settings
                'category_ids'   => isset($campaign_data['settings']['category_ids']) ? 
                    wp_json_encode(array_map('intval', (array)$campaign_data['settings']['category_ids'])) : '[]',
            ];
            
            // Use the Database class for cleaner operations
            if ($campaign_id > 0) {
                // Updating an existing campaign
                $result = ATM_Automation_Database::update_campaign($campaign_id, $data);
                $message = 'Campaign updated successfully!';
            } else {
                // Creating a new campaign
                $result = ATM_Automation_Database::create_campaign($data);
                $campaign_id = $result;
                $message = 'Campaign created successfully!';
            }
            
            if ($result === false) {
                throw new Exception('Database operation failed.');
            }
            
            wp_send_json_success([
                'message'     => $message,
                'campaign_id' => $campaign_id
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Delete automation campaign
     */
    public function delete_automation_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID.');
            }
            
            global $wpdb;
            $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
            $executions_table = $wpdb->prefix . 'atm_automation_executions';
            
            // Delete execution logs first (foreign key constraint)
            $wpdb->delete($executions_table, ['campaign_id' => $campaign_id]);
            
            // Delete campaign
            $result = $wpdb->delete($campaigns_table, ['id' => $campaign_id]);
            if ($result === false) {
                throw new Exception('Failed to delete campaign: ' . $wpdb->last_error);
            }
            
            wp_send_json_success(['message' => 'Campaign deleted successfully!']);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get all automation campaigns
     */
    public function get_automation_campaigns() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            global $wpdb;
            $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
            $executions_table = $wpdb->prefix . 'atm_automation_executions';
            
            // Get campaigns with execution stats
            $campaigns = $wpdb->get_results("
                SELECT 
                    c.*,
                    COALESCE(e.total_executions, 0) as total_executions,
                    COALESCE(e.successful_executions, 0) as successful_executions,
                    COALESCE(e.failed_executions, 0) as failed_executions,
                    le.executed_at as last_execution,
                    le.status as last_status,
                    le.post_id as last_post_id
                FROM {$campaigns_table} c
                LEFT JOIN (
                    SELECT 
                        campaign_id,
                        COUNT(*) as total_executions,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_executions,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_executions
                    FROM {$executions_table}
                    GROUP BY campaign_id
                ) e ON c.id = e.campaign_id
                LEFT JOIN {$executions_table} le ON c.id = le.campaign_id 
                    AND le.id = (
                        SELECT MAX(id) 
                        FROM {$executions_table} 
                        WHERE campaign_id = c.id
                    )
                ORDER BY c.created_at DESC
            ");
            
            // Process each campaign and convert to proper types
            foreach ($campaigns as &$campaign) {
                // Convert strings to integers
                $campaign->total_executions = intval($campaign->total_executions);
                $campaign->successful_executions = intval($campaign->successful_executions);
                $campaign->failed_executions = intval($campaign->failed_executions);
                
                // Parse settings JSON
                $campaign->settings = json_decode($campaign->settings, true) ?: [];
                
                // Format dates
                $campaign->next_run_formatted = $campaign->next_run ? 
                    wp_date('M j, Y g:i A', strtotime($campaign->next_run)) : 'Not scheduled';
                $campaign->last_execution_formatted = $campaign->last_execution ?
                    wp_date('M j, Y g:i A', strtotime($campaign->last_execution)) : 'Never';
            }
            
            wp_send_json_success(['campaigns' => $campaigns]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get single automation campaign
     */
    public function get_automation_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID.');
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'atm_automation_campaigns';
            
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
            if (!$campaign) {
                throw new Exception('Campaign not found.');
            }
            
            $campaign->settings = json_decode($campaign->settings, true) ?: [];
            
            wp_send_json_success(['campaign' => $campaign]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Toggle campaign active status
     */
    public function toggle_automation_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            
            // Fix: Properly handle the boolean conversion
            $is_active_raw = $_POST['is_active'] ?? false;
            $is_active = ($is_active_raw === 'true' || $is_active_raw === true || $is_active_raw === 1 || $is_active_raw === '1') ? 1 : 0;
            
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID.');
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'atm_automation_campaigns';
            
            $result = $wpdb->update(
                $table_name,
                ['is_active' => $is_active, 'updated_at' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%d', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                throw new Exception('Failed to update campaign status: ' . $wpdb->last_error);
            }
            
            wp_send_json_success([
                'message' => $is_active ? 'Campaign activated!' : 'Campaign paused!',
                'is_active' => $is_active,
                'campaign_id' => $campaign_id
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Run automation campaign immediately
     */
    public function run_automation_campaign_now() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID.');
            }
            
            // Execute campaign via automation API
            $result = ATM_Automation_API::execute_campaign($campaign_id);
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Campaign executed successfully!',
                    'post_id' => $result['post_id'] ?? null,
                    'post_url' => $result['post_url'] ?? null
                ]);
            } else {
                throw new Exception($result['message'] ?? 'Campaign execution failed.');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get automation execution logs
     */
    public function get_automation_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
            $limit = intval($_POST['limit'] ?? 50);
            $limit = min(max($limit, 1), 100); // Ensure between 1 and 100
            
            global $wpdb;
            $executions_table = $wpdb->prefix . 'atm_automation_executions';
            $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
            
            $where_clause = $campaign_id ? $wpdb->prepare("WHERE ae.campaign_id = %d", $campaign_id) : "";
            
            $logs = $wpdb->get_results($wpdb->prepare("
                SELECT ae.*, ac.name as campaign_name, ac.type as campaign_type
                FROM $executions_table ae
                LEFT JOIN $campaigns_table ac ON ae.campaign_id = ac.id
                $where_clause
                ORDER BY ae.executed_at DESC
                LIMIT %d
            ", $limit));
            
            // Format timestamps
            foreach ($logs as &$log) {
                $log->executed_at_formatted = wp_date('M j, Y g:i A', strtotime($log->executed_at));
                if ($log->post_id) {
                    $log->post_title = get_the_title($log->post_id);
                    $log->post_url = get_permalink($log->post_id);
                    $log->edit_url = get_edit_post_link($log->post_id);
                }
            }
            
            wp_send_json_success(['logs' => $logs]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Clear automation logs
     */
    public function clear_automation_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'atm_automation_executions';
            
            if ($campaign_id) {
                $result = $wpdb->delete($table_name, ['campaign_id' => $campaign_id]);
            } else {
                // Clear all logs older than 30 days
                $result = $wpdb->query($wpdb->prepare("
                    DELETE FROM $table_name 
                    WHERE executed_at < %s
                ", date('Y-m-d H:i:s', strtotime('-30 days'))));
            }
            
            if ($result === false) {
                throw new Exception('Failed to clear logs: ' . $wpdb->last_error);
            }
            
            $message = $campaign_id ? 
                'Campaign logs cleared successfully!' : 
                'Old logs cleared successfully!';
                
            wp_send_json_success(['message' => $message]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Helper: Sanitize automation settings
     */
    private function sanitize_automation_settings($settings) {
        if (!is_array($settings)) {
            return [];
        }
        
        $sanitized = [];
        
        // Common settings
        if (isset($settings['ai_model'])) {
            $sanitized['ai_model'] = sanitize_text_field($settings['ai_model']);
        }
        if (isset($settings['writing_style'])) {
            $sanitized['writing_style'] = sanitize_text_field($settings['writing_style']);
        }
        if (isset($settings['creativity_level'])) {
            $sanitized['creativity_level'] = sanitize_text_field($settings['creativity_level']);
        }
        if (isset($settings['word_count'])) {
            $sanitized['word_count'] = intval($settings['word_count']);
        }
        if (isset($settings['generate_image'])) {
            $sanitized['generate_image'] = (bool)$settings['generate_image'];
        }
        if (isset($settings['custom_prompt'])) {
            $sanitized['custom_prompt'] = wp_kses_post($settings['custom_prompt']);
        }
        if (isset($settings['enable_web_search'])) {
            $sanitized['enable_web_search'] = (bool)$settings['enable_web_search'];
        }
        if (isset($settings['include_subheadlines'])) {
            $sanitized['include_subheadlines'] = (bool)$settings['include_subheadlines'];
        }
        
        // Trending-specific settings - THIS WAS MISSING!
        if (isset($settings['trending_region'])) {
            $sanitized['trending_region'] = sanitize_text_field($settings['trending_region']);
        }
        if (isset($settings['trending_language'])) {
            $sanitized['trending_language'] = sanitize_text_field($settings['trending_language']);
        }
        if (isset($settings['smart_angles'])) {
            $sanitized['smart_angles'] = (bool)$settings['smart_angles'];
        }
        if (isset($settings['real_time_trends'])) {
            $sanitized['real_time_trends'] = (bool)$settings['real_time_trends'];
        }
        if (isset($settings['include_breaking_news'])) {
            $sanitized['include_breaking_news'] = (bool)$settings['include_breaking_news'];
        }
        if (isset($settings['angle_refresh_days'])) {
            $sanitized['angle_refresh_days'] = intval($settings['angle_refresh_days']);
        }
        if (isset($settings['min_trend_score'])) {
            $sanitized['min_trend_score'] = intval($settings['min_trend_score']);
        }
        if (isset($settings['category_ids'])) {
            $sanitized['category_ids'] = array_map('intval', (array)$settings['category_ids']);
        }

        // RSS-specific settings - ADD THESE!
        if (isset($settings['rss_urls'])) {
            $sanitized['rss_urls'] = wp_kses_post($settings['rss_urls']); // Allow URLs and newlines
        }
        if (isset($settings['use_full_content'])) {
            $sanitized['use_full_content'] = (bool)$settings['use_full_content'];
        }
        if (isset($settings['skip_duplicates'])) {
            $sanitized['skip_duplicates'] = (bool)$settings['skip_duplicates'];
        }
        
        // News-specific settings
        if (isset($settings['news_method'])) {
            $sanitized['news_method'] = sanitize_text_field($settings['news_method']);
        }
        if (isset($settings['article_language'])) {
            $sanitized['article_language'] = sanitize_text_field($settings['article_language']);
        }
        if (isset($settings['source_languages'])) {
            $sanitized['source_languages'] = array_map('sanitize_text_field', (array)$settings['source_languages']);
        }
        if (isset($settings['countries'])) {
            $sanitized['countries'] = array_map('sanitize_text_field', (array)$settings['countries']);
        }
        if (isset($settings['news_source'])) {
            $sanitized['news_source'] = sanitize_text_field($settings['news_source']);
        }
        
        // Video-specific settings
        if (isset($settings['video_duration'])) {
            $sanitized['video_duration'] = sanitize_text_field($settings['video_duration']);
        }
        if (isset($settings['video_order'])) {
            $sanitized['video_order'] = sanitize_text_field($settings['video_order']);
        }
        
        // Podcast-specific settings
        if (isset($settings['podcast_language'])) {
            $sanitized['podcast_language'] = sanitize_text_field($settings['podcast_language']);
        }
        if (isset($settings['podcast_duration'])) {
            $sanitized['podcast_duration'] = sanitize_text_field($settings['podcast_duration']);
        }
        if (isset($settings['host_a_voice'])) {
            $sanitized['host_a_voice'] = sanitize_text_field($settings['host_a_voice']);
        }
        if (isset($settings['host_b_voice'])) {
            $sanitized['host_b_voice'] = sanitize_text_field($settings['host_b_voice']);
        }
        if (isset($settings['audio_provider'])) {
            $sanitized['audio_provider'] = sanitize_text_field($settings['audio_provider']);
        }

        // Title-based settings
        if (isset($settings['generated_titles'])) {
            $sanitized['generated_titles'] = array_map('sanitize_text_field', (array)$settings['generated_titles']);
        }
        if (isset($settings['used_titles'])) {
            $sanitized['used_titles'] = array_map('sanitize_text_field', (array)$settings['used_titles']);
        }
        if (isset($settings['auto_regenerate_titles'])) {
            $sanitized['auto_regenerate_titles'] = (bool)$settings['auto_regenerate_titles'];
        }
        if (isset($settings['titles_batch_size'])) {
            $sanitized['titles_batch_size'] = intval($settings['titles_batch_size']);
        }
        // Title generation specific settings
        if (isset($settings['title_ai_model'])) {
            $sanitized['title_ai_model'] = sanitize_text_field($settings['title_ai_model']);
        }
        if (isset($settings['title_writing_style'])) {
            $sanitized['title_writing_style'] = sanitize_text_field($settings['title_writing_style']);
        }
        if (isset($settings['title_web_search'])) {
            $sanitized['title_web_search'] = (bool)$settings['title_web_search'];
        }
        
        // 🔥 ADD HUMANIZATION SETTINGS HERE
        if (isset($settings['humanization'])) {
            $humanization = $settings['humanization'];
            $sanitized_humanization = [];
            
            if (isset($humanization['enabled'])) {
                $sanitized_humanization['enabled'] = (bool)$humanization['enabled'];
            }
            if (isset($humanization['provider'])) {
                $sanitized_humanization['provider'] = sanitize_text_field($humanization['provider']);
            }
            if (isset($humanization['mode'])) {
                $sanitized_humanization['mode'] = sanitize_text_field($humanization['mode']);
            }
            if (isset($humanization['tone'])) {
                $sanitized_humanization['tone'] = sanitize_text_field($humanization['tone']);
            }
            if (isset($humanization['openrouter_model'])) {
                $sanitized_humanization['openrouter_model'] = sanitize_text_field($humanization['openrouter_model']);
            }
            if (isset($humanization['business_mode'])) {
                $sanitized_humanization['business_mode'] = (bool)$humanization['business_mode'];
            }
            if (isset($humanization['preserve_formatting'])) {
                $sanitized_humanization['preserve_formatting'] = (bool)$humanization['preserve_formatting'];
            }
            if (isset($humanization['retry_on_detection'])) {
                $sanitized_humanization['retry_on_detection'] = (bool)$humanization['retry_on_detection'];
            }
            if (isset($humanization['fallback_to_draft'])) {
                $sanitized_humanization['fallback_to_draft'] = (bool)$humanization['fallback_to_draft'];
            }
            
            $sanitized['humanization'] = $sanitized_humanization;
        }
        
        return $sanitized;
    }
    
    /**
     * Helper: Calculate next run time
     */
    private function calculate_next_run($schedule_type, $value, $unit) {
        $current_time = current_time('timestamp');
        
        switch ($schedule_type) {
            case 'interval':
                $interval_map = [
                    'minute' => $value * MINUTE_IN_SECONDS,
                    'hour' => $value * HOUR_IN_SECONDS,
                    'day' => $value * DAY_IN_SECONDS,
                    'week' => $value * WEEK_IN_SECONDS
                ];
                
                $seconds = $interval_map[$unit] ?? HOUR_IN_SECONDS;
                return gmdate('Y-m-d H:i:s', $current_time + $seconds);
                
            case 'daily':
                // TODO: Implement specific time of day scheduling
                return gmdate('Y-m-d H:i:s', $current_time + DAY_IN_SECONDS);
                
            case 'weekly':
                // TODO: Implement specific day of week scheduling
                return gmdate('Y-m-d H:i:s', $current_time + WEEK_IN_SECONDS);
                
            default:
                return gmdate('Y-m-d H:i:s', $current_time + HOUR_IN_SECONDS);
        }
    }
}

class ATM_Title_Generation {
    
    /**
     * Generate article titles based on keyword and web research
     */
    public static function generate_article_titles() {
        // Verify permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
        try {
            $keyword = sanitize_text_field($_POST['keyword'] ?? '');
            $batch_size = intval($_POST['batch_size'] ?? 100);
            $ai_model = sanitize_text_field($_POST['ai_model'] ?? '');
            $writing_style = sanitize_text_field($_POST['writing_style'] ?? 'default_seo');
            $enable_web_search = isset($_POST['enable_web_search']) ? filter_var($_POST['enable_web_search'], FILTER_VALIDATE_BOOLEAN) : true;
            $existing_titles = json_decode(stripslashes($_POST['existing_titles'] ?? '[]'), true);
            
            if (empty($keyword)) {
                throw new Exception('Keyword is required.');
            }
            
            // Validate batch size
            $batch_size = max(10, min(1000, $batch_size));
            
            // Get current web research about the topic (if enabled)
            $web_research = [];
            if ($enable_web_search) {
                $web_research = self::conduct_web_research($keyword);
            }
            
            // Generate titles using AI with web research context
            $generated_titles = self::generate_titles_with_ai(
                $keyword,
                $batch_size,
                $ai_model,
                $writing_style,
                $web_research,
                $existing_titles,
                $enable_web_search
            );
            
            // Filter out duplicates and similar titles
            $unique_titles = self::filter_unique_titles($generated_titles, $existing_titles);
            
            // FORCE EXACT COUNT: If we don't have enough, generate more
            $attempts = 0;
            while (count($unique_titles) < $batch_size && $attempts < 3) {
                $attempts++;
                error_log("ATM Title Generation: Attempt {$attempts} - Need " . ($batch_size - count($unique_titles)) . " more titles");
                
                $additional_titles = self::generate_titles_with_ai(
                    $keyword,
                    $batch_size - count($unique_titles) + 10, // Generate extra to account for filtering
                    $ai_model,
                    $writing_style,
                    $web_research,
                    array_merge($existing_titles, $unique_titles), // Include already generated titles
                    $enable_web_search
                );
                
                $additional_unique = self::filter_unique_titles($additional_titles, array_merge($existing_titles, $unique_titles));
                $unique_titles = array_merge($unique_titles, $additional_unique);
                
                if (count($unique_titles) >= $batch_size) {
                    break;
                }
            }
            
            // Trim to exact batch size
            $unique_titles = array_slice($unique_titles, 0, $batch_size);
            
            wp_send_json_success([
                'titles' => $unique_titles,
                'research_summary' => $web_research['summary'] ?? '',
                'generated_count' => count($unique_titles),
                'attempts_needed' => $attempts + 1
            ]);
            
        } catch (Exception $e) {
            error_log('ATM Title Generation Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * FIXED: Conduct web research using OpenRouter's web search feature
     */
    public static function conduct_web_research($keyword) {
        try {
            // Use OpenRouter's web search to gather current information about the topic
            $research_prompt = "Research the topic '{$keyword}' using web search. Provide a comprehensive summary of:

1. Recent developments and news about {$keyword}
2. Current trends and popular discussions
3. Key facts and statistics
4. Recent events or changes
5. What people are currently interested in regarding {$keyword}

Focus on information from the last 6 months. Provide factual, current information that would help generate relevant article titles.

Return your response as JSON:
{
    \"summary\": \"Comprehensive summary of current information about the topic\",
    \"recent_developments\": [\"list of recent developments\"],
    \"trending_aspects\": [\"list of what's currently trending about this topic\"],
    \"key_facts\": [\"important current facts and statistics\"]
}";

            if (!class_exists('ATM_API') || !method_exists('ATM_API', 'enhance_content_with_openrouter')) {
                throw new Exception('ATM_API not available for web research');
            }

            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $keyword],
                $research_prompt,
                'anthropic/claude-3-haiku', // Use fast, cost-effective model for research
                true, // JSON mode
                true  // Enable web search - this is the key!
            );
            
            $result = json_decode($raw_response, true);
            if (!$result || !isset($result['summary'])) {
                error_log('ATM Web Research: Invalid response from AI: ' . $raw_response);
                throw new Exception('Invalid research response from AI');
            }
            
            return [
                'summary' => $result['summary'],
                'recent_developments' => $result['recent_developments'] ?? [],
                'trending_aspects' => $result['trending_aspects'] ?? [],
                'key_facts' => $result['key_facts'] ?? [],
                'search_method' => 'openrouter_web_search'
            ];
            
        } catch (Exception $e) {
            error_log('Web research error: ' . $e->getMessage());
            return [
                'summary' => "Unable to gather recent information about '{$keyword}' due to search limitations.",
                'recent_developments' => [],
                'trending_aspects' => [],
                'key_facts' => []
            ];
        }
    }
    
    /**
     * Generate titles using AI with web research context
     */
    public static function generate_titles_with_ai($keyword, $batch_size, $ai_model, $writing_style, $research, $existing_titles, $enable_web_search = true) {
        // Build the prompt for title generation
        $existing_titles_text = '';
        if (!empty($existing_titles)) {
            $existing_titles_text = "\n\nEXISTING TITLES TO AVOID (do not create similar titles):\n" . implode("\n", array_slice($existing_titles, -20));
        }
        
        $research_context = '';
        if ($enable_web_search && !empty($research['summary'])) {
            $research_context = "\n\nRESEARCH CONTEXT:\n" . $research['summary'];
            
            if (!empty($research['recent_developments'])) {
                $research_context .= "\n\nRecent developments:\n- " . implode("\n- ", array_slice($research['recent_developments'], 0, 5));
            }
            
            if (!empty($research['trending_aspects'])) {
                $research_context .= "\n\nTrending aspects:\n- " . implode("\n- ", array_slice($research['trending_aspects'], 0, 5));
            }
        }
        
        $style_instruction = self::get_style_instruction($writing_style);
        
        $prompt = "You are a professional content strategist. Generate EXACTLY {$batch_size} unique, engaging article titles about '{$keyword}'.

CRITICAL REQUIREMENTS:
- Generate EXACTLY {$batch_size} titles, no more, no less
- Each title must be completely unique and approach the topic from a different angle
- DO NOT include any introductory text like 'Here are X titles about...' or 'Here's a list of...'
- DO NOT number the titles (no 1., 2., etc.)
- DO NOT use bullet points or dashes
- Each title should be on its own line
- Titles should be SEO-friendly and compelling for readers
- {$style_instruction}
- Include a mix of: how-to guides, listicles, case studies, reviews, comparisons, and informational articles
- Make titles actionable and specific when possible

{$research_context}
{$existing_titles_text}

IMPORTANT: Return ONLY the {$batch_size} titles, each on a separate line, with no additional text or formatting.";

        try {
            // Use the API class to generate content
            $response = ATM_API::enhance_content_with_openrouter(
                ['content' => $keyword],
                $prompt,
                $ai_model ?: 'anthropic/claude-3-haiku',
                false, // Not JSON mode for simple list
                $enable_web_search // Use web search setting
            );
            
            if (empty($response)) {
                throw new Exception('No response from AI service.');
            }
            
            // Parse the response into individual titles
            $titles = self::parse_titles_from_response($response);
            
            // Clean and validate titles
            $cleaned_titles = self::clean_and_validate_titles($titles, $keyword);
            
            return $cleaned_titles;
            
        } catch (Exception $e) {
            error_log('AI title generation error: ' . $e->getMessage());
            throw new Exception('Failed to generate titles: ' . $e->getMessage());
        }
    }
    
    /**
     * Get style-specific instructions
     */
    private static function get_style_instruction($writing_style) {
        $style_map = [
            'default_seo' => 'Focus on SEO-optimized titles with clear value propositions',
            'professional' => 'Use professional, business-oriented language and formal tone',
            'conversational' => 'Use friendly, conversational language that feels approachable',
            'technical' => 'Include technical terms and focus on detailed, expert-level content',
            'news' => 'Write titles like news headlines with urgency and timeliness',
            'educational' => 'Focus on learning outcomes and educational value'
        ];
        
        return $style_map[$writing_style] ?? $style_map['default_seo'];
    }
    
    /**
     * IMPROVED: Parse titles from AI response with better filtering
     */
    private static function parse_titles_from_response($response) {
        // Split by newlines and clean up
        $lines = explode("\n", $response);
        $titles = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // FILTER OUT generic introductory lines
            if (preg_match('/^(here are|here\'s|below are|the following are|i\'ve generated|i\'ve created)/i', $line)) {
                continue;
            }
            
            // FILTER OUT lines that mention the number of titles
            if (preg_match('/\b\d+\s+(titles?|articles?|headlines?)\s+(about|for|on)\b/i', $line)) {
                continue;
            }
            
            // Remove numbering, bullets, or dashes
            $line = preg_replace('/^[\d\.\-\*\>\s]+/', '', $line);
            $line = trim($line);
            
            // Remove quotes if present
            $line = trim($line, '"\'');
            
            // Skip if it's still a generic instruction or header
            if (preg_match('/^(titles?|articles?|headlines?)(\s|:)/i', $line)) {
                continue;
            }
            
            if (!empty($line) && strlen($line) > 10) {
                $titles[] = $line;
            }
        }
        
        return $titles;
    }
    
    /**
     * IMPROVED: Clean and validate titles with better filtering
     */
    private static function clean_and_validate_titles($titles, $keyword) {
        $cleaned = [];
        
        foreach ($titles as $title) {
            // Basic cleaning
            $title = trim($title);
            $title = preg_replace('/\s+/', ' ', $title); // Normalize whitespace
            
            // FILTER OUT obvious generic titles
            if (preg_match('/^(here are|here\'s|the following|below are)/i', $title)) {
                continue;
            }
            
            // FILTER OUT titles that mention generating or listing
            if (preg_match('/\b(generated?|creating?|list of|collection of)\b/i', $title)) {
                continue;
            }
            
            // Validate length (reasonable title length)
            if (strlen($title) < 15 || strlen($title) > 200) {
                continue;
            }
            
            // Ensure it's somewhat related to the keyword
            if (!self::is_title_relevant($title, $keyword)) {
                continue;
            }
            
            // FILTER OUT titles that are too generic
            if (self::is_title_too_generic($title)) {
                continue;
            }
            
            $cleaned[] = $title;
        }
        
        return $cleaned;
    }
    
    /**
     * NEW: Check if title is too generic
     */
    private static function is_title_too_generic($title) {
        $generic_patterns = [
            '/^(the|a|an)\s+(complete|ultimate|best|top)\s+(guide|list)\s+to\s+everything$/i',
            '/^everything\s+you\s+need\s+to\s+know$/i',
            '/^(the\s+)?(complete|ultimate)\s+(guide|resource|collection)$/i',
            '/^(tips?|advice|information)\s+(about|on|for)\s+\w+$/i'
        ];
        
        foreach ($generic_patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if title is relevant to keyword
     */
    private static function is_title_relevant($title, $keyword) {
        $title_lower = strtolower($title);
        $keyword_lower = strtolower($keyword);
        
        // Split keyword into words
        $keyword_words = explode(' ', $keyword_lower);
        
        // Check if at least one keyword word appears in title
        foreach ($keyword_words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && strpos($title_lower, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filter out duplicate and similar titles
     */
    public static function filter_unique_titles($new_titles, $existing_titles) {
        $all_existing = array_map('strtolower', $existing_titles);
        $unique_titles = [];
        
        foreach ($new_titles as $title) {
            $title_lower = strtolower($title);
            
            // Check for exact duplicates
            if (in_array($title_lower, $all_existing)) {
                continue;
            }
            
            // Check for similarity with existing titles
            $is_similar = false;
            foreach ($all_existing as $existing) {
                if (self::titles_are_similar($title_lower, $existing)) {
                    $is_similar = true;
                    break;
                }
            }
            
            if (!$is_similar) {
                $unique_titles[] = $title;
                $all_existing[] = $title_lower; // Add to existing to check against remaining titles
            }
        }
        
        return $unique_titles;
    }
    
    /**
     * Check if two titles are too similar
     */
    private static function titles_are_similar($title1, $title2) {
        // Simple similarity check using similar_text
        $similarity = 0;
        similar_text($title1, $title2, $similarity);
        
        // Consider titles similar if they're more than 80% similar
        return $similarity > 80;
    }
}