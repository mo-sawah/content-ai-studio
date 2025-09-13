<?php
/**
 * ATM Content Generator Utility
 * Shared utility class for content generation logic
 * 
 * @package Content_AI_Studio
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Content_Generator_Utility {
    
    /**
     * Ensure angles table exists
     */
    public static function ensure_angles_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        error_log("ATM Debug: Checking if angles table exists");
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            error_log("ATM Debug: Creating content angles table");
            ATM_Main::create_content_angles_table();
            
            // Verify it was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                error_log("ATM Debug: FAILED to create content angles table");
            } else {
                error_log("ATM Debug: Successfully created content angles table");
            }
        } else {
            error_log("ATM Debug: Content angles table already exists");
        }
    }
    
    /**
     * Get previous angles for a keyword
     */
    public static function get_previous_angles($keyword) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT angle, title, created_at FROM $table_name 
            WHERE keyword = %s 
            ORDER BY created_at DESC 
            LIMIT 50",
            $keyword
        ), ARRAY_A);
        
        // Log angle diversity metrics
        if (!empty($results)) {
            $unique_combinations = array_unique(array_column($results, 'angle'));
            $diversity_score = count($unique_combinations) / count($results);
            error_log("ATM Angle Diversity for '$keyword': " . round($diversity_score * 100, 1) . "% unique combinations");
        }
        
        return $results ?: [];
    }
    
    /**
     * Get massive scale angles dimensions
     */
    public static function get_massive_scale_angles() {
        return [
            'target_audiences' => [
                'beginners', 'professionals', 'entrepreneurs', 'small_businesses', 'startups',
                'freelancers', 'consultants', 'agencies', 'enterprises', 'non_profits',
                'students', 'job_seekers', 'managers', 'executives', 'creatives'
            ],
            'industries' => [
                'healthcare', 'finance', 'education', 'retail', 'manufacturing',
                'real_estate', 'hospitality', 'automotive', 'legal', 'consulting',
                'technology', 'media', 'sports', 'fashion', 'food_beverage',
                'construction', 'agriculture', 'energy', 'government', 'aerospace'
            ],
            'problem_types' => [
                'mistakes_to_avoid', 'optimization_strategies', 'cost_reduction',
                'time_saving', 'efficiency_improvement', 'quality_enhancement',
                'security_concerns', 'compliance_issues', 'scalability_challenges',
                'integration_problems', 'training_gaps', 'measurement_difficulties'
            ],
            'content_formats' => [
                'ultimate_guide', 'step_by_step', 'checklist', 'case_study',
                'comparison', 'review', 'trend_analysis', 'prediction', 'interview',
                'toolkit', 'template', 'framework', 'strategy', 'blueprint'
            ],
            'time_contexts' => [
                '2025', '2026', 'next_5_years', '30_days', '90_days', '6_months',
                'this_year', 'pandemic_era', 'post_covid', 'recession_proof',
                'economic_uncertainty', 'digital_transformation_era'
            ],
            'skill_levels' => [
                'complete_beginner', 'intermediate', 'advanced', 'expert',
                'transitioning_career', 'self_taught', 'formally_trained'
            ],
            'budget_constraints' => [
                'zero_budget', 'bootstrap', 'small_budget', 'medium_investment',
                'enterprise_budget', 'cost_effective', 'premium_solutions'
            ]
        ];
    }
    
    /**
     * Generate massive scale angle
     */
    public static function generate_massive_scale_angle($keyword, $previous_angles) {
        $dimensions = self::get_massive_scale_angles();
        
        // Get previously used combinations to avoid duplicates
        $used_combinations = [];
        foreach ($previous_angles as $prev) {
            if (isset($prev['angle'])) {
                $used_combinations[] = md5($prev['angle']);
            }
        }
        
        // Generate unique combination
        $max_attempts = 50;
        $attempts = 0;
        
        do {
            $combination = [
                'audience' => $dimensions['target_audiences'][array_rand($dimensions['target_audiences'])],
                'industry' => $dimensions['industries'][array_rand($dimensions['industries'])],
                'problem' => $dimensions['problem_types'][array_rand($dimensions['problem_types'])],
                'format' => $dimensions['content_formats'][array_rand($dimensions['content_formats'])],
                'time' => $dimensions['time_contexts'][array_rand($dimensions['time_contexts'])],
                'skill' => $dimensions['skill_levels'][array_rand($dimensions['skill_levels'])],
                'budget' => $dimensions['budget_constraints'][array_rand($dimensions['budget_constraints'])]
            ];
            
            $combination_key = implode('|', $combination);
            $combination_hash = md5($combination_key);
            $attempts++;
            
        } while (in_array($combination_hash, $used_combinations) && $attempts < $max_attempts);
        
        // Generate angle description from combination
        $angle_description = self::build_angle_description($combination);
        
        return [
            'angle_description' => $angle_description,
            'combination' => $combination,
            'combination_key' => $combination_key,
            'target_audience' => self::format_simple($combination['audience']),
            'prompt_focus' => self::build_detailed_prompt_focus($combination)
        ];
    }
    
    /**
     * Generate intelligent angle classification
     */
    public static function generate_intelligent_angle_classification($keyword, $previous_angles) {
        $previous_angles_text = '';
        if (!empty($previous_angles)) {
            $previous_angles_text = "\n\nPREVIOUS ANGLES ALREADY USED:\n";
            foreach ($previous_angles as $i => $angle_data) {
                $previous_angles_text .= "- " . ($i + 1) . ". " . $angle_data['angle'] . "\n";
            }
            $previous_angles_text .= "\nYou MUST create a completely different angle.";
        }
        
        $classification_prompt = "Analyze the keyword '$keyword' and create a unique article angle.

**ANALYSIS REQUIRED:**
1. Classify the keyword type (person, business, technology, health, entertainment, location, event, product, concept)
2. Determine the most appropriate content approach
3. Create a specific, unique angle that hasn't been used before

{$previous_angles_text}

**OUTPUT FORMAT (JSON):**
{
\"keyword_type\": \"category of the keyword\",
\"content_approach\": \"best format for this topic\",
\"target_audience\": \"who would be interested in this\",
\"angle_description\": \"Specific unique angle in one detailed sentence\",
\"title_guidance\": \"Specific instructions for creating an engaging title\"
}

**REQUIREMENTS:**
- The angle must be factually grounded and respectful
- Must be completely different from previous angles
- Should be interesting and clickable
- Must be appropriate for the keyword type

Return only the JSON object.";

        // Use ATM_API if available, otherwise fall back to simple angle generation
        if (class_exists('ATM_API') && method_exists('ATM_API', 'enhance_content_with_openrouter')) {
            $raw_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $keyword],
                $classification_prompt,
                'anthropic/claude-3-haiku', // Fast, cost-effective model
                true, // JSON mode
                false // NO web search - saves cost and time
            );
            
            $result = json_decode($raw_response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('ATM Angle Classification - Invalid JSON: ' . $raw_response);
                // Fall back to massive scale angle generation
                return self::generate_massive_scale_angle($keyword, $previous_angles);
            }
            
            return $result;
        } else {
            // Fallback to local generation if API not available
            return self::generate_massive_scale_angle($keyword, $previous_angles);
        }
    }
    
    /**
     * Store content angle
     */
    public static function store_content_angle($keyword, $angle, $title) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        // Verify table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            error_log("ATM Debug: Content angles table does not exist, creating...");
            ATM_Main::create_content_angles_table();
        }
        
        error_log("ATM Debug: Attempting to store angle - Keyword: $keyword, Angle: $angle, Title: $title");
        
        $result = $wpdb->insert($table_name, [
            'keyword' => $keyword,
            'angle' => $angle,
            'title' => $title,
            'created_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            error_log("ATM Debug: Failed to store angle. Error: " . $wpdb->last_error);
            error_log("ATM Debug: Last query: " . $wpdb->last_query);
            // Try to create table again if insert failed
            ATM_Main::create_content_angles_table();
        } else {
            error_log("ATM Debug: Successfully stored angle. Insert ID: " . $wpdb->insert_id);
        }
        
        return $result;
    }
    
    /**
     * Update stored angle with actual title
     */
    public static function update_stored_angle($keyword, $angle_description, $actual_title) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atm_content_angles';
        
        $updated = $wpdb->update(
            $table_name,
            ['title' => $actual_title],
            [
                'keyword' => $keyword,
                'angle' => $angle_description,
                'title' => '[Automation Generated]'
            ],
            ['%s'],
            ['%s', '%s', '%s']
        );
        
        if ($updated) {
            error_log("ATM Debug: Successfully updated title to: " . $actual_title);
        } else {
            // Try alternative update for manual generation
            $updated = $wpdb->update(
                $table_name,
                ['title' => $actual_title],
                [
                    'keyword' => $keyword,
                    'title' => '[AI Generated]'
                ],
                ['%s'],
                ['%s', '%s']
            );
            
            if ($updated) {
                error_log("ATM Debug: Successfully updated title via alternative method");
            } else {
                error_log("ATM Debug: Failed to update title. No matching record found.");
            }
        }
        
        return $updated;
    }
    
    /**
     * Build comprehensive angle context for content generation
     */
    public static function build_comprehensive_angle_context($angle_data, $keyword) {
        return "\n\n**INTELLIGENT CONTENT STRATEGY:**
        
**Keyword Analysis:**
- Topic: '$keyword'
- Type: {$angle_data['keyword_type']}
- Target Audience: {$angle_data['target_audience']}
- Content Approach: {$angle_data['content_approach']}

**MANDATORY ANGLE:**
{$angle_data['angle_description']}

**Title Creation Instructions:**
{$angle_data['title_guidance']}

**CRITICAL REQUIREMENTS:**
1. The title and content MUST align with this specific angle
2. Content must be factually accurate and well-researched
3. Use current, verifiable information from web search
4. Stay focused on this unique perspective throughout
5. Make the content valuable and engaging for the target audience
6. Ensure the angle is clearly reflected in both title and content structure

**CONTENT QUALITY STANDARDS:**
- Use specific examples and current data
- Include relevant context and background
- Avoid speculation or unverified claims
- Be respectful and objective, especially for people/sensitive topics
- Create genuine value for readers interested in this angle";
    }
    
    /**
     * Helper methods
     */
    private static function build_angle_description($combination) {
        return sprintf(
            "Target %s in %s industry focusing on %s, structured as %s, with %s perspective, at %s level, considering %s budget constraints",
            self::format_simple($combination['audience']),
            self::format_simple($combination['industry']),
            self::format_simple($combination['problem']),
            self::format_simple($combination['format']),
            self::format_simple($combination['time']),
            self::format_simple($combination['skill']),
            self::format_simple($combination['budget'])
        );
    }
    
    private static function build_detailed_prompt_focus($combination) {
        return "Write specifically for {$combination['audience']} in the {$combination['industry']} industry who are dealing with {$combination['problem']}. " .
            "Focus on {$combination['skill']} level content with a {$combination['budget']} budget approach. " .
            "Structure this as a {$combination['format']} with a {$combination['time']} perspective. " .
            "Include industry-specific examples, realistic constraints, and actionable advice that this specific audience can actually implement.";
    }
    
    private static function format_simple($text) {
        // Replace underscores with spaces and capitalize words
        $formatted = str_replace('_', ' ', $text);
        return ucwords($formatted);
    }
}