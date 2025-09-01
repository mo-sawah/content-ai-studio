<?php
// in includes/class-atm-campaign-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Campaign_Manager {

    public function __construct() {
        // This is the single hook that will run periodically
        add_action('atm_run_due_campaigns', array($this, 'execute_due_campaigns'));
    }
    
    // Schedule our main cron job if it's not already scheduled
    public static function schedule_main_cron() {
        if (!wp_next_scheduled('atm_run_due_campaigns')) {
            // Runs every 5 minutes.
            wp_schedule_event(time(), 'five_minutes', 'atm_run_due_campaigns');
        }
    }

    // This is the function that gets executed by the cron job
    public function execute_due_campaigns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';

        // Find all active campaigns that are due to run
        $campaigns_to_run = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE is_active = 1 AND next_run <= %s",
                current_time('mysql', 1) // Use GMT time
            )
        );

        foreach ($campaigns_to_run as $campaign) {
            // Use a try-catch block so one failed campaign doesn't stop others
            try {
                self::execute_campaign($campaign->id);
            } catch (Exception $e) {
                error_log('Content AI Campaign Error (ID: ' . $campaign->id . '): ' . $e->getMessage());
            }
        }
    }

    // The core logic for running a single campaign
    public static function execute_campaign($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $campaign_id));
        if (!$campaign) return;

        // De-duplication Logic: Check if a post with the exact keyword as a title already exists
        if (post_exists($campaign->keyword)) {
            error_log('ATM Campaign ' . $campaign_id . ': Aborted. An article with the title "' . $campaign->keyword . '" already exists.');
            // Update next_run time even if we abort, to prevent it from running constantly
            self::update_next_run_time($campaign_id, $campaign->frequency_value, $campaign->frequency_unit);
            return;
        }
        
        $system_prompt = "You are an expert content creator specializing in '{$campaign->article_type}' articles for a target audience in '{$campaign->country}'. Your task is to generate a complete, high-quality article about '{$campaign->keyword}'. " . ($campaign->custom_prompt ?: ATM_API::get_default_article_prompt());

        $generated_json = ATM_API::enhance_content_with_openrouter(['content' => $campaign->keyword], $system_prompt, '', true);
        $article_data = json_decode($generated_json, true);

        if (!$article_data || empty($article_data['title']) || post_exists($article_data['title'])) {
            error_log('ATM Campaign ' . $campaign_id . ': Aborted due to duplicate or empty title from AI.');
            self::update_next_run_time($campaign_id, $campaign->frequency_value, $campaign->frequency_unit);
            return;
        }

        $post_data = [
            'post_title'    => wp_strip_all_tags($article_data['title']),
            'post_content'  => $article_data['content'],
            'post_status'   => $campaign->post_status,
            'post_author'   => $campaign->author_id,
            'post_category' => array($campaign->category_id)
        ];
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            if (!empty($article_data['subheadline'])) {
                ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $article_data['subheadline'], '');
            }

            if ($campaign->generate_image) {
                $ajax_handler = new ATM_Ajax();
                $image_prompt = ATM_API::get_default_image_prompt();
                $processed_prompt = ATM_API::replace_prompt_shortcodes($image_prompt, get_post($post_id));
                $image_url = ATM_API::generate_image_with_openai($processed_prompt);
                $attachment_id = $ajax_handler->set_image_from_url($image_url, $post_id);
                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            }
            
            // Update the last_run and calculate the next run time
            $wpdb->update($table_name, ['last_run' => current_time('mysql', 1)], ['id' => $campaign_id]);
            self::update_next_run_time($campaign_id, $campaign->frequency_value, $campaign->frequency_unit);
        }
    }

    // Helper function to update the next run time
    private static function update_next_run_time($campaign_id, $value, $unit) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_ai_campaigns';
        $interval_string = "+$value $unit";
        $next_run_timestamp = strtotime($interval_string, current_time('timestamp', 1));
        $next_run_mysql = date('Y-m-d H:i:s', $next_run_timestamp);
        $wpdb->update($table_name, ['next_run' => $next_run_mysql], ['id' => $campaign_id]);
    }
}