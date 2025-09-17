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
        // Fix: Add the missing action handler that JavaScript is calling
        add_action('wp_ajax_test_automation_humanization', array($this, 'ajax_test_humanization'));
        
        // Keep existing handlers
        add_action('wp_ajax_atm_test_automation_humanization', array($this, 'ajax_test_humanization')); 
        add_filter('atm_automation_content_generated', array($this, 'process_automation_humanization'), 10, 3);
    }
    
    /**
     * AJAX: Test humanization for automation
     */
    public function ajax_test_humanization() {
        check_ajax_referer('atm_automation_nonce', 'nonce');
        
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
     * Update the main process method to use the new automation-specific method
     */
    public function process_automation_humanization($content_result, $campaign, $settings) {
        $humanization_settings = $settings['humanization'] ?? [];
        
        if (empty($humanization_settings['enabled'])) {
            return $content_result;
        }
        
        try {
            error_log("ATM Automation: Starting humanization for campaign: " . $campaign->name);
            
            $content_to_humanize = $content_result['article_content'] ?? $content_result['content'] ?? '';
            
            if (empty($content_to_humanize)) {
                return $content_result;
            }
            
            $humanization_options = [
                'tone' => $humanization_settings['tone'] ?? 'conversational',
                'mode' => $humanization_settings['mode'] ?? 'Medium',
                'business_mode' => $humanization_settings['business_mode'] ?? true,
                'preserve_formatting' => $humanization_settings['preserve_formatting'] ?? true,
                'model' => $humanization_settings['openrouter_model'] ?? 'anthropic/claude-3.5-sonnet'
            ];
            
            $provider = $humanization_settings['provider'] ?? 'openrouter';
            
            // Use our optimized automation method
            $humanization_result = $this->humanize_automation_content(
                $content_to_humanize, 
                $provider, 
                $humanization_options
            );
            
            $humanized_content = $humanization_result['humanized_content'];
            $credits_used = $humanization_result['credits_used'];
            
            // Update content result
            if (isset($content_result['article_content'])) {
                $content_result['article_content'] = $humanized_content;
            } else {
                $content_result['content'] = $humanized_content;
            }
            
            $content_result['humanization_applied'] = true;
            $content_result['humanization_credits_used'] = $credits_used;
            $content_result['humanization_provider'] = $provider;
            $content_result['humanization_method'] = $humanization_result['method'];
            
            error_log("ATM Automation: Humanization completed using {$provider}. Credits: {$credits_used}, Method: {$humanization_result['method']}");
            
            return $content_result;
            
        } catch (Exception $e) {
            error_log('ATM Automation Humanization Error: ' . $e->getMessage());
            
            if ($humanization_settings['fallback_to_draft'] ?? true) {
                $content_result['force_draft'] = true;
                $content_result['humanization_error'] = $e->getMessage();
            }
            
            return $content_result;
        }
    }

    /**
     * Optimized OpenRouter humanization specifically for automation
     */
    private function humanize_with_openrouter_automation($content, $options = []) {
        $api_key = get_option('atm_openrouter_api_key', get_option('atm_openrouter_key'));
        
        if (empty($api_key)) {
            throw new Exception('OpenRouter API key not configured.');
        }
        
        // Pre-clean the content first
        $cleaned_content = $this->pre_clean_ai_content($content);
        
        $model = $options['model'] ?? 'anthropic/claude-3-5-sonnet-20241022';
        $tone = $options['tone'] ?? 'conversational';
        
        $system_prompt = $this->build_automation_humanization_prompt($tone);
        
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user', 
                    'content' => "Please rewrite this content to sound more human and natural:\n\n" . $cleaned_content
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => min(8000, max(1000, strlen($content) * 1.5)),
            'top_p' => 0.85,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.1
        ];
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => 'Content AI Studio - Automation'
            ],
            'body' => json_encode($payload),
            'timeout' => 90 // Slightly longer timeout for automation
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('OpenRouter API connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->handle_openrouter_error($response_code, $response_body);
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenRouter API.');
        }
        
        $humanized_content = trim($data['choices'][0]['message']['content']);
        $credits_used = $this->calculate_automation_credits($content, $model);
        
        return [
            'humanized_content' => $humanized_content,
            'credits_used' => $credits_used
        ];
    }

    private function pre_clean_ai_content($content) {
        // Remove common AI markers before sending to humanizer
        $ai_replacements = [
            // Common AI phrases
            '/\bIt is important to note that\b/i' => '',
            '/\bIt should be noted that\b/i' => '',
            '/\bFurthermore,?\s+/i' => 'Also, ',
            '/\bMoreover,?\s+/i' => 'Plus, ',
            '/\bAdditionally,?\s+/i' => 'Also, ',
            '/\bIn conclusion,?\s+/i' => 'So, ',
            '/\bTo summarize,?\s+/i' => 'In short, ',
            
            // Overly formal words
            '/\butilize\b/i' => 'use',
            '/\bfacilitate\b/i' => 'help',
            '/\bcommence\b/i' => 'start',
            '/\bsubsequent\b/i' => 'next',
            '/\bnotwithstanding\b/i' => 'despite',
            '/\bnevertheless\b/i' => 'still',
            
            // Redundant phrases
            '/\bvery unique\b/i' => 'unique',
            '/\bcompletely eliminate\b/i' => 'eliminate',
            '/\bfuture plans\b/i' => 'plans',
            
            // Excessive spacing
            '/\s+/' => ' ',
        ];
        
        foreach ($ai_replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return trim($content);
    }
    
    /**
     * Optimized humanization prompt for automation - focused on practical results
     */
    private function build_automation_humanization_prompt($tone) {
        $tone_styles = [
            'conversational' => 'friendly and natural, like explaining to a colleague',
            'professional' => 'polished but approachable business writing',
            'casual' => 'relaxed and informal',
            'academic' => 'scholarly but clear',
            'journalistic' => 'clear news writing style',
            'creative' => 'Expressive and vivid - use metaphors, paint pictures with words',
            'technical' => 'Expert-level but clear - explain complex topics simply',
            'persuasive' => 'Convincing and compelling - use strong, confident language',
            'storytelling' => 'Narrative flow - create scenes, use descriptive language'
        ];
        
        $style_instruction = $tone_styles[$tone] ?? $tone_styles['conversational'];
        
        return "You are an expert writer tasked with rewriting AI-generated content to sound naturally human-written while maintaining all original information.

WRITING STYLE: Make it {$style_instruction}

ESSENTIAL RULES:
1. Keep ALL facts, data, and links exactly as provided
2. Maintain the same general length and structure
3. Use natural language patterns that humans actually use
4. Vary sentence lengths naturally (mix short and long sentences)
5. Use contractions where appropriate (don't, can't, it's, we're)

HUMANIZATION TECHNIQUES:
- Replace formal transitions: 'Furthermore' → 'Also', 'Moreover' → 'Plus'
- Use active voice when possible
- Add subtle personality without changing meaning
- Include natural imperfections (humans aren't perfect writers)
- Use simpler alternatives: 'utilize' → 'use', 'facilitate' → 'help'

OUTPUT: Return ONLY the rewritten content with no explanations or notes.";
    }
    
    /**
     * Enhanced automation humanization with fallbacks and optimization
     */
    public function humanize_automation_content($content, $provider, $options = []) {
        $start_time = microtime(true);
        
        // Pre-processing: Quick checks
        if (strlen(trim(wp_strip_all_tags($content))) < 100) {
            // Too short to effectively humanize
            return [
                'humanized_content' => $content,
                'credits_used' => 0,
                'processing_time' => 0,
                'method' => 'skipped_too_short'
            ];
        }
        
        // Content optimization before humanization
        $processed_content = $this->optimize_content_for_humanization($content);
        
        try {
            switch ($provider) {
                case 'openrouter':
                    $result = $this->humanize_with_openrouter_automation($processed_content, $options);
                    break;
                    
                case 'stealthgpt':
                    // Use the main class but with automation-optimized settings
                    if (class_exists('ATM_Humanize')) {
                        $humanizer = new ATM_Humanize();
                        $automation_options = array_merge($options, [
                            'business_mode' => false, // Faster for automation
                            'mode' => $options['mode'] ?? 'Medium'
                        ]);
                        $result = $humanizer->humanize_content($processed_content, 'stealthgpt', $automation_options);
                    } else {
                        throw new Exception('StealthGPT humanizer not available');
                    }
                    break;
                    
                default:
                    throw new Exception('Unsupported provider for automation: ' . $provider);
            }
            
            // Post-processing: Quality check
            $final_result = $this->post_process_humanized_content($result['humanized_content'], $content);
            
            $processing_time = round((microtime(true) - $start_time) * 1000);
            
            return [
                'humanized_content' => $final_result,
                'credits_used' => $result['credits_used'],
                'processing_time' => $processing_time,
                'method' => $provider
            ];
            
        } catch (Exception $e) {
            error_log('ATM Automation Humanization Error: ' . $e->getMessage());
            
            // Return original content on failure
            return [
                'humanized_content' => $content,
                'credits_used' => 0,
                'processing_time' => round((microtime(true) - $start_time) * 1000),
                'method' => 'failed_fallback',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Optimize content before humanization
     */
    private function optimize_content_for_humanization($content) {
        // Remove excessive spacing
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Fix common AI patterns before sending to humanizer
        $ai_patterns = [
            '/\b(Furthermore|Moreover|Additionally|However),?\s+/i' => '',
            '/\b(It is important to note that|It should be noted that)\s+/i' => '',
            '/\b(In conclusion|To summarize|In summary),?\s+/i' => 'So ',
            '/\bunprecedented\b/i' => 'remarkable',
            '/\brevolutionary\b/i' => 'game-changing',
            '/\bseamlessly\b/i' => 'smoothly',
            '/\butilize\b/i' => 'use',
            '/\bfacilitate\b/i' => 'help',
        ];
        
        foreach ($ai_patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return trim($content);
    }
    
    /**
     * Post-process humanized content for quality
     */
    private function post_process_humanized_content($humanized, $original) {
        // Ensure minimum length (shouldn't be drastically shorter)
        $original_length = strlen(wp_strip_all_tags($original));
        $humanized_length = strlen(wp_strip_all_tags($humanized));
        
        if ($humanized_length < ($original_length * 0.7)) {
            error_log('ATM Humanization: Content too short after humanization, using original');
            return $original;
        }
        
        // Basic quality checks
        $humanized = trim($humanized);
        
        // Ensure it doesn't start with quotes (common AI mistake)
        $humanized = preg_replace('/^["\'"](.*)["\'"]$/s', '$1', $humanized);
        
        return $humanized;
    }
    
    /**
     * Calculate credits for automation (more conservative estimates)
     */
    private function calculate_automation_credits($content, $model) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        $tokens_estimate = $word_count * 1.3;
        
        $cost_per_1k_tokens = [
            'anthropic/claude-3.5-sonnet' => 3.0,
            'openai/gpt-4o' => 2.5,
            'anthropic/claude-3-opus' => 15.0,
            'anthropic/claude-3-haiku' => 1.0,
        ];
        
        $rate = $cost_per_1k_tokens[$model] ?? 3.0;
        return ceil(($tokens_estimate / 1000) * $rate);
    }
    
    /**
     * Handle OpenRouter errors
     */
    private function handle_openrouter_error($response_code, $response_body) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? "HTTP {$response_code} error";
        
        switch ($response_code) {
            case 401:
                throw new Exception('Invalid OpenRouter API key for automation.');
            case 429:
                throw new Exception('OpenRouter rate limit exceeded for automation.');
            case 402:
                throw new Exception('Insufficient OpenRouter credits for automation.');
            default:
                throw new Exception("OpenRouter automation error: {$error_message}");
        }
    }
    
    
}

// Initialize the class
new ATM_Automation_Humanization();