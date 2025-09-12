<?php
/**
 * Core Automation Processor
 * File: includes/automation/class-atm-automation-processor.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Processor {
    
    const MAX_EXECUTION_TIME = 300; // 5 minutes per campaign execution
    const LOCK_TIMEOUT = 600; // 10 minutes lock timeout
    
    /**
     * Process the automation queue - called every minute by Action Scheduler
     */
    public static function process_queue() {
        global $wpdb;
        
        @set_time_limit(self::MAX_EXECUTION_TIME);
        
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        
        $due_items = $wpdb->get_results($wpdb->prepare("
            SELECT q.*, c.name as campaign_name, c.type, c.status as campaign_status
            FROM $queue_table q
            JOIN $campaigns_table c ON q.campaign_id = c.id
            WHERE q.status = 'pending' 
            AND q.scheduled_for <= %s 
            AND c.status = 'active'
            AND q.attempts < q.max_attempts
            ORDER BY q.priority ASC, q.scheduled_for ASC
            LIMIT 10
        ", current_time('mysql', 1)));
        
        foreach ($due_items as $item) {
            self::process_queue_item($item);
        }
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM $queue_table 
            WHERE status IN ('completed', 'failed') 
            AND updated_at < %s
        ", date('Y-m-d H:i:s', strtotime('-7 days'))));
        
        self::schedule_next_executions();
    }
    
    /**
     * Process a single queue item
     */
    private static function process_queue_item($item) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        $executions_table = $wpdb->prefix . 'atm_automation_executions';
        
        $lock_id = uniqid(gethostname() . '_', true);
        $locked = $wpdb->update(
            $queue_table,
            [
                'status' => 'processing',
                'locked_at' => current_time('mysql', 1),
                'locked_by' => $lock_id,
                'attempts' => $item->attempts + 1
            ],
            ['id' => $item->id, 'status' => 'pending']
        );
        
        if (!$locked) {
            return;
        }
        
        $execution_id = $wpdb->insert($executions_table, [
            'campaign_id' => $item->campaign_id,
            'status' => 'running',
            'started_at' => current_time('mysql', 1)
        ]);
        
        $execution_id = $wpdb->insert_id;
        $start_time_for_duration = microtime(true);
        
        try {
            $result = self::execute_campaign($item->campaign_id);
            
            $wpdb->update($executions_table, [
                'status' => 'completed',
                'completed_at' => current_time('mysql', 1),
                'post_id' => $result['post_id'] ?? null,
                'generated_title' => $result['title'] ?? null,
                'execution_time_seconds' => microtime(true) - $start_time_for_duration,
                'api_calls_made' => $result['api_calls'] ?? 1,
                'execution_data' => json_encode($result)
            ], ['id' => $execution_id]);
            
            $wpdb->update($queue_table, [
                'status' => 'completed',
                'updated_at' => current_time('mysql', 1)
            ], ['id' => $item->id]);
            
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}atm_automation_campaigns
                SET consecutive_failures = 0, posts_generated = posts_generated + 1
                WHERE id = %d
            ", $item->campaign_id));
            
        } catch (Exception $e) {
            error_log("ATM Automation Error: Campaign {$item->campaign_id} - " . $e->getMessage());
            
            $wpdb->update($executions_table, [
                'status' => 'failed',
                'completed_at' => current_time('mysql', 1),
                'error_message' => $e->getMessage(),
                'execution_time_seconds' => microtime(true) - $start_time_for_duration
            ], ['id' => $execution_id]);
            
            if ($item->attempts >= $item->max_attempts) {
                $wpdb->update($queue_table, [
                    'status' => 'failed',
                    'updated_at' => current_time('mysql', 1)
                ], ['id' => $item->id]);
                
                $wpdb->query($wpdb->prepare("
                    UPDATE {$wpdb->prefix}atm_automation_campaigns 
                    SET consecutive_failures = consecutive_failures + 1 
                    WHERE id = %d
                ", $item->campaign_id));
            } else {
                $delay = min(3600, pow(2, $item->attempts) * 60);
                $next_attempt = date('Y-m-d H:i:s', time() + $delay);
                
                $wpdb->update($queue_table, [
                    'status' => 'pending',
                    'scheduled_for' => $next_attempt,
                    'locked_at' => null,
                    'locked_by' => null,
                    'updated_at' => current_time('mysql', 1)
                ], ['id' => $item->id]);
            }
        }
    }
    
    /**
     * Execute a specific campaign
     */
    public static function execute_campaign($campaign_id) {
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}atm_automation_campaigns 
            WHERE id = %d AND status = 'active'
        ", $campaign_id));
        
        if (!$campaign) {
            throw new Exception("Campaign not found or inactive: $campaign_id");
        }
        
        $settings = json_decode($campaign->content_settings, true);
        if (!$settings) {
            throw new Exception("Invalid campaign settings");
        }
        
        if ($campaign->max_posts > 0 && $campaign->posts_generated >= $campaign->max_posts) {
            $wpdb->update($wpdb->prefix . 'atm_automation_campaigns', ['status' => 'completed'], ['id' => $campaign_id]);
            throw new Exception("Campaign completed: reached maximum posts limit");
        }
        
        switch ($campaign->type) {
            case 'article':
                $result = self::execute_article_campaign($campaign, $settings);
                break;
            // ... cases for news, video, podcast will be added here
            default:
                throw new Exception("Unknown campaign type: {$campaign->type}");
        }
        
        $post_id = self::create_post_from_result($campaign, $result);
        
        $wpdb->update($wpdb->prefix . 'atm_automation_campaigns', [
            'last_executed' => current_time('mysql', 1)
        ], ['id' => $campaign_id]);
        
        return [
            'post_id' => $post_id,
            'title' => $result['title'] ?? '',
            'api_calls' => $result['api_calls'] ?? 1,
            'type' => $campaign->type
        ];
    }
    
    /**
     * Execute article generation campaign
     */
    private static function execute_article_campaign($campaign, $settings) {
        $keyword = $settings['keyword'] ?? '';
        $title = $settings['title'] ?? '';
        $model = $settings['model'] ?? '';
        
        $topic = $title ?: $keyword;
        if (empty($topic)) {
            throw new Exception("Article campaign missing keyword or title");
        }
        
        if (!self::is_content_unique($campaign->id, $topic)) {
            throw new Exception("Duplicate content detected for topic '{$topic}', skipping generation to avoid repetition.");
        }
        
        $system_prompt = self::build_article_prompt($settings);
        
        $response = ATM_API::enhance_content_with_openrouter(
            ['content' => $topic],
            $system_prompt,
            $model ?: get_option('atm_article_model'),
            true, // JSON mode
            true  // Enable web search
        );
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            throw new Exception('Invalid AI response for article generation. The AI did not return a valid JSON object.');
        }
        
        self::track_generated_content($campaign->id, $result['title'], $topic);
        
        return $result;
    }
    
    /**
     * Create WordPress post from generation result
     */
    private static function create_post_from_result($campaign, $result) {
        $category_ids = json_decode($campaign->category_ids ?: '[]', true);
        $parsedown = new Parsedown();
        
        $post_data = [
            'post_title' => sanitize_text_field($result['title']),
            'post_content' => wp_kses_post($parsedown->text($result['content'])),
            'post_status' => 'draft', // Always create as draft first
            'post_author' => $campaign->author_id,
            'post_category' => is_array($category_ids) ? array_map('intval', $category_ids) : [],
        ];
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create post: ' . $post_id->get_error_message());
        }
        
        if (!empty($result['subtitle'])) {
            ATM_Theme_Subtitle_Manager::save_subtitle($post_id, sanitize_text_field($result['subtitle']));
        }
        
        if ($campaign->generate_featured_image) {
            self::generate_featured_image_async($post_id, $result['title']);
        }

        // Handle final post status
        if ($campaign->post_status === 'publish' || $campaign->post_status === 'scheduled') {
            $final_post_data = ['ID' => $post_id, 'post_status' => 'publish'];
            if ($campaign->post_status === 'scheduled') {
                $final_post_data['post_date'] = date('Y-m-d H:i:s', time() + rand(600, 86400)); // Schedule 10min to 24hr in future
                $final_post_data['post_date_gmt'] = get_gmt_from_date($final_post_data['post_date']);
            }
            wp_update_post($final_post_data);
        }
        
        return $post_id;
    }
    
    /**
     * Schedule next executions for all active campaigns
     */
    private static function schedule_next_executions() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'atm_automation_campaigns';
        $queue_table = $wpdb->prefix . 'atm_automation_queue';
        
        $campaigns = $wpdb->get_results("
            SELECT * FROM $campaigns_table 
            WHERE status = 'active' 
            AND (next_execution IS NULL OR next_execution <= NOW())
            AND consecutive_failures < 5
        ");
        
        foreach ($campaigns as $campaign) {
            $next_execution = self::calculate_next_execution($campaign);
            
            $wpdb->update($campaigns_table, ['next_execution' => $next_execution], ['id' => $campaign->id]);
            
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $queue_table 
                WHERE campaign_id = %d AND status = 'pending'
            ", $campaign->id));
            
            if (!$existing) {
                $wpdb->insert($queue_table, [
                    'campaign_id' => $campaign->id,
                    'scheduled_for' => $next_execution
                ]);
            }
        }
    }
    
    /**
     * Calculate next execution time based on campaign frequency
     */
    private static function calculate_next_execution($campaign) {
        $now = time();
        $frequency_value = max(1, $campaign->frequency_value);
        
        $interval = 3600; // Default 1 hour
        switch ($campaign->frequency_unit) {
            case 'minute': $interval = $frequency_value * 60; break;
            case 'hour':   $interval = $frequency_value * 3600; break;
            case 'day':    $interval = $frequency_value * 86400; break;
            case 'week':   $interval = $frequency_value * 604800; break;
        }
        
        $interval = max($interval, 600); // Minimum interval of 10 minutes
        
        return date('Y-m-d H:i:s', $now + $interval);
    }
    
    // --- COMPLETED ---
    /**
     * Checks if content for a given identifier has already been generated to prevent duplicates.
     */
    private static function is_content_unique($campaign_id, $identifier) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_content_tracking';
        $content_hash = hash('sha256', $identifier);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE campaign_id = %d AND content_hash = %s",
            $campaign_id,
            $content_hash
        ));

        return $exists == 0;
    }

    // --- COMPLETED ---
    /**
     * Stores a hash of generated content to the tracking table to prevent future duplicates.
     */
    private static function track_generated_content($campaign_id, $generated_title, $source_identifier) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_automation_content_tracking';

        // Insert hashes for both the source and the result to be thorough
        $source_hash = hash('sha256', $source_identifier);
        $title_hash = hash('sha256', $generated_title);

        $wpdb->insert($table_name, [
            'campaign_id' => $campaign_id,
            'content_hash' => $source_hash,
            'source_identifier' => $source_identifier,
            'created_at' => current_time('mysql', 1)
        ]);
        
        if ($source_hash !== $title_hash) {
            $wpdb->insert($table_name, [
                'campaign_id' => $campaign_id,
                'content_hash' => $title_hash,
                'source_identifier' => $generated_title,
                'created_at' => current_time('mysql', 1)
            ]);
        }
    }

    // --- COMPLETED ---
    /**
     * Builds the system prompt for article generation based on campaign settings.
     */
    private static function build_article_prompt($settings) {
        $writing_styles = ATM_API::get_writing_styles();
        $style_key = $settings['writing_style'] ?? 'default_seo';
        
        $base_prompt = $settings['custom_prompt'] 
            ?: ($writing_styles[$style_key]['prompt'] ?? $writing_styles['default_seo']['prompt']);

        if (!empty($settings['word_count'])) {
            $base_prompt .= " The final article should be approximately " . intval($settings['word_count']) . " words long.";
        }
        
        // This reuses the same robust JSON structure instructions from the manual generator
        return ATM_API::get_enhanced_output_instructions($settings['title'] ?? '');
    }

    // --- COMPLETED ---
    /**
     * Schedules a background job to generate the featured image asynchronously.
     */
    private static function generate_featured_image_async($post_id, $title) {
        if (function_exists('as_enqueue_action')) {
            as_enqueue_action(
                'atm_generate_featured_image_job', 
                ['post_id' => $post_id, 'title' => $title], 
                'atm-automation'
            );
        } else {
            // Fallback for if Action Scheduler isn't active
            error_log("ATM Automation: Action Scheduler not found. Cannot generate featured image asynchronously.");
        }
    }
    
    // --- COMPLETED ---
    /**
     * The actual worker function for the asynchronous image generation job.
     */
    public static function run_image_generation_job($post_id, $title) {
        try {
            $prompt = ATM_API::get_default_image_prompt();
            $final_prompt = str_replace('[article_title]', $title, $prompt);

            $image_result = ATM_API::generate_image_with_configured_provider($final_prompt);
            
            $ajax_handler = new ATM_Ajax(); // To reuse the image handling logic
            if ($image_result['is_url']) {
                $attachment_id = $ajax_handler->set_image_from_url($image_result['data'], $post_id);
            } else {
                $attachment_id = $ajax_handler->set_image_from_data($image_result['data'], $post_id, $final_prompt);
            }

            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            } else {
                throw new Exception($attachment_id->get_error_message());
            }
        } catch (Exception $e) {
            error_log("ATM Automation Image Job Failed for Post {$post_id}: " . $e->getMessage());
        }
    }
}