<?php
/**
 * ATM Humanize Class
 * 
 * Handles all humanization functionality including StealthGPT integration
 * and OpenRouter's most powerful models for content humanization
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Humanize {
    
    /**
     * Available humanization providers
     */
    const PROVIDERS = [
        'stealthgpt' => 'StealthGPT (Recommended)',
        'openrouter' => 'OpenRouter (Claude/GPT-4)',
        'undetectable' => 'Undetectable.AI',
        'combo' => 'Combo (Multiple Passes)'
    ];
    
    /**
     * OpenRouter's most powerful models for humanization
     */
    const OPENROUTER_MODELS = [
        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Best Overall)',
        'openai/gpt-4o' => 'GPT-4o (OpenAI Latest)',
        'anthropic/claude-3-opus' => 'Claude 3 Opus (Most Intelligent)',
        'openai/gpt-4-turbo' => 'GPT-4 Turbo',
        'google/gemini-pro-1.5' => 'Gemini Pro 1.5',
        'meta-llama/llama-3.1-405b-instruct' => 'Llama 3.1 405B (Open Source)',
        'mistralai/mistral-large' => 'Mistral Large',
        'cohere/command-r-plus' => 'Command R+'
    ];
    
    /**
     * Tone options for humanization
     */
    const TONE_OPTIONS = [
        'conversational' => 'Conversational & Natural',
        'professional' => 'Professional',
        'casual' => 'Casual & Friendly',
        'academic' => 'Academic',
        'journalistic' => 'Journalistic',
        'creative' => 'Creative & Engaging',
        'technical' => 'Technical',
        'persuasive' => 'Persuasive',
        'storytelling' => 'Storytelling'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_humanize_content', array($this, 'ajax_humanize_content'));
        add_action('wp_ajax_check_ai_detection', array($this, 'ajax_check_ai_detection'));
        add_action('wp_ajax_batch_humanize_content', array($this, 'ajax_batch_humanize_content'));
        add_action('wp_ajax_test_humanization', array($this, 'ajax_test_humanization'));
        add_action('wp_ajax_validate_humanize_api', array($this, 'ajax_validate_api'));
        add_action('wp_ajax_get_humanize_stats', array($this, 'ajax_get_stats'));
        
        // Settings integration
        add_action('admin_init', array($this, 'register_settings'));
        
        // Auto-humanization hooks
        add_filter('atm_generated_content', array($this, 'maybe_auto_humanize'), 10, 2);
    }
    
    /**
     * AJAX: Humanize content
     */
    public function ajax_humanize_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);
        
        try {
            $content = wp_kses_post(stripslashes($_POST['content']));
            $provider = sanitize_text_field($_POST['provider'] ?? 'stealthgpt');
            $tone = sanitize_text_field($_POST['tone'] ?? 'conversational');
            $mode = sanitize_text_field($_POST['mode'] ?? 'High');
            $business_mode = filter_var($_POST['business_mode'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $preserve_formatting = filter_var($_POST['preserve_formatting'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $model = sanitize_text_field($_POST['model'] ?? '');
            
            if (empty($content)) {
                throw new Exception('Content is required for humanization.');
            }
            
            if (strlen($content) < 50) {
                throw new Exception('Content must be at least 50 characters long.');
            }
            
            // Extract and preserve formatting if needed
            $original_formatting = null;
            $plain_content = $content;
            
            if ($preserve_formatting) {
                $original_formatting = $this->extract_formatting($content);
                $plain_content = wp_strip_all_tags($content);
            }
            
            // Humanize based on provider
            $result = $this->humanize_content($plain_content, $provider, [
                'tone' => $tone,
                'mode' => $mode,
                'business_mode' => $business_mode,
                'model' => $model
            ]);
            
            // Restore formatting if preserved
            if ($preserve_formatting && $original_formatting) {
                $result['humanized_content'] = $this->restore_formatting($result['humanized_content'], $original_formatting);
            }
            
            // Optional: Run AI detection check
            $detection_score = null;
            if (get_option('atm_auto_check_detection', true)) {
                try {
                    $detection_score = $this->check_ai_detection($result['humanized_content']);
                } catch (Exception $e) {
                    error_log('ATM: AI detection check failed: ' . $e->getMessage());
                }
            }
            
            // Log usage
            $this->log_usage($content, $result['humanized_content'], $provider, $result['credits_used']);
            
            wp_send_json_success([
                'humanized_content' => $result['humanized_content'],
                'original_length' => strlen($content),
                'humanized_length' => strlen($result['humanized_content']),
                'detection_score' => $detection_score,
                'credits_used' => $result['credits_used'],
                'provider' => $provider,
                'processing_time' => $result['processing_time'] ?? null
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Check AI detection
     */
    public function ajax_check_ai_detection() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        try {
            $content = wp_kses_post(stripslashes($_POST['content']));
            
            if (empty($content)) {
                throw new Exception('Content is required for AI detection check.');
            }
            
            $detection_score = $this->check_ai_detection($content);
            
            wp_send_json_success([
                'detection_score' => $detection_score,
                'status' => $this->get_detection_status($detection_score),
                'recommendation' => $this->get_detection_recommendation($detection_score)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Batch humanize content
     */
    public function ajax_batch_humanize_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 600);
        
        try {
            $content_items = $_POST['content_items'];
            $settings = $_POST['settings'];
            
            if (empty($content_items) || !is_array($content_items)) {
                throw new Exception('Content items array is required.');
            }
            
            $results = [];
            $total_credits = 0;
            
            foreach ($content_items as $index => $item) {
                try {
                    $content = wp_kses_post(stripslashes($item['content']));
                    
                    $result = $this->humanize_content($content, $settings['provider'] ?? 'stealthgpt', $settings);
                    $total_credits += $result['credits_used'];
                    
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'humanized_content' => $result['humanized_content'],
                        'credits_used' => $result['credits_used']
                    ];
                    
                } catch (Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            wp_send_json_success([
                'results' => $results,
                'total_credits_used' => $total_credits,
                'processed_count' => count($content_items)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Test humanization
     */
    public function ajax_test_humanization() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        try {
            $content = wp_kses_post(stripslashes($_POST['content']));
            $provider = sanitize_text_field($_POST['provider'] ?? 'stealthgpt');
            
            if (empty($content) || strlen($content) < 50) {
                throw new Exception('Content must be at least 50 characters.');
            }
            
            $result = $this->humanize_content($content, $provider, [
                'tone' => 'conversational',
                'mode' => 'Medium',
                'business_mode' => false // Use cheaper mode for testing
            ]);
            
            $detection_score = null;
            try {
                $detection_score = $this->check_ai_detection($result['humanized_content']);
            } catch (Exception $e) {
                // Detection failed, continue anyway
            }
            
            wp_send_json_success([
                'humanized_content' => $result['humanized_content'],
                'detection_score' => $detection_score,
                'credits_used' => $result['credits_used'],
                'provider' => $provider
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Validate API keys
     */
    public function ajax_validate_api() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        try {
            $provider = sanitize_text_field($_POST['provider']);
            $api_key = sanitize_text_field($_POST['api_key']);
            
            $result = $this->validate_api_key($provider, $api_key);
            
            if ($result['valid']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get humanization statistics
     */
    public function ajax_get_stats() {
        check_ajax_referer('atm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        $stats = $this->get_humanization_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * Main humanization method
     */
    public function humanize_content($content, $provider = 'stealthgpt', $options = []) {
        $start_time = microtime(true);
        
        switch ($provider) {
            case 'stealthgpt':
                $result = $this->humanize_with_stealthgpt($content, $options);
                break;
                
            case 'openrouter':
                $result = $this->humanize_with_openrouter($content, $options);
                break;
                
            case 'undetectable':
                $result = $this->humanize_with_undetectable($content, $options);
                break;
                
            case 'combo':
                $result = $this->humanize_with_combo($content, $options);
                break;
                
            default:
                throw new Exception('Invalid humanization provider: ' . $provider);
        }
        
        $processing_time = round((microtime(true) - $start_time) * 1000); // milliseconds
        $result['processing_time'] = $processing_time;
        
        return $result;
    }
    
    /**
     * Humanize using StealthGPT
     */
    private function humanize_with_stealthgpt($content, $options = []) {
        $api_key = get_option('atm_stealthgpt_api_key');
        
        if (empty($api_key)) {
            throw new Exception('StealthGPT API key not configured.');
        }
        
        $tone = $options['tone'] ?? 'conversational';
        $mode = $options['mode'] ?? 'High';
        $business_mode = $options['business_mode'] ?? true;
        
        // Map our tones to StealthGPT tones
        $stealthgpt_tone = $this->map_tone_to_stealthgpt($tone);
        
        $payload = [
            'prompt' => $content,
            'rephrase' => true,
            'tone' => $stealthgpt_tone,
            'mode' => $mode,
            'business' => $business_mode,
            'isMultilingual' => true
        ];
        
        $response = wp_remote_post('https://stealthgpt.ai/api/stealthify', [
            'headers' => [
                'api-token' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('StealthGPT API connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->handle_stealthgpt_error($response_code, $response_body);
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['content'])) {
            throw new Exception('Invalid response from StealthGPT API.');
        }
        
        $credits_used = $this->calculate_stealthgpt_credits($content, $business_mode);
        
        return [
            'humanized_content' => trim($data['content']),
            'credits_used' => $credits_used
        ];
    }
    
    /**
     * Humanize using OpenRouter's powerful models
     */
    private function humanize_with_openrouter($content, $options = []) {
        $api_key = get_option('atm_openrouter_api_key', get_option('atm_openrouter_key'));
        
        if (empty($api_key)) {
            throw new Exception('OpenRouter API key not configured.');
        }
        
        $model = $options['model'] ?? 'anthropic/claude-3.5-sonnet';
        $tone = $options['tone'] ?? 'conversational';
        
        $humanization_prompt = $this->build_openrouter_humanization_prompt($tone);
        
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $humanization_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => min(4000, strlen($content) * 2)
        ];
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => 'Content AI Studio'
            ],
            'body' => json_encode($payload),
            'timeout' => 120
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
        $credits_used = $this->calculate_openrouter_credits($content, $model);
        
        return [
            'humanized_content' => $humanized_content,
            'credits_used' => $credits_used
        ];
    }
    
    /**
     * Humanize using Undetectable.AI
     */
    private function humanize_with_undetectable($content, $options = []) {
        $api_key = get_option('atm_undetectable_api_key');
        
        if (empty($api_key)) {
            throw new Exception('Undetectable.AI API key not configured.');
        }
        
        $tone = $options['tone'] ?? 'conversational';
        $readability = $this->map_tone_to_readability($tone);
        
        // Submit content for humanization
        $submit_payload = [
            'content' => $content,
            'readability' => $readability,
            'purpose' => 'General Writing',
            'strength' => 'More Human'
        ];
        
        $submit_response = wp_remote_post('https://humanize.undetectable.ai/submit', [
            'headers' => [
                'apikey' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($submit_payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($submit_response)) {
            throw new Exception('Undetectable.AI submission failed: ' . $submit_response->get_error_message());
        }
        
        $submit_data = json_decode(wp_remote_retrieve_body($submit_response), true);
        
        if (empty($submit_data['id'])) {
            throw new Exception('Failed to submit content to Undetectable.AI');
        }
        
        $document_id = $submit_data['id'];
        
        // Poll for completion
        $max_attempts = 30; // 5 minutes max
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            sleep(10); // Wait 10 seconds between checks
            
            $check_response = wp_remote_post('https://humanize.undetectable.ai/document', [
                'headers' => [
                    'apikey' => $api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode(['id' => $document_id]),
                'timeout' => 30
            ]);
            
            if (!is_wp_error($check_response)) {
                $check_data = json_decode(wp_remote_retrieve_body($check_response), true);
                
                if (!empty($check_data['output'])) {
                    $credits_used = $this->calculate_undetectable_credits($content);
                    
                    return [
                        'humanized_content' => trim($check_data['output']),
                        'credits_used' => $credits_used
                    ];
                }
            }
            
            $attempt++;
        }
        
        throw new Exception('Undetectable.AI processing timeout');
    }
    
    /**
     * Humanize using combo approach (multiple passes)
     */
    private function humanize_with_combo($content, $options = []) {
        // First pass: OpenRouter with Claude 3.5 Sonnet
        $openrouter_result = $this->humanize_with_openrouter($content, array_merge($options, [
            'model' => 'anthropic/claude-3.5-sonnet'
        ]));
        
        // Second pass: StealthGPT to ensure undetectability
        $stealthgpt_result = $this->humanize_with_stealthgpt($openrouter_result['humanized_content'], $options);
        
        return [
            'humanized_content' => $stealthgpt_result['humanized_content'],
            'credits_used' => $openrouter_result['credits_used'] + $stealthgpt_result['credits_used']
        ];
    }

    /**
     * Build OpenRouter humanization prompt
     */
    private function build_openrouter_humanization_prompt(
    $tone,
    $opts = []
) {
    // Defaults
    $opts = array_merge([
        'audience' => 'general',
        'brand' => get_bloginfo('name'),
        'readability_grade' => 9,   // approximate US grade level target
        'preserve_headings' => true,
        'preserve_formatting' => $options['preserve_formatting'] ?? true,
        'preserve_lists' => true,
        'allow_creative_changes' => false, // if true, minor reordering allowed
        'safety_mode' => true,
        'debug' => false
    ], $opts);

    $tone_instructions = [
        'conversational' => 'Speak like a trusted friend: contractions, varied sentence length, natural cadence.',
        'professional'   => 'Clear, confident, business-appropriate language with a helpful tone.',
        'casual'         => 'Relaxed, friendly, short sentences; everyday vocabulary.',
        'academic'       => 'Precise, formal register but accessible; avoid unnecessary jargon.',
        'journalistic'   => 'Lead with the key point, use active voice, fact-forward and concise.',
        'creative'       => 'Vivid, descriptive language and metaphors. Keep clarity while being imaginative.',
        'technical'      => 'Precise technical wording appropriate to subject; define acronyms if present.',
        'persuasive'     => 'Convincing, benefit-first language with clear calls-to-action where appropriate.',
        'storytelling'   => 'Narrative flow, show-don’t-tell, with descriptive beats and a clear arc.'
    ];

    $tone_instruction = $tone_instructions[$tone] ?? $tone_instructions['conversational'];

    // Build preservation clauses
    $preserve_headings_clause = $opts['preserve_headings']
        ? "Keep all headings (H1, H2, H3, etc.) text exactly as they are unless a minor rephrase is required for tone — indicate any heading edits in-place."
        : "You may rephrase headings to better match tone and readability, but preserve meaning.";

    $preserve_formatting_clause = $opts['preserve_formatting']
        ? "Keep inline formatting (bold, italic, code spans) intact and do not remove markup."
        : "Preserve essential formatting where it aids readability, but minor formatting tweaks are allowed.";

    $preserve_lists_clause = $opts['preserve_lists']
        ? "Preserve bullet and numbered lists and their order unless explicitly allowed to reorder."
        : "You may reorder list items to improve flow if doing so improves clarity.";

    $allow_creative = $opts['allow_creative_changes']
        ? "You may make small structural changes (move a sentence between paragraphs) to improve flow, but do not drop or invent facts."
        : "Do not rearrange sections or add new facts; only rewrite sentences and small transitions.";

    $safety_clause = $opts['safety_mode']
        ? "SAFETY: If the text asks for instructions that could facilitate illegal or harmful activity (e.g., how to illegally modify or use weapons for wrongdoing), do not provide step-by-step operational instructions. Instead return a brief refusal line: '[REDACTED — unsafe content].' Then provide a safe alternative such as policy/contextual info or suggest contacting certified professionals or regulators."
        : "";

    $debug_clause = $opts['debug']
        ? "DEBUG_MODE: After the humanized output, append a single-line JSON object inside an HTML comment with a short summary of changes (e.g. <!--DEBUG: {\"changes\":\"tone, sentence-length\"}-->)."
        : "";

    $prompt = <<<PROMPT
You are an expert content humanizer/editor hired by "{$opts['brand']}".
ROLE: Transform the supplied AI-generated text into natural, human-written content that matches the requested tone and audience while preserving facts, formatting, and structure.

CONTEXT:
- Audience: {$opts['audience']}
- Target readability: approx. grade {$opts['readability_grade']} (aim for clear accessible prose)
- Tone instruction: {$tone_instruction}

CORE OBJECTIVES (must follow in order of priority):
1. Preserve meaning and factual data exactly (names, numbers, dates, prices, specs). Do not invent or remove factual info.
2. Remove AI-like phrasing and robotic patterns.
3. Create natural flow: varied sentence lengths, natural transitions, and occasional personal touches appropriate to the tone.
4. Maintain logical structure and readability.

PRESERVATION RULES:
- {$preserve_headings_clause}
- {$preserve_formatting_clause}
- {$preserve_lists_clause}
- Preserve code blocks, tables, URLs, product SKUs, legal disclaimers, and any quoted material verbatim.
- If a numeric value or unit appears, keep it exactly as-is unless explicitly directed to convert.

WRITING AND STYLE RULES (concrete guidance):
- Prefer active voice; avoid repeated sentence openings.
- Use contractions only if tone allows (conversational/casual).
- Vary paragraph length (short paragraphs for emphasis).
- Replace formal connectors like "Furthermore", "Moreover", "In conclusion" with natural transitions ("Also", "Plus", "To wrap up") unless journalistic/academic tone requires formality.
- Do not over-correct grammar to the point of sounding mechanical; small human imperfections are allowed.
- Keep SEO keywords intact if the input contains them (do not remove).
- Do not add new claims, statistics, or references not present in the original text.

SAFETY:
- {$safety_clause}

OPERATIONAL RULES:
- Output: Return ONLY the humanized content and preserve original formatting. Do not output analysis, commentary, or change-log unless DEBUG_MODE is enabled.
- If a requested change would alter factual accuracy, keep the original and add nothing.
- If the original text is ambiguous and altering it risks changing meaning, keep the original phrasing.

{$allow_creative}
{$debug_clause}

EXAMPLES (before -> after, tone = conversational):
- Before: "Furthermore, the product possesses numerous advantageous qualities." 
  After: "Plus, the product has a bunch of useful features."
- Before: "In conclusion, we recommend compliance."
  After: "To wrap up — we recommend following these rules."

If the text explicitly requests instructions for illegal or harmful activity, follow the SAFETY clause above.

Now: humanize the text that follows, matching the tone and audience provided. Return ONLY the revised, humanized text with the same structure and formatting.
PROMPT;

    return $prompt;
}

    
    /**
     * Check AI detection using multiple methods
     */
    public function check_ai_detection($content) {
        $detection_apis = get_option('atm_detection_apis', []);
        
        if (empty($detection_apis)) {
            return $this->basic_ai_detection_heuristics($content);
        }
        
        $scores = [];
        
        foreach ($detection_apis as $api_name => $api_config) {
            try {
                switch ($api_name) {
                    case 'gptzero':
                        $scores[] = $this->check_gptzero($content, $api_config);
                        break;
                    case 'originality':
                        $scores[] = $this->check_originality_ai($content, $api_config);
                        break;
                    case 'zerogpt':
                        $scores[] = $this->check_zerogpt($content, $api_config);
                        break;
                }
            } catch (Exception $e) {
                error_log("AI Detection API {$api_name} failed: " . $e->getMessage());
            }
        }
        
        if (empty($scores)) {
            return $this->basic_ai_detection_heuristics($content);
        }
        
        return array_sum($scores) / count($scores);
    }
    
    /**
     * Basic AI detection heuristics
     */
    private function basic_ai_detection_heuristics($content) {
        $score = 0;
        $factors = 0;
        
        // Check for AI-typical phrases
        $ai_phrases = [
            'in conclusion', 'furthermore', 'moreover', 'additionally', 'however',
            'it is important to note', 'it should be noted', 'in summary',
            'comprehensive', 'robust', 'cutting-edge', 'state-of-the-art',
            'seamlessly', 'effortlessly', 'unprecedented', 'revolutionary',
            'innovative', 'groundbreaking', 'transformative', 'optimize',
            'leverage', 'utilize', 'facilitate', 'enhance', 'maximize'
        ];
        
        $content_lower = strtolower($content);
        $phrase_count = 0;
        
        foreach ($ai_phrases as $phrase) {
            $phrase_count += substr_count($content_lower, $phrase);
        }
        
        $phrase_density = $phrase_count / str_word_count($content) * 100;
        $score += min($phrase_density * 15, 35);
        $factors++;
        
        // Check sentence structure uniformity
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) > 2) {
            $lengths = array_map('str_word_count', $sentences);
            $avg_length = array_sum($lengths) / count($lengths);
            $variance = 0;
            
            foreach ($lengths as $length) {
                $variance += pow($length - $avg_length, 2);
            }
            $variance /= count($lengths);
            $std_dev = sqrt($variance);
            
            // Lower variance = more AI-like
            $uniformity_score = max(0, (25 - $std_dev) * 1.5);
            $score += min($uniformity_score, 25);
            $factors++;
        }
        
        // Check for repetitive patterns
        $structure_score = $this->check_structure_repetition($content);
        $score += $structure_score;
        $factors++;
        
        // Check for overly perfect grammar/punctuation
        $perfection_score = $this->check_perfection_patterns($content);
        $score += $perfection_score;
        $factors++;
        
        return min($score / max($factors, 1), 100);
    }
    
    /**
     * Check for repetitive structure patterns
     */
    private function check_structure_repetition($content) {
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $patterns = [];
        
        foreach ($sentences as $sentence) {
            $words = str_word_count(trim($sentence));
            $first_word = strtolower(trim(strtok(trim($sentence), ' ')));
            
            $pattern = $first_word . '_' . ($words > 15 ? 'long' : ($words > 8 ? 'medium' : 'short'));
            $patterns[] = $pattern;
        }
        
        $pattern_counts = array_count_values($patterns);
        $max_repetition = max($pattern_counts);
        $total_sentences = count($sentences);
        
        if ($total_sentences < 3) return 0;
        
        $repetition_ratio = $max_repetition / $total_sentences;
        return min($repetition_ratio * 40, 20);
    }
    
    /**
     * Check for overly perfect patterns
     */
    private function check_perfection_patterns($content) {
        $score = 0;
        
        // Check for lack of contractions (AI often avoids them)
        $contraction_count = preg_match_all("/\b\w+'\w+\b/", $content);
        $word_count = str_word_count($content);
        $contraction_ratio = $contraction_count / max($word_count, 1) * 100;
        
        if ($contraction_ratio < 1) { // Less than 1% contractions
            $score += 15;
        }
        
        // Check for overly consistent paragraph lengths
        $paragraphs = preg_split('/\n\s*\n/', $content);
        if (count($paragraphs) > 2) {
            $paragraph_lengths = array_map('str_word_count', $paragraphs);
            $avg_length = array_sum($paragraph_lengths) / count($paragraph_lengths);
            $variance = 0;
            
            foreach ($paragraph_lengths as $length) {
                $variance += pow($length - $avg_length, 2);
            }
            $variance /= count($paragraph_lengths);
            $std_dev = sqrt($variance);
            
            if ($std_dev < 10) { // Very uniform paragraph lengths
                $score += 10;
            }
        }
        
        return min($score, 25);
    }
    
    /**
     * Extract formatting from content
     */
    private function extract_formatting($content) {
        $formatting = [];
        
        // Extract headings
        preg_match_all('/<(h[1-6])[^>]*>(.*?)<\/\1>/i', $content, $headings, PREG_SET_ORDER);
        $formatting['headings'] = $headings;
        
        // Extract bold text
        preg_match_all('/<(strong|b)[^>]*>(.*?)<\/\1>/i', $content, $bold, PREG_SET_ORDER);
        $formatting['bold'] = $bold;
        
        // Extract italic text
        preg_match_all('/<(em|i)[^>]*>(.*?)<\/\1>/i', $content, $italic, PREG_SET_ORDER);
        $formatting['italic'] = $italic;
        
        // Extract lists
        preg_match_all('/<(ul|ol)[^>]*>(.*?)<\/\1>/s', $content, $lists, PREG_SET_ORDER);
        $formatting['lists'] = $lists;
        
        // Extract paragraphs
        preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $content, $paragraphs, PREG_SET_ORDER);
        $formatting['paragraphs'] = $paragraphs;
        
        return $formatting;
    }
    
    /**
     * Restore formatting to humanized content
     */
    private function restore_formatting($humanized_content, $formatting) {
        // Simple approach: split into sentences and try to map back
        $sentences = preg_split('/[.!?]+/', $humanized_content, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_map('trim', $sentences);
        
        // If we have paragraph formatting, restore it
        if (!empty($formatting['paragraphs'])) {
            $paragraph_count = count($formatting['paragraphs']);
            $sentences_per_paragraph = max(1, ceil(count($sentences) / $paragraph_count));
            
            $formatted_content = '';
            for ($i = 0; $i < $paragraph_count; $i++) {
                $start = $i * $sentences_per_paragraph;
                $end = min(($i + 1) * $sentences_per_paragraph, count($sentences));
                $paragraph_sentences = array_slice($sentences, $start, $end - $start);
                
                if (!empty($paragraph_sentences)) {
                    $formatted_content .= '<p>' . implode('. ', $paragraph_sentences) . '.</p>' . "\n";
                }
            }
            
            return trim($formatted_content);
        }
        
        // Fallback: wrap in paragraphs
        $paragraph_size = max(2, ceil(count($sentences) / 3)); // Aim for 3 paragraphs
        $formatted_content = '';
        
        for ($i = 0; $i < count($sentences); $i += $paragraph_size) {
            $paragraph_sentences = array_slice($sentences, $i, $paragraph_size);
            $formatted_content .= '<p>' . implode('. ', $paragraph_sentences) . '.</p>' . "\n";
        }
        
        return trim($formatted_content);
    }
    
    /**
     * Map tone to StealthGPT tone
     */
    private function map_tone_to_stealthgpt($tone) {
        $mapping = [
            'conversational' => 'Standard',
            'professional' => 'Professional',
            'casual' => 'Standard',
            'academic' => 'PhD',
            'journalistic' => 'College',
            'creative' => 'Standard',
            'technical' => 'PhD',
            'persuasive' => 'Professional',
            'storytelling' => 'Standard'
        ];
        
        return $mapping[$tone] ?? 'Standard';
    }
    
    /**
     * Map tone to readability level for Undetectable.AI
     */
    private function map_tone_to_readability($tone) {
        $mapping = [
            'conversational' => 'High School',
            'professional' => 'University',
            'casual' => 'High School',
            'academic' => 'University',
            'journalistic' => 'University',
            'creative' => 'High School',
            'technical' => 'University',
            'persuasive' => 'University',
            'storytelling' => 'High School'
        ];
        
        return $mapping[$tone] ?? 'High School';
    }
    
    /**
     * Calculate StealthGPT credits
     */
    private function calculate_stealthgpt_credits($content, $business_mode = false) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        $base_credits = ceil($word_count / 100) * 10; // 10 credits per 100 words
        
        if ($business_mode) {
            $base_credits *= 3; // Business mode costs 3x more
        }
        
        return $base_credits;
    }
    
    /**
     * Calculate OpenRouter credits (estimated)
     */
    private function calculate_openrouter_credits($content, $model) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        $tokens_estimate = $word_count * 1.3; // Rough estimate
        
        // Different models have different costs
        $cost_per_1k_tokens = [
            'anthropic/claude-3.5-sonnet' => 3.0,
            'openai/gpt-4o' => 2.5,
            'anthropic/claude-3-opus' => 15.0,
            'openai/gpt-4-turbo' => 10.0,
            'google/gemini-pro-1.5' => 3.5,
            'meta-llama/llama-3.1-405b-instruct' => 5.0,
            'mistralai/mistral-large' => 4.0,
            'cohere/command-r-plus' => 3.0
        ];
        
        $rate = $cost_per_1k_tokens[$model] ?? 3.0;
        return ceil(($tokens_estimate / 1000) * $rate);
    }
    
    /**
     * Calculate Undetectable.AI credits
     */
    private function calculate_undetectable_credits($content) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        return ceil($word_count / 100) * 15; // 15 credits per 100 words
    }
    
    /**
     * Handle StealthGPT API errors
     */
    private function handle_stealthgpt_error($response_code, $response_body) {
        switch ($response_code) {
            case 401:
                throw new Exception('Invalid StealthGPT API key. Please check your settings.');
            case 429:
                throw new Exception('StealthGPT rate limit exceeded. Please try again later.');
            case 402:
                throw new Exception('Insufficient StealthGPT credits. Please top up your account.');
            case 500:
                throw new Exception('StealthGPT server error. Please try again later.');
            default:
                $error_data = json_decode($response_body, true);
                $error_message = $error_data['error'] ?? "HTTP {$response_code} error";
                throw new Exception("StealthGPT API error: {$error_message}");
        }
    }
    
    /**
     * Handle OpenRouter API errors
     */
    private function handle_openrouter_error($response_code, $response_body) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? "HTTP {$response_code} error";
        
        switch ($response_code) {
            case 401:
                throw new Exception('Invalid OpenRouter API key. Please check your settings.');
            case 429:
                throw new Exception('OpenRouter rate limit exceeded. Please try again later.');
            case 402:
                throw new Exception('Insufficient OpenRouter credits. Please top up your account.');
            default:
                throw new Exception("OpenRouter API error: {$error_message}");
        }
    }
    
    /**
     * Validate API key for a provider
     */
    public function validate_api_key($provider, $api_key) {
        switch ($provider) {
            case 'stealthgpt':
                return $this->validate_stealthgpt_api($api_key);
            case 'openrouter':
                return $this->validate_openrouter_api($api_key);
            case 'undetectable':
                return $this->validate_undetectable_api($api_key);
            default:
                return ['valid' => false, 'error' => 'Unknown provider'];
        }
    }
    
    /**
     * Validate StealthGPT API key
     */
    private function validate_stealthgpt_api($api_key) {
        $test_content = "This is a test message to validate the API key.";
        
        $payload = [
            'prompt' => $test_content,
            'rephrase' => true,
            'tone' => 'Standard',
            'mode' => 'Low',
            'business' => false
        ];
        
        $response = wp_remote_post('https://stealthgpt.ai/api/stealthify', [
            'headers' => [
                'api-token' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return ['valid' => true, 'message' => 'StealthGPT API key is valid and working.'];
        } elseif ($response_code === 401) {
            return ['valid' => false, 'error' => 'Invalid API key.'];
        } else {
            return ['valid' => false, 'error' => "HTTP {$response_code} error."];
        }
    }
    
    /**
     * Validate OpenRouter API key
     */
    private function validate_openrouter_api($api_key) {
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer' => home_url(),
                'X-Title' => 'Content AI Studio'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return ['valid' => true, 'message' => 'OpenRouter API key is valid and working.'];
        } elseif ($response_code === 401) {
            return ['valid' => false, 'error' => 'Invalid API key.'];
        } else {
            return ['valid' => false, 'error' => "HTTP {$response_code} error."];
        }
    }
    
    /**
     * Validate Undetectable.AI API key
     */
    private function validate_undetectable_api($api_key) {
        $response = wp_remote_get('https://humanize.undetectable.ai/check-user-credits', [
            'headers' => [
                'apikey' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return ['valid' => true, 'message' => 'Undetectable.AI API key is valid and working.'];
        } elseif ($response_code === 401) {
            return ['valid' => false, 'error' => 'Invalid API key.'];
        } else {
            return ['valid' => false, 'error' => "HTTP {$response_code} error."];
        }
    }
    
    /**
     * Get detection status text
     */
    private function get_detection_status($score) {
        if ($score < 10) return 'excellent';
        if ($score < 30) return 'good';
        if ($score < 60) return 'fair';
        return 'poor';
    }
    
    /**
     * Get detection recommendation
     */
    private function get_detection_recommendation($score) {
        if ($score < 10) {
            return 'Content passes as human-written. Ready to publish!';
        } elseif ($score < 30) {
            return 'Good humanization with minor AI signatures. Consider light editing.';
        } elseif ($score < 60) {
            return 'Moderate AI detection. Try running humanization again with enhanced settings.';
        } else {
            return 'High AI detection. Consider using business mode or manual rewriting.';
        }
    }
    
    /**
     * Log humanization usage
     */
    private function log_usage($original, $humanized, $provider, $credits_used) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'provider' => $provider,
            'original_length' => strlen($original),
            'humanized_length' => strlen($humanized),
            'word_count' => str_word_count($original),
            'credits_used' => $credits_used,
            'user_id' => get_current_user_id()
        ];
        
        $existing_logs = get_option('atm_humanization_logs', []);
        $existing_logs[] = $log_data;
        
        // Keep only last 100 entries
        if (count($existing_logs) > 100) {
            $existing_logs = array_slice($existing_logs, -100);
        }
        
        update_option('atm_humanization_logs', $existing_logs);
    }
    
    /**
     * Get humanization statistics
     */
    public function get_humanization_stats() {
        $logs = get_option('atm_humanization_logs', []);
        
        if (empty($logs)) {
            return [
                'total_requests' => 0,
                'total_words' => 0,
                'total_credits' => 0,
                'average_length' => 0,
                'last_30_days' => 0,
                'provider_breakdown' => [],
                'daily_usage' => []
            ];
        }
        
        $total_requests = count($logs);
        $total_words = array_sum(array_column($logs, 'word_count'));
        $total_credits = array_sum(array_column($logs, 'credits_used'));
        $average_length = $total_words / max($total_requests, 1);
        
        // Last 30 days
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $recent_logs = array_filter($logs, fn($log) => $log['timestamp'] >= $thirty_days_ago);
        $last_30_days = count($recent_logs);
        
        // Provider breakdown
        $provider_breakdown = [];
        foreach ($logs as $log) {
            $provider = $log['provider'];
            if (!isset($provider_breakdown[$provider])) {
                $provider_breakdown[$provider] = 0;
            }
            $provider_breakdown[$provider]++;
        }
        
        // Daily usage for last 7 days
        $daily_usage = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $daily_usage[$date] = 0;
        }
        
        foreach ($recent_logs as $log) {
            $date = date('Y-m-d', strtotime($log['timestamp']));
            if (isset($daily_usage[$date])) {
                $daily_usage[$date]++;
            }
        }
        
        return [
            'total_requests' => $total_requests,
            'total_words' => $total_words,
            'total_credits' => $total_credits,
            'average_length' => round($average_length),
            'last_30_days' => $last_30_days,
            'provider_breakdown' => $provider_breakdown,
            'daily_usage' => $daily_usage
        ];
    }
    
    /**
     * Auto-humanize filter hook
     */
    public function maybe_auto_humanize($content, $context = '') {
        if (!get_option('atm_auto_humanize_articles', false)) {
            return $content;
        }
        
        try {
            $provider = get_option('atm_default_humanize_provider', 'stealthgpt');
            $result = $this->humanize_content($content, $provider, [
                'tone' => get_option('atm_default_humanize_tone', 'conversational'),
                'mode' => get_option('atm_default_humanize_mode', 'High'),
                'business_mode' => get_option('atm_default_business_mode', true)
            ]);
            
            return $result['humanized_content'];
            
        } catch (Exception $e) {
            error_log('ATM Auto-humanization failed: ' . $e->getMessage());
            return $content; // Return original on failure
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('atm_humanization_settings', 'atm_stealthgpt_api_key');
        register_setting('atm_humanization_settings', 'atm_undetectable_api_key');
        register_setting('atm_humanization_settings', 'atm_default_humanize_provider');
        register_setting('atm_humanization_settings', 'atm_default_humanize_tone');
        register_setting('atm_humanization_settings', 'atm_default_humanize_mode');
        register_setting('atm_humanization_settings', 'atm_default_business_mode');
        register_setting('atm_humanization_settings', 'atm_auto_check_detection');
        register_setting('atm_humanization_settings', 'atm_auto_humanize_articles');
    }
    
    /**
     * Get available providers
     */
    public static function get_providers() {
        return self::PROVIDERS;
    }
    
    /**
     * Get OpenRouter models
     */
    public static function get_openrouter_models() {
        return self::OPENROUTER_MODELS;
    }
    
    /**
     * Get tone options
     */
    public static function get_tone_options() {
        return self::TONE_OPTIONS;
    }
}

?>