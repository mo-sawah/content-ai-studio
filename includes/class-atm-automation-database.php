<?php
/**
 * ATM Automation Database
 * Handles database table creation and management for automation system
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Database {

    /**
     * Update database schema to support sub-types
     */
    public static function update_schema_for_subtypes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        // Check if sub_type column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'sub_type'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN sub_type varchar(50) DEFAULT 'standard' AFTER type");
            error_log('ATM Automation: Added sub_type column to campaigns table');
        }
    }
        
    /**
     * Create all automation tables
     */
    public static function create_tables() {
        self::create_campaigns_table();
        self::create_executions_table();
        self::create_queue_table();
    }
    
    /**
     * Create automation campaigns table
     */
    public static function create_campaigns_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            keyword varchar(500) NOT NULL,
            settings longtext,
            schedule_type varchar(20) DEFAULT 'interval',
            schedule_value int(11) DEFAULT 1,
            schedule_unit varchar(10) DEFAULT 'hour',
            content_mode varchar(20) DEFAULT 'publish',
            category_id bigint(20) DEFAULT 0,
            author_id bigint(20) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            next_run datetime DEFAULT '0000-00-00 00:00:00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active),
            KEY next_run (next_run),
            KEY keyword (keyword(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('ATM Automation: Failed to create campaigns table');
        } else {
            error_log('ATM Automation: Campaigns table created successfully');
        }
    }
    
    /**
     * Create automation executions table
     */
    public static function create_executions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'atm_automation_executions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            post_id bigint(20) DEFAULT NULL,
            execution_time decimal(10,3) DEFAULT NULL,
            memory_usage varchar(20) DEFAULT NULL,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status),
            KEY executed_at (executed_at),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('ATM Automation: Failed to create executions table');
        } else {
            error_log('ATM Automation: Executions table created successfully');
        }
    }
    
    /**
     * Create automation queue table (for future background processing)
     */
    public static function create_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'atm_automation_queue';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            job_type varchar(50) NOT NULL,
            job_data longtext,
            priority int(11) DEFAULT 5,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('ATM Automation: Failed to create queue table');
        } else {
            error_log('ATM Automation: Queue table created successfully');
        }
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        
        $campaigns_exists = $wpdb->get_var("SHOW TABLES LIKE '$campaigns_table'") == $campaigns_table;
        $executions_exists = $wpdb->get_var("SHOW TABLES LIKE '$executions_table'") == $executions_table;
        $queue_exists = $wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table;
        
        return $campaigns_exists && $executions_exists && $queue_exists;
    }
    
    /**
     * Update database schema if needed
     */
    public static function maybe_update_schema() {
        $current_version = get_option('atm_automation_db_version', '1.0.0');
        $new_version = '1.0.1';
        
        if (version_compare($current_version, $new_version, '<')) {
            self::update_schema_to_101();
            update_option('atm_automation_db_version', $new_version);
        }
    }
    
    /**
     * Schema updates for version 1.0.1
     */
    private static function update_schema_to_101() {
        global $wpdb;
        
        // Add any future schema updates here
        // Example:
        // $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        // $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_field varchar(255) DEFAULT NULL");
    }
    
    /**
     * Drop all automation tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'atm_automation_queue',
            $wpdb->prefix . 'atm_automation_executions', 
            $wpdb->prefix . 'atm_automation_campaigns'
        ];
        
        // Drop in reverse order due to foreign key constraints
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Clean up options
        delete_option('atm_automation_db_version');
    }
    
    /**
     * Get table statistics
     */
    public static function get_table_stats() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        
        $stats = [];
        
        // Campaign stats
        $stats['campaigns'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table"),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE is_active = 1"),
            'by_type' => $wpdb->get_results("SELECT type, COUNT(*) as count FROM $campaigns_table GROUP BY type")
        ];
        
        // Execution stats
        $stats['executions'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $executions_table"),
            'last_24h' => $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'successful' => $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE status = 'completed'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE status = 'failed'")
        ];
        
        // Queue stats
        $stats['queue'] = [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'")
        ];
        
        return $stats;
    }
    
    /**
     * Reset all automation data (for troubleshooting)
     */
    public static function reset_automation_data() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        
        // Clear queue and executions but keep campaigns
        $wpdb->query("TRUNCATE TABLE $queue_table");
        $wpdb->query("TRUNCATE TABLE $executions_table");
        
        // Reset campaign next_run times
        $wpdb->query("UPDATE $campaigns_table SET next_run = NOW()");
        
        return true;
    }
}