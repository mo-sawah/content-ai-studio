<?php
/**
 * ATM Automation Scheduler
 * Handles cron scheduling and background processing for automation campaigns
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Scheduler {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize scheduler hooks
     */
    private function init_hooks() {
        // Schedule main cron job
        add_action('init', array($this, 'schedule_automation_cron'));
        
        // Register cron action
        add_action('atm_automation_check_campaigns', array($this, 'check_due_campaigns'));
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Cleanup old logs periodically
        add_action('atm_automation_cleanup_logs', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Schedule automation cron job
     */
    public function schedule_automation_cron() {
        // Schedule main campaign checker to run every 5 minutes
        if (!wp_next_scheduled('atm_automation_check_campaigns')) {
            wp_schedule_event(time(), 'every_5_minutes', 'atm_automation_check_campaigns');
        }
        
        // Schedule log cleanup to run daily
        if (!wp_next_scheduled('atm_automation_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'atm_automation_cleanup_logs');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        // Every 5 minutes for checking due campaigns
        $schedules['every_5_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes', 'content-ai-studio')
        );
        
        // Every 10 minutes for minimum campaign frequency
        $schedules['every_10_minutes'] = array(
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display' => __('Every 10 Minutes', 'content-ai-studio')
        );
        
        // Every 15 minutes
        $schedules['every_15_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'content-ai-studio')
        );
        
        // Every 30 minutes
        $schedules['every_30_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'content-ai-studio')
        );
        
        return $schedules;
    }
    
    /**
     * Check for due campaigns and execute them
     */
    public function check_due_campaigns() {
        // Prevent overlapping executions
        $lock_key = 'atm_automation_running';
        if (get_transient($lock_key)) {
            error_log('ATM Automation: Previous execution still running, skipping...');
            return;
        }
        
        // Set lock for 5 minutes
        set_transient($lock_key, true, 5 * MINUTE_IN_SECONDS);
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'atm_automation_campaigns';
            
            // Get all active campaigns that are due to run
            $due_campaigns = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE is_active = 1 
                 AND next_run <= %s 
                 ORDER BY next_run ASC 
                 LIMIT 5", // Process max 5 campaigns per run to avoid timeout
                current_time('mysql')
            ));
            
            if (empty($due_campaigns)) {
                error_log('ATM Automation: No campaigns due for execution');
                delete_transient($lock_key);
                return;
            }
            
            error_log('ATM Automation: Found ' . count($due_campaigns) . ' campaigns due for execution');
            
            foreach ($due_campaigns as $campaign) {
                try {
                    error_log('ATM Automation: Executing campaign ID ' . $campaign->id . ' (' . $campaign->name . ')');
                    
                    // Execute campaign
                    $result = ATM_Automation_API::execute_campaign($campaign->id);
                    
                    if ($result['success']) {
                        error_log('ATM Automation: Campaign ' . $campaign->id . ' executed successfully');
                    } else {
                        error_log('ATM Automation: Campaign ' . $campaign->id . ' failed: ' . $result['message']);
                    }
                    
                } catch (Exception $e) {
                    error_log('ATM Automation: Exception executing campaign ' . $campaign->id . ': ' . $e->getMessage());
                }
                
                // Add small delay between campaigns to prevent overwhelming the server
                sleep(2);
            }
            
        } catch (Exception $e) {
            error_log('ATM Automation: Exception in check_due_campaigns: ' . $e->getMessage());
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }
    
    /**
     * Cleanup old execution logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_executions';
        
        // Keep logs for 30 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE executed_at < %s",
            $cutoff_date
        ));
        
        if ($deleted !== false && $deleted > 0) {
            error_log("ATM Automation: Cleaned up $deleted old execution logs");
        }
    }
    
    /**
     * Manually trigger campaign execution (for testing)
     */
    public static function trigger_campaign_check() {
        $scheduler = new self();
        $scheduler->check_due_campaigns();
    }
    
    /**
     * Get next scheduled run time for debugging
     */
    public static function get_next_scheduled_run() {
        $next_run = wp_next_scheduled('atm_automation_check_campaigns');
        return $next_run ? wp_date('Y-m-d H:i:s', $next_run) : 'Not scheduled';
    }
    
    /**
     * Force reschedule of automation cron
     */
    public static function reschedule_automation_cron() {
        // Clear existing schedule
        wp_clear_scheduled_hook('atm_automation_check_campaigns');
        wp_clear_scheduled_hook('atm_automation_cleanup_logs');
        
        // Reschedule
        wp_schedule_event(time(), 'every_5_minutes', 'atm_automation_check_campaigns');
        wp_schedule_event(time(), 'daily', 'atm_automation_cleanup_logs');
        
        return true;
    }
    
    /**
     * Get automation status for debugging
     */
    public static function get_automation_status() {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        
        $active_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE is_active = 1");
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table");
        
        $next_check = wp_next_scheduled('atm_automation_check_campaigns');
        $is_locked = get_transient('atm_automation_running');
        
        return [
            'active_campaigns' => $active_campaigns,
            'total_campaigns' => $total_campaigns,
            'next_check' => $next_check ? wp_date('Y-m-d H:i:s', $next_check) : 'Not scheduled',
            'is_running' => (bool)$is_locked,
            'cron_working' => wp_next_scheduled('atm_automation_check_campaigns') !== false
        ];
    }
}