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
     * Add status column to campaigns table
     */
    public static function add_status_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'status'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status varchar(20) DEFAULT 'idle' AFTER is_active");
            error_log('ATM Automation: Added status column to campaigns table');
        }
    }

    /**
     * Update campaign status
     */
    public static function update_campaign_status($campaign_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $valid_statuses = ['idle', 'running', 'paused', 'failed'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        return $wpdb->update(
            $table_name,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $campaign_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Get campaign execution statistics
     */
    public static function get_campaign_stats($campaign_id) {
        global $wpdb;
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_executions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                MAX(executed_at) as last_execution,
                AVG(execution_time) as avg_execution_time
            FROM $executions_table 
            WHERE campaign_id = %d",
            $campaign_id
        ));
        
        return $stats ?: (object)[
            'total_executions' => 0,
            'successful' => 0, 
            'failed' => 0,
            'last_execution' => null,
            'avg_execution_time' => null
        ];
    }

    /**
     * Get campaign by ID
     */
    public static function get_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $campaign_id
        ));
        
        return $campaign;
    }
    
    /**
     * Get all campaigns with optional filters
     */
    public static function get_campaigns($filters = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $where_clauses = [];
        $where_values = [];
        
        if (isset($filters['type'])) {
            $where_clauses[] = 'type = %s';
            $where_values[] = $filters['type'];
        }
        
        if (isset($filters['is_active'])) {
            $where_clauses[] = 'is_active = %d';
            $where_values[] = $filters['is_active'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Create new campaign
     */
    public static function create_campaign($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        // Set next_run based on schedule
        $next_run = self::calculate_next_run($data['schedule_value'], $data['schedule_unit']);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $data['name'],
                'type' => $data['type'],
                'sub_type' => $data['sub_type'] ?? 'standard',
                'keyword' => $data['keyword'],
                'settings' => is_array($data['settings']) ? json_encode($data['settings']) : $data['settings'],
                'schedule_type' => $data['schedule_type'] ?? 'interval',
                'schedule_value' => $data['schedule_value'],
                'schedule_unit' => $data['schedule_unit'],
                'content_mode' => $data['content_mode'],
                'category_id' => $data['category_id'] ?? 0,
                'author_id' => $data['author_id'],
                'is_active' => $data['is_active'] ? 1 : 0,
                'next_run' => $next_run
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s'
            ]
        );
        
        if ($result === false) {
            error_log('ATM Automation: Failed to create campaign - ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update campaign
     */
    public static function update_campaign($campaign_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $update_data = [];
        $update_format = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $update_format[] = '%s';
        }
        
        if (isset($data['type'])) {
            $update_data['type'] = $data['type'];
            $update_format[] = '%s';
        }
        
        if (isset($data['sub_type'])) {
            $update_data['sub_type'] = $data['sub_type'];
            $update_format[] = '%s';
        }
        
        if (isset($data['keyword'])) {
            $update_data['keyword'] = $data['keyword'];
            $update_format[] = '%s';
        }
        
        if (isset($data['settings'])) {
            $update_data['settings'] = is_array($data['settings']) ? json_encode($data['settings']) : $data['settings'];
            $update_format[] = '%s';
        }
        
        if (isset($data['schedule_value'])) {
            $update_data['schedule_value'] = $data['schedule_value'];
            $update_format[] = '%d';
        }
        
        if (isset($data['schedule_unit'])) {
            $update_data['schedule_unit'] = $data['schedule_unit'];
            $update_format[] = '%s';
        }
        
        if (isset($data['content_mode'])) {
            $update_data['content_mode'] = $data['content_mode'];
            $update_format[] = '%s';
        }
        
        if (isset($data['category_id'])) {
            $update_data['category_id'] = $data['category_id'];
            $update_format[] = '%d';
        }
        
        if (isset($data['author_id'])) {
            $update_data['author_id'] = $data['author_id'];
            $update_format[] = '%d';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $update_format[] = '%d';
        }
        
        // Recalculate next_run if schedule changed
        if (isset($data['schedule_value']) || isset($data['schedule_unit'])) {
            $schedule_value = $data['schedule_value'] ?? null;
            $schedule_unit = $data['schedule_unit'] ?? null;
            
            // Get current values if not provided
            if (!$schedule_value || !$schedule_unit) {
                $current = $wpdb->get_row($wpdb->prepare(
                    "SELECT schedule_value, schedule_unit FROM $table_name WHERE id = %d",
                    $campaign_id
                ));
                $schedule_value = $schedule_value ?: $current->schedule_value;
                $schedule_unit = $schedule_unit ?: $current->schedule_unit;
            }
            
            $update_data['next_run'] = self::calculate_next_run($schedule_value, $schedule_unit);
            $update_format[] = '%s';
        }
        
        if (empty($update_data)) {
            return true; // Nothing to update
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $campaign_id],
            $update_format,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete campaign
     */
    public static function delete_campaign($campaign_id) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        
        // Delete related records first
        $wpdb->delete($queue_table, ['campaign_id' => $campaign_id], ['%d']);
        $wpdb->delete($executions_table, ['campaign_id' => $campaign_id], ['%d']);
        
        // Delete the campaign
        $result = $wpdb->delete($campaigns_table, ['id' => $campaign_id], ['%d']);
        
        return $result !== false;
    }
    
    /**
     * Get campaigns due for execution
     */
    public static function get_due_campaigns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE is_active = 1 
            AND next_run <= %s 
            ORDER BY next_run ASC",
            current_time('mysql')
        ));
    }
    
    /**
     * Update campaign next run time
     */
    public static function update_next_run($campaign_id, $schedule_value, $schedule_unit) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_campaigns';
        
        $next_run = self::calculate_next_run($schedule_value, $schedule_unit);
        
        return $wpdb->update(
            $table_name,
            ['next_run' => $next_run],
            ['id' => $campaign_id],
            ['%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Log execution result
     */
    public static function log_execution($campaign_id, $status, $message = '', $post_id = null, $execution_time = null, $memory_usage = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_executions';
        
        return $wpdb->insert(
            $table_name,
            [
                'campaign_id' => $campaign_id,
                'status' => $status,
                'message' => $message,
                'post_id' => $post_id,
                'execution_time' => $execution_time,
                'memory_usage' => $memory_usage
            ],
            ['%d', '%s', '%s', '%d', '%f', '%s']
        ) !== false;
    }
    
    /**
     * Calculate next run time based on schedule
     */
    private static function calculate_next_run($schedule_value, $schedule_unit) {
        $interval_map = [
            'minute' => 'MINUTE',
            'hour' => 'HOUR',
            'day' => 'DAY',
            'week' => 'WEEK'
        ];
        
        $unit = $interval_map[$schedule_unit] ?? 'HOUR';
        
        return date('Y-m-d H:i:s', strtotime("+{$schedule_value} {$unit}"));
    }

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
            category_ids text DEFAULT NULL,
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