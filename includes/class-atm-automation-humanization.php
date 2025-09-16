<?php
/**
 * ATM Automation Humanization Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Automation_Humanization {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX handler for testing humanization
        add_action('wp_ajax_test_automation_humanization', array($this, 'ajax_test_humanization'));
        
        // Hook into automation content generation
        add_filter('atm_automation_content_generated', array($this, 'process_automation_humanization'), 10, 3);
    }
    
    /**
     * AJAX: Test humanization for automation
     */
    public function ajax_test_humanization() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            // Debug: Log the request
            error_log('ATM Test Humanization: Starting test...');
            error_log('ATM Test Humanization: POST data: ' . print_r($_POST, true));
            
            $content = wp_kses_post(stripslashes($_POST['content']));
            $provider = sanitize_text_field($_POST['provider'] ?? 'stealthgpt');
            $tone = sanitize_text_field($_POST['tone'] ?? 'conversational');
            $mode = sanitize_text_field($_POST['mode'] ?? 'Medium');
            $model = sanitize_text_field($_POST['model'] ?? 'anthropic/claude-3.5-sonnet');
            
            if (empty($content)) {
                throw new Exception('Content is required for testing.');
            }
            
            // Check if humanization class exists
            if (!class_exists('ATM_Humanize')) {
                error_log('ATM Test Humanization: ATM_Humanize class not found');
                throw new Exception('Humanization service not available. Please ensure the humanization module is loaded.');
            }
            
            error_log("ATM Test Humanization: Using provider: {$provider}");
            
            // Initialize humanizer
            $humanizer = new ATM_Humanize();
            
            $options = [
                'tone' => $tone,
                'mode' => $mode,
                'business_mode' => false, // Use cheaper mode for testing
                'model' => $model,
                'preserve_formatting' => false // Disable for testing
            ];
            
            error_log('ATM Test Humanization: Options: ' . print_r($options, true));
            
            // Test humanization
            $result = $humanizer->humanize_content($content, $provider, $options);
            
            error_log('ATM Test Humanization: Result: ' . print_r($result, true));
            
            // Optional: Check AI detection (but don't fail if it errors)
            $detection_score = null;
            try {
                $detection_score = $humanizer->check_ai_detection($result['humanized_content']);
            } catch (Exception $e) {
                error_log('ATM Test Humanization: Detection check failed: ' . $e->getMessage());
                // Continue without detection score
            }
            
            wp_send_json_success([
                'humanized_content' => $result['humanized_content'],
                'credits_used' => $result['credits_used'],
                'detection_score' => $detection_score,
                'processing_time' => $result['processing_time'] ?? null,
                'provider_used' => $provider
            ]);
            
        } catch (Exception $e) {
            error_log('ATM Test Humanization Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Process automation content through humanization
     * DISABLE WEB SEARCH for humanization
     */
    public function process_automation_humanization($content_result, $campaign, $settings) {
        // Check if humanization is enabled for this campaign
        $humanization_settings = $settings['humanization'] ?? [];
        
        if (empty($humanization_settings['enabled'])) {
            return $content_result; // Return original content
        }
        
        try {
            error_log("ATM Automation: Starting humanization for campaign: " . $campaign->name);
            
            // Extract content to humanize
            $content_to_humanize = $content_result['article_content'] ?? $content_result['content'] ?? '';
            
            if (empty($content_to_humanize)) {
                error_log("ATM Automation: No content to humanize");
                return $content_result;
            }
            
            // Prepare humanization options - DISABLE WEB SEARCH
            $humanization_options = [
                'tone' => $humanization_settings['tone'] ?? 'conversational',
                'mode' => $humanization_settings['mode'] ?? 'Medium',
                'business_mode' => $humanization_settings['business_mode'] ?? true,
                'preserve_formatting' => $humanization_settings['preserve_formatting'] ?? true,
                'model' => $humanization_settings['openrouter_model'] ?? 'anthropic/claude-3.5-sonnet',
                'disable_web_search' => true // DISABLE WEB SEARCH FOR HUMANIZATION
            ];
            
            $provider = $humanization_settings['provider'] ?? 'stealthgpt';
            
            // Initialize humanizer
            if (!class_exists('ATM_Humanize')) {
                throw new Exception('Humanization service not available');
            }
            
            $humanizer = new ATM_Humanize();
            
            // Humanize the content
            $humanization_result = $humanizer->humanize_content(
                $content_to_humanize, 
                $provider, 
                $humanization_options
            );
            
            $humanized_content = $humanization_result['humanized_content'];
            $credits_used = $humanization_result['credits_used'];
            
            error_log("ATM Automation: Content humanized successfully. Credits used: " . $credits_used);
            
            // Optional: Check AI detection and retry if needed
            if ($humanization_settings['retry_on_detection'] ?? false) {
                try {
                    $detection_score = $humanizer->check_ai_detection($humanized_content);
                    
                    if ($detection_score > 70) { // High AI detection
                        error_log("ATM Automation: High AI detection ({$detection_score}%), retrying with aggressive mode");
                        
                        $retry_options = $humanization_options;
                        $retry_options['mode'] = 'High';
                        
                        $retry_result = $humanizer->humanize_content(
                            $content_to_humanize, 
                            $provider, 
                            $retry_options
                        );
                        
                        $humanized_content = $retry_result['humanized_content'];
                        $credits_used += $retry_result['credits_used'];
                    }
                } catch (Exception $e) {
                    error_log("ATM Automation: AI detection check failed: " . $e->getMessage());
                    // Continue without detection check
                }
            }
            
            // Update the content result
            if (isset($content_result['article_content'])) {
                $content_result['article_content'] = $humanized_content;
            } else {
                $content_result['content'] = $humanized_content;
            }
            
            // Add humanization metadata
            $content_result['humanization_applied'] = true;
            $content_result['humanization_credits_used'] = $credits_used;
            $content_result['humanization_provider'] = $provider;
            
            return $content_result;
            
        } catch (Exception $e) {
            error_log('ATM Automation Humanization Error: ' . $e->getMessage());
            
            // Check fallback setting
            if ($humanization_settings['fallback_to_draft'] ?? true) {
                // Modify the post status to draft if humanization fails
                $content_result['force_draft'] = true;
                $content_result['humanization_error'] = $e->getMessage();
            }
            
            return $content_result; // Return original content on failure
        }
    }
}

// Initialize the class
new ATM_Automation_Humanization();