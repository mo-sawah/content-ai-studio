<?php
/**
 * ATM Automation Main
 * Main automation system integration class
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation {
    
    private static $instance = null;
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load automation dependencies
     */
    private function load_dependencies() {
        $required_files = [
            'includes/class-atm-automation-database.php',
            'includes/class-atm-automation-api.php',
            'includes/class-atm-automation-ajax.php',
            'includes/class-atm-automation-scheduler.php',
            'includes/admin/class-atm-automation-admin.php'
        ];
        
        foreach ($required_files as $file) {
            $file_path = ATM_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("ATM Automation: Missing required file - $file");
            }
        }
    }
    
    /**
     * Initialize automation hooks
     */
    private function init_hooks() {
        // Initialize components only if classes exist
        if (class_exists('ATM_Automation_Ajax')) {
            new ATM_Automation_Ajax();
        }
        
        if (class_exists('ATM_Automation_Scheduler')) {
            new ATM_Automation_Scheduler();
        }
        
        if (class_exists('ATM_Automation_Admin')) {
            new ATM_Automation_Admin();
        }
        
        // Plugin activation/deactivation hooks
        add_action('atm_activation', array($this, 'on_activation'));
        add_action('atm_deactivation', array($this, 'on_deactivation'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // AJAX hooks for debugging
        add_action('wp_ajax_atm_automation_debug', array($this, 'debug_automation'));
        add_action('wp_ajax_atm_automation_reset', array($this, 'reset_automation'));
    }
    
    /**
     * Plugin activation hook
     */
    public function on_activation() {
        // Create database tables
        if (class_exists('ATM_Automation_Database')) {
            ATM_Automation_Database::create_tables();
        }
        
        // Schedule cron jobs
        if (class_exists('ATM_Automation_Scheduler')) {
            ATM_Automation_Scheduler::reschedule_automation_cron();
        }
        
        // Set activation flag
        update_option('atm_automation_activated', true);
        
        error_log('ATM Automation: System activated successfully');
    }
    
    /**
     * Plugin deactivation hook
     */
    public function on_deactivation() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('atm_automation_check_campaigns');
        wp_clear_scheduled_hook('atm_automation_cleanup_logs');
        
        // Note: We don't drop tables on deactivation, only on uninstall
        error_log('ATM Automation: System deactivated');
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if automation is properly configured
        if (!$this->is_automation_configured()) {
            $this->show_configuration_notice();
        }
        
        // Check if cron is working
        if (!$this->is_cron_working()) {
            $this->show_cron_notice();
        }
    }
    
    /**
     * Check if automation is properly configured
     */
    private function is_automation_configured() {
        // Check if required API keys are set
        $required_keys = [
            'atm_openrouter_api_key'
        ];
        
        foreach ($required_keys as $key) {
            if (empty(get_option($key))) {
                return false;
            }
        }
        
        // Check if tables exist
        if (class_exists('ATM_Automation_Database')) {
            return ATM_Automation_Database::tables_exist();
        }
        
        return true;
    }
    
    /**
     * Check if cron is working
     */
    private function is_cron_working() {
        return wp_next_scheduled('atm_automation_check_campaigns') !== false;
    }
    
    /**
     * Show configuration notice
     */
    private function show_configuration_notice() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'content-ai-studio') !== false) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>ATM Automation:</strong> 
                    The automation system requires configuration. Please check that your 
                    <a href="<?php echo admin_url('admin.php?page=content-ai-studio&tab=api'); ?>">API keys are set</a> 
                    and the database tables are created.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Show cron notice
     */
    private function show_cron_notice() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'content-ai-studio-automation') !== false) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>ATM Automation:</strong> 
                    WordPress cron is not working properly. Automation campaigns may not run as scheduled. 
                    <a href="#" onclick="atmAutomationRescheduleCron(); return false;">Try rescheduling</a>
                </p>
            </div>
            <script>
            function atmAutomationRescheduleCron() {
                jQuery.post(ajaxurl, {
                    action: 'atm_automation_debug',
                    operation: 'reschedule_cron',
                    nonce: '<?php echo wp_create_nonce('atm_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to reschedule cron: ' + response.data);
                    }
                });
            }
            </script>
            <?php
        }
    }
    
    /**
     * Debug automation system (AJAX)
     */
    public function debug_automation() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        
        $operation = sanitize_text_field($_POST['operation'] ?? '');
        
        try {
            switch ($operation) {
                case 'status':
                    $status = $this->get_automation_status();
                    wp_send_json_success($status);
                    break;
                    
                case 'reschedule_cron':
                    if (class_exists('ATM_Automation_Scheduler')) {
                        ATM_Automation_Scheduler::reschedule_automation_cron();
                        wp_send_json_success('Cron rescheduled successfully');
                    }
                    break;
                    
                case 'trigger_check':
                    if (class_exists('ATM_Automation_Scheduler')) {
                        ATM_Automation_Scheduler::trigger_campaign_check();
                        wp_send_json_success('Campaign check triggered');
                    }
                    break;
                    
                case 'table_stats':
                    if (class_exists('ATM_Automation_Database')) {
                        $stats = ATM_Automation_Database::get_table_stats();
                        wp_send_json_success($stats);
                    }
                    break;
                    
                default:
                    throw new Exception('Unknown debug operation');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Reset automation system (AJAX)
     */
    public function reset_automation() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            if (class_exists('ATM_Automation_Database')) {
                ATM_Automation_Database::reset_automation_data();
            }
            
            if (class_exists('ATM_Automation_Scheduler')) {
                ATM_Automation_Scheduler::reschedule_automation_cron();
            }
            
            wp_send_json_success('Automation system reset successfully');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get automation system status
     */
    private function get_automation_status() {
        $status = [
            'configured' => $this->is_automation_configured(),
            'cron_working' => $this->is_cron_working(),
            'tables_exist' => false,
            'active_campaigns' => 0,
            'total_executions' => 0,
            'last_execution' => null
        ];
        
        if (class_exists('ATM_Automation_Database')) {
            $status['tables_exist'] = ATM_Automation_Database::tables_exist();
        }
        
        if (class_exists('ATM_Automation_Scheduler')) {
            $scheduler_status = ATM_Automation_Scheduler::get_automation_status();
            $status = array_merge($status, $scheduler_status);
        }
        
        return $status;
    }
    
    /**
     * Uninstall automation system
     */
    public static function uninstall() {
        // Drop all tables
        if (class_exists('ATM_Automation_Database')) {
            ATM_Automation_Database::drop_tables();
        }
        
        // Clear cron jobs
        wp_clear_scheduled_hook('atm_automation_check_campaigns');
        wp_clear_scheduled_hook('atm_automation_cleanup_logs');
        
        // Remove options
        delete_option('atm_automation_activated');
        delete_option('atm_automation_db_version');
        
        error_log('ATM Automation: System uninstalled');
    }
}