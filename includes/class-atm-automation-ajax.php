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